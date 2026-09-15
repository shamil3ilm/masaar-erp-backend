<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Sales\OutputConditionRecord;
use App\Models\Sales\OutputMessage;
use App\Models\Sales\OutputType;
use App\Services\Core\EmailService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Output determination: which documents are printed, emailed or sent, and the
 * messages that carry them.
 *
 * Output messages and condition records have no organization column. They
 * belong to an output type, which is scoped to the current organization, so
 * every read or change of a message goes through its type.
 */
class OutputDeterminationService
{
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * Output types of the current organization with their condition records,
     * latest first, narrowed to a document type and to active types when set.
     */
    public function listTypes(?string $documentType, bool $activeOnly, int $perPage): LengthAwarePaginator
    {
        return OutputType::with(['conditionRecords'])
            ->latest()
            ->when($documentType !== null, fn ($q) => $q->forDocumentType($documentType))
            ->when($activeOnly, fn ($q) => $q->active())
            ->paginate($perPage);
    }

    /**
     * Create an output type and its condition records in one transaction.
     *
     * @param  array<string, mixed>  $data  validated type fields
     * @param  list<array<string, mixed>>  $conditionRecords  validated records
     */
    public function createType(int $organizationId, array $data, array $conditionRecords): OutputType
    {
        return DB::transaction(function () use ($organizationId, $data, $conditionRecords): OutputType {
            $outputType = OutputType::create(array_merge($data, ['organization_id' => $organizationId]));

            foreach ($conditionRecords as $record) {
                OutputConditionRecord::create(array_merge($record, [
                    'output_type_id' => $outputType->id,
                    'is_active'      => $record['is_active'] ?? true,
                ]));
            }

            return $outputType->load('conditionRecords');
        });
    }

    public function typeDetails(OutputType $outputType): OutputType
    {
        return $outputType->load('conditionRecords');
    }

    /**
     * @param  array<string, mixed>  $data  validated type fields
     */
    public function updateType(OutputType $outputType, array $data): OutputType
    {
        $outputType->update($data);

        return $outputType->fresh('conditionRecords');
    }

    public function deleteType(OutputType $outputType): void
    {
        $outputType->delete();
    }

