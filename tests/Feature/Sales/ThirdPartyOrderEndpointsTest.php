<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Models\Sales\ThirdPartyOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the third-party (drop shipment) order endpoints: the list, creating an
 * order with lines, editing it, creating its purchase order and confirming
 * shipment, delivery and cancellation. Keeps orders, vendors, products and
 * order lines inside the caller's organization and the vendor's tax number
 * out of the responses.
 */
class ThirdPartyOrderEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Contact $vendor;
    private SalesOrder $order;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.third-party-orders.view', 'sales.third-party-orders.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->vendor = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
            'tax_number' => '300000000000003',
        ]);
        $this->order = SalesOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_an_order_is_created_with_lines_and_listed_without_the_vendors_tax_number(): void
    {
        $line = SalesOrderLine::factory()->create(['sales_order_id' => $this->order->id, 'product_id' => $this->product->id]);

        $created = $this->send('POST', 'sd.tpo.store', [], $this->payload([
            'lines' => [[
                'product_id' => $this->product->id,
                'sales_order_line_id' => $line->id,
                'quantity' => 2,
                'unit_price' => 50,
                'vendor_price' => 30,
            ]],
        ]));

        $created->assertStatus(201)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.lines.0.product.id', $this->product->id);
        $this->assertArrayNotHasKey('tax_number', $created->json('data.vendor'));

        $this->tpo(['status' => ThirdPartyOrder::STATUS_SHIPPED]);
        $this->tpo(['organization_id' => $this->otherOrg->id]);

        $listed = $this->send('GET', 'sd.tpo.index')->assertOk();
        $this->assertCount(2, $listed->json('data'));
        $this->assertSame($this->vendor->company_name, $listed->json('data.1.vendor.company_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.vendor'));

        $this->assertSame(
            [$created->json('data.id')],
            array_column($this->send('GET', 'sd.tpo.index', [], ['status' => 'pending'])->json('data'), 'id')
        );
    }

    public function test_another_organizations_order_vendor_product_and_order_line_are_refused(): void
    {
        $foreignOrder = SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignLine = SalesOrderLine::factory()->create(['sales_order_id' => $foreignOrder->id, 'product_id' => $foreignProduct->id]);

        $response = $this->send('POST', 'sd.tpo.store', [], [
            'sales_order_id' => $foreignOrder->id,
            'vendor_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'lines' => [[
                'product_id' => $foreignProduct->id,
                'sales_order_line_id' => $foreignLine->id,
                'quantity' => 1,
                'unit_price' => 1,
            ]],
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertErrorsOn($response, ['sales_order_id', 'vendor_id', 'lines.0.product_id', 'lines.0.sales_order_line_id']);
        $this->assertSame(0, ThirdPartyOrder::count());
    }

    public function test_an_order_is_shown_and_updated_and_another_organizations_is_not_found(): void
    {
        $tpo = $this->tpo();
        $foreign = $this->tpo(['organization_id' => $this->otherOrg->id]);

        $shown = $this->send('GET', 'sd.tpo.show', ['id' => $tpo->id])->assertOk();
        $this->assertSame($this->order->id, $shown->json('data.sales_order.id'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.vendor'));

        $this->send('PUT', 'sd.tpo.update', ['id' => $tpo->id], ['vendor_reference' => 'V-1'])
            ->assertOk()
            ->assertJsonPath('data.vendor_reference', 'V-1');

        $this->send('GET', 'sd.tpo.show', ['id' => $foreign->id])->assertNotFound();
        $this->send('PUT', 'sd.tpo.update', ['id' => $foreign->id], ['notes' => 'x'])->assertNotFound();
        $this->send('POST', 'sd.tpo.cancel', ['id' => $foreign->id])->assertNotFound();
    }

    public function test_a_purchase_order_is_created_once_for_a_pending_order(): void
    {
        $created = $this->send('POST', 'sd.tpo.store', [], $this->payload([
            'lines' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50, 'vendor_price' => 30]],
        ]));
        $id = $created->json('data.id');

        $po = $this->send('POST', 'sd.tpo.create-po', ['id' => $id]);

        $po->assertStatus(201)
            ->assertJsonPath('message', 'Purchase order created successfully.')
            ->assertJsonPath('data.supplier_id', $this->vendor->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total', '60.0000');

        $tpo = ThirdPartyOrder::findOrFail($id);
        $this->assertSame(ThirdPartyOrder::STATUS_PO_CREATED, $tpo->status);
        $this->assertSame($po->json('data.id'), $tpo->purchase_order_id);

        $this->send('POST', 'sd.tpo.create-po', ['id' => $id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_shipment_delivery_and_cancellation_are_confirmed(): void
    {
        $tpo = $this->tpo();

        $this->send('POST', 'sd.tpo.confirm-shipment', ['id' => $tpo->id], ['shipping_confirmation' => 'TRK-1'])
            ->assertOk()
            ->assertJsonPath('message', 'Shipment confirmed.')
            ->assertJsonPath('data.status', ThirdPartyOrder::STATUS_SHIPPED)
            ->assertJsonPath('data.shipping_confirmation', 'TRK-1');

        $this->send('POST', 'sd.tpo.confirm-delivery', ['id' => $tpo->id])->assertStatus(422);

        $this->send('POST', 'sd.tpo.confirm-delivery', ['id' => $tpo->id], ['actual_delivery_date' => '2026-09-01'])
            ->assertOk()
            ->assertJsonPath('message', 'Delivery confirmed.')
            ->assertJsonPath('data.status', ThirdPartyOrder::STATUS_DELIVERED);

        $other = $this->tpo();
        $this->send('POST', 'sd.tpo.cancel', ['id' => $other->id])
            ->assertOk()
            ->assertJsonPath('message', 'Third-party order cancelled.')
            ->assertJsonPath('data.status', ThirdPartyOrder::STATUS_CANCELLED);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sales_order_id' => $this->order->id,
            'vendor_id' => $this->vendor->id,
            'shipping_city' => 'Riyadh',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tpo(array $attributes = []): ThirdPartyOrder
    {
        $tpo = new ThirdPartyOrder();
        $tpo->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'sales_order_id' => $this->order->id,
            'vendor_id' => $this->vendor->id,
            'status' => ThirdPartyOrder::STATUS_PENDING,
        ], $attributes))->save();

        return $tpo;
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
