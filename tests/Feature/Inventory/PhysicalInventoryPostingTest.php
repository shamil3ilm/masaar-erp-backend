<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\PhysicalInventoryDocument;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\System\Setting;
use App\Services\Inventory\PhysicalInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Posting a count runs once, on the locked document, and books the value of
 * the difference in the ledger; when that entry cannot be posted nothing is.
 */
class PhysicalInventoryPostingTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private PhysicalInventoryService $service;
    private Warehouse $warehouse;
    private Product $short;
    private Product $exact;
    private Account $inventory;
    private Account $shrinkage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->warehouse = $this->warehouse();
        $this->short = $this->stockedProduct();
        $this->exact = $this->stockedProduct();
        $this->stockLevel($this->short, $this->warehouse, 10, 5);
        $this->stockLevel($this->exact, $this->warehouse, 4, 5);

        $this->inventory = $this->account('1300', Account::TYPE_ASSET, Account::SUBTYPE_INVENTORY);
        $this->shrinkage = $this->account('5900', Account::TYPE_EXPENSE, Account::SUBTYPE_OTHER_EXPENSE);
        Setting::set('accounting', 'inventory_adjustment_account_id', $this->shrinkage->id, null, $this->organization->id);

        $this->service = app(PhysicalInventoryService::class);
    }

    public function test_a_second_post_from_a_stale_document_is_rejected(): void
    {
        $document = $this->countedDocument();
        $stale = PhysicalInventoryDocument::findOrFail($document->id);

        $this->service->postAdjustments($document);

        $this->assertRejected(fn () => $this->service->postAdjustments($stale));
        $this->assertSame(1, StockAdjustment::count());
        $this->assertSame(1, StockMovement::count());
        $this->assertEquals(7, $this->quantityOf($this->short, $this->warehouse));
    }

    public function test_posting_books_the_counted_difference_in_the_ledger(): void
    {
        $document = $this->service->postAdjustments($this->countedDocument());

        $entry = JournalEntry::where('source_type', PhysicalInventoryDocument::class)
            ->where('source_id', $document->id)
            ->with('lines')
            ->sole();

        $this->assertEquals(15, (float) $entry->lines->firstWhere('account_id', $this->shrinkage->id)->debit);
        $this->assertEquals(15, (float) $entry->lines->firstWhere('account_id', $this->inventory->id)->credit);
        $this->assertSame(StockAdjustment::STATUS_POSTED, StockAdjustment::sole()->status);
    }

    public function test_posting_rolls_back_when_the_difference_cannot_be_booked(): void
    {
        $document = $this->countedDocument();
        FiscalYear::withoutGlobalScopes()->where('organization_id', $this->organization->id)->update(['is_closed' => true]);

        $this->assertRejected(fn () => $this->service->postAdjustments($document));

        $this->assertSame(PhysicalInventoryDocument::STATUS_COUNTED, $document->fresh()->status);
        $this->assertEquals(10, $this->quantityOf($this->short, $this->warehouse));
        $this->assertSame(0, StockAdjustment::count());
    }

    /** A document counting 7 of the 10 short items and all 4 exact ones. */
    private function countedDocument(): PhysicalInventoryDocument
    {
        $document = $this->service->createDocument([
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->toDateString(),
        ]);

        $counts = [$this->short->id => 7, $this->exact->id => 4];

        return $this->service->enterCounts($document, $document->lines->map(fn ($line) => [
            'line_id' => $line->id,
            'counted_quantity' => $counts[$line->product_id],
        ])->all());
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