    /**
     * Messages whose output type belongs to the current organization, latest
     * first, narrowed by the filters that are set.
     *
     * @param  array{status?: ?string, document_type?: ?string, document_id?: ?int, medium?: ?string}  $filters
     */
    public function listMessages(array $filters, int $perPage): LengthAwarePaginator
    {
        return OutputMessage::with(['outputType'])
            ->whereHas('outputType')
            ->latest()
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['document_type']), fn ($q) => $q->where('document_type', $filters['document_type']))
            ->when(isset($filters['document_id']), fn ($q) => $q->where('document_id', $filters['document_id']))
            ->when(isset($filters['medium']), fn ($q) => $q->where('medium', $filters['medium']))
            ->paginate($perPage);
    }

    /**
     * Retry a failed message of the current organization.
     *
     * The message is claimed under a lock: its status is checked on the locked
     * row and set back to pending before the transaction ends, so two retries
     * cannot both send it. Sending happens after the commit, outside the
     * transaction.
     *
     * @throws ModelNotFoundException when the message's type is not the organization's
     * @throws BusinessRuleException when the message has not failed
     */
    public function retry(OutputMessage $message): OutputMessage
    {
        $claimed = DB::transaction(function () use ($message): OutputMessage {
            $locked = OutputMessage::query()
                ->whereHas('outputType')
                ->lockForUpdate()
                ->findOrFail($message->id);

            if (! $locked->canRetry()) {
                throw new BusinessRuleException(
                    "Output message #{$locked->id} cannot be retried (status: {$locked->status}).",
                    'INVALID_STATUS'
                );
            }

            $locked->update(['status' => OutputMessage::STATUS_PENDING]);

            return $locked;
        });

        $this->dispatch($claimed->load('outputType'));

        return $claimed->fresh();
    }

    /**
     * Determine which output messages should be created for a document event.
     *
     * @param  string  $documentType  e.g. 'invoice', 'sales_order'
     * @param  int     $documentId
     * @param  string  $triggerEvent  matches dispatch_time: 'immediately'|'on_save'|'on_post'|'scheduled'
     * @param  int|null $customerId   Used for condition record matching
     * @param  int|null $customerGroupId
     * @return Collection<int, OutputMessage>
     */
    public function determineOutputs(
        string $documentType,
        int $documentId,
        string $triggerEvent,
        ?int $customerId = null,
        ?int $customerGroupId = null
    ): Collection {
        $today = now()->toDateString();

        $outputTypes = OutputType::active()
            ->forDocumentType($documentType)
            ->forDispatchTime($triggerEvent)
            ->with(['conditionRecords' => function ($q) use ($today) {
                $q->active()->validOn($today);
            }])
            ->get();

        $createdMessages = new Collection();

        foreach ($outputTypes as $outputType) {
            $matchingRecord = $this->findMatchingConditionRecord(
                $outputType->conditionRecords,
                $customerId,
                $customerGroupId
            );

            if ($matchingRecord === null) {
                continue;
            }

            $recipient = $this->resolveRecipient($outputType, $customerId);

            $scheduledAt = $triggerEvent === OutputType::DISPATCH_SCHEDULED
                ? now()->addMinutes(5)
                : null;

            $message = OutputMessage::create([
                'output_type_id' => $outputType->id,
                'document_type'  => $documentType,
                'document_id'    => $documentId,
                'status'         => OutputMessage::STATUS_PENDING,
                'medium'         => $outputType->output_medium,
                'recipient'      => $recipient,
                'scheduled_at'   => $scheduledAt,
            ]);

            $createdMessages->push($message);
        }

        return $createdMessages;
    }

    /**
     * Dispatch a single output message based on its medium.
     */
    public function dispatch(OutputMessage $message): void
    {
        $message->update([
            'status'    => OutputMessage::STATUS_PROCESSING,
            'retry_count' => $message->retry_count + 1,
        ]);

        try {
            match ($message->medium) {
                OutputMessage::STATUS_SENT => null, // guard
                'email'  => $this->dispatchEmail($message),
                'print'  => $this->dispatchPrint($message),
                'edi'    => $this->dispatchEdi($message),
                'portal' => $this->dispatchPortal($message),
                default  => throw new \InvalidArgumentException("Unknown medium: {$message->medium}"),
            };

            $message->update([
                'status'  => OutputMessage::STATUS_SENT,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Output message dispatch failed', [
                'message_id' => $message->id,
                'medium'     => $message->medium,
                'error'      => $e->getMessage(),
            ]);

            $message->update([
                'status'        => OutputMessage::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process all pending output messages whose scheduled_at has passed.
     * Intended to be called from the scheduler.
     */
    public function processPendingOutputs(): void
    {
        $messages = OutputMessage::pending()
            ->scheduledBefore(now())
            ->with('outputType')
            ->get();

        foreach ($messages as $message) {
            $this->dispatch($message);
        }

        Log::info('OutputDeterminationService: processed pending outputs', [
            'count' => $messages->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Find the best-matching condition record using priority: customer > customer_group > all.
     */
    private function findMatchingConditionRecord(
        Collection $records,
        ?int $customerId,
        ?int $customerGroupId
    ): ?OutputConditionRecord {
        // Priority 1: specific customer
        if ($customerId !== null) {
            $match = $records->first(
                fn ($r) => $r->key_combination === OutputConditionRecord::KEY_CUSTOMER
                    && $r->customer_id === $customerId
            );
            if ($match !== null) {
                return $match;
            }
        }

        // Priority 2: customer group
        if ($customerGroupId !== null) {
            $match = $records->first(
                fn ($r) => $r->key_combination === OutputConditionRecord::KEY_CUSTOMER_GROUP
                    && $r->customer_group_id === $customerGroupId
            );
            if ($match !== null) {
                return $match;
            }
        }

        // Priority 3: catch-all
        return $records->first(
            fn ($r) => $r->key_combination === OutputConditionRecord::KEY_ALL
        );
    }

    private function resolveRecipient(OutputType $outputType, ?int $customerId): ?string
    {
        // If there is a customer, attempt to load their email from the contacts table
        if ($customerId !== null) {
            $email = \Illuminate\Support\Facades\DB::table('contacts')
                ->where('id', $customerId)
                ->value('email');

            if ($email) {
                return (string) $email;
            }
        }

        return null;
    }

    private function dispatchEmail(OutputMessage $message): void
    {
        if (empty($message->recipient)) {
            throw new \RuntimeException("No recipient email for output message {$message->id}.");
        }

        $template = $message->outputType?->email_template ?? 'generic_document';

        $this->emailService->sendTemplate(
            $template,
            $message->recipient,
            [
                'document_type' => $message->document_type,
                'document_id'   => $message->document_id,
            ],
            null,
            null,
            null,
            [],
            'en',
            null
        );
    }

    private function dispatchPrint(OutputMessage $message): void
    {
        // Delegate to a print service when available; for now log the intent
        Log::info('OutputDeterminationService: queued for print', [
            'message_id'    => $message->id,
            'print_template' => $message->outputType?->print_template,
            'document_type' => $message->document_type,
            'document_id'   => $message->document_id,
        ]);
    }

    private function dispatchEdi(OutputMessage $message): void
    {
        Log::info('OutputDeterminationService: queued for EDI', [
            'message_id'    => $message->id,
            'document_type' => $message->document_type,
            'document_id'   => $message->document_id,
        ]);
    }

    private function dispatchPortal(OutputMessage $message): void
    {
        Log::info('OutputDeterminationService: queued for portal', [
            'message_id'    => $message->id,
            'document_type' => $message->document_type,
            'document_id'   => $message->document_id,
        ]);
    }
}
