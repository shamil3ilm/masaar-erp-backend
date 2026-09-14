<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Sales\Contact;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * An Indian purchase order charges GST on its lines, as a bill for the same
 * goods does. For a GST organization the tax calculator returns CGST and SGST
 * (or IGST) rates and no tax rate, so a line that ignores the split stores no
 * tax and the order total leaves GST out.
 */
class PurchaseOrderGstTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_an_intra_state_purchase_order_charges_cgst_and_sgst(): void
    {
        $this->setUpOrganization('IN');
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'INR',
        ]);

        $order = app(PurchaseOrderService::class)->create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
        ], [
            ['description' => 'Steel rods', 'quantity' => '10', 'unit_price' => '100', 'tax_rate' => '18'],
        ]);

        $line = $order->fresh('lines')->lines->sole();
        $this->assertSame(['9.0000', '9.0000'], [$line->cgst_rate, $line->sgst_rate]);
        $this->assertSame(['90.0000', '90.0000', '180.0000'], [$line->cgst_amount, $line->sgst_amount, $line->tax_amount]);

        $order = $order->fresh();
        $this->assertSame(['1000.0000', '180.0000', '1180.0000'], [$order->subtotal, $order->tax_amount, $order->total]);
    }
}
