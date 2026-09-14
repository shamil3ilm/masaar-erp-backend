<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorCreditNote;
use App\Models\Sales\Contact;
use App\Services\Purchase\VendorCreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Posting and applying a vendor credit note run on the locked note: it is
 * posted once, applied no further than its total, and not posted at all when
 * its journal entry cannot be.
 */
class VendorCreditNoteTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private VendorCreditNoteService $service;
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.credit-notes.view', 'purchase.credit-notes.create']);
        $this->setUpOpenFiscalPeriod();

        $payable = $this->account('2000', 'Accounts Payable', Account::TYPE_LIABILITY, 'payable');
        $returns = $this->account('5100', 'Purchase Returns', Account::TYPE_EXPENSE, 'cost_of_goods');
        Config::set('erp.default_accounts.payable', $payable->id);
        Config::set('erp.default_accounts.purchase_returns', $returns->id);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
        ]);

        $this->service = app(VendorCreditNoteService::class);
    }

    public function test_a_second_post_on_a_stale_credit_note_is_rejected_and_posts_one_journal(): void
    {
        $note = $this->creditNote(100);
        $stale = VendorCreditNote::findOrFail($note->id);

        $this->service->post($note);

        $this->assertRejected(fn () => $this->service->post($stale));
        $this->assertSame(1, JournalEntry::where('source_type', VendorCreditNote::class)
            ->where('source_id', $note->id)->count());
    }

    public function test_post_rolls_back_when_the_journal_cannot_be_posted(): void
    {
        $note = $this->creditNote(100);
        FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->update(['is_closed' => true]);

        $this->assertRejected(fn () => $this->service->post($note));

        $this->assertSame(VendorCreditNote::STATUS_DRAFT, $note->fresh()->status);
    }

    public function test_a_stale_credit_note_is_not_applied_beyond_its_total(): void
    {
        $note = $this->service->post($this->creditNote(100));
        $bill = $this->bill(1000);
        $stale = VendorCreditNote::findOrFail($note->id);

        $this->service->apply($note, $bill, 80);

        $this->assertRejected(fn () => $this->service->apply($stale, $bill, 80));
        $this->assertSame(0, bccomp((string) $note->fresh()->applied_amount, '80', 4));
        $this->assertSame(0, bccomp((string) $bill->fresh()->amount_paid, '80', 4));
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException) {
            return;
        }

        $this->fail('The transition was expected to be rejected.');
    }

    private function creditNote(float $total): VendorCreditNote
    {
        return VendorCreditNote::create([
            'organization_id' => $this->organization->id,
            'credit_note_number' => 'VCN-'.fake()->unique()->numerify('#####'),
            'vendor_id' => $this->supplier->id,
            'issue_date' => now()->toDateString(),
            'credit_date' => now()->toDateString(),
            'status' => VendorCreditNote::STATUS_DRAFT,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total_amount' => $total,
            'applied_amount' => 0,
        ]);
    }

    private function bill(float $total): Bill
    {
        return Bill::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'total' => $total,
            'base_total' => $total,
            'amount_paid' => 0,
            'amount_due' => $total,
            'currency_code' => 'SAR',
        ]);
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
