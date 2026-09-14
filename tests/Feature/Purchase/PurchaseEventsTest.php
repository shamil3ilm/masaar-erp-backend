<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Events\Purchase\BillApproved;
use App\Events\Purchase\PurchaseOrderReceived;
use App\Models\Core\Notification;
use App\Models\Inventory\Product;
use App\Models\Purchase\Bill;
use App\Models\Purchase\BillLine;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Sales\Contact;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseEventsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['purchase.bills.approve', 'purchase.orders.receive']);
        $this->setUpOpenFiscalPeriod();

        $this->supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
            'company_name' => 'Acme Supplies',
        ]);
    }

    public function test_approving_a_bill_dispatches_bill_approved(): void
    {
        Event::fake([BillApproved::class]);

        $bill = $this->draftBill();

        $this->assertSuccessResponse($this->apiPost("/purchase/bills/{$bill->id}/approve"));

        Event::assertDispatched(BillApproved::class, fn (BillApproved $event) => $event->bill->id === $bill->id
            && $event->bill->status === Bill::STATUS_APPROVED);
    }

    public function test_bill_approval_notifies_the_creator_with_the_supplier_name(): void
    {
        $bill = $this->draftBill();

        $this->assertSuccessResponse($this->apiPost("/purchase/bills/{$bill->id}/approve"));

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'bill_approved')
            ->firstOrFail();

        $this->assertSame('Acme Supplies', $notification->data['supplier_name']);
    }

    public function test_receiving_dispatches_the_quantities_this_receipt_took(): void
    {
        Event::fake([PurchaseOrderReceived::class]);

        [$order, $line] = $this->confirmedOrder();

        $this->assertSuccessResponse($this->apiPost("/purchase/purchase-orders/{$order->id}/receive", [
            'line_quantities' => [$line->id => 4],
        ]));

        Event::assertDispatched(PurchaseOrderReceived::class, fn (PurchaseOrderReceived $event) => $event->purchaseOrder->id === $order->id
            && $event->receivedQuantities === [$line->id => 4.0]
            && $event->isFullyReceived === false);

        // Asking for more than remains receives only the remaining six.
        $this->assertSuccessResponse($this->apiPost("/purchase/purchase-orders/{$order->id}/receive", [
            'line_quantities' => [$line->id => 20],
        ]));

        Event::assertDispatched(PurchaseOrderReceived::class, fn (PurchaseOrderReceived $event) => $event->receivedQuantities === [$line->id => 6.0]
            && $event->isFullyReceived === true);
        Event::assertDispatchedTimes(PurchaseOrderReceived::class, 2);
    }

    public function test_a_receipt_that_takes_nothing_dispatches_nothing(): void
    {
        Event::fake([PurchaseOrderReceived::class]);

        [$order, $line] = $this->confirmedOrder();

        app(PurchaseOrderService::class)->receive($order, [$line->id => 0]);

        Event::assertNotDispatched(PurchaseOrderReceived::class);
    }

    public function test_purchase_order_receipt_notifies_the_creator(): void
    {
        [$order, $line] = $this->confirmedOrder();

        $this->assertSuccessResponse($this->apiPost("/purchase/purchase-orders/{$order->id}/receive", [
            'line_quantities' => [$line->id => 10],
        ]));

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'po_received')
            ->firstOrFail();

        $this->assertTrue($notification->data['is_fully_received']);
    }

    private function draftBill(): Bill
    {
        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'status' => Bill::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        BillLine::factory()->create([
            'bill_id' => $bill->id,
            'product_id' => Product::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);

        return $bill;
    }

    /** @return array{0: PurchaseOrder, 1: PurchaseOrderLine} */
    private function confirmedOrder(): array
    {
        $order = PurchaseOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'currency_code' => 'SAR',
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'created_by' => $this->user->id,
        ]);

        $line = PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $order->id,
            'quantity' => 10,
            'unit_price' => 50.00,
        ]);

        return [$order, $line];
    }
}
