<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\Contact;
use App\Services\Purchase\BillService;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A bill charges what its purchase order charged: the bill lines carry the
 * order's tax rate and discount, so their amounts follow from the same figures.
 */
class BillConversionLineFieldsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_billing_a_purchase_order_carries_the_line_tax_rate_and_discount(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.bills.create']);
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

        $order = PurchaseOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $supplier->id,
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'status' => PurchaseOrder::STATUS_RECEIVED,
        ]);

        $orderLine = PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'description' => 'Supplies',
            'quantity' => 2,
            'quantity_received' => 2,
            'quantity_billed' => 0,
            'unit_price' => 100,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 15,
        ]);
        $order->recalculateTotals();

        $bill = app(BillService::class)->createFromPurchaseOrder($order);

        $billLine = $bill->lines()->firstOrFail();

        $this->assertSame(0, bccomp((string) $billLine->tax_rate, '15', 4));
        $this->assertSame('percentage', $billLine->discount_type);
        $this->assertSame(0, bccomp((string) $billLine->discount_value, '10', 4));
        $this->assertSame(0, bccomp((string) $billLine->discount_amount, '20', 4));
        $this->assertSame(0, bccomp((string) $billLine->subtotal, '180', 4));
        $this->assertSame(0, bccomp((string) $billLine->tax_amount, '27', 4));
        $this->assertSame(0, bccomp((string) $billLine->total, '207', 4));

        $orderLine->refresh();
        $this->assertSame(0, bccomp((string) $bill->subtotal, (string) $orderLine->subtotal, 4));
        $this->assertSame(0, bccomp((string) $bill->tax_amount, (string) $orderLine->tax_amount, 4));
        $this->assertSame(0, bccomp((string) $bill->total, (string) $order->fresh()->total, 4));
    }

    public function test_billing_an_indian_purchase_order_carries_its_gst(): void
    {
        $this->setUpOrganization('IN');
        $this->setUpAuthenticatedUser(['purchase.bills.create']);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod();

        $supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'INR',
            'payment_terms' => 30,
        ]);

        $order = app(PurchaseOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
        ], [
            ['description' => 'Steel rods', 'quantity' => '10', 'unit_price' => '100', 'tax_rate' => '18'],
        ]);

        $order->lines()->update(['quantity_received' => 10]);
        $order->forceFill(['status' => PurchaseOrder::STATUS_RECEIVED])->save();

        $bill = app(BillService::class)->createFromPurchaseOrder($order->fresh('lines'));

        $billLine = $bill->lines()->firstOrFail();

        $this->assertSame(['9.0000', '9.0000'], [$billLine->cgst_rate, $billLine->sgst_rate]);
        $this->assertSame(0, bccomp((string) $billLine->tax_amount, '180', 4));
        $this->assertSame(0, bccomp((string) $bill->total, (string) $order->fresh()->total, 4));
    }
}
