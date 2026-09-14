<?php

declare(strict_types=1);

namespace App\Orchestrators\Core;

use App\Models\Accounting\JournalEntry;
use App\Models\Core\RecurringProfile;
use App\Models\Core\RecurringProfileLog;
use App\Models\Purchase\Bill;
use App\Models\Sales\Invoice;
use App\Services\Accounting\JournalService;
use App\Services\Core\RecurringTransactionService;
use App\Services\Purchase\BillService;
use App\Services\Sales\InvoiceService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Runs recurring profiles. RecurringTransactionService creates each document;
 * for a profile set to auto-send, the module that owns the document then
 * sends it: an invoice through InvoiceService (journal entry, stock, ZATCA),
 * a bill through BillService, a journal entry through JournalService.
 *
 * Sending starts only after the document's own transaction has committed, so
 * none of its side effects runs for a document that rolls back. A failed send
 * leaves the committed draft and is recorded on the run's log entry.
 */
class RunRecurringProfilesOrchestrator
{
    public function __construct(
        private readonly RecurringTransactionService $recurring,
        private readonly InvoiceService $invoices,
        private readonly BillService $bills,
        private readonly JournalService $journals,
    ) {}

    /**
     * Run every profile due on $date.
     *
     * @return array{processed: int, success: int, failed: int, skipped: int, errors: list<array{profile_id: int, error: string}>}
     */
    public function runDue(?Carbon $date = null): array
    {
        $date = $date ?? today();
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

        foreach (RecurringProfile::dueToRun($date)->get() as $profile) {
            $results['processed']++;

            try {
                $result = $this->run($profile, $date);
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = ['profile_id' => $profile->id, 'error' => $e->getMessage()];

                Log::error('Recurring profile processing failed', [
                    'profile_id' => $profile->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $results[$result['status']]++;

            if (isset($result['auto_send_error'])) {
                $results['errors'][] = ['profile_id' => $profile->id, 'error' => $result['auto_send_error']];
            }
        }

        return $results;
    }

    /**
     * Run one profile: create its document and, if the profile auto-sends, send it.
     *
     * @return array<string, mixed>
     */
    public function run(RecurringProfile $profile, ?Carbon $date = null): array
    {
        $result = $this->recurring->processProfile($profile, $date);

        if ($result['status'] !== 'success' || ! $profile->auto_send) {
            return $result;
        }

        $document = $result['document_type']::findOrFail($result['document_id']);
        $error = $this->send($profile, $document);

        if ($error !== null) {
            $this->recurring->recordAutoSendFailure(RecurringProfileLog::findOrFail($result['log_id']), $error);
            $result['auto_send_error'] = $error;
        }

        return $result;
    }

    /**
     * Sends a document through the service that owns the transition. Expenses
     * and other types stay drafts. Returns the failure, if any.
     */
    private function send(RecurringProfile $profile, Model $document): ?string
    {
        try {
            match (true) {
                $document instanceof Invoice => $this->invoices->send($document),
                $document instanceof Bill => $this->bills->approve($document, (int) $profile->created_by),
                $document instanceof JournalEntry => $this->journals->postEntry($document),
                default => Log::warning('Auto-send not supported for document type', [
                    'document_class' => get_class($document),
                    'document_id' => $document->id,
                ]),
            };
        } catch (\Throwable $e) {
            Log::error('Auto-send failed for recurring document', [
                'profile_id' => $profile->id,
                'document_class' => get_class($document),
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return "Auto-send failed: {$e->getMessage()}";
        }

        return null;
    }
}
