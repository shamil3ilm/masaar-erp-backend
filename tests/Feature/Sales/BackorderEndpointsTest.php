<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\BackorderRecord;
use App\Models\Sales\Contact;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the backorder endpoints: the list in priority order, recording a
 * backorder, rescheduling, fulfilling and cancelling it and the report. Keeps
 * orders, order lines and products inside the caller's organization.
 */
class BackorderEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private SalesOrder $order;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.backorders.view', 'sales.backorders.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_the_list_shows_the_organizations_backorders_by_priority_and_filters(): void
    {
        $low = $this->backorder(['priority' => 8]);
        $high = $this->backorder(['priority' => 1, 'status' => BackorderRecord::STATUS_PARTIALLY_FULFILLED]);
        $this->backorder(['organization_id' => $this->otherOrg->id, 'priority' => 0]);

        $ids = fn (array $query) => array_column($this->send('GET', 'sd.backorders.index', [], $query)->assertOk()->json('data'), 'id');

        $this->assertSame([$high->id, $low->id], $ids([]));
        $this->assertSame([$low->id], $ids(['status' => 'open']));
        $this->assertSame([$high->id], $ids(['priority' => 1]));
        $this->assertSame($this->product->id, $this->send('GET', 'sd.backorders.index')->json('data.0.product.id'));
    }

    public function test_a_backorder_is_recorded_for_the_organizations_order(): void
    {
        $line = SalesOrderLine::factory()->create(['sales_order_id' => $this->order->id, 'product_id' => $this->product->id]);

        $this->send('POST', 'sd.backorders.store', [], [
            'sales_order_id' => $this->order->id,
            'sales_order_line_id' => $line->id,
            'product_id' => $this->product->id,
            'original_quantity' => 10,
            'backordered_quantity' => 4,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.sales_order.id', $this->order->id)
            ->assertJsonPath('data.product.id', $this->product->id);
    }

    public function test_another_organizations_order_line_and_product_are_refused(): void
    {
        $foreignOrder = SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignLine = SalesOrderLine::factory()->create(['sales_order_id' => $foreignOrder->id, 'product_id' => $foreignProduct->id]);

        $response = $this->send('POST', 'sd.backorders.store', [], [
            'sales_order_id' => $foreignOrder->id,
            'sales_order_line_id' => $foreignLine->id,
            'product_id' => $foreignProduct->id,
            'original_quantity' => 10,
            'backordered_quantity' => 4,
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertErrorsOn($response, ['sales_order_id', 'sales_order_line_id', 'product_id']);
        $this->assertSame(0, BackorderRecord::count());
    }

    public function test_a_backorder_is_shown_rescheduled_and_another_organizations_is_not_found(): void
    {
        $record = $this->backorder();
        $foreign = $this->backorder(['organization_id' => $this->otherOrg->id]);

        $this->send('GET', 'sd.backorders.show', ['id' => $record->id])
            ->assertOk()
            ->assertJsonPath('data.product.id', $this->product->id);

        $this->send('POST', 'sd.backorders.reschedule', ['id' => $record->id], [
            'rescheduled_delivery_date' => '2026-10-01',
            'reason' => 'Supplier delay',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Backorder rescheduled.')
            ->assertJsonPath('data.reason', 'Supplier delay');

        $this->send('GET', 'sd.backorders.show', ['id' => $foreign->id])->assertNotFound();
        $this->send('POST', 'sd.backorders.fulfill', ['id' => $foreign->id], ['quantity' => 1])->assertNotFound();
        $this->send('POST', 'sd.backorders.cancel', ['id' => $foreign->id])->assertNotFound();
    }

    public function test_fulfilment_is_partial_then_complete_and_capped_at_the_backordered_quantity(): void
    {
        $record = $this->backorder(['backordered_quantity' => 5]);

        $this->send('POST', 'sd.backorders.fulfill', ['id' => $record->id], ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('message', 'Backorder fulfilled.')
            ->assertJsonPath('data.status', BackorderRecord::STATUS_PARTIALLY_FULFILLED)
            ->assertJsonPath('data.fulfilled_quantity', '2.0000');

        $this->send('POST', 'sd.backorders.fulfill', ['id' => $record->id], ['quantity' => 9])
            ->assertOk()
            ->assertJsonPath('data.status', BackorderRecord::STATUS_FULFILLED)
            ->assertJsonPath('data.fulfilled_quantity', '5.0000');
    }

    public function test_a_cancelled_or_fulfilled_backorder_is_not_fulfilled_again(): void
    {
        $record = $this->backorder();

        $this->send('POST', 'sd.backorders.cancel', ['id' => $record->id])
            ->assertOk()
            ->assertJsonPath('message', 'Backorder cancelled.')
            ->assertJsonPath('data.status', BackorderRecord::STATUS_CANCELLED);

        $this->send('POST', 'sd.backorders.fulfill', ['id' => $record->id], ['quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(BackorderRecord::STATUS_CANCELLED, $record->fresh()->status);
    }

    public function test_the_report_totals_open_backorders_by_product_and_order(): void
    {
        $this->backorder(['backordered_quantity' => 4, 'fulfilled_quantity' => 1, 'status' => BackorderRecord::STATUS_PARTIALLY_FULFILLED]);
        $this->backorder(['backordered_quantity' => 6]);
        $this->backorder(['backordered_quantity' => 9, 'status' => BackorderRecord::STATUS_FULFILLED]);
        $this->backorder(['organization_id' => $this->otherOrg->id, 'backordered_quantity' => 50]);

        $response = $this->send('GET', 'sd.backorders.report')->assertOk();

        $this->assertEquals(10, $response->json('data.by_product.0.total_backordered'));
        $this->assertSame($this->product->name, $response->json('data.by_product.0.product.name'));
        $this->assertEquals(9, $response->json('data.by_sales_order.0.outstanding_quantity'));
        $this->assertSame(1, $response->json('data.summary.total_open'));
        $this->assertSame(1, $response->json('data.summary.total_partially_fulfilled'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function backorder(array $attributes = []): BackorderRecord
    {
        $record = new BackorderRecord();
        $record->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'sales_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'original_quantity' => 10,
            'backordered_quantity' => 5,
            'fulfilled_quantity' => 0,
            'status' => BackorderRecord::STATUS_OPEN,
            'priority' => 5,
        ], $attributes))->save();

        return $record;
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
