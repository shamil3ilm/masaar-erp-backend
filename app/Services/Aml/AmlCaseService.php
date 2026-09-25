<?php

declare(strict_types=1);

namespace App\Services\Aml;

use App\Jobs\RunAmlScreeningJob;
use App\Models\Aml\AmlRiskScore;
use App\Models\Aml\AmlSuspiciousActivity;
use App\Models\Aml\AmlTransactionFlag;
use App\Models\Sales\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * What a compliance officer reviews: contact risk scores, flagged
 * transactions and suspicious activity reports, and re-screening a contact.
 *
 * A contact appears in these only by its reference columns, and the creator
 * of a report by id and name: the listings are for triage, and a contact's
 * full record is a separate, separately permitted read.
 */
final class AmlCaseService
{
    /**
     * @param  array{risk_level?: string, sanctions_only?: bool, pep_only?: bool}  $filters
     */
    public function paginateRiskScores(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return AmlRiskScore::with($this->contactReference())
            ->where('organization_id', $organizationId)
            ->orderByDesc('score')
            ->when(isset($filters['risk_level']), fn ($q) => $q->where('risk_level', $filters['risk_level']))
            ->when(! empty($filters['sanctions_only']), fn ($q) => $q->where('sanctions_hit', true))
            ->when(! empty($filters['pep_only']), fn ($q) => $q->where('pep_hit', true))
            ->paginate($perPage);
    }

    public function contactRisk(int $organizationId, int $contactId): AmlRiskScore
    {
        return AmlRiskScore::with($this->contactReference())
            ->where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->firstOrFail();
    }

    /**
     * @param  array{status?: string, flag_reason?: string, transaction_type?: string, contact_id?: int}  $filters
     */
    public function paginateTransactionFlags(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return AmlTransactionFlag::with($this->contactReference())
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['flag_reason']), fn ($q) => $q->where('flag_reason', $filters['flag_reason']))
            ->when(isset($filters['transaction_type']), fn ($q) => $q->where('transaction_type', $filters['transaction_type']))
            ->when(isset($filters['contact_id']), fn ($q) => $q->where('contact_id', $filters['contact_id']))
            ->paginate($perPage);
    }

    /**
     * @param  array{status?: string, report_type?: string, activity_type?: string}  $filters
     */
    public function paginateSuspiciousActivities(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return AmlSuspiciousActivity::with([$this->contactReference(), 'creator:id,name'])
            ->where('organization_id', $organizationId)
            ->orderByDesc('created_at')
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['report_type']), fn ($q) => $q->where('report_type', $filters['report_type']))
            ->when(isset($filters['activity_type']), fn ($q) => $q->where('activity_type', $filters['activity_type']))
            ->paginate($perPage);
    }

    /**
     * The report with its contact by reference and its creator by name.
     */
    public function presentSuspiciousActivity(AmlSuspiciousActivity $sar): AmlSuspiciousActivity
    {
        return $sar->load([$this->contactReference(), 'creator:id,name']);
    }

    /**
     * One of the organization's contacts, to be screened.
     */
    public function findContact(int $organizationId, int $contactId): Contact
    {
        return Contact::where('organization_id', $organizationId)->findOrFail($contactId);
    }

    /**
     * Queues a fresh screening of the contact.
     */
    public function dispatchScreening(Contact $contact): void
    {
        RunAmlScreeningJob::dispatch($contact->id, $contact->organization_id);
    }

    private function contactReference(): string
    {
        return 'contact:'.implode(',', Contact::REFERENCE_COLUMNS);
    }
}
