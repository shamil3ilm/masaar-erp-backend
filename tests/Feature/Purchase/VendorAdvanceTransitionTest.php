<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorAdvanceClearing;
use App\Models\Purchase\VendorAdvancePayment;
use App\Models\Purchase\VendorAdvanceRequest;
use App\Models\Sales\Contact;
use App\Models\System\Setting;
use App\Services\Purchase\VendorAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Paying and clearing a vendor advance run on locked rows, and the ledger
 * entry is part of each: when it cannot be posted nothing is recorded.
 */
class VendorAdvanceTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private VendorAdvanceService $service;
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.advances.view', 'purchase.advances.create']);
        $this->setUpOpenFiscalPeriod();

        $advance = $this->account('1400', 'Vendor Advances', Account::TYPE_ASSET, Account::SUBTYPE_OTHER_ASSET);
        $this->account('1010', 'Cash at Bank', Account::TYPE_ASSET, Account::SUBTYPE_BANK);
        $this->account('2000', 'Accounts Payable', Account::TYPE_LIABILITY, 'payable');
        Setting::set('accounting', 'vendor_advance_account_id', $advance->id, null, $this->organization->id);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
        ]);

        $this->service = app(VendorAdvanceService::class);
    }

    public function test_a_second_payment_on_a_stale_request_is_rejected(): void
    {
        $request = $this->approvedRequest(500);
        $stale = VendorAdvanceRequest::findOrFail($request->id);

        $this->service->recordPayment($request, $this->paymentData(500));

        $this->assertRejected(fn () => $this->service->recordPayment($stale, $this->paymentData(500)));
        $this->assertSame(1, VendorAdvancePayment::where('advance_request_id', $request->id)->count());
    }

    public function test_recording_a_payment_rolls_back_when_the_journal_cannot_be_posted(): void
    {
        $request = $this->approvedRequest(500);
        $this->closeFiscalYear();

        $this->assertRejected(fn () => $this->service->recordPayment($request, $this->paymentData(500)));

        $this->assertSame(VendorAdvanceRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame(0, VendorAdvancePayment::count());
    }

    public function test_clearing_rolls_back_when_the_journal_cannot_be_posted(): void
    {
        $payment = $this->service->recordPayment($this->approvedRequest(500), $this->paymentData(500));
        $bill = $this->bill(800);
        $this->closeFiscalYear();

        $this->assertRejected(fn () => $this->service->clearAgainstBill($payment, $bill, 300));

        $this->assertSame(0, VendorAdvanceClearing::count());
    }

    public function test_clearing_is_recorded_with_its_journal_entry(): void
    {
        $payment = $this->service->recordPayment($this->approvedRequest(500), $this->paymentData(500));

        $clearing = $this->service->clearAgainstBill($payment, $this->bill(800), 500);

        $this->assertNotNull($clearing->journal_entry_id);
        $this->assertSame(VendorAdvanceRequest::STATUS_CLEARED, $payment->advanceRequest->fresh()->status);
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

    private function approvedRequest(float $amount): VendorAdvanceRequest
    {
        return VendorAdvanceRequest::create([
            'organization_id' => $this->organization->id,
            'request_number' => 'VAR-'.fake()->unique()->numerify('#####'),
            'contact_id' => $this->supplier->id,
            'requested_amount' => $amount,
            'currency_code' => 'SAR',
            'requested_by' => $this->user->id,
            'status' => VendorAdvanceRequest::STATUS_APPROVED,
        ]);
    }

    private function paymentData(float $amount): array
    {
        return [
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
        ];
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

    private function closeFiscalYear(): void
    {
        FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->update(['is_closed' => true]);
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
