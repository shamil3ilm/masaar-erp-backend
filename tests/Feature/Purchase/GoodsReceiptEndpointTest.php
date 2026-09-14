<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchase\Bill;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Goods receipt endpoints: listing, creating a draft against an order, and
 * refusing ids of rows the caller's organization or order does not own.
 */
class GoodsReceiptEndpointTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/goods-receipts';
    private Warehouse $warehouse;
    private Product $product;
    private PurchaseOrder $order;
    private PurchaseOrderLine $orderLine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['purchase.gr.view', 'purchase.gr.create']);

        $this->warehouse = $this->warehouse();
        $this->product = $this->stockedProduct();
        $this->order = PurchaseOrder::factory()->confirmed()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $this->organization->id])->id,
            'currency_code' => 'SAR',
        ]);
        $this->orderLine = PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->product->unit_id,
            'quantity' => 10,
        ]);
    }

    public function test_store_creates_a_draft_receipt_linked_to_the_order_line(): void
    {
        $response = $this->apiPost($this->baseUrl, $this->payload($this->order, $this->orderLine->id));

        $response->assertCreated()
            ->assertJsonPath('data.status', GoodsReceipt::STATUS_DRAFT)
            ->assertJsonPath('data.lines.0.unit.id', $this->product->unit_id);
        $this->assertDatabaseHas('goods_receipt_lines', [
            'gr_id' => $response->json('data.id'),
            'po_line_id' => $this->orderLine->id,
            'description' => $this->orderLine->description,
        ]);
    }

    public function test_index_lists_the_organizations_receipts(): void
    {
        $gr = $this->apiPost($this->baseUrl, $this->payload($this->order, $this->orderLine->id))->assertCreated();

        $this->apiGet($this->baseUrl)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.gr_number', $gr->json('data.gr_number'));
    }

    public function test_store_refuses_another_organizations_purchase_order(): void
    {
        [$foreignOrder] = $this->foreignOrderWithLine();

        $this->apiPost($this->baseUrl, $this->payload($foreignOrder))
            ->assertStatus(422)->assertJsonValidationErrors('purchase_order_id');
    }

    public function test_store_refuses_a_line_of_another_organizations_order(): void
    {
        [, $foreignLine] = $this->foreignOrderWithLine();

        $this->apiPost($this->baseUrl, $this->payload($this->order, $foreignLine->id))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.po_line_id');

        $this->assertSame(0, GoodsReceipt::withoutGlobalScopes()->count());
    }

    public function test_three_way_match_refuses_another_organizations_bill(): void
    {
        $other = Organization::factory()->create();
        $foreignBill = Bill::factory()->approved()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);

        $this->apiGet("{$this->baseUrl}/three-way-match?bill_id={$foreignBill->id}")
            ->assertStatus(422)->assertJsonValidationErrors('bill_id');
    }

    private function payload(PurchaseOrder $order, ?int $poLineId = null): array
    {
        return [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [array_filter([
                'po_line_id' => $poLineId,
                'product_id' => $this->product->id,
                'unit_id' => $this->product->unit_id,
                'quantity_received' => 2,
                'unit_cost' => 5,
            ], fn ($value) => $value !== null)],
        ];
    }

    /**
     * @return array{PurchaseOrder, PurchaseOrderLine}
     */
    private function foreignOrderWithLine(): array
    {
        $other = Organization::factory()->create();
        $order = PurchaseOrder::factory()->confirmed()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);

        return [$order, PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id, 'quantity' => 5])];
    }
}
