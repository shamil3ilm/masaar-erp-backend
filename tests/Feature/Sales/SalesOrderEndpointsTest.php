<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\Sales\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the sales order list, show, update, delete and confirm, shows the
 * customer without its tax number, and checks that each status change is
 * made against the locked row.
 */
class SalesOrderEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.orders.view',
            'sales.orders.create',
            'sales.orders.edit',
            'sales.orders.delete',
            'sales.orders.confirm',
            'sales.orders.cancel',
            'sales.orders.deliver',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'tax_number' => '300000000000003',
        ]);
    }

    public function test_the_list_searches_and_shows_the_customer_without_its_tax_number(): void
    {
        $alpha = $this->order(SalesOrder::STATUS_DRAFT, ['order_number' => 'SO-ALPHA']);
        $this->order(SalesOrder::STATUS_DRAFT, ['order_number' => 'SO-BETA']);

        $response = $this->apiGet('/sales/sales-orders?search=ALPHA');

        $response->assertOk();
        $this->assertSame([$alpha->id], array_column($response->json('data'), 'id'));
        $this->assertSame($this->customer->contact_name, $response->json('data.0.customer.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $response->json('data.0.customer'));
    }

    public function test_the_list_filters_by_status_and_date_newest_first(): void
    {
        $early = $this->order(SalesOrder::STATUS_DRAFT, ['order_date' => '2025-01-10']);
        $late = $this->order(SalesOrder::STATUS_DRAFT, ['order_date' => '2025-01-20']);
        $this->order(SalesOrder::STATUS_CONFIRMED, ['order_date' => '2025-01-15']);
        $this->order(SalesOrder::STATUS_DRAFT, ['order_date' => '2025-03-01']);

        $response = $this->apiGet('/sales/sales-orders?status=draft&from_date=2025-01-01&to_date=2025-01-31');

        $response->assertOk();
        $this->assertSame([$late->id, $early->id], array_column($response->json('data'), 'id'));
    }

    public function test_show_adds_fulfillment_progress_and_hides_the_customer_tax_number(): void
    {
        $order = $this->order(SalesOrder::STATUS_DRAFT);
        $this->line($order);

        $response = $this->apiGet("/sales/sales-orders/{$order->id}");

        $response->assertOk();
        $this->assertArrayHasKey('fulfillment_progress', $response->json('data'));
        $this->assertCount(1, $response->json('data.lines'));
        $this->assertSame($this->customer->company_name, $response->json('data.customer.company_name'));
        $this->assertArrayNotHasKey('tax_number', $response->json('data.customer'));
    }

    public function test_a_confirmed_order_is_not_updated(): void
    {
        $order = $this->order(SalesOrder::STATUS_CONFIRMED);

        $this->apiPut("/sales/sales-orders/{$order->id}", ['notes' => 'changed'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft sales orders can be updated.');
    }

    public function test_updating_replaces_the_lines_and_renames_a_changed_customer(): void
    {
        $order = $this->order(SalesOrder::STATUS_DRAFT);
        $this->line($order);
        $this->line($order);
        $other = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $response = $this->apiPut("/sales/sales-orders/{$order->id}", [
            'customer_id' => $other->id,
            'lines' => [
                ['description' => 'Replacement', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 15],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Sales order updated successfully.')
            ->assertJsonPath('data.customer_name', $other->getDisplayName());
        $this->assertArrayNotHasKey('tax_number', $response->json('data.customer'));
        $this->assertSame(1, $order->lines()->count());
    }

    public function test_only_a_draft_order_is_deleted_and_its_lines_with_it(): void
    {
        $draft = $this->order(SalesOrder::STATUS_DRAFT);
        $this->line($draft);
        $confirmed = $this->order(SalesOrder::STATUS_CONFIRMED);

        $this->apiDelete("/sales/sales-orders/{$confirmed->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft sales orders can be deleted.');

        $this->apiDelete("/sales/sales-orders/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Sales order deleted successfully.');

        $this->assertNull(SalesOrder::find($draft->id));
        $this->assertSame(0, SalesOrderLine::where('sales_order_id', $draft->id)->count());
    }

    public function test_an_order_without_lines_is_not_confirmed(): void
    {
        $order = $this->order(SalesOrder::STATUS_DRAFT);

        $this->apiPost("/sales/sales-orders/{$order->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Sales order must have at least one line item.');
    }

    public function test_an_invoiced_order_is_not_cancelled(): void
    {
        $order = $this->order(SalesOrder::STATUS_INVOICED);

        $this->apiPost("/sales/sales-orders/{$order->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Sales order cannot be cancelled in its current status.');
    }

    public function test_a_delivery_from_a_warehouse_of_another_organization_is_refused(): void
    {
        $order = $this->order(SalesOrder::STATUS_CONFIRMED);
        $this->line($order);
        $foreign = \App\Models\Inventory\Warehouse::factory()->create([
            'organization_id' => \App\Models\Core\Organization::factory()->create()->id,
        ]);

        $this->apiPost("/sales/sales-orders/{$order->id}/create-delivery", ['warehouse_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id'], 'error.details');
    }

    public function test_an_order_cancelled_meanwhile_is_not_confirmed_through_a_stale_draft(): void
    {
        $order = $this->order(SalesOrder::STATUS_DRAFT);
        $this->line($order);
        $stale = SalesOrder::findOrFail($order->id);
        $service = app(SalesOrderService::class);

        $service->cancel($order);

        try {
            $service->confirm($stale);
            $this->fail('A cancelled order must not be confirmed.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(SalesOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_an_order_confirmed_meanwhile_is_not_updated_deleted_or_cancelled_twice_through_a_stale_draft(): void
    {
        $order = $this->order(SalesOrder::STATUS_DRAFT, ['notes' => 'as confirmed']);
        $this->line($order);
        $stale = SalesOrder::findOrFail($order->id);
        $service = app(SalesOrderService::class);

        $service->confirm($order);

        foreach ([
            'update' => fn () => $service->update($stale, ['notes' => 'rewritten']),
            'delete' => fn () => $service->delete($stale),
        ] as $action => $call) {
            try {
                $call();
                $this->fail("A confirmed order must not be {$action}d through a stale draft.");
            } catch (\InvalidArgumentException) {
            }
        }

        SalesOrder::whereKey($order->id)->update(['status' => SalesOrder::STATUS_INVOICED]);

        try {
            $service->cancel($stale);
            $this->fail('An invoiced order must not be cancelled through a stale draft.');
        } catch (\InvalidArgumentException) {
        }

        $fresh = $order->fresh();
        $this->assertSame('as confirmed', $fresh->notes);
        $this->assertSame(SalesOrder::STATUS_INVOICED, $fresh->status);
    }

    /** @param  array<string, mixed>  $attributes */
    private function order(string $status, array $attributes = []): SalesOrder
    {
        return SalesOrder::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'currency_code' => 'SAR',
            'status' => $status,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    private function line(SalesOrder $order): SalesOrderLine
    {
        return SalesOrderLine::factory()->create([
            'sales_order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);
    }
}
