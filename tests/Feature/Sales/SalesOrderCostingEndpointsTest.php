<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\CostingVersion;
use App\Models\Sales\Contact;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderCostEstimate;
use App\Models\Sales\SalesOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the sales order cost estimate endpoints: the list, creating and editing
 * an estimate, adding cost items and releasing it. Keeps orders, quotations,
 * costing versions, order lines and products inside the caller's organization.
 */
class SalesOrderCostingEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private SalesOrder $order;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.order-costing.view', 'sales.order-costing.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_the_list_shows_the_organizations_estimates_newest_first_and_filters(): void
    {
        $first = $this->estimate();
        $second = $this->estimate(['status' => SalesOrderCostEstimate::STATUS_RELEASED]);
        $this->estimate(['organization_id' => $this->otherOrg->id, 'sales_order_id' => null]);

        $ids = fn (array $query) => array_column($this->send('GET', 'sd.order-costing.index', [], $query)->assertOk()->json('data'), 'id');

        $this->assertSame([$second->id, $first->id], $ids([]));
        $this->assertSame([$second->id], $ids(['status' => 'released']));

        $listed = $this->send('GET', 'sd.order-costing.index');
        $this->assertSame(['id' => $this->user->id, 'name' => $this->user->name], $listed->json('data.0.costed_by'));
        $this->assertSame($this->order->id, $listed->json('data.0.sales_order.id'));
    }

    public function test_an_estimate_is_created_as_a_draft_for_the_organizations_order(): void
    {
        $version = CostingVersion::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->send('POST', 'sd.order-costing.store', [], [
            'sales_order_id' => $this->order->id,
            'costing_version_id' => $version->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', SalesOrderCostEstimate::STATUS_DRAFT)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.costed_by', $this->user->id);
    }

    public function test_another_organizations_order_quotation_and_costing_version_are_refused(): void
    {
        $response = $this->send('POST', 'sd.order-costing.store', [], [
            'sales_order_id' => SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'quotation_id' => Quotation::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'costing_version_id' => CostingVersion::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $response->assertStatus(422);
        $this->assertErrorsOn($response, ['sales_order_id', 'quotation_id', 'costing_version_id']);
        $this->assertSame(0, SalesOrderCostEstimate::count());

        $estimate = $this->estimate();
        $updated = $this->send('PUT', 'sd.order-costing.update', ['id' => $estimate->id], [
            'sales_order_id' => SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);
        $updated->assertStatus(422);
        $this->assertErrorsOn($updated, ['sales_order_id']);
    }

    public function test_items_update_the_totals_and_release_freezes_the_estimate(): void
    {
        $estimate = $this->estimate();
        $line = SalesOrderLine::factory()->create(['sales_order_id' => $this->order->id, 'product_id' => $this->product->id]);

        $this->send('POST', 'sd.order-costing.items.add', ['id' => $estimate->id], [
            'sales_order_line_id' => $line->id,
            'product_id' => $this->product->id,
            'cost_category' => 'material',
            'quantity' => 2,
            'cost_per_unit' => 10,
            'revenue' => 50,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.total_cost', '20.0000');

        $this->send('GET', 'sd.order-costing.show', ['id' => $estimate->id])
            ->assertOk()
            ->assertJsonPath('data.items.0.product.id', $this->product->id)
            ->assertJsonPath('data.total_cost', '20.0000')
            ->assertJsonPath('data.gross_margin', '30.0000');

        $this->send('POST', 'sd.order-costing.release', ['id' => $estimate->id])
            ->assertOk()
            ->assertJsonPath('message', 'Cost estimate released successfully.')
            ->assertJsonPath('data.status', SalesOrderCostEstimate::STATUS_RELEASED)
            ->assertJsonPath('data.gross_margin_percent', '60.0000');

        $this->send('POST', 'sd.order-costing.release', ['id' => $estimate->id])
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Only draft estimates can be released.');

        $this->send('POST', 'sd.order-costing.items.add', ['id' => $estimate->id], [
            'cost_category' => 'labor', 'quantity' => 1, 'cost_per_unit' => 5,
        ])
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Cannot add items to a released cost estimate.');

        $this->send('PUT', 'sd.order-costing.update', ['id' => $estimate->id], ['quotation_id' => null])
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Cannot modify a released cost estimate.');
    }

    public function test_an_item_refuses_another_organizations_product_and_order_line(): void
    {
        $estimate = $this->estimate();
        $foreignOrder = SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignLine = SalesOrderLine::factory()->create(['sales_order_id' => $foreignOrder->id, 'product_id' => $foreignProduct->id]);

        $response = $this->send('POST', 'sd.order-costing.items.add', ['id' => $estimate->id], [
            'sales_order_line_id' => $foreignLine->id,
            'product_id' => $foreignProduct->id,
            'cost_category' => 'material',
            'quantity' => 1,
            'cost_per_unit' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertErrorsOn($response, ['sales_order_line_id', 'product_id']);
    }

    public function test_another_organizations_estimate_is_not_found(): void
    {
        $foreign = $this->estimate(['organization_id' => $this->otherOrg->id, 'sales_order_id' => null]);

        $this->send('GET', 'sd.order-costing.show', ['id' => $foreign->id])->assertNotFound();
        $this->send('PUT', 'sd.order-costing.update', ['id' => $foreign->id], [])->assertNotFound();
        $this->send('POST', 'sd.order-costing.release', ['id' => $foreign->id])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function estimate(array $attributes = []): SalesOrderCostEstimate
    {
        $estimate = new SalesOrderCostEstimate();
        $estimate->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'sales_order_id' => $this->order->id,
            'status' => SalesOrderCostEstimate::STATUS_DRAFT,
            'costed_by' => $this->user->id,
            'costed_at' => now(),
        ], $attributes))->save();

        return $estimate;
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
