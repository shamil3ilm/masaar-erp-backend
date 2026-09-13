<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Billing\OrganizationSubscription;
use App\Models\Billing\SubscriptionPlan;
use App\Models\Core\DashboardWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Premium dashboard widgets are offered only to a subscription that has
 * dashboard customization, from its plan or granted to it directly.
 */
class DashboardWidgetAccessTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.dashboards.view']);

        DashboardWidget::factory()->create(['code' => 'free-widget', 'is_premium' => false]);
        DashboardWidget::factory()->create(['code' => 'premium-widget', 'is_premium' => true]);
    }

    public function test_without_a_subscription_premium_widgets_are_withheld(): void
    {
        $this->assertOffered(false, ['free-widget']);
    }

    public function test_a_plan_with_dashboard_customization_offers_them(): void
    {
        $this->subscribe(planFeatures: [SubscriptionPlan::FEATURE_DASHBOARD_CUSTOMIZATION]);

        $this->assertOffered(true, ['free-widget', 'premium-widget']);
    }

    public function test_a_feature_granted_to_the_subscription_offers_them(): void
    {
        $this->subscribe(enabledFeatures: [SubscriptionPlan::FEATURE_DASHBOARD_CUSTOMIZATION]);

        $this->assertOffered(true, ['free-widget', 'premium-widget']);
    }

    public function test_a_cancelled_subscription_does_not(): void
    {
        $this->subscribe(planFeatures: [SubscriptionPlan::FEATURE_DASHBOARD_CUSTOMIZATION], status: 'cancelled');

        $this->assertOffered(false, ['free-widget']);
    }

    private function subscribe(array $planFeatures = [], array $enabledFeatures = [], string $status = 'active'): void
    {
        $plan = SubscriptionPlan::factory()->create(['features' => $planFeatures]);

        OrganizationSubscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'enabled_features' => $enabledFeatures,
        ]);
    }

    private function assertOffered(bool $premium, array $codes): void
    {
        $response = $this->apiGet('/dashboard/widgets');

        $response->assertOk();
        $this->assertSame($premium, $response->json('data.premium_access'));

        $offered = collect($response->json('data.widgets'))->pluck('code')->sort()->values()->all();
        $this->assertSame($codes, $offered);
    }
}
