<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\BillingPlan;
use App\Models\Sales\BillingPlanItem;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the billing plan endpoints: the list, creating a milestone or periodic
 * plan, editing it and its items, billing an item against an invoice and the
 * due items. Keeps orders, quotations and invoices inside the caller's
 * organization and the invoice embedded only by its reference columns.
 */
class BillingPlanEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Contact $customer;
    private SalesOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.billing-plans.view', 'sales.billing-plans.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_the_list_shows_the_organizations_plans_latest_first_and_filters(): void
    {
        $older = $this->plan(['status' => 'draft', 'created_at' => now()->subDay()]);
        $newer = $this->plan(['status' => 'active', 'plan_type' => 'periodic', 'created_at' => now()]);
        $this->plan(['organization_id' => $this->otherOrg->id, 'sales_order_id' => null]);

        $ids = fn (array $query) => array_column($this->send('GET', 'sd.billing-plans.index', [], $query)->assertOk()->json('data'), 'id');

        $this->assertSame([$newer->id, $older->id], $ids([]));
        $this->assertSame([$older->id], $ids(['status' => 'draft']));
        $this->assertSame([$newer->id], $ids(['plan_type' => 'periodic']));
        $this->assertSame($this->order->id, $this->send('GET', 'sd.billing-plans.index')->json('data.0.sales_order.id'));
    }

    public function test_a_milestone_plan_is_created_for_the_organizations_order(): void
    {
        $this->send('POST', 'sd.billing-plans.store', [], [
            'sales_order_id' => $this->order->id,
            'plan_type' => 'milestone',
            'total_value' => 1000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.sales_order.id', $this->order->id)
            ->assertJsonPath('data.items', []);
    }

    public function test_a_periodic_plan_generates_its_items(): void
    {
        $response = $this->send('POST', 'sd.billing-plans.store', [], [
            'plan_type' => 'periodic',
            'total_value' => 100,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-15',
            'periodic_interval_days' => 30,
            'auto_generate_items' => true,
        ]);

        $response->assertStatus(201);
        $this->assertSame(['2026-01-01', '2026-01-31'], array_map(
            fn (string $date) => substr($date, 0, 10),
            array_column($response->json('data.items'), 'billing_date')
        ));
    }

    public function test_another_organizations_order_and_quotation_are_refused(): void
    {
        $foreignOrder = SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignQuotation = Quotation::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->send('POST', 'sd.billing-plans.store', [], [
            'sales_order_id' => $foreignOrder->id,
            'quotation_id' => $foreignQuotation->id,
            'plan_type' => 'milestone',
            'total_value' => 1000,
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertErrorsOn($response, ['sales_order_id', 'quotation_id']);
        $this->assertSame(0, BillingPlan::count());
    }

    public function test_a_plan_is_shown_updated_and_deleted_and_another_organizations_is_not_found(): void
    {
        $plan = $this->plan();
        $foreign = $this->plan(['organization_id' => $this->otherOrg->id, 'sales_order_id' => null]);

        $this->send('GET', 'sd.billing-plans.show', ['id' => $plan->id])
            ->assertOk()
            ->assertJsonPath('data.sales_order.id', $this->order->id);

        $this->send('PUT', 'sd.billing-plans.update', ['id' => $plan->id], ['notes' => 'Quarterly', 'status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Quarterly')
            ->assertJsonPath('data.status', 'active');

        $this->send('GET', 'sd.billing-plans.show', ['id' => $foreign->id])->assertNotFound();
        $this->send('PUT', 'sd.billing-plans.update', ['id' => $foreign->id], ['notes' => 'x'])->assertNotFound();
        $this->send('DELETE', 'sd.billing-plans.destroy', ['id' => $foreign->id])->assertNotFound();

        $this->send('DELETE', 'sd.billing-plans.destroy', ['id' => $plan->id])->assertNoContent();
        $this->assertNull(BillingPlan::find($plan->id));
    }

    public function test_items_are_added_and_updated_within_their_plan(): void
    {
        $plan = $this->plan();
        $otherPlan = $this->plan();

        $added = $this->send('POST', 'sd.billing-plans.items.add', ['id' => $plan->id], [
            'billing_date' => '2026-03-01',
            'billing_amount' => 400,
            'milestone_description' => 'Kick-off',
        ]);
        $added->assertStatus(201)->assertJsonPath('data.billing_plan_id', $plan->id);
        $itemId = $added->json('data.id');

        $this->send('PUT', 'sd.billing-plans.items.update', ['id' => $plan->id, 'itemId' => $itemId], ['billing_amount' => 450])
            ->assertOk()
            ->assertJsonPath('data.billing_amount', '450.0000');

        $this->send('PUT', 'sd.billing-plans.items.update', ['id' => $otherPlan->id, 'itemId' => $itemId], ['billing_amount' => 1])
            ->assertNotFound();
    }

    public function test_billing_an_item_records_the_invoice_completes_the_plan_and_happens_once(): void
    {
        $plan = $this->plan();
        $item = $this->item($plan, 250);
        $invoice = $this->invoice();

        $this->send('POST', 'sd.billing-plans.items.bill', ['id' => $plan->id, 'itemId' => $item->id], ['invoice_id' => $invoice->id])
            ->assertOk()
            ->assertJsonPath('message', 'Item billed successfully.')
            ->assertJsonPath('data.status', BillingPlanItem::STATUS_BILLED)
            ->assertJsonPath('data.invoice.id', $invoice->id);

        $plan->refresh();
        $this->assertSame('250.0000', $plan->billed_value);
        $this->assertSame(BillingPlan::STATUS_COMPLETED, $plan->status);

        $this->send('POST', 'sd.billing-plans.items.bill', ['id' => $plan->id, 'itemId' => $item->id], ['invoice_id' => $invoice->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_billed_item_embeds_only_the_invoices_reference_columns(): void
    {
        $plan = $this->plan();
        $item = $this->item($plan, 250);
        $invoice = $this->invoice();
        $item->forceFill(['status' => BillingPlanItem::STATUS_BILLED, 'invoice_id' => $invoice->id])->save();

        $shown = $this->send('GET', 'sd.billing-plans.show', ['id' => $plan->id])->assertOk();

        $this->assertEqualsCanonicalizing(Invoice::REFERENCE_COLUMNS, array_keys($shown->json('data.items.0.invoice')));
    }

    public function test_billing_refuses_another_organizations_invoice(): void
    {
        $plan = $this->plan();
        $item = $this->item($plan, 250);
        $foreignInvoice = Invoice::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->send('POST', 'sd.billing-plans.items.bill', ['id' => $plan->id, 'itemId' => $item->id], [
            'invoice_id' => $foreignInvoice->id,
        ]);

        $response->assertStatus(422);
        $this->assertErrorsOn($response, ['invoice_id']);
        $this->assertSame(BillingPlanItem::STATUS_PENDING, $item->fresh()->status);
    }

    public function test_due_items_are_the_organizations_pending_items_up_to_today(): void
    {
        $plan = $this->plan();
        $due = $this->item($plan, 100, now()->subDay()->toDateString());
        $this->item($plan, 100, now()->addWeek()->toDateString());
        $billed = $this->item($plan, 100, now()->subDay()->toDateString());
        $billed->forceFill(['status' => BillingPlanItem::STATUS_BILLED])->save();

        $response = $this->send('GET', 'sd.billing-plans.due-items')->assertOk();

        $this->assertSame([$due->id], array_column($response->json('data'), 'id'));
        $this->assertSame($plan->id, $response->json('data.0.billing_plan.id'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): BillingPlan
    {
        $plan = new BillingPlan();
        $plan->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'sales_order_id' => $this->order->id,
            'plan_type' => 'milestone',
            'billing_currency' => 'SAR',
            'total_value' => 1000,
            'billed_value' => 0,
            'status' => 'draft',
        ], $attributes))->save();

        return $plan;
    }

    private function item(BillingPlan $plan, int $amount, ?string $date = null): BillingPlanItem
    {
        return BillingPlanItem::create([
            'organization_id' => $plan->organization_id,
            'billing_plan_id' => $plan->id,
            'billing_date' => $date ?? now()->toDateString(),
            'billing_amount' => $amount,
            'status' => BillingPlanItem::STATUS_PENDING,
        ]);
    }

    private function invoice(): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $data
     */
    private function send(string $method, string $route, array $params = [], array $data = []): TestResponse
    {
        $url = route($route, $params);

        if ($method === 'GET' && $data !== []) {
            $url .= '?'.http_build_query($data);
            $data = [];
        }

        return $this->json($method, $url, $data, $this->authHeaders());
    }

    /**
     * @param  list<string>  $fields
     */
    private function assertErrorsOn(TestResponse $response, array $fields): void
    {
        $errors = $response->json('errors') ?? $response->json('error.details') ?? [];

        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $errors, 'No validation error on '.$field.': '.json_encode($response->json()));
        }
    }
}
