<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Accounting\JournalEntry;
use App\Models\Purchase\Bill;
use App\Models\Purchase\BillPaymentAllocation;
use App\Models\Purchase\PaymentMade;
use App\Models\Purchase\SupplierCredit;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PaymentMadeService
{
    public function __construct(
        private JournalService $journalService,
        private JournalEntryFactory $journalEntryFactory,
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * A page of payments matching the filters, with supplier, bank account and
     * allocated bills loaded.
     *
     * The sort column and direction are expected already checked against an
     * allowlist by the caller.
     *
     * @param  array<string, mixed>  $filters  status, supplier_id, payment_method, start_date, end_date, search
     */
    public function list(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return PaymentMade::with(['supplier', 'bankAccount', 'allocations.bill'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn ($q, $id) => $q->forSupplier((int) $id))
            ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('payment_method', $method))
            ->when($filters['start_date'] ?? null, fn ($q, $date) => $q->where('payment_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($q, $date) => $q->where('payment_date', '<=', $date))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($q) use ($search) {
                            $q->where('company_name', 'like', "%{$search}%")
                                ->orWhere('contact_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Counts and values of the organization's payments, optionally for one supplier.
     *
     * @return array{total_count: int, pending_count: int, completed_count: int, pending_value: float, completed_value: float, this_month_value: float}
     */
    public function summary(?int $supplierId = null): array
    {
        $query = PaymentMade::query()->when($supplierId, fn ($q, $id) => $q->forSupplier($id));

        return [
            'total_count' => (clone $query)->count(),
            'pending_count' => (clone $query)->pending()->count(),
            'completed_count' => (clone $query)->completed()->count(),
            'pending_value' => (float) (clone $query)->pending()->sum('amount'),
            'completed_value' => (float) (clone $query)->completed()->sum('amount'),
            'this_month_value' => (float) (clone $query)->completed()
                ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];
    }

    /**
     * Create a new payment.
     */
    public function create(array $data, array $allocations = []): PaymentMade
    {
        $data['payment_date'] = $data['payment_date'] ?? now()->toDateString();

        $requested = array_reduce(
            $allocations,
            fn (string $sum, array $allocation): string => bcadd($sum, (string) $allocation['amount'], 4),
            '0'
        );

        if (bccomp($requested, (string) $data['amount'], 4) > 0) {
            throw new \InvalidArgumentException('Total allocation amount cannot exceed payment amount.');
        }

        return DB::transaction(function () use ($data, $allocations) {
            if (empty($data['payment_number'])) {
                $data['payment_number'] = $this->numberGenerator->generate('PAYM');
            }

            if (isset($data['exchange_rate']) && bccomp((string) $data['exchange_rate'], '0', 4) <= 0) {
                throw new \InvalidArgumentException('Exchange rate must be positive.');
            }

            $data['base_amount'] = bcmul(
                (string) $data['amount'],
                (string) ($data['exchange_rate'] ?? 1),
                4
            );

            // India TDS withholding went with the India tax module. A caller
            // may still send these keys; they are not columns.
            unset($data['tds_section_code'], $data['tds_deductee_type'], $data['supplier_pan']);

            // Read back the columns the database filled in, such as the
            // currency and exchange rate, which the allocations and the
            // overpayment credit copy.
            $payment = PaymentMade::create($data)->refresh();

            $totalAllocated = 0;
            $orgId = $data['organization_id'] ?? ($payment->organization_id ?? null);

            if ($orgId === null) {
                throw new \RuntimeException('Organization ID is required for bill allocation.');
            }

            $billIds = array_column($allocations, 'bill_id');

            // Reject duplicate bill IDs in a single allocation batch
            if (count($billIds) !== count(array_unique($billIds))) {
                throw new \InvalidArgumentException('Duplicate bill IDs in allocations.');
            }

            $bills = Bill::whereIn('id', $billIds)
                ->where('organization_id', $orgId)
                ->get()
                ->keyBy('id');

            foreach ($allocations as $allocation) {
                $bill = $bills->get($allocation['bill_id'])
                    ?? throw new \InvalidArgumentException("Bill {$allocation['bill_id']} not found.");
                $amount = min($allocation['amount'], (float) $bill->amount_due);

                if ($amount > 0) {
                    // During initial creation, skip currency validation to allow flexible allocation
                    BillPaymentAllocation::create([
                        'payment_made_id' => $payment->id,
                        'bill_id' => $bill->id,
                        'amount' => $amount,
                        'base_amount' => bcmul((string) $amount, (string) $payment->exchange_rate, 4),
                        'allocated_at' => now(),
                    ]);
                    $bill->recordPayment($amount);
                    $totalAllocated = bcadd((string) $totalAllocated, (string) $amount, 4);
                }
            }

            $unallocated = bcsub((string) $payment->amount, (string) $totalAllocated, 4);
            if (bccomp($unallocated, '0', 4) > 0) {
                $this->createSupplierCredit($payment, (float) $unallocated);
            }

            return $payment->load('allocations.bill', 'supplier');
        });
    }

    /**
     * Complete/confirm a payment.
     *
     * The journal entry is part of completing: when it cannot be posted, for a
     * missing account or a closed period, the payment stays pending.
     */
    public function complete(PaymentMade $payment, int $userId): PaymentMade
    {
        return $payment->lockForTransition(function (PaymentMade $payment) use ($userId): PaymentMade {
            if ($payment->status !== PaymentMade::STATUS_PENDING) {
                throw new \InvalidArgumentException('Only pending payments can be completed.');
            }

            $journal = $this->createJournalEntry($payment);

            $payment->transitionTo(PaymentMade::STATUS_COMPLETED, [
                'journal_entry_id' => $journal->id,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Void a payment: take its allocations off their bills, withdraw its
     * overpayment credit and void its journal entry.
     */
    public function void(PaymentMade $payment, string $reason = ''): PaymentMade
    {
        return $payment->lockForTransition(function (PaymentMade $payment) use ($reason): PaymentMade {
            if ($payment->status === PaymentMade::STATUS_VOIDED) {
                throw new \InvalidArgumentException('Payment is already voided.');
            }

            if (! $payment->canTransitionTo(PaymentMade::STATUS_VOIDED)) {
                throw new \InvalidArgumentException("A {$payment->status} payment cannot be voided.");
            }

            $this->releaseAllocations($payment);

            if ($payment->journal_entry_id && ($journalEntry = $payment->journalEntry)) {
                $this->journalService->voidSourceEntry($journalEntry, $reason);
            }

            $payment->transitionTo(PaymentMade::STATUS_VOIDED, [
                'notes' => $payment->notes."\n\nVoided: ".$reason,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Delete a pending payment.
     *
     * Creating a payment already records its allocations on the bills and
     * books any overpayment as a supplier credit, so deleting it takes both
     * back. The status is checked on the locked row, so a payment completed by
     * another request meanwhile is not deleted.
     */
    public function delete(PaymentMade $payment): void
    {
        $payment->lockForTransition(function (PaymentMade $payment): void {
            if (! $payment->isEditable()) {
                throw new \InvalidArgumentException('Only pending payments can be deleted.');
            }

            $this->releaseAllocations($payment);
            $payment->delete();
        });
    }

    /**
     * Allocate a payment to several bills as one change.
     *
     * Every bill must be the payment's supplier's and the total must fit the
     * unallocated amount of the locked payment. When any allocation is refused,
     * none is recorded.
     *
     * @param  list<array{bill_id: int|string, amount: int|float|string}>  $allocations
     */
    public function allocateMany(PaymentMade $payment, array $allocations): PaymentMade
    {
        return $payment->lockForTransition(function (PaymentMade $payment) use ($allocations): PaymentMade {
            $bills = Bill::whereIn('id', array_column($allocations, 'bill_id'))->get()->keyBy('id');
            $planned = [];
            $total = '0';

            foreach ($allocations as $allocation) {
                $bill = $bills->get($allocation['bill_id'])
                    ?? throw (new ModelNotFoundException)->setModel(Bill::class, [$allocation['bill_id']]);

                if ((int) $bill->supplier_id !== (int) $payment->supplier_id) {
                    throw new \InvalidArgumentException('Cannot allocate payment to bills from a different supplier.');
                }

                $planned[] = [$bill, (float) $allocation['amount']];
                $total = bcadd($total, (string) $allocation['amount'], 4);
            }

            $available = $payment->getUnallocatedAmount();
            if (bccomp($total, (string) $available, 4) > 0) {
                $requested = (float) $total;

                throw new \InvalidArgumentException("Cannot allocate {$requested}. Only {$available} available.");
            }

            foreach ($planned as [$bill, $amount]) {
                $this->allocate($payment, $bill, $amount);
            }

            return $payment->fresh(['allocations.bill']);
        });
    }

    /**
     * Allocate payment to a bill.
     */
    public function allocate(PaymentMade $payment, Bill $bill, float $amount): BillPaymentAllocation
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be positive.');
        }

        return DB::transaction(function () use ($payment, $bill, $amount) {
            // Lock the payment row first to prevent concurrent over-allocation
            $payment = PaymentMade::lockForUpdate()->findOrFail($payment->id);

            // Lock the bill row to prevent concurrent double-allocation
            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            $available = $payment->getUnallocatedAmount();
            if ($amount > $available) {
                throw new \InvalidArgumentException("Cannot allocate {$amount}. Only {$available} available.");
            }

            if ($amount > $bill->amount_due) {
                throw new \InvalidArgumentException("Cannot allocate {$amount}. Bill only has {$bill->amount_due} due.");
            }

            // Currency validation is handled at the controller level if needed

            $allocation = BillPaymentAllocation::create([
                'payment_made_id' => $payment->id,
                'bill_id' => $bill->id,
                'amount' => $amount,
                'base_amount' => bcmul((string) $amount, (string) $payment->exchange_rate, 4),
                'allocated_at' => now(),
            ]);

            $bill->recordPayment($amount);

            return $allocation;
        });
    }

    /**
     * Take a payment's allocations off their bills and withdraw its
     * overpayment credit. Runs inside the caller's lock on the payment.
     */
    private function releaseAllocations(PaymentMade $payment): void
    {
        // Each bill is read and written under its own lock, so a payment
        // recorded on it meanwhile is kept.
        foreach ($payment->allocations()->with('bill')->get() as $allocation) {
            $allocation->bill->reversePayment($allocation->amount);
        }

        $payment->allocations()->delete();

        SupplierCredit::where('source_type', SupplierCredit::SOURCE_OVERPAYMENT)
            ->where('source_id', $payment->id)
            ->update(['is_active' => false, 'remaining_amount' => 0]);
    }

    /**
     * Create supplier credit.
     */
    protected function createSupplierCredit(PaymentMade $payment, float $amount): SupplierCredit
    {
        return SupplierCredit::create([
            'organization_id' => $payment->organization_id,
            'supplier_id' => $payment->supplier_id,
            'source_type' => SupplierCredit::SOURCE_OVERPAYMENT,
            'source_id' => $payment->id,
            'original_amount' => $amount,
            'remaining_amount' => $amount,
            'currency_code' => $payment->currency_code,
            'credit_date' => $payment->payment_date,
            'notes' => "Overpayment from payment {$payment->payment_number}",
        ]);
    }

    /**
     * Create journal entry for payment.
     */
    protected function createJournalEntry(PaymentMade $payment): JournalEntry
    {
        return $this->journalEntryFactory->forPaymentMade($payment);
    }

    /**
     * Get supplier statement.
     */
    public function getSupplierStatement(
        int $supplierId,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $startDate = $startDate ?? now()->startOfYear();
        $endDate = $endDate ?? now();

        $openingBalance = Bill::forSupplier($supplierId)
            ->where('bill_date', '<', $startDate)
            ->sum('amount_due');

        $bills = Bill::forSupplier($supplierId)
            ->inDateRange($startDate, $endDate)
            ->whereNotIn('status', [Bill::STATUS_DRAFT, Bill::STATUS_VOIDED])
            ->orderBy('bill_date')
            ->get();

        $payments = PaymentMade::forSupplier($supplierId)
            ->inDateRange($startDate, $endDate)
            ->completed()
            ->orderBy('payment_date')
            ->get();

        $lines = [];
        $runningBalance = (float) $openingBalance;

        $allTransactions = collect()
            ->merge($bills->map(fn ($b) => ['type' => 'bill', 'date' => $b->bill_date, 'data' => $b]))
            ->merge($payments->map(fn ($p) => ['type' => 'payment', 'date' => $p->payment_date, 'data' => $p]))
            ->sortBy('date');

        foreach ($allTransactions as $transaction) {
            if ($transaction['type'] === 'bill') {
                $bill = $transaction['data'];
                $runningBalance = bcadd((string) $runningBalance, (string) $bill->total, 4);

                $lines[] = [
                    'date' => $bill->bill_date->toDateString(),
                    'type' => 'bill',
                    'number' => $bill->bill_number,
                    'description' => 'Bill',
                    'debit' => 0,
                    'credit' => $bill->total,
                    'balance' => $runningBalance,
                ];
            } else {
                $payment = $transaction['data'];
                $runningBalance = bcsub((string) $runningBalance, (string) $payment->amount, 4);

                $lines[] = [
                    'date' => $payment->payment_date->toDateString(),
                    'type' => 'payment',
                    'number' => $payment->payment_number,
                    'description' => "Payment - {$payment->getPaymentMethodLabel()}",
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'balance' => $runningBalance,
                ];
            }
        }

        return [
            'supplier_id' => $supplierId,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'total_billed' => $bills->sum('total'),
            'total_paid' => $payments->sum('amount'),
            'lines' => $lines,
        ];
    }
}
