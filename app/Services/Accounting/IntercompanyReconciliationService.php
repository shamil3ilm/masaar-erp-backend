<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\IntercompanyReconciliationItem;
use App\Models\Accounting\IntercompanyReconciliationMatch;
use App\Models\Accounting\IntercompanyReconciliationSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Intercompany Reconciliation — SAP FI F.13 / FBICN equivalent.
 *
 * Matches intercompany receivables and payables across organizations using
 * a reference key (e.g. IC invoice number) and amount tolerance.
 *
 * Process:
 *  1. createSession()   — start a reconciliation period run
 *  2. loadItems()       — add IC transactions to the session
 *  3. autoMatch()       — attempt automatic matching by reference + currency
 *  4. manualMatch()     — confirm or override proposed matches
 *  5. closeSession()    — lock and summarise results
 */
class IntercompanyReconciliationService
{
    // Tolerance for amount differences (absolute value)
    private const AMOUNT_TOLERANCE = 0.01;

    // ----------------------------------------------------------------
    // Session lifecycle
    // ----------------------------------------------------------------

    /**
     * Page through an organisation's sessions, newest first. A fiscal year or
     * status filter applies only when its value is truthy.
     */
    public function paginateSessions(int $organizationId, mixed $fiscalYear, mixed $status, int $perPage): LengthAwarePaginator
    {
        return IntercompanyReconciliationSession::where('organization_id', $organizationId)
            ->when($fiscalYear, fn ($q) => $q->where('fiscal_year', $fiscalYear))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function createSession(
        int $organizationId,
        string $fiscalYear,
        int $period,
        int $runByUserId,
    ): IntercompanyReconciliationSession {
        $sessionNumber = 'ICR-' . $fiscalYear . '-' . str_pad((string) $period, 2, '0', STR_PAD_LEFT)
            . '-' . strtoupper(substr(uniqid(), -4));

        return IntercompanyReconciliationSession::create([
            'organization_id' => $organizationId,
            'session_number'  => $sessionNumber,
            'fiscal_year'     => $fiscalYear,
            'period'          => $period,
            'status'          => 'draft',
            'run_by'          => $runByUserId,
        ]);
    }

    /**
     * Add intercompany items to the session.
     *
     * @param  array<int, array{source_type: string, source_id: int, reference_number: string,
     *                          amount: float, currency: string, transaction_date: string,
     *                          item_type: string, counterparty_organization_id?: int}>  $items
     */
    public function loadItems(IntercompanyReconciliationSession $session, array $items): void
    {
        DB::transaction(function () use ($session, $items): void {
            foreach ($items as $item) {
                IntercompanyReconciliationItem::create([
                    'session_id'                     => $session->id,
                    'organization_id'                => $session->organization_id,
                    'source_type'                    => $item['source_type'],
                    'source_id'                      => $item['source_id'],
                    'reference_number'               => $item['reference_number'],
                    'amount'                         => $item['amount'],
                    'currency'                       => $item['currency'],
                    'transaction_date'               => $item['transaction_date'],
                    'item_type'                      => $item['item_type'],
                    'counterparty_organization_id'   => $item['counterparty_organization_id'] ?? null,
                    'counterparty_reference'         => $item['counterparty_reference'] ?? null,
                    'match_status'                   => 'unmatched',
                ]);
            }

            $session->update(['items_count' => $session->items()->count(), 'status' => 'running']);
        });
    }

    // ----------------------------------------------------------------
    // Matching
    // ----------------------------------------------------------------

    /**
     * Auto-match receivables vs payables within tolerance.
     *
     * Matching key: reference_number + currency (amount compared with tolerance).
     *
     * @return array{matched: int, unmatched: int}
     */
    public function autoMatch(IntercompanyReconciliationSession $session): array
    {
        $receivables = $session->items()
            ->where('item_type', 'receivable')
            ->where('match_status', 'unmatched')
            ->get()
            ->keyBy('reference_number');

        $payables = $session->items()
            ->where('item_type', 'payable')
            ->where('match_status', 'unmatched')
            ->get();

        $matched = 0;

        DB::transaction(function () use ($receivables, $payables, $session, &$matched): void {
            foreach ($payables as $payable) {
                /** @var IntercompanyReconciliationItem|null $receivable */
                $receivable = $receivables->get($payable->reference_number);

                if (! $receivable || $receivable->currency !== $payable->currency) {
                    continue;
                }

                if ($receivable->match_status !== 'unmatched') {
                    continue;
                }

                $difference = (float) $payable->amount - (float) $receivable->amount;

                $match = IntercompanyReconciliationMatch::create([
                    'session_id'        => $session->id,
                    'receivable_item_id' => $receivable->id,
                    'payable_item_id'   => $payable->id,
                    'receivable_amount' => $receivable->amount,
                    'payable_amount'    => $payable->amount,
                    'difference'        => $difference,
                    'currency'          => $payable->currency,
                    'match_type'        => 'auto',
                    'status'            => abs($difference) <= self::AMOUNT_TOLERANCE ? 'confirmed' : 'proposed',
                ]);

                $receivable->update(['match_status' => 'matched', 'match_id' => $match->id]);
                $payable->update(['match_status' => 'matched', 'match_id' => $match->id]);

                // Remove from collection to prevent double-matching
                $receivables->forget($payable->reference_number);

                $matched++;
            }
        });

        $this->refreshSessionSummary($session);

        return [
            'matched'   => $matched,
            'unmatched' => $session->items()->where('match_status', 'unmatched')->count(),
        ];
    }

    /**
     * Manually match two items identified by id.
     *
     * Both items are looked up among the session's own items, so an item of
     * another session, closed or not, or of another organisation is a 404.
     */
    public function manualMatchItems(
        IntercompanyReconciliationSession $session,
        int $receivableItemId,
        int $payableItemId,
        ?string $notes = null,
    ): IntercompanyReconciliationMatch {
        $receivable = $session->items()->findOrFail($receivableItemId);
        $payable    = $session->items()->findOrFail($payableItemId);

        return $this->manualMatch($session, $receivable, $payable, $notes);
    }

    /**
     * Manually match two specific items.
     */
    public function manualMatch(
        IntercompanyReconciliationSession $session,
        IntercompanyReconciliationItem $receivable,
        IntercompanyReconciliationItem $payable,
        ?string $notes = null,
    ): IntercompanyReconciliationMatch {
        return DB::transaction(function () use ($session, $receivable, $payable, $notes): IntercompanyReconciliationMatch {
            $difference = (float) $payable->amount - (float) $receivable->amount;

            $match = IntercompanyReconciliationMatch::create([
                'session_id'         => $session->id,
                'receivable_item_id' => $receivable->id,
                'payable_item_id'    => $payable->id,
                'receivable_amount'  => $receivable->amount,
                'payable_amount'     => $payable->amount,
                'difference'         => $difference,
                'currency'           => $receivable->currency,
                'match_type'         => 'manual',
                'status'             => 'confirmed',
                'notes'              => $notes,
            ]);

            $receivable->update(['match_status' => 'matched', 'match_id' => $match->id]);
            $payable->update(['match_status' => 'matched', 'match_id' => $match->id]);

            $this->refreshSessionSummary($session);

            return $match;
        });
    }

    /**
     * Close the session — no further changes allowed.
     */
    public function closeSession(IntercompanyReconciliationSession $session): IntercompanyReconciliationSession
    {
        $this->refreshSessionSummary($session);

        $session->update([
            'status'       => 'closed',
            'completed_at' => now(),
        ]);

        return $session->fresh();
    }

    // ----------------------------------------------------------------

    private function refreshSessionSummary(IntercompanyReconciliationSession $session): void
    {
        $matchedCount   = $session->items()->where('match_status', 'matched')->count();
        $unmatchedCount = $session->items()->where('match_status', 'unmatched')->count();
        $matchedAmount  = $session->matches()->where('status', 'confirmed')->sum('receivable_amount');
        $totalDiff      = $session->matches()->where('status', 'confirmed')->sum('difference');

        $session->update([
            'matched_count'    => $matchedCount,
            'unmatched_count'  => $unmatchedCount,
            'matched_amount'   => $matchedAmount,
            'difference_amount' => $totalDiff,
        ]);
    }
}
