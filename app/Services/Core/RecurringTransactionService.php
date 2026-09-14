<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Accounting\JournalEntry;
use App\Models\Core\Notification;
use App\Models\Core\RecurringProfile;
use App\Models\Core\RecurringProfileLog;
use App\Models\Purchase\Bill;
use App\Models\Sales\Invoice;
use App\Models\User;
use App\Services\Core\NotificationService;
use App\Services\Core\NumberGeneratorService;
use Carbon\Carbon;
use App\Services\Accounting\JournalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates the documents of recurring profiles. Running due profiles and
 * sending what they create spans modules, so it lives in
 * App\Orchestrators\Core\RunRecurringProfilesOrchestrator.
 */
class RecurringTransactionService
{
    public function __construct(
        protected readonly NotificationService $notificationService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Create a new recurring profile.
     */
    public function createProfile(array $data, int $userId): RecurringProfile
    {
        return DB::transaction(function () use ($data, $userId) {
            $profile = RecurringProfile::create([
                'organization_id' => $data['organization_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'name' => $data['name'],
                'profile_type' => $data['profile_type'],
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'],
                'frequency' => $data['frequency'],
                'interval' => $data['interval'] ?? 1,
                'schedule_config' => $data['schedule_config'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'max_occurrences' => $data['max_occurrences'] ?? null,
                'auto_send' => $data['auto_send'] ?? false,
                'send_reminder' => $data['send_reminder'] ?? false,
                'reminder_days_before' => $data['reminder_days_before'] ?? 3,
                'status' => RecurringProfile::STATUS_ACTIVE,
                'notify_on_creation' => $data['notify_on_creation'] ?? true,
                'notify_email' => $data['notify_email'] ?? null,
                'created_by' => $userId,
            ]);

            $profile->calculateNextRunDate(Carbon::parse($data['start_date'])->subDay());
            $profile->save();

            return $profile;
        });
    }

    /**
     * Update an existing profile.
     */
    public function updateProfile(RecurringProfile $profile, array $data): RecurringProfile
    {
        return DB::transaction(function () use ($profile, $data) {
            $profile->fill([
                'name' => $data['name'] ?? $profile->name,
                'frequency' => $data['frequency'] ?? $profile->frequency,
                'interval' => $data['interval'] ?? $profile->interval,
                'schedule_config' => $data['schedule_config'] ?? $profile->schedule_config,
                'end_date' => $data['end_date'] ?? $profile->end_date,
                'max_occurrences' => $data['max_occurrences'] ?? $profile->max_occurrences,
                'auto_send' => $data['auto_send'] ?? $profile->auto_send,
                'send_reminder' => $data['send_reminder'] ?? $profile->send_reminder,
                'reminder_days_before' => $data['reminder_days_before'] ?? $profile->reminder_days_before,
                'notify_on_creation' => $data['notify_on_creation'] ?? $profile->notify_on_creation,
                'notify_email' => $data['notify_email'] ?? $profile->notify_email,
            ]);

            // Recalculate next run date if schedule changed
            if (isset($data['frequency']) || isset($data['interval']) || isset($data['schedule_config'])) {
                $profile->calculateNextRunDate();
            }

            $profile->save();
            return $profile;
        });
    }

    /**
     * Process a single recurring profile.
     *
     * The document, its log entry and the occurrence count are written in one
     * transaction on the locked profile, which is checked again for being due,
     * so two runs cannot both create the same occurrence. The creator is
     * notified once that transaction has committed, and a failed run is logged
     * after its transaction has rolled back, so the log entry is kept.
     *
     * Sending the document is not done here: it posts journals, moves stock and
     * calls ZATCA through other modules, so RunRecurringProfilesOrchestrator
     * does it after this has returned.
     */
    public function processProfile(RecurringProfile $profile, ?Carbon $date = null): array
    {
        $date = $date ?? today();

        if (!$profile->isDueToRun($date)) {
            return [
                'status' => 'skipped',
                'reason' => 'Not due to run',
            ];
        }

        try {
            $run = DB::transaction(function () use ($profile, $date): ?array {
                $profile = RecurringProfile::lockForUpdate()->findOrFail($profile->id);

                if (! $profile->isDueToRun($date)) {
                    return null;
                }

                $document = $this->createDocument($profile);

                $log = RecurringProfileLog::create([
                    'recurring_profile_id' => $profile->id,
                    'created_type' => get_class($document),
                    'created_id' => $document->id,
                    'scheduled_date' => $profile->next_run_date,
                    'created_date' => $date,
                    'status' => RecurringProfileLog::STATUS_SUCCESS,
                ]);

                $profile->incrementOccurrence();

                return [$profile, $document, $log];
            });
        } catch (\Exception $e) {
            $this->logFailedRun($profile, $date, $e);

            throw $e;
        }

        if ($run === null) {
            return [
                'status' => 'skipped',
                'reason' => 'Not due to run',
            ];
        }

        [$profile, $document, $log] = $run;

        if ($profile->notify_on_creation) {
            $this->sendNotification($profile, $document);
        }

        return [
            'status' => 'success',
            'document_type' => get_class($document),
            'document_id' => $document->id,
            'log_id' => $log->id,
        ];
    }

    private function logFailedRun(RecurringProfile $profile, Carbon $date, \Throwable $e): void
    {
        RecurringProfileLog::create([
            'recurring_profile_id' => $profile->id,
            'created_type' => null,
            'created_id' => null,
            'scheduled_date' => $profile->next_run_date,
            'created_date' => $date,
            'status' => RecurringProfileLog::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ]);
    }

    /**
     * Create document from recurring profile.
     */
    protected function createDocument(RecurringProfile $profile): Model
    {
        $source = $profile->source;

        if (!$source) {
            throw new \RuntimeException("Source document not found for recurring profile {$profile->id}");
        }

        if ($source->organization_id !== $profile->organization_id) {
            throw new \RuntimeException('Source document does not belong to the same organization as the recurring profile.');
        }

        return match ($profile->profile_type) {
            RecurringProfile::TYPE_INVOICE => $this->createInvoiceFromSource($source, $profile),
            RecurringProfile::TYPE_BILL => $this->createBillFromSource($source, $profile),
            RecurringProfile::TYPE_JOURNAL => $this->createJournalFromSource($source, $profile),
            RecurringProfile::TYPE_EXPENSE => $this->createExpenseFromSource($source, $profile),
            default => throw new \RuntimeException("Unknown profile type: {$profile->profile_type}"),
        };
    }

    /**
     * Create invoice from source.
     */
    protected function createInvoiceFromSource(Model $source, RecurringProfile $profile): Model
    {
        // Replicate the source invoice
        $newInvoice = $source->replicate([
            'uuid',
            'invoice_number',
            'status',
            'compliance_status',
            'compliance_uuid',
            'compliance_hash',
            'compliance_qr_code',
            'compliance_response',
            'compliance_submitted_at',
            'amount_paid',
            'journal_entry_id',
            'posted_at',
            'sent_at',
        ]);

        // Update dates
        $newInvoice->invoice_date = today();
        $newInvoice->due_date = today()->addDays($source->payment_terms ?? 30);
        $newInvoice->status = Invoice::STATUS_DRAFT;
        $newInvoice->compliance_status = Invoice::COMPLIANCE_PENDING;
        $newInvoice->amount_paid = 0;
        $newInvoice->amount_due = $source->total;

        // Generate new invoice number
        $newInvoice->invoice_number = app(NumberGeneratorService::class)->generate(
            'INV',
            null,
            $profile->organization_id
        );

        $newInvoice->save();

        // Saved through the relation, which points each copy at the new invoice.
        foreach ($source->lines as $line) {
            $newInvoice->lines()->save($line->replicate());
        }

        return $newInvoice;
    }

    /**
     * Create bill from source.
     */
    protected function createBillFromSource(Model $source, RecurringProfile $profile): Model
    {
        $newBill = $source->replicate([
            'uuid',
            'bill_number',
            'status',
            'amount_paid',
            'journal_entry_id',
            'posted_at',
        ]);

        $newBill->bill_date = today();
        $newBill->due_date = today()->addDays($source->payment_terms ?? 30);
        $newBill->status = Bill::STATUS_DRAFT;
        $newBill->amount_paid = 0;
        $newBill->amount_due = $source->total;

        $newBill->bill_number = app(NumberGeneratorService::class)->generate(
            'BILL',
            null,
            $profile->organization_id
        );

        $newBill->save();

        foreach ($source->lines as $line) {
            $newBill->lines()->save($line->replicate());
        }

        return $newBill;
    }

    /**
     * Create journal entry from source.
     *
     * The copy is a new entry made through JournalService: it is numbered like
     * every other entry, gets the fiscal year of today's date and has its lines
     * checked. It is a draft until auto-send posts it.
     */
    protected function createJournalFromSource(Model $source, RecurringProfile $profile): Model
    {
        return $this->journalService->createEntry([
            'organization_id' => $profile->organization_id,
            'branch_id' => $source->branch_id,
            'entry_date' => today()->toDateString(),
            'reference' => $source->reference,
            'description' => $source->description,
            'currency_code' => $source->currency_code,
            'exchange_rate' => $source->exchange_rate,
            'source_type' => $source->source_type,
            'source_id' => $source->source_id,
            'created_by' => $source->created_by,
        ], $source->lines->map(fn ($line): array => [
            'account_id' => $line->account_id,
            'description' => $line->description,
            'debit' => $line->debit,
            'credit' => $line->credit,
            'cost_center_id' => $line->cost_center_id,
            'contact_id' => $line->contact_id,
            'line_order' => $line->line_order,
        ])->all());
    }

    /**
     * Create expense from source.
     */
    protected function createExpenseFromSource(Model $source, RecurringProfile $profile): Model
    {
        $newExpense = $source->replicate([
            'uuid',
            'expense_number',
            'status',
            'journal_entry_id',
        ]);

        $newExpense->expense_date = today();
        $newExpense->status = \App\Models\Expense\Expense::STATUS_DRAFT;

        $newExpense->expense_number = app(NumberGeneratorService::class)->generate(
            'EXP',
            null,
            $profile->organization_id
        );

        $newExpense->save();

        return $newExpense;
    }

    /**
     * Send notification for created document.
     */
    protected function sendNotification(RecurringProfile $profile, Model $document): void
    {
        $creator = $profile->creator;

        if (!$creator) {
            Log::warning('Cannot send recurring transaction notification: creator not found', [
                'profile_id' => $profile->id,
            ]);
            return;
        }

        $documentNumber = $this->resolveDocumentNumber($document, $profile->profile_type);
        $documentLabel  = $this->resolveDocumentLabel($profile->profile_type);

        $title   = "Recurring {$documentLabel} Created";
        $message = "{$documentLabel} {$documentNumber} has been automatically created from recurring profile \"{$profile->name}\".";

        $channels = ['database'];

        // If the profile has a notify_email address or creator has an email, include the email channel
        if ($profile->notify_email || $creator->email) {
            $channels[] = 'email';
        }

        $this->notificationService->send(
            user: $creator,
            type: Notification::TYPE_SYSTEM_ALERT,
            title: $title,
            message: $message,
            notifiable: $document,
            actionUrl: $this->resolveDocumentUrl($document, $profile->profile_type),
            actionText: "View {$documentLabel}",
            data: [
                'profile_id'      => $profile->id,
                'profile_name'    => $profile->name,
                'document_type'   => $profile->profile_type,
                'document_number' => $documentNumber,
                'icon'            => 'refresh-cw',
                'color'           => '#3b82f6',
            ],
            channels: $channels,
        );
    }

    /**
     * Resolve the human-readable number/reference from a generated document.
     */
    protected function resolveDocumentNumber(Model $document, string $profileType): string
    {
        return match ($profileType) {
            RecurringProfile::TYPE_INVOICE => $document->invoice_number ?? (string) $document->id,
            RecurringProfile::TYPE_BILL    => $document->bill_number ?? (string) $document->id,
            RecurringProfile::TYPE_JOURNAL => $document->entry_number ?? (string) $document->id,
            RecurringProfile::TYPE_EXPENSE => $document->expense_number ?? (string) $document->id,
            default                        => (string) $document->id,
        };
    }

    /**
     * Resolve a user-friendly label for the document type.
     */
    protected function resolveDocumentLabel(string $profileType): string
    {
        return match ($profileType) {
            RecurringProfile::TYPE_INVOICE => 'Invoice',
            RecurringProfile::TYPE_BILL    => 'Bill',
            RecurringProfile::TYPE_JOURNAL => 'Journal Entry',
            RecurringProfile::TYPE_EXPENSE => 'Expense',
            default                        => 'Document',
        };
    }

    /**
     * Build a front-end URL for the generated document.
     */
    protected function resolveDocumentUrl(mixed $document, string $profileType): string
    {
        if (!$document) {
            return '#';
        }

        return match ($profileType) {
            RecurringProfile::TYPE_INVOICE => "/sales/invoices/{$document->id}",
            RecurringProfile::TYPE_BILL    => "/purchase/bills/{$document->id}",
            RecurringProfile::TYPE_JOURNAL => "/accounting/journal-entries/{$document->id}",
            RecurringProfile::TYPE_EXPENSE => "/accounting/expenses/{$document->id}",
            default                        => "#",
        };
    }

    /**
     * Records on a run's log entry that its document was created but could not
     * be sent, so the draft is visible and can be sent by hand.
     */
    public function recordAutoSendFailure(RecurringProfileLog $log, string $error): void
    {
        $log->update(['error_message' => $error]);
    }

    /**
     * Get upcoming scheduled transactions.
     */
    public function getUpcoming(int $organizationId, int $days = 30): array
    {
        $endDate = today()->addDays($days);

        return RecurringProfile::where('organization_id', $organizationId)
            ->where('status', RecurringProfile::STATUS_ACTIVE)
            ->where('next_run_date', '<=', $endDate)
            ->orderBy('next_run_date')
            ->get()
            ->map(fn($profile) => [
                'id' => $profile->id,
                'name' => $profile->name,
                'profile_type' => $profile->profile_type,
                'frequency' => $profile->getFrequencyLabel(),
                'next_run_date' => $profile->next_run_date->format('Y-m-d'),
                'remaining_occurrences' => $profile->getRemainingOccurrences(),
            ])
            ->toArray();
    }

    /**
     * Get profile statistics.
     */
    public function getStatistics(int $organizationId): array
    {
        $profiles = RecurringProfile::where('organization_id', $organizationId);

        return [
            'total' => (clone $profiles)->count(),
            'active' => (clone $profiles)->where('status', RecurringProfile::STATUS_ACTIVE)->count(),
            'paused' => (clone $profiles)->where('status', RecurringProfile::STATUS_PAUSED)->count(),
            'completed' => (clone $profiles)->where('status', RecurringProfile::STATUS_COMPLETED)->count(),
            'by_type' => (clone $profiles)->selectRaw('profile_type, count(*) as count')
                ->groupBy('profile_type')
                ->pluck('count', 'profile_type')
                ->toArray(),
            'documents_created_this_month' => RecurringProfileLog::whereHas('profile', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            })
                ->where('status', RecurringProfileLog::STATUS_SUCCESS)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];
    }

    /**
     * Skip the next occurrence of a profile.
     */
    public function skipNext(RecurringProfile $profile): RecurringProfile
    {
        return DB::transaction(function () use ($profile) {
            RecurringProfileLog::create([
                'recurring_profile_id' => $profile->id,
                'created_type' => null,
                'created_id' => null,
                'scheduled_date' => $profile->next_run_date,
                'created_date' => today(),
                'status' => RecurringProfileLog::STATUS_SKIPPED,
                'error_message' => 'Manually skipped',
            ]);

            $profile->incrementOccurrence();

            return $profile;
        });
    }
}
