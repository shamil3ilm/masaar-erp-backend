<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Contact;
use App\Services\Purchase\PaymentMadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Completing and voiding a supplier payment run on the locked row: a second
 * call with an instance loaded before the first is rejected, bill balances
 * are adjusted from the locked bill, and a journal failure rolls back.
 */
class PaymentMadeTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private PaymentMadeService $service;
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.payments.view', 'purchase.payments.create']);
        $this->setUpOpenFiscalPeriod();

        $payable = $this->account('2000', 'Accounts Payable', Account::TYPE_LIABILITY, 'payable');
        $bank = $this->account('1010', 'Cash at Bank', Account::TYPE_ASSET, Account::SUBTYPE_BANK);

        Config::set('erp.default_accounts.cash', $bank->id);
        Config::set('erp.default_accounts.payable', $payable->id);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
            'payable_account_id' => $payable->id,
        ]);

        $this->service = app(PaymentMadeService::class);
    }

    public function test_a_second_complete_on_a_stale_payment_is_rejected_and_posts_one_journal(): void
    {
        $payment = $this->payment(500);
        $stale = PaymentMade::findOrFail($payment->id);

        $this->service->complete($payment, $this->user->id);

        $this->assertRejected(fn () => $this->service->complete($stale, $this->user->id));
        $this->assertSame(1, JournalEntry::where('source_type', PaymentMade::class)
            ->where('source_id', $payment->id)->count());
    }

    public function test_complete_rolls_back_when_the_journal_cannot_be_posted(): void
    {
        Config::set('erp.default_accounts.payable', null);
        $this->supplier->update(['payable_account_id' => null]);
        $payment = $this->payment(500);

        $this->assertRejected(fn () => $this->service->complete($payment, $this->user->id));

        $fresh = $payment->fresh();
        $this->assertSame(PaymentMade::STATUS_PENDING, $fresh->status);
        $this->assertNull($fresh->journal_entry_id);
        $this->assertSame(0, JournalEntry::where('source_type', PaymentMade::class)->count());
    }

    public function test_a_second_void_on_a_stale_payment_is_rejected(): void
    {
        $bill = $this->bill(1000);
        $payment = $this->payment(400, PaymentMade::STATUS_COMPLETED);
        $this->service->allocate($payment, $bill, 400);
        $stale = PaymentMade::with('allocations.bill')->findOrFail($payment->id);

        $this->service->void($payment, 'duplicate');

        $this->assertRejected(fn () => $this->service->void($stale, 'duplicate again'));
        $this->assertBillBalance($bill, paid: '0', due: '1000', status: Bill::STATUS_APPROVED);
        $this->assertSame(1, substr_count((string) $payment->fresh()->notes, 'Voided:'));
    }

    public function test_void_keeps_a_payment_recorded_on_the_bill_meanwhile(): void
    {
        $bill = $this->bill(1000);
        $first = $this->payment(400, PaymentMade::STATUS_COMPLETED);
        $this->service->allocate($first, $bill, 400);
        $stale = PaymentMade::with('allocations.bill')->findOrFail($first->id);

        $this->service->allocate($this->payment(300, PaymentMade::STATUS_COMPLETED), $bill, 300);
        $this->service->void($stale, 'wrong supplier');

        $this->assertBillBalance($bill, paid: '300', due: '700', status: Bill::STATUS_PARTIAL);
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

    private function assertBillBalance(Bill $bill, string $paid, string $due, string $status): void
    {
        $bill = $bill->fresh();

        $this->assertSame(0, bccomp((string) $bill->amount_paid, $paid, 4), "amount_paid is {$bill->amount_paid}");
        $this->assertSame(0, bccomp((string) $bill->amount_due, $due, 4), "amount_due is {$bill->amount_due}");
        $this->assertSame($status, $bill->status);
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

    private function payment(float $amount, string $status = PaymentMade::STATUS_PENDING): PaymentMade
    {
        return PaymentMade::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'payment_date' => now(),
            'amount' => $amount,
            'base_amount' => $amount,
            'currency_code' => 'SAR',
            'exchange_rate' => 1,
            'status' => $status,
            'journal_entry_id' => null,
            'created_by' => $this->user->id,
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
