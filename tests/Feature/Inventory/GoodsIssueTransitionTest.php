<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\GoodsIssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Posting and reversing a goods issue check its status on the locked issue,
 * and a reversal whose journal entry cannot be voided is not recorded.
 */
class GoodsIssueTransitionTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private GoodsIssueService $service;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->warehouse = $this->warehouse();
        $this->product = $this->stockedProduct();
        $this->stockLevel($this->product, $this->warehouse, 10);

        $this->account('1300', 'Inventory', Account::TYPE_ASSET, Account::SUBTYPE_INVENTORY);
        $this->account('5000', 'Cost of Goods Sold', Account::TYPE_EXPENSE, Account::SUBTYPE_COST_OF_GOODS);

        $this->service = app(GoodsIssueService::class);
    }

    public function test_a_second_post_from_a_stale_issue_is_rejected(): void
    {
        $issue = $this->draftIssue(3);
        $stale = GoodsIssue::findOrFail($issue->id);

        $this->service->post($issue, $this->user->id);

        $this->assertRejected(fn () => $this->service->post($stale, $this->user->id));
        $this->assertEquals(7, $this->quantityOf($this->product, $this->warehouse));
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_a_second_reversal_from_a_stale_issue_is_rejected(): void
    {
        $issue = $this->service->post($this->draftIssue(3), $this->user->id);
        $stale = GoodsIssue::findOrFail($issue->id);

        $this->service->reverse($issue, 'Issued in error', $this->user->id);

        $this->assertRejected(fn () => $this->service->reverse($stale, 'Issued in error', $this->user->id));
        $this->assertEquals(10, $this->quantityOf($this->product, $this->warehouse));
    }

    public function test_a_reversal_rolls_back_when_its_journal_entry_cannot_be_voided(): void
    {
        $issue = $this->service->post($this->draftIssue(3), $this->user->id);
        JournalEntry::whereKey($issue->journal_entry_id)->update(['status' => JournalEntry::STATUS_VOIDED]);

        $this->assertRejected(fn () => $this->service->reverse($issue, 'Issued in error', $this->user->id));

        $this->assertSame(GoodsIssue::STATUS_POSTED, $issue->fresh()->status);
        $this->assertEquals(7, $this->quantityOf($this->product, $this->warehouse));
    }

    private function draftIssue(float $quantity): GoodsIssue
    {
        return $this->service->create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => GoodsIssue::MOVEMENT_SCRAPPING,
            'lines' => [[
                'product_id' => $this->product->id,
                'unit_id' => $this->product->unit_id,
                'quantity' => $quantity,
                'unit_cost' => 5,
            ]],
        ], $this->user->id);
    }

    private function account(string $code, string $name, string $type, string $subType): Account
    {
        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => $type,
            'sub_type' => $subType,
            'code' => $code,
            'name' => $name,
            'is_system' => true,
            'currency_code' => null,
        ]);
    }
}
