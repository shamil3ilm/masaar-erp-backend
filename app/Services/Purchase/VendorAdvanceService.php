<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Accounting\Account;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorAdvanceClearing;
use App\Models\Purchase\VendorAdvancePayment;
use App\Models\Purchase\VendorAdvanceRequest;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VendorAdvanceService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator,
        private JournalService $journalService,
        private AccountResolver $accountResolver,
    ) {}

    /**
     * A page of advance requests matching the filters, with contact, users and order loaded.
     *
     * The sort column and direction are expected already checked against an
     * allowlist by the caller.
     *
     * @param  array<string, mixed>  $filters  status, contact_id, purchase_order_id, search
     */
    public function list(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return VendorAdvanceRequest::with(['contact', 'requester', 'approver', 'purchaseOrder'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['contact_id'] ?? null, fn ($q, $id) => $q->where('contact_id', $id))
            ->when($filters['purchase_order_id'] ?? null, fn ($q, $id) => $q->where('purchase_order_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('request_number', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * An advance payment of the caller's organization.
     *
     * Payments have no organization column; one counts as the caller's when
     * its request, which is tenant-scoped, is found.
     */
    public function findPayment(int $id): VendorAdvancePayment
    {
        return VendorAdvancePayment::whereHas('advanceRequest')->findOrFail($id);
    }

    /**
     * Every clearing of the request's payments, with its bill.
     *
     * @return Collection<int, VendorAdvanceClearing>
     */
    public function clearingsFor(VendorAdvanceRequest $request): Collection
    {
        return $request->payments()
            ->with(['clearings.bill'])
            ->get()
            ->flatMap(fn (VendorAdvancePayment $payment) => $payment->clearings)
            ->values();
    }

    /**
     * Create an advance payment request.
     */
    public function createRequest(array $data): VendorAdvanceRequest
    {
        if (empty($data['request_number'])) {
            $data['request_number'] = $this->numberGenerator->generate('VAR');
        }

        $data['requested_by'] = $data['requested_by'] ?? auth()->id();
        $data['status'] = VendorAdvanceRequest::STATUS_DRAFT;

        return VendorAdvanceRequest::create($data);
    }

    /**
     * Approve an advance payment request.
     *
     * The status is checked on the locked request: approving a stale copy of
     * a request paid meanwhile would move it back to approved and open it to a
     * second payment.
     */
    public function approveRequest(VendorAdvanceRequest $request): VendorAdvanceRequest
    {
        return $request->lockForTransition(function (VendorAdvanceRequest $request): VendorAdvanceRequest {
            if (! $request->canBeApproved()) {
                throw new \InvalidArgumentException('Only draft requests can be approved.');
            }

            $request->update([
                'status' => VendorAdvanceRequest::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Record an actual payment against an approved advance request.
     *
     * The journal entry (Debit Vendor Advance / Credit Bank) is part of the
     * payment: when it cannot be posted, no payment is recorded and the request
     * stays approved.
     */
    public function recordPayment(VendorAdvanceRequest $request, array $paymentData): VendorAdvancePayment
    {
        return $request->lockForTransition(function (VendorAdvanceRequest $request) use ($paymentData): VendorAdvancePayment {
            if (! $request->canBePaid()) {
                throw new \InvalidArgumentException('Advance request must be approved before recording a payment.');
            }

            $paymentData['advance_request_id'] = $request->id;
            $paymentData['payment_date'] = $paymentData['payment_date'] ?? now()->toDateString();

            $payment = VendorAdvancePayment::create($paymentData);
            $payment->update(['journal_entry_id' => $this->createPaymentJournalEntry($request, $payment)]);

            $request->update(['status' => VendorAdvanceRequest::STATUS_PAID]);

            return $payment->fresh(['advanceRequest', 'journalEntry']);
        });
    }

    /**
     * Clear an advance payment against a supplier bill.
     *
     * The advance payment row is locked, so the uncleared balance cannot be
     * spent twice; the clearing journal entry (Debit AP / Credit Vendor
     * Advance) is part of the clearing and a failure to post it rolls back.
     */
    public function clearAgainstBill(VendorAdvancePayment $payment, Bill $bill, float $amount): VendorAdvanceClearing
    {
        return $payment->lockForTransition(function (VendorAdvancePayment $payment) use ($bill, $amount): VendorAdvanceClearing {
            $request = $payment->advanceRequest;

            if ((int) $request->organization_id !== (int) $bill->organization_id) {
                throw new \InvalidArgumentException('Advance and bill must belong to the same organization.');
            }

            // Clearing against another supplier's bill would move that supplier's AP balance.
            if ((int) $bill->supplier_id !== (int) $request->contact_id) {
                throw new \InvalidArgumentException('Cannot clear advance against a bill from a different supplier.');
            }

            $uncleared = (string) $payment->getUnclearedAmount();

            if (bccomp($uncleared, '0', 4) <= 0) {
                throw new \InvalidArgumentException('Advance payment is already fully cleared.');
            }

            if (bccomp((string) $amount, $uncleared, 4) > 0) {
                throw new \InvalidArgumentException(
                    "Clearing amount ({$amount}) exceeds available uncleared balance ({$uncleared})."
                );
            }

            $clearing = VendorAdvanceClearing::create([
                'advance_payment_id' => $payment->id,
                'bill_id' => $bill->id,
                'cleared_amount' => $amount,
                'clearing_date' => now()->toDateString(),
            ]);

            $clearing->update(['journal_entry_id' => $this->createClearingJournalEntry($payment, $bill, $amount)]);

            if (bccomp((string) $amount, $uncleared, 4) === 0) {
                $request->update(['status' => VendorAdvanceRequest::STATUS_CLEARED]);
            }

            return $clearing->fresh(['advancePayment', 'bill']);
        });
    }

    private function createPaymentJournalEntry(VendorAdvanceRequest $request, VendorAdvancePayment $payment): ?int
    {
        $orgId = $request->organization_id;
        $amount = (float) $payment->amount;

        // The vendor advance account has no sub-type, so it is mapped in
        // accounting settings; matching '%Advance%' chose employee advances.
        $advanceAccount = $this->accountResolver->mapped($orgId, 'vendor_advance_account_id');

        $bankAccount = $payment->bank_account_id
            ? Account::withoutGlobalScopes()->where('organization_id', $orgId)->whereKey($payment->bank_account_id)->first()
            : $this->accountResolver->bankOrCash($orgId);

        if (!$advanceAccount || !$bankAccount) {
            Log::info('Vendor advance payment journal entry skipped: accounts not configured', [
                'request_id' => $request->id,
            ]);

            return null;
        }

        $entry = $this->journalService->createAndPost([
            'organization_id' => $orgId,
            'entry_date' => $payment->payment_date->toDateString(),
            'reference' => $request->request_number,
            'description' => "Vendor Advance Payment - {$request->request_number}",
        ], [
            [
                'account_id' => $advanceAccount->id,
                'description' => "Vendor advance paid - {$request->request_number}",
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $bankAccount->id,
                'description' => "Bank/cash disbursement - {$request->request_number}",
                'debit' => 0,
                'credit' => $amount,
            ],
        ]);

        return $entry?->id;
    }

    private function createClearingJournalEntry(VendorAdvancePayment $payment, Bill $bill, float $amount): ?int
    {
        $request = $payment->advanceRequest;
        $orgId = $request->organization_id;

        $advanceAccount = $this->accountResolver->mapped($orgId, 'vendor_advance_account_id');
        $apAccount = $this->accountResolver->bySubType($orgId, 'payable');

        if (!$advanceAccount || !$apAccount) {
            Log::info('Vendor advance clearing journal entry skipped: accounts not configured', [
                'payment_id' => $payment->id,
            ]);

            return null;
        }

        $entry = $this->journalService->createAndPost([
            'organization_id' => $orgId,
            'entry_date' => now()->toDateString(),
            'reference' => $bill->bill_number ?? (string) $bill->id,
            'description' => "Vendor Advance Clearing - {$request->request_number}",
        ], [
            [
                'account_id' => $apAccount->id,
                'description' => "AP cleared against advance {$request->request_number}",
                'debit' => $amount,
                'credit' => 0,
            ],
            [
                'account_id' => $advanceAccount->id,
                'description' => "Advance cleared against bill {$bill->bill_number}",
                'debit' => 0,
                'credit' => $amount,
            ],
        ]);

        return $entry?->id;
    }
}
