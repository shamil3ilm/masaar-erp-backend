<?php

declare(strict_types=1);

namespace App\Services\Tax;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Core\Organization;
use App\Models\Tax\VatReturnBox;
use App\Models\Tax\VatReturnPeriod;
use App\Models\Tax\VatTransaction;
use Illuminate\Support\Facades\DB;

class VatReturnService
{
    /**
     * Prepare (create or retrieve) a VAT return period.
     *
     * The period may not exist yet, so there is no row of it to lock; the
     * organization's row is locked instead, which makes two requests preparing
     * the same period find one period rather than create two. The dates are
     * compared as dates because the columns store them with a time part.
     */
    public function preparePeriod(
        Organization $organization,
        string $countryCode,
        string $periodStart,
        string $periodEnd
    ): VatReturnPeriod {
        return DB::transaction(function () use ($organization, $countryCode, $periodStart, $periodEnd): VatReturnPeriod {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            $existing = VatReturnPeriod::where('organization_id', $organization->id)
                ->where('country_code', $countryCode)
                ->whereDate('period_start', $periodStart)
                ->whereDate('period_end', $periodEnd)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return VatReturnPeriod::create([
                'organization_id' => $organization->id,
                'country_code'    => $countryCode,
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
                'status'          => 'draft',
            ]);
        });
    }

    /**
     * Build VAT return boxes by aggregating transactions for the period.
     *
     * Runs on the locked period and only while it is a draft or ready: a
     * submitted return keeps the boxes it was filed with.
     */
    public function buildReturnBoxes(VatReturnPeriod $period): VatReturnPeriod
    {
        return $period->lockForTransition(fn (VatReturnPeriod $period): VatReturnPeriod => $this->rebuildBoxes($period));
    }

    private function rebuildBoxes(VatReturnPeriod $period): VatReturnPeriod
    {
        $this->assertOpen($period, 'Only draft or ready returns can be rebuilt.');

        // Credit notes, refunds and returns carry negative amounts, so they net
        // against the sales they reverse.
        $transactions = VatTransaction::where('organization_id', $period->organization_id)
            ->where('country_code', $period->country_code)
            ->whereBetween('tax_period', [
                $period->period_start->toDateString(),
                $period->period_end->toDateString(),
            ])
            ->whereIn('transaction_type', VatTransaction::TYPES)
            ->get();

        $outputTaxable = '0';
        $outputVat     = '0';
        $inputTaxable  = '0';
        $inputVat      = '0';
        $zeroRated     = '0';
        $exempt        = '0';

        foreach ($transactions as $txn) {
            if (in_array($txn->transaction_type, VatTransaction::REVERSAL_TYPES, true) && bccomp((string) $txn->taxable_amount, '0', 4) > 0) {
                throw new \InvalidArgumentException('Refund/return/credit note amounts must be negative.');
            }

            if (in_array($txn->transaction_type, VatTransaction::OUTPUT_TYPES, true)) {
                if ($txn->is_exempt) {
                    $exempt = bcadd($exempt, (string) $txn->taxable_amount, 4);
                } elseif ($txn->is_zero_rated) {
                    $zeroRated = bcadd($zeroRated, (string) $txn->taxable_amount, 4);
                } else {
                    $outputTaxable = bcadd($outputTaxable, (string) $txn->taxable_amount, 4);
                    $outputVat     = bcadd($outputVat, (string) $txn->vat_amount, 4);
                }
            } elseif ($txn->transaction_type === VatTransaction::TYPE_PURCHASE) {
                $inputTaxable = bcadd($inputTaxable, (string) $txn->taxable_amount, 4);
                $inputVat     = bcadd($inputVat, (string) $txn->vat_amount, 4);
            }
        }

        $netVat = bcsub($outputVat, $inputVat, 4);

        $boxes = [
            ['box_number' => '1',  'box_label' => 'Standard rated supplies',           'output_amount' => (float) $outputTaxable, 'input_amount' => 0,                  'net_vat' => 0],
            ['box_number' => '2',  'box_label' => 'Zero rated supplies',               'output_amount' => (float) $zeroRated,     'input_amount' => 0,                  'net_vat' => 0],
            ['box_number' => '3',  'box_label' => 'Exempt supplies',                   'output_amount' => (float) $exempt,        'input_amount' => 0,                  'net_vat' => 0],
            ['box_number' => '4',  'box_label' => 'VAT on standard rated supplies',    'output_amount' => (float) $outputVat,     'input_amount' => 0,                  'net_vat' => (float) $outputVat],
            ['box_number' => '5',  'box_label' => 'Standard rated purchases',          'output_amount' => 0,                      'input_amount' => (float) $inputTaxable, 'net_vat' => 0],
            ['box_number' => '6',  'box_label' => 'VAT on standard rated purchases',   'output_amount' => 0,                      'input_amount' => (float) $inputVat,  'net_vat' => (float) $inputVat],
            ['box_number' => '7',  'box_label' => 'Net VAT due / (refundable)',        'output_amount' => (float) $outputVat,     'input_amount' => (float) $inputVat,  'net_vat' => (float) $netVat],
        ];

        $period->boxes()->delete();

        foreach ($boxes as $box) {
            VatReturnBox::create(array_merge($box, [
                'vat_return_period_id' => $period->id,
            ]));
        }

        $period->update(['status' => 'ready']);

        return $period->fresh(['boxes']);
    }

