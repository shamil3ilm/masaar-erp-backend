<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Accounting\Account;
use App\Models\Inventory\PhysicalInventoryDocument;
use App\Models\Inventory\PhysicalInventoryLine;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockLevel;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\JournalService;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PhysicalInventoryService
{
    public function __construct(
        private StockService $stockService,
        private NumberGeneratorService $numberGenerator,
        private JournalService $journalService,
        private AccountResolver $accountResolver,
    ) {}

    /**
     * Create a physical inventory document and auto-populate lines with current book quantities.
     */
    public function createDocument(array $data): PhysicalInventoryDocument
    {
        return DB::transaction(function () use ($data): PhysicalInventoryDocument {
            $data['organization_id'] = auth()->user()->organization_id;

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->numberGenerator->generate('PI');
            }

            $data['status'] = PhysicalInventoryDocument::STATUS_CREATED;

            $document = PhysicalInventoryDocument::create($data);

            // Auto-populate lines from current stock levels in the warehouse
            $stockLevels = StockLevel::where('warehouse_id', $document->warehouse_id)
                ->with(['product', 'variant'])
                ->get();

            foreach ($stockLevels as $level) {
                $document->lines()->create([
                    'product_id' => $level->product_id,
                    'variant_id' => $level->variant_id,
                    'warehouse_location_id' => $level->location_id,
                    'book_quantity' => $level->quantity,
                    'unit_cost' => $level->average_cost,
                    'counted_quantity' => null,
                    'difference_quantity' => null,
                    'difference_value' => null,
                    'adjustment_status' => 'pending',
                ]);
            }

            return $document->load(['lines.product', 'lines.variant', 'warehouse']);
        });
    }

    /**
     * Enter counted quantities for lines, calculating differences automatically.
     *
     * @param  array<int, array{line_id: int, counted_quantity: float}>  $lines
     */
    public function enterCounts(PhysicalInventoryDocument $document, array $lines): PhysicalInventoryDocument
    {
        if (!$document->canEnterCounts()) {
            throw new \InvalidArgumentException('Counts can only be entered for documents in created or in_progress status.');
        }

        DB::transaction(function () use ($document, $lines): void {
            foreach ($lines as $lineData) {
                /** @var PhysicalInventoryLine|null $line */
                $line = $document->lines()->find($lineData['line_id']);

                if ($line === null) {
                    continue;
                }

                $counted = (float) $lineData['counted_quantity'];
                $diff = (float) bcsub((string) $counted, (string) $line->book_quantity, 4);
                $diffValue = $line->unit_cost !== null
                    ? (float) bcmul((string) $diff, (string) $line->unit_cost, 4)
                    : null;

                $line->update([
                    'counted_quantity' => $counted,
                    'difference_quantity' => $diff,
                    'difference_value' => $diffValue,
                ]);
            }

            // Transition to in_progress if still at created
            if ($document->status === PhysicalInventoryDocument::STATUS_CREATED) {
                $document->update(['status' => PhysicalInventoryDocument::STATUS_IN_PROGRESS]);
            }

            // If all lines are counted, move to counted status
            $uncounted = $document->lines()->whereNull('counted_quantity')->count();
            if ($uncounted === 0) {
                $document->update([
                    'status' => PhysicalInventoryDocument::STATUS_COUNTED,
                    'counted_at' => now(),
                ]);
            }
        });

        return $document->fresh(['lines.product', 'lines.variant', 'warehouse']);
    }

    /**
     * Post adjustments: bring stock to the counted quantities through a posted
     * stock adjustment, book the value of the difference and mark the document
     * posted.
     *
     * Runs on the locked document, so a second submit waits and then finds it
     * posted. A failure to book the difference throws and nothing is posted.
     */
    public function postAdjustments(PhysicalInventoryDocument $document): PhysicalInventoryDocument
    {
        return $document->lockForTransition(function (PhysicalInventoryDocument $document): PhysicalInventoryDocument {
            if (! $document->canPost()) {
                throw new \InvalidArgumentException('Document cannot be posted in its current status.');
            }

            $linesWithDiffs = $document->lines()
                ->pending()
                ->whereNotNull('difference_quantity')
                ->where('difference_quantity', '!=', 0)
                ->with(['product', 'variant'])
                ->get();

            if ($linesWithDiffs->isNotEmpty()) {
                $this->adjustStockToCounts($document, $linesWithDiffs);
                $this->bookDifference($document, $linesWithDiffs);
            }

            $document->update([
                'status' => PhysicalInventoryDocument::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $document->fresh(['lines.product', 'lines.variant', 'warehouse']);
        });
    }

    /**
     * @param  Collection<int, PhysicalInventoryLine>  $lines
     */
    private function adjustStockToCounts(PhysicalInventoryDocument $document, Collection $lines): void
    {
        $adjustment = StockAdjustment::create([
            'organization_id' => $document->organization_id,
            'warehouse_id' => $document->warehouse_id,
            'adjustment_number' => $this->numberGenerator->generate('ADJ'),
            'adjustment_date' => now()->toDateString(),
            'reason' => StockAdjustment::REASON_COUNT_CORRECTION,
            'notes' => "Physical inventory count: {$document->document_number}",
            'status' => StockAdjustment::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);

        foreach ($lines as $line) {
            $newQuantity = (float) $line->counted_quantity;

            $adjustment->lines()->create([
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'location_id' => $line->warehouse_location_id,
                'system_quantity' => $line->book_quantity,
                'actual_quantity' => $newQuantity,
                'difference' => $line->difference_quantity,
                'unit_cost' => $line->unit_cost ?? 0,
                'total_cost' => $line->difference_value ?? 0,
            ]);

            $this->stockService->adjust(
                productId: $line->product_id,
                warehouseId: $document->warehouse_id,
                newQuantity: $newQuantity,
                variantId: $line->variant_id,
                locationId: $line->warehouse_location_id,
                referenceNumber: $document->document_number,
                referenceId: $document->id,
                notes: "Physical inventory adjustment: {$document->document_number}"
            );

            $line->update(['adjustment_status' => 'adjusted']);
        }

        $adjustment->transitionTo(StockAdjustment::STATUS_POSTED, [
            'posted_at' => now(),
            'posted_by' => auth()->id(),
        ]);
    }

    /**
     * Books the counted difference at the value the count recorded on its
     * lines: a shortage debits the inventory adjustment account and credits
     * inventory, a surplus the reverse.
     *
     * Inventory is the organization's inventory account; the adjustment account
     * is mapped in accounting settings. Without both the entry is skipped, as
     * for a goods receipt. Once they exist, a failure to post throws.
     *
     * @param  Collection<int, PhysicalInventoryLine>  $lines
     */
    private function bookDifference(PhysicalInventoryDocument $document, Collection $lines): void
    {
        $difference = $lines->reduce(
            fn (string $sum, PhysicalInventoryLine $line): string => bcadd($sum, (string) ($line->difference_value ?? 0), 4),
            '0'
        );
        $sign = bccomp($difference, '0', 4);

        if ($sign === 0) {
            return;
        }

        $inventory = $this->accountResolver->bySubType($document->organization_id, Account::SUBTYPE_INVENTORY);
        $adjustment = $this->accountResolver->mapped($document->organization_id, 'inventory_adjustment_account_id');

        if (! $inventory || ! $adjustment) {
            Log::info('Physical inventory journal entry skipped: inventory or adjustment account not configured', [
                'document_id' => $document->id,
            ]);

            return;
        }

        $amount = (float) ltrim($difference, '-');
        [$debit, $credit] = $sign < 0 ? [$adjustment, $inventory] : [$inventory, $adjustment];

        $this->journalService->createAndPost([
            'organization_id' => $document->organization_id,
            'entry_date' => now()->toDateString(),
            'reference' => $document->document_number,
            'description' => "Physical inventory {$document->document_number}",
            'source_type' => PhysicalInventoryDocument::class,
            'source_id' => $document->id,
        ], [
            ['account_id' => $debit->id, 'description' => "Count difference - {$document->document_number}", 'debit' => $amount, 'credit' => 0],
            ['account_id' => $credit->id, 'description' => "Count difference - {$document->document_number}", 'debit' => 0, 'credit' => $amount],
        ]);
    }
}
