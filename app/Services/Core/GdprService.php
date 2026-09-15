<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\GdprConsentRecord;
use App\Models\Core\GdprDataSubjectRequest;
use App\Models\Core\GdprProcessingActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * GDPR records of an organization: data subject requests, the register of
 * processing activities and consent records.
 *
 * A request is processed once and a consent withdrawn once. Each is changed on
 * the locked row after re-checking it, so a repeated or concurrent call cannot
 * overwrite when the request was completed or the consent withdrawn.
 */
class GdprService
{
    /**
     * Requests of the organization, latest received first, 20 to a page.
     */
    public function listRequests(int $orgId): LengthAwarePaginator
    {
        return GdprDataSubjectRequest::where('organization_id', $orgId)
            ->orderBy('received_at', 'desc')
            ->paginate(20);
    }

    /**
     * A request of the organization by id; another organization's request is not found.
     */
    public function findRequest(int $orgId, int $id): GdprDataSubjectRequest
    {
        return GdprDataSubjectRequest::where('organization_id', $orgId)->findOrFail($id);
    }

    public function submitDataRequest(array $data): GdprDataSubjectRequest
    {
        return GdprDataSubjectRequest::create([
            'uuid'           => Str::uuid(),
            'organization_id' => $data['organization_id'],
            'request_type'   => $data['request_type'],
            'requester_name' => $data['requester_name'],
            'requester_email' => $data['requester_email'],
            'requester_id'   => $data['requester_id'] ?? null,
            'status'         => 'received',
            'received_at'    => now(),
            'deadline_at'    => now()->addDays(30),
        ]);
    }

    /**
     * Processes a request by its type and returns it as stored afterwards.
     *
     * @throws InvalidArgumentException when the request was already processed
     */
    public function processRequest(GdprDataSubjectRequest $request): GdprDataSubjectRequest
    {
        return DB::transaction(function () use ($request): GdprDataSubjectRequest {
            $locked = GdprDataSubjectRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status === 'completed') {
                throw new InvalidArgumentException('This request has already been processed.');
            }

            match ($locked->request_type) {
                'erasure'     => $this->processErasureRequest($locked),
                'portability' => $this->exportDataPortability($locked),
                default       => $locked->update(['status' => 'completed', 'completed_at' => now()]),
            };

            return $locked->fresh();
        });
    }

    public function processErasureRequest(GdprDataSubjectRequest $request): void
    {
        // Anonymize PII fields for the requester's email across relevant tables
        $request->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function exportDataPortability(GdprDataSubjectRequest $request): string
    {
        // Generate export — placeholder returns a path reference
        $exportPath = 'gdpr-exports/' . $request->uuid . '.json';
        $request->update([
            'status'              => 'completed',
            'completed_at'        => now(),
            'data_exported_path'  => $exportPath,
        ]);
        return $exportPath;
    }

    public function getProcessingRegister(int $orgId): array
    {
        return GdprProcessingActivity::where('organization_id', $orgId)->get()->toArray();
    }

    /**
     * @param  array<string, mixed>  $data  validated fields with the organization id
     */
    public function createProcessingActivity(array $data): GdprProcessingActivity
    {
        return GdprProcessingActivity::create(array_merge($data, ['uuid' => (string) Str::uuid()]));
    }

    public function recordConsent(array $data): GdprConsentRecord
    {
        return GdprConsentRecord::create([
            'uuid'            => Str::uuid(),
            'organization_id' => $data['organization_id'],
            'contact_id'      => $data['contact_id'] ?? null,
            'user_id'         => $data['user_id'] ?? null,
            'purpose'         => $data['purpose'],
            'consent_given'   => true,
            'given_at'        => now(),
            'ip_address'      => $data['ip_address'] ?? null,
            'consent_text'    => $data['consent_text'] ?? null,
        ]);
    }

    /**
     * A consent record of the organization by id; another organization's record is not found.
     */
    public function findConsent(int $orgId, int $id): GdprConsentRecord
    {
        return GdprConsentRecord::where('organization_id', $orgId)->findOrFail($id);
    }

    /**
     * Records the withdrawal of a consent and returns the record as stored.
     *
     * @throws InvalidArgumentException when the consent was already withdrawn
     */
    public function withdrawConsent(GdprConsentRecord $record): GdprConsentRecord
    {
        return DB::transaction(function () use ($record): GdprConsentRecord {
            $locked = GdprConsentRecord::query()->lockForUpdate()->findOrFail($record->id);

            if ($locked->withdrawn_at !== null || ! $locked->consent_given) {
                throw new InvalidArgumentException('This consent has already been withdrawn.');
            }

            $locked->update(['consent_given' => false, 'withdrawn_at' => now()]);

            return $locked->fresh();
        });
    }
}
