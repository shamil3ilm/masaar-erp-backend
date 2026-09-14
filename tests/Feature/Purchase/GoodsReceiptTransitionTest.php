<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Purchase\GoodsReceipt;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\System\Setting;
use App\Services\Purchase\GoodsReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Posting and reversing a goods receipt run once, on the locked receipt. A
 * receipt that needs quality inspection is held in inspection with its lot
 * and posts once the lot is resolved.
 */
class GoodsReceiptTransitionTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private GoodsReceiptService $service;
    private Warehouse $warehouse;
    private PurchaseOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->warehouse = $this->warehouse();
        $this->order = PurchaseOrder::factory()->confirmed()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $this->organization->id])->id,
            'currency_code' => 'SAR',
        ]);

        $this->account('1300', Account::TYPE_ASSET, Account::SUBTYPE_INVENTORY);
        $grni = $this->account('2150', Account::TYPE_LIABILITY, Account::SUBTYPE_OTHER_LIABILITY);
        Setting::set('accounting', 'grni_account_id', $grni->id, null, $this->organization->id);

        $this->service = app(GoodsReceiptService::class);
    }

    public function test_a_second_post_from_a_stale_receipt_is_rejected(): void
    {
        $product = $this->stockedProduct();
        $receipt = $this->draftReceipt($product, 10);
        $stale = GoodsReceipt::findOrFail($receipt->id);

        $this->service->postGr($receipt);

        $this->assertRejected(fn () => $this->service->postGr($stale));
        $this->assertEquals(10, $this->quantityOf($product, $this->warehouse));
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_a_second_reversal_from_a_stale_receipt_is_rejected(): void
    {
        $product = $this->stockedProduct();
        $this->stockLevel($product, $this->warehouse, 10);
        $receipt = $this->service->postGr($this->draftReceipt($product, 10));
        $stale = GoodsReceipt::findOrFail($receipt->id);

        $this->service->reverseGr($receipt, 'Wrong supplier');

        $this->assertRejected(fn () => $this->service->reverseGr($stale, 'Wrong supplier'));
        $this->assertEquals(10, $this->quantityOf($product, $this->warehouse));
        $this->assertNotNull($receipt->journalEntry->fresh()->reversed_by_id);
    }

    public function test_a_receipt_needing_inspection_is_held_in_inspection(): void
    {
        $product = $this->stockedProduct(['requires_inspection' => true]);

        $receipt = $this->service->postGr($this->draftReceipt($product, 10));

        $this->assertSame(GoodsReceipt::STATUS_IN_INSPECTION, $receipt->status);
        $this->assertSame($receipt->id, InspectionLot::sole()->source_id);
        $this->assertSame(InspectionLot::sole()->id, $receipt->inspection_lot_id);
        $this->assertEquals(0, $this->quantityOf($product, $this->warehouse));
    }

    public function test_a_receipt_in_inspection_is_not_posted_before_the_inspection_is_resolved(): void
    {
        $product = $this->stockedProduct(['requires_inspection' => true]);
        $receipt = $this->service->postGr($this->draftReceipt($product, 10));

        $this->assertRejected(fn () => $this->service->postGr($receipt));

        $this->assertSame(GoodsReceipt::STATUS_IN_INSPECTION, $receipt->fresh()->status);
        $this->assertEquals(0, $this->quantityOf($product, $this->warehouse));
    }

    public function test_a_receipt_posts_the_accepted_quantity_once_its_inspection_is_resolved(): void
    {
        $product = $this->stockedProduct(['requires_inspection' => true]);
        $receipt = $this->service->postGr($this->draftReceipt($product, 10));

        $resolved = $this->service->resolveInspection($receipt, 8, 2, $this->user->id);
        $posted = $this->service->postGr($resolved);

        $this->assertSame(GoodsReceipt::STATUS_POSTED, $posted->status);
        $this->assertEquals(8, $this->quantityOf($product, $this->warehouse));
        $this->assertSame(1, InspectionLot::count());
    }

    private function draftReceipt(Product $product, float $quantity): GoodsReceipt
    {
        return $this->service->createGr($this->order, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [[
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'description' => $product->name,
                'quantity_ordered' => $quantity,
                'quantity_received' => $quantity,
                'unit_cost' => 5,
            ]],
        ]);
    }

    private function account(string $code, string $type, string $subType): Account
    {
        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => $type,
            'sub_type' => $subType,
            'code' => $code,
            'is_system' => true,
            'currency_code' => null,
        ]);
    }
}
