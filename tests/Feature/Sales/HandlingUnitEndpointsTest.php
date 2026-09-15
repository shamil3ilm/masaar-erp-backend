<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\DeliveryMode;
use App\Models\Sales\HandlingUnit;
use App\Models\Sales\HandlingUnitItem;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Models\Sales\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the handling unit endpoints: the list, packing a unit with items,
 * editing and deleting it, adding and removing items, sealing it and the
 * packing list. Keeps shipments, orders, products, batches and order lines
 * inside the caller's organization.
 */
class HandlingUnitEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private SalesOrder $order;
    private Shipment $shipment;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.handling-units.view', 'sales.handling-units.manage']);

        $this->otherOrg = Organization::factory()->create();
        $customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->order = SalesOrder::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $this->shipment = $this->shipmentFor($this->organization, $customer);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_unit_is_packed_with_items_and_listed_with_filters(): void
    {
        $created = $this->send('POST', 'sd.handling-units.store', [], [
            'shipment_id' => $this->shipment->id,
            'sales_order_id' => $this->order->id,
            'hu_type' => 'pallet',
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ]);

        $created->assertStatus(201)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.items.0.product.id', $this->product->id);
        $this->assertStringStartsWith('HU-', $created->json('data.hu_number'));

        $this->unit(['hu_type' => 'box', 'is_sealed' => true, 'shipment_id' => null]);
        $this->unit(['organization_id' => $this->otherOrg->id]);

        $ids = fn (array $query) => array_column($this->send('GET', 'sd.handling-units.index', [], $query)->assertOk()->json('data'), 'id');

        $this->assertCount(2, $ids([]));
        $this->assertSame([$created->json('data.id')], $ids(['hu_type' => 'pallet']));
        $this->assertSame([$created->json('data.id')], $ids(['shipment_id' => $this->shipment->id]));
    }

    public function test_another_organizations_references_are_refused(): void
    {
        $foreignCustomer = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignOrder = SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignLine = SalesOrderLine::factory()->create(['sales_order_id' => $foreignOrder->id, 'product_id' => $foreignProduct->id]);
        $foreignBatch = InventoryBatch::factory()->create(['organization_id' => $this->otherOrg->id, 'product_id' => $foreignProduct->id]);

        $response = $this->send('POST', 'sd.handling-units.store', [], [
            'shipment_id' => $this->shipmentFor($this->otherOrg, $foreignCustomer)->id,
            'sales_order_id' => $foreignOrder->id,
            'items' => [[
                'product_id' => $foreignProduct->id,
                'inventory_batch_id' => $foreignBatch->id,
                'sales_order_line_id' => $foreignLine->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertErrorsOn($response, [
            'shipment_id', 'sales_order_id', 'items.0.product_id', 'items.0.inventory_batch_id', 'items.0.sales_order_line_id',
        ]);

        $unit = $this->unit();
        $added = $this->send('POST', 'sd.handling-units.items.add', ['id' => $unit->id], ['product_id' => $foreignProduct->id, 'quantity' => 1]);
        $added->assertStatus(422);
        $this->assertErrorsOn($added, ['product_id']);
    }

    public function test_a_unit_is_shown_updated_and_deleted_and_another_organizations_is_not_found(): void
    {
        $unit = $this->unit();
        $foreign = $this->unit(['organization_id' => $this->otherOrg->id, 'shipment_id' => null, 'sales_order_id' => null]);

        $this->send('GET', 'sd.handling-units.show', ['id' => $unit->id])
            ->assertOk()
            ->assertJsonPath('data.shipment.id', $this->shipment->id);

        $this->send('PUT', 'sd.handling-units.update', ['id' => $unit->id], ['gross_weight' => 12.5])
            ->assertOk()
            ->assertJsonPath('data.gross_weight', '12.5000');

        foreach ([['GET', 'show'], ['PUT', 'update'], ['DELETE', 'destroy'], ['POST', 'seal']] as [$method, $route]) {
            $this->send($method, "sd.handling-units.{$route}", ['id' => $foreign->id])->assertNotFound();
        }

        $this->send('DELETE', 'sd.handling-units.destroy', ['id' => $unit->id])->assertNoContent();
        $this->assertNull(HandlingUnit::find($unit->id));
    }

    public function test_items_are_added_and_removed_until_the_unit_is_sealed(): void
    {
        $unit = $this->unit();

        $added = $this->send('POST', 'sd.handling-units.items.add', ['id' => $unit->id], ['product_id' => $this->product->id, 'quantity' => 2]);
        $added->assertStatus(201)->assertJsonPath('data.product.id', $this->product->id);

        $second = $this->send('POST', 'sd.handling-units.items.add', ['id' => $unit->id], ['product_id' => $this->product->id, 'quantity' => 1]);
        $this->send('DELETE', 'sd.handling-units.items.remove', ['id' => $unit->id, 'itemId' => $second->json('data.id')])->assertNoContent();

        $this->send('POST', 'sd.handling-units.seal', ['id' => $unit->id])
            ->assertOk()
            ->assertJsonPath('message', 'Handling unit sealed.')
            ->assertJsonPath('data.is_sealed', true);

        $this->send('POST', 'sd.handling-units.seal', ['id' => $unit->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_SEALED');

        $this->send('POST', 'sd.handling-units.items.add', ['id' => $unit->id], ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SEALED')
            ->assertJsonPath('error.message', 'Cannot add items to a sealed handling unit.');

        $this->send('DELETE', 'sd.handling-units.items.remove', ['id' => $unit->id, 'itemId' => $added->json('data.id')])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Cannot remove items from a sealed handling unit.');

        $this->assertSame(1, HandlingUnitItem::where('handling_unit_id', $unit->id)->count());
    }

    public function test_the_packing_list_totals_the_shipments_units(): void
    {
        $first = $this->unit(['gross_weight' => 10]);
        $this->unit(['gross_weight' => 5]);
        HandlingUnitItem::create([
            'organization_id' => $this->organization->id,
            'handling_unit_id' => $first->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
        ]);

        $response = $this->send('GET', 'sd.handling-units.packing-list', ['shipmentId' => $this->shipment->id])->assertOk();

        $response->assertJsonPath('data.shipment_id', $this->shipment->id)
            ->assertJsonPath('data.total_handling_units', 2);
        $this->assertEquals(15, $response->json('data.total_gross_weight'));
        $this->assertSame($this->product->sku, $response->json('data.handling_units.0.items.0.product.sku'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function unit(array $attributes = []): HandlingUnit
    {
        $unit = new HandlingUnit();
        $unit->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'shipment_id' => $this->shipment->id,
            'sales_order_id' => $this->order->id,
            'hu_type' => 'box',
            'hu_number' => 'HU-'.uniqid(),
            'is_sealed' => false,
        ], $attributes))->save();

        return $unit;
    }

    private function shipmentFor(Organization $organization, Contact $contact): Shipment
    {
        return Shipment::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $this->user->id,
            'delivery_mode_id' => DeliveryMode::factory()->create(['organization_id' => $organization->id])->id,
            'contact_id' => $contact->id,
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