    /**
     * Mark a VAT return as submitted, once, on the locked period.
     */
    public function submitReturn(VatReturnPeriod $period, ?string $referenceNumber = null): VatReturnPeriod
    {
        return $period->lockForTransition(function (VatReturnPeriod $period) use ($referenceNumber): VatReturnPeriod {
            $this->assertOpen($period, 'Only draft or ready returns can be submitted.');

            $period->update([
                'status'           => 'submitted',
                'submitted_at'     => now(),
                'reference_number' => $referenceNumber,
            ]);

            return $period->fresh();
        });
    }

    private function assertOpen(VatReturnPeriod $period, string $message): void
    {
        if (! in_array($period->status, ['draft', 'ready'], true)) {
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * Record a VAT transaction for later aggregation.
     */
    public function recordTransaction(array $data): VatTransaction
    {
        return VatTransaction::create($data);
    }

    /**
     * Get VAT reconciliation summary for a period.
     */
    public function getReconciliationSummary(
        int $organizationId,
        string $countryCode,
        string $periodStart,
        string $periodEnd
    ): array {
        $transactions = VatTransaction::where('organization_id', $organizationId)
            ->where('country_code', $countryCode)
            ->whereBetween('tax_period', [$periodStart, $periodEnd])
            ->get();

        // Output includes the reversals, as the return boxes do.
        $output = $transactions->whereIn('transaction_type', VatTransaction::OUTPUT_TYPES);
        $input  = $transactions->where('transaction_type', VatTransaction::TYPE_PURCHASE);

        return [
            'period_start'          => $periodStart,
            'period_end'            => $periodEnd,
            'country_code'          => $countryCode,
            'output_taxable_amount' => $output->sum('taxable_amount'),
            'output_vat'            => $output->sum('vat_amount'),
            'input_taxable_amount'  => $input->sum('taxable_amount'),
            'input_vat'             => $input->sum('vat_amount'),
            'net_vat_payable'       => (float) bcsub(
                (string) $output->sum('vat_amount'),
                (string) $input->sum('vat_amount'),
                4
            ),
            'zero_rated_supplies'   => $output->where('is_zero_rated', true)->sum('taxable_amount'),
            'exempt_supplies'       => $output->where('is_exempt', true)->sum('taxable_amount'),
        ];
    }

    /**
     * The organization's return periods, latest first.
     *
     * @param  array{country_code?: string, status?: string}  $filters
     */
    public function paginatePeriods(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return VatReturnPeriod::where('organization_id', $organizationId)
            ->orderByDesc('period_start')
            ->when(isset($filters['country_code']), fn ($q) => $q->where('country_code', $filters['country_code']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->paginate($perPage);
    }

    /**
     * The organization's VAT transactions, latest period first. The period
     * range applies only when both its ends are given.
     *
     * @param  array{country_code?: string, transaction_type?: string, period_start?: string, period_end?: string}  $filters
     */
    public function paginateTransactions(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return VatTransaction::where('organization_id', $organizationId)
            ->orderByDesc('tax_period')
            ->when(isset($filters['country_code']), fn ($q) => $q->where('country_code', $filters['country_code']))
            ->when(isset($filters['transaction_type']), fn ($q) => $q->where('transaction_type', $filters['transaction_type']))
            ->when(
                isset($filters['period_start'], $filters['period_end']),
                fn ($q) => $q->whereBetween('tax_period', [$filters['period_start'], $filters['period_end']])
            )
            ->paginate($perPage);
    }

    /**
     * Prepares the period for the organization with this id.
     */
    public function preparePeriodFor(int $organizationId, string $countryCode, string $periodStart, string $periodEnd): VatReturnPeriod
    {
        return $this->preparePeriod(Organization::findOrFail($organizationId), $countryCode, $periodStart, $periodEnd);
    }
}
