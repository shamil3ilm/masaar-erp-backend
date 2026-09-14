<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\ParkedDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Parks documents that are not ready for the ledger, and later approves,
 * posts or discards them.
 */
class ParkedDocumentService
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {}

    /**
     * Newest document date first. Filters are passed as the keys the caller
     * received, so a filter present with an empty value still applies.
     *
     * @param  array{status?: mixed, document_type?: mixed, date_from?: mixed, date_to?: mixed}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return ParkedDocument::with(['parkedBy:id,name', 'approvedBy:id,name'])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('document_type', $filters), fn ($q) => $q->where('document_type', $filters['document_type']))
            ->when(array_key_exists('date_from', $filters), fn ($q) => $q->whereDate('document_date', '>=', $filters['date_from']))
            ->when(array_key_exists('date_to', $filters), fn ($q) => $q->whereDate('document_date', '<=', $filters['date_to']))
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  validated document attributes
     */
    public function park(int $organizationId, int $userId, array $data): ParkedDocument
    {
        return ParkedDocument::create([
            ...$data,
            'organization_id' => $organizationId,
            'parked_by'       => $userId,
            'status'          => ParkedDocument::STATUS_PARKED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException when the document is no longer parked
     */
    public function update(ParkedDocument $document, array $data): ParkedDocument
    {
        return $document->lockForTransition(function (ParkedDocument $locked) use ($data): ParkedDocument {
            if ($locked->status !== ParkedDocument::STATUS_PARKED) {
                throw new BusinessRuleException('Only parked documents can be updated.', 'INVALID_STATUS', 422);
            }

            $locked->update($data);

            return $locked->fresh();
        });
    }

    /**
     * Record the approver and return the document to parked, ready to post.
     *
     * @throws BusinessRuleException when the document is posted or rejected
     */
    public function approve(ParkedDocument $document, int $userId): ParkedDocument
    {
        return $document->lockForTransition(function (ParkedDocument $locked) use ($userId): ParkedDocument {
            if (! in_array($locked->status, [ParkedDocument::STATUS_PARKED, ParkedDocument::STATUS_PENDING_APPROVAL], true)) {
                throw new BusinessRuleException('Only parked or pending-approval documents can be approved.', 'INVALID_STATUS', 422);
            }

            $locked->update([
                'status'      => ParkedDocument::STATUS_PARKED,
                'approved_by' => $userId,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Book the document's lines as a posted journal entry and mark it posted.
     *
     * The status is checked on the locked row and the entry is booked in the
     * same transaction, so a document posted by a concurrent request is
     * refused rather than booked to the ledger a second time.
     *
     * @throws BusinessRuleException when the document cannot be posted in its status
     * @throws InvalidArgumentException when the lines are missing or the entry is refused
     */
    public function post(ParkedDocument $document, int $userId): JournalEntry
    {
        return $document->lockForTransition(function (ParkedDocument $locked) use ($userId): JournalEntry {
            if (! $locked->isPostable()) {
                throw new BusinessRuleException('Only parked or pending-approval documents can be posted.', 'INVALID_STATUS', 422);
            }

            $data = $locked->document_data;
            $lines = $data['lines'] ?? [];

            if (empty($lines)) {
                throw new InvalidArgumentException('Parked document has no journal lines in document_data.lines.');
            }

            $entry = $this->journalService->createAndPost([
                'organization_id' => $locked->organization_id,
                'entry_date'      => $locked->posting_date->toDateString(),
                'reference'       => $locked->reference,
                'description'     => $data['description'] ?? ('Parked doc: ' . $locked->document_type),
                'currency_code'   => $locked->currency_code,
                'source_type'     => ParkedDocument::class,
                'source_id'       => $locked->id,
            ], $lines);

            $locked->update([
                'status'      => ParkedDocument::STATUS_POSTED,
                'approved_by' => $locked->approved_by ?? $userId,
            ]);

            return $entry;
        });
    }

    /**
     * Mark an unposted document rejected and soft-delete it.
     *
     * @throws BusinessRuleException when the document is posted
     */
    public function delete(ParkedDocument $document): void
    {
        $document->lockForTransition(function (ParkedDocument $locked): void {
            if ($locked->status === ParkedDocument::STATUS_POSTED) {
                throw new BusinessRuleException('Posted documents cannot be deleted.', 'INVALID_STATUS', 422);
            }

            $locked->update(['status' => ParkedDocument::STATUS_REJECTED]);
            $locked->delete();
        });
    }
}
