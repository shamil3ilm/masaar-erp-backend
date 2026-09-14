<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Inventory\Product;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\Contact;
use App\Services\Purchase\BillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Billing a purchase order reads what is left to bill from the locked order,
 * so the same received quantity is never billed twice.
 */
class BillFromPurchaseOrderTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private BillService $service;
    private PurchaseOrder $order;
    private PurchaseOrderLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.bills.view', 'purchase.bills.create']);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod();

        $supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
            'payment_terms' => 30,
        ]);

        $product = Product::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'track_inventory' => false,
        ]);

        $this->order = PurchaseOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'currency_code' => 'SAR',
            'status' => PurchaseOrder::STATUS_RECEIVED,
        ]);

        $this->line = PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $this->order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'quantity_received' => 10,
            'quantity_billed' => 0,
            'unit_price' => 50,
        ]);

        $this->service = app(BillService::class);
    }

    public function test_a_second_bill_from_a_stale_order_is_rejected(): void
    {
        $stale = PurchaseOrder::with('lines')->findOrFail($this->order->id);

        $this->service->createFromPurchaseOrder($this->order);

        $this->assertRejected(fn () => $this->service->createFromPurchaseOrder($stale));
        $this->assertSame(1, Bill::where('purchase_order_id', $this->order->id)->count());
        $this->assertSame(0, bccomp((string) $this->line->fresh()->quantity_billed, '10', 4));
        $this->assertSame(PurchaseOrder::STATUS_BILLED, $this->order->fresh()->status);
    }

    public function test_billing_more_than_is_left_to_bill_is_rejected(): void
    {
        $this->assertRejected(fn () => $this->service->createFromPurchaseOrder($this->order, [$this->line->id => 15]));

        $this->assertSame(0, Bill::count());
        $this->assertSame(0, bccomp((string) $this->line->fresh()->quantity_billed, '0', 4));
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('The transition was expected to be rejected.');
    }
}
