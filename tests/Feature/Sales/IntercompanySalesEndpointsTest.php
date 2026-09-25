<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\Sales\IntercompanyBillingDocument;
use App\Models\Sales\IntercompanySalesOrder;
use App\Services\Sales\IntercompanySalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the intercompany sales order endpoints: the list and its filters,
 * creating an order with lines, the confirm, delivery, billing and cancel
 * workflow and linking the buyer's purchase order.
 *
 * An intercompany order belongs to two organizations and has no single
 * organization column. Only a user of the selling or the buying organization
 * may see or act on it, both sides belong to one parent-subsidiary group,
 * the order's products belong to the seller and the linked purchase order to
 * the buyer.
 */
class IntercompanySalesEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $buyer;
    private Organization $stranger;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.intercompany-orders.view', 'sales.intercompany-orders.manage']);

        $this->buyer = Organization::factory()->create(['parent_organization_id' => $this->organization->id]);
        $this->stranger = Organization::factory()->create();
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_an_order_is_created_with_lines_and_totals(): void
    {
        $response = $this->send('POST', 'ic.sales-orders.store', [], $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Intercompany sales order created.')
            ->assertJsonPath('data.status', IntercompanySalesOrder::STATUS_DRAFT)
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.tax_amount', 30)
            ->assertJsonPath('data.total_amount', 230)
            ->assertJsonPath('data.lines.0.product_id', $this->product->id)
            ->assertJsonPath('data.purchase_order_link.status', 'pending')
            ->assertJsonPath('data.created_by', $this->user->id);
    }

    public function test_an_order_between_two_other_organizations_or_with_the_buyers_products_is_refused(): void
    {
        $between = $this->send('POST', 'ic.sales-orders.store', [], $this->payload([
            'selling_organization_id' => $this->stranger->id,
            'lines' => [$this->line(['product_id' => Product::factory()->create(['organization_id' => $this->stranger->id])->id])],
        ]));
        $between->assertStatus(422);
        $this->assertArrayHasKey('selling_organization_id', $between->json('errors') ?? []);

        $foreignProduct = $this->send('POST', 'ic.sales-orders.store', [], $this->payload([
            'lines' => [$this->line(['product_id' => Product::factory()->create(['organization_id' => $this->buyer->id])->id])],
        ]));
        $foreignProduct->assertStatus(422);
        $this->assertArrayHasKey('lines.0.product_id', $foreignProduct->json('errors') ?? []);

        $this->assertSame(0, IntercompanySalesOrder::count());
    }

    public function test_the_list_shows_orders_of_the_callers_organization_on_either_side_and_filters(): void
    {
        $sold = $this->order();
        $bought = $this->order(['selling_organization_id' => $this->buyer->id, 'buying_organization_id' => $this->organization->id, 'status' => 'confirmed']);
        $this->order(['selling_organization_id' => $this->buyer->id, 'buying_organization_id' => $this->stranger->id]);

        $ids = fn (array $query) => array_column($this->send('GET', 'ic.sales-orders.index', [], $query)->assertOk()->json('data'), 'id');

        $this->assertEqualsCanonicalizing([$sold->id, $bought->id], $ids([]));
        $this->assertSame([$sold->id], $ids(['selling_organization_id' => $this->organization->id]));
        $this->assertSame([$bought->id], $ids(['buying_organization_id' => $this->organization->id]));
        $this->assertSame([$bought->id], $ids(['status' => 'confirmed']));
    }

    public function test_an_order_of_other_organizations_is_not_found(): void
    {
        $foreign = $this->order(['selling_organization_id' => $this->buyer->id, 'buying_organization_id' => $this->stranger->id]);

        $this->send('GET', 'ic.sales-orders.show', ['id' => $foreign->id])->assertNotFound();
        $this->send('PUT', 'ic.sales-orders.update', ['id' => $foreign->id], ['notes' => 'x'])->assertNotFound();
        $this->send('POST', 'ic.sales-orders.confirm', ['id' => $foreign->id])->assertNotFound();
        $this->send('POST', 'ic.sales-orders.cancel', ['id' => $foreign->id])->assertNotFound();

        $this->assertSame(IntercompanySalesOrder::STATUS_DRAFT, $foreign->fresh()->status);
    }

    public function test_an_order_is_shown_updated_confirmed_delivered_billed_and_posted(): void
    {
        $id = $this->send('POST', 'ic.sales-orders.store', [], $this->payload())->json('data.id');

        $this->send('GET', 'ic.sales-orders.show', ['id' => $id])
            ->assertOk()
            ->assertJsonPath('data.lines.0.product_id', $this->product->id);

        $this->send('PUT', 'ic.sales-orders.update', ['id' => $id], ['notes' => 'Urgent'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Urgent');

        $this->send('POST', 'ic.sales-orders.confirm', ['id' => $id])
            ->assertOk()
            ->assertJsonPath('data.status', IntercompanySalesOrder::STATUS_CONFIRMED);

        $this->send('POST', 'ic.sales-orders.confirm', ['id' => $id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->send('POST', 'ic.sales-orders.start-delivery', ['id' => $id])
            ->assertOk()
            ->assertJsonPath('data.status', IntercompanySalesOrder::STATUS_IN_DELIVERY);

        $document = $this->send('POST', 'ic.sales-orders.billing.create', ['id' => $id], [
            'document_number' => 'IV-1',
            'billing_date' => '2026-09-01',
            'subtotal' => 200,
            'tax_amount' => 30,
            'total_amount' => 230,
        ]);
        $document->assertStatus(201)->assertJsonPath('data.status', IntercompanyBillingDocument::STATUS_DRAFT);
        $documentId = $document->json('data.id');

        $this->send('POST', 'ic.sales-orders.billing.post', ['id' => $id, 'billingDocId' => $documentId])
            ->assertOk()
            ->assertJsonPath('message', 'Billing document posted.')
            ->assertJsonPath('data.status', IntercompanyBillingDocument::STATUS_POSTED);

        $this->assertSame(IntercompanySalesOrder::STATUS_BILLED, IntercompanySalesOrder::findOrFail($id)->status);

        $this->send('POST', 'ic.sales-orders.billing.post', ['id' => $id, 'billingDocId' => $documentId])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_draft_is_cancelled_with_its_purchase_order_link_and_cannot_be_billed(): void
    {
        $id = $this->send('POST', 'ic.sales-orders.store', [], $this->payload())->json('data.id');

        $this->send('POST', 'ic.sales-orders.billing.create', ['id' => $id], [
            'document_number' => 'IV-1', 'billing_date' => '2026-09-01', 'subtotal' => 1, 'total_amount' => 1,
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->send('POST', 'ic.sales-orders.start-delivery', ['id' => $id])->assertStatus(422);

        $this->send('POST', 'ic.sales-orders.cancel', ['id' => $id])
            ->assertOk()
            ->assertJsonPath('message', 'Intercompany sales order cancelled.')
            ->assertJsonPath('data.status', IntercompanySalesOrder::STATUS_CANCELLED);

        $this->assertSame('cancelled', IntercompanySalesOrder::findOrFail($id)->purchaseOrderLink()->value('status'));
    }

    public function test_only_a_purchase_order_of_the_buying_organization_is_linked(): void
    {
        $id = $this->send('POST', 'ic.sales-orders.store', [], $this->payload())->json('data.id');

        $buyersOrder = $this->purchaseOrder($this->buyer);
        $this->send('POST', 'ic.sales-orders.link-po', ['id' => $id], ['purchase_order_id' => $buyersOrder->id])
            ->assertOk()
            ->assertJsonPath('message', 'Purchase order linked.')
            ->assertJsonPath('data.purchase_order_id', $buyersOrder->id)
            ->assertJsonPath('data.status', 'linked');

        $strangersOrder = $this->purchaseOrder($this->stranger);
        $refused = $this->send('POST', 'ic.sales-orders.link-po', ['id' => $id], ['purchase_order_id' => $strangersOrder->id]);
        $refused->assertStatus(422);
        $this->assertArrayHasKey('purchase_order_id', $refused->json('errors') ?? []);
    }

    public function test_an_order_outside_the_callers_group_is_refused_and_one_with_the_parent_is_accepted(): void
    {
        $outsideBuyer = $this->send('POST', 'ic.sales-orders.store', [], $this->payload([
            'buying_organization_id' => $this->stranger->id,
        ]));
        $outsideBuyer->assertStatus(422);
        $this->assertArrayHasKey('buying_organization_id', $outsideBuyer->json('errors') ?? []);

        $outsideSeller = $this->send('POST', 'ic.sales-orders.store', [], $this->payload([
            'selling_organization_id' => $this->stranger->id,
            'buying_organization_id' => $this->organization->id,
            'lines' => [$this->line(['product_id' => Product::factory()->create(['organization_id' => $this->stranger->id])->id])],
        ]));
        $outsideSeller->assertStatus(422);
        $this->assertArrayHasKey('selling_organization_id', $outsideSeller->json('errors') ?? []);

        $this->assertSame(0, IntercompanySalesOrder::count());

        $parent = Organization::factory()->create();

        $this->organization->parent_organization_id = $parent->id;
        $this->organization->save();

        $this->send('POST', 'ic.sales-orders.store', [], $this->payload([
            'selling_organization_id' => $parent->id,
            'buying_organization_id' => $this->organization->id,
            'lines' => [$this->line(['product_id' => Product::factory()->create(['organization_id' => $parent->id])->id])],
        ]))->assertStatus(201);

        $this->assertSame(1, IntercompanySalesOrder::count());
    }

    /**
     * The request rule is not the only guard: an order commits both ledgers,
     * so the service refuses a counterparty outside the group when it is
     * called directly, and again on the locked order at confirm.
     */
    public function test_the_service_refuses_a_counterparty_outside_the_group(): void
    {
        $service = app(IntercompanySalesService::class);

        try {
            $service->create([
                'selling_organization_id' => $this->organization->id,
                'buying_organization_id' => $this->stranger->id,
                'order_number' => 'ICSO-X',
                'order_date' => '2026-09-01',
                'lines' => [$this->line()],
            ]);
            $this->fail('The service created an order outside the group.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('ORGANIZATION_OUTSIDE_GROUP', $e->getErrorCode());
        }

        $this->assertSame(0, IntercompanySalesOrder::count());

        $order = $this->order(['buying_organization_id' => $this->stranger->id]);

        try {
            $service->confirm($order);
            $this->fail('The service confirmed an order outside the group.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('ORGANIZATION_OUTSIDE_GROUP', $e->getErrorCode());
        }

        $this->assertSame(IntercompanySalesOrder::STATUS_DRAFT, $order->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'selling_organization_id' => $this->organization->id,
            'buying_organization_id' => $this->buyer->id,
            'order_number' => 'ICSO-1',
            'order_date' => '2026-09-01',
            'lines' => [$this->line()],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function line(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->product->id,
            'line_number' => 1,
            'quantity' => 2,
            'transfer_price' => 100,
            'tax_rate' => 15,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): IntercompanySalesOrder
    {
        $order = new IntercompanySalesOrder();
        $order->forceFill(array_merge([
            'selling_organization_id' => $this->organization->id,
            'buying_organization_id' => $this->buyer->id,
            'order_number' => 'ICSO-'.uniqid(),
            'status' => IntercompanySalesOrder::STATUS_DRAFT,
            'order_date' => '2026-09-01',
            'currency_code' => 'SAR',
        ], $attributes))->save();

        return $order;
    }

    private function purchaseOrder(Organization $organization): PurchaseOrder
    {
        return PurchaseOrder::factory()->create([
            'organization_id' => $organization->id,
            'supplier_id' => Contact::factory()->create(['organization_id' => $organization->id])->id,
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
}
