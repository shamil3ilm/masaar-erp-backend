<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Billing\BillingInvoice;
use App\Models\Billing\OrganizationSubscription;
use App\Models\Billing\SubscriptionAddon;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Billing\UsageAlert;
use App\Models\Billing\UsageMetric;
use App\Models\Billing\UsageSnapshot;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * An organization's own subscription, invoices and usage, and the platform's
 * plan catalog.
 *
 * The user here holds every billing permission, as the administrator role a
 * registration grants does. That role belongs to a tenant, so it may run its
 * own subscription but must not rewrite the catalog every tenant subscribes
 * from, mark its own invoices paid, or take a plan or add-on the platform has
 * not offered.
 */
class BillingEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PERMISSIONS = [
        'billing.plans.view', 'billing.plans.create', 'billing.plans.update', 'billing.plans.delete',
        'billing.subscriptions.view', 'billing.subscriptions.create', 'billing.subscriptions.update',
        'billing.invoices.view', 'billing.invoices.pay', 'billing.usage.view',
    ];

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(self::PERMISSIONS);
        $this->otherOrganization = Organization::factory()->create();
    }

    // ----------------------------------------------------------------
    // Plan catalog
    // ----------------------------------------------------------------

    public function test_plans_lists_the_active_public_plans_in_display_order(): void
    {
        SubscriptionPlan::factory()->create(['name' => 'Second', 'display_order' => 2]);
        SubscriptionPlan::factory()->create(['name' => 'First', 'display_order' => 1]);
        SubscriptionPlan::factory()->create(['name' => 'Private', 'is_public' => false]);
        SubscriptionPlan::factory()->create(['name' => 'Retired', 'is_active' => false]);

        $names = array_column($this->apiGet('/billing/plans')->assertOk()->json('data'), 'name');

        $this->assertSame(['First', 'Second'], $names);
    }

    public function test_a_plan_is_shown_with_its_metered_pricing(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->apiGet('/billing/plans/'.$plan->getRouteKey())
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.metered_pricing_tiers', []);
    }

    public function test_a_tenant_administrator_cannot_change_the_plan_catalog(): void
    {
        $plan = SubscriptionPlan::factory()->create(['base_price' => 99]);

        $this->apiPost('/billing/plans', $this->planPayload('free-enterprise'))->assertForbidden();
        $this->apiPut('/billing/plans/'.$plan->getRouteKey(), $this->planPayload($plan->code, ['base_price' => 0]))->assertForbidden();
        $this->apiDelete('/billing/plans/'.$plan->getRouteKey())->assertForbidden();

        $this->assertDatabaseMissing('subscription_plans', ['code' => 'free-enterprise']);
        $this->assertEquals(99, $plan->fresh()->base_price);
    }

    // ----------------------------------------------------------------
    // Subscription
    // ----------------------------------------------------------------

    public function test_current_answers_a_placeholder_without_a_subscription(): void
    {
        $this->apiGet('/billing/subscriptions/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'none')
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_current_returns_this_organizations_subscription_with_its_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create(['name' => 'Professional']);
        $this->subscription(['plan_id' => $plan->id, 'status' => 'active']);
        $this->subscription(['status' => 'active'], $this->otherOrganization->id);

        $this->apiGet('/billing/subscriptions/current')
            ->assertOk()
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.plan.name', 'Professional');
    }

    public function test_subscribing_to_an_offered_plan_starts_its_trial(): void
    {
        $plan = SubscriptionPlan::factory()->create(['trial_days' => 14]);

        $this->apiPost('/billing/subscriptions/subscribe', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'trial')
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_a_plan_the_platform_does_not_offer_cannot_be_taken(): void
    {
        $private = SubscriptionPlan::factory()->create(['is_public' => false]);
        $retired = SubscriptionPlan::factory()->create(['is_active' => false]);
        $this->subscription(['status' => 'active']);

        foreach ([$private, $retired] as $plan) {
            $this->apiPost('/billing/subscriptions/subscribe', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])
                ->assertStatus(422);
            $this->apiPost('/billing/subscriptions/change-plan', ['plan_id' => $plan->id])
                ->assertStatus(422);
        }

        $this->assertSame(0, OrganizationSubscription::whereIn('plan_id', [$private->id, $retired->id])->count());
    }

    public function test_change_plan_moves_the_active_subscription_to_the_new_plan(): void
    {
        $subscription = $this->subscription(['status' => 'active']);
        $plan = SubscriptionPlan::factory()->create(['base_price' => 250, 'max_users' => 40]);

        $this->apiPost('/billing/subscriptions/change-plan', ['plan_id' => $plan->id])
            ->assertOk()
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.max_users', 40);

        $this->assertEquals(250, $subscription->fresh()->base_price);
    }

    public function test_change_plan_without_an_active_subscription_is_not_found(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->apiPost('/billing/subscriptions/change-plan', ['plan_id' => $plan->id])->assertNotFound();
    }

    public function test_cancel_ends_the_active_subscription_and_answers_when_there_is_none(): void
    {
        $this->apiPost('/billing/subscriptions/cancel')
            ->assertOk()
            ->assertJsonPath('message', 'No active subscription to cancel');

        $subscription = $this->subscription(['status' => 'trial']);

        $this->apiPost('/billing/subscriptions/cancel', ['reason' => 'Too expensive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Too expensive');

        $this->assertFalse($subscription->fresh()->auto_renew);
    }

    public function test_addons_lists_the_active_addons_and_one_is_purchased(): void
    {
        $addon = SubscriptionAddon::factory()->create(['price' => 12.5]);
        SubscriptionAddon::factory()->create(['is_active' => false]);
        $this->subscription(['status' => 'active']);

        $this->apiGet('/billing/subscriptions/addons')->assertOk()->assertJsonCount(1, 'data');

        $this->apiPost('/billing/subscriptions/addons', ['addon_id' => $addon->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.addon_id', $addon->id)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('subscription_addon_purchases', ['addon_id' => $addon->id, 'quantity' => 2, 'total_price' => 25]);
    }

    public function test_an_addon_the_platform_withdrew_cannot_be_purchased(): void
    {
        $withdrawn = SubscriptionAddon::factory()->create(['is_active' => false]);
        $this->subscription(['status' => 'active']);

        $this->apiPost('/billing/subscriptions/addons', ['addon_id' => $withdrawn->id])->assertStatus(422);

        $this->assertDatabaseMissing('subscription_addon_purchases', ['addon_id' => $withdrawn->id]);
    }

    // ----------------------------------------------------------------
    // Invoices
    // ----------------------------------------------------------------

    public function test_invoices_lists_this_organizations_invoices_and_hides_another_organizations(): void
    {
        $subscription = $this->subscription(['status' => 'active']);
        $invoice = $this->invoice(['subscription_id' => $subscription->id]);
        $foreign = $this->invoice([], $this->otherOrganization->id);

        $this->apiGet('/billing/invoices')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $invoice->id)
            ->assertJsonPath('data.0.subscription.plan.id', $subscription->plan_id);

        $this->apiGet('/billing/invoices/'.$invoice->getRouteKey())
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.payments', []);

        $this->apiGet('/billing/invoices/'.$foreign->getRouteKey())->assertNotFound();
    }

    public function test_a_tenant_cannot_mark_its_own_invoice_paid(): void
    {
        $invoice = $this->invoice(['status' => 'sent', 'total' => 300, 'amount_due' => 300]);

        $this->apiPost('/billing/invoices/'.$invoice->getRouteKey().'/pay')->assertForbidden();

        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertEquals(300, $invoice->amount_due);
    }

    // ----------------------------------------------------------------
    // Usage
    // ----------------------------------------------------------------

    public function test_usage_answers_zeroes_without_a_snapshot_and_the_snapshot_with_one(): void
    {
        $this->apiGet('/billing/usage')
            ->assertOk()
            ->assertJsonPath('data.users_count', 0)
            ->assertJsonPath('data.organization_id', $this->organization->id);

        UsageSnapshot::factory()->create(['organization_id' => $this->organization->id, 'users_count' => 7]);
        UsageSnapshot::factory()->create(['organization_id' => $this->otherOrganization->id, 'users_count' => 99]);

        $this->apiGet('/billing/usage')->assertOk()->assertJsonPath('data.users_count', 7);
        $this->apiGet('/billing/usage/summary')->assertOk()->assertJsonPath('data.users_count', 7);
    }

    public function test_usage_history_and_alerts_are_filtered_and_scoped(): void
    {
        UsageMetric::factory()->create(['organization_id' => $this->organization->id, 'metric_type' => 'api_calls']);
        UsageMetric::factory()->create(['organization_id' => $this->organization->id, 'metric_type' => 'storage']);
        UsageMetric::factory()->create(['organization_id' => $this->otherOrganization->id, 'metric_type' => 'api_calls']);
        UsageAlert::factory()->create(['organization_id' => $this->organization->id, 'status' => 'triggered']);
        UsageAlert::factory()->create(['organization_id' => $this->organization->id, 'status' => 'resolved']);
        UsageAlert::factory()->create(['organization_id' => $this->otherOrganization->id, 'status' => 'triggered']);

        $this->apiGet('/billing/usage/history?metric_type=api_calls')->assertOk()->assertJsonCount(1, 'data');
        $this->apiGet('/billing/usage/alerts?status=triggered')->assertOk()->assertJsonCount(1, 'data');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function subscription(array $attributes = [], ?int $organizationId = null): OrganizationSubscription
    {
        return OrganizationSubscription::withoutGlobalScopes()->create(
            OrganizationSubscription::factory()->make(array_merge([
                'organization_id' => $organizationId ?? $this->organization->id,
                'plan_id' => SubscriptionPlan::factory()->create()->id,
            ], $attributes))->getAttributes()
        );
    }

    private function invoice(array $attributes = [], ?int $organizationId = null): BillingInvoice
    {
        return BillingInvoice::withoutGlobalScopes()->create(
            BillingInvoice::factory()->make(array_merge([
                'organization_id' => $organizationId ?? $this->organization->id,
            ], $attributes))->getAttributes()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(string $code, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Enterprise',
            'code' => $code,
            'tier' => 'enterprise',
            'billing_cycle' => 'monthly',
            'base_price' => 0,
            'included_modules' => ['sales', 'accounting'],
        ], $overrides);
    }
}
