<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\Complaint;
use App\Models\Manufacturing\ComplaintCommunication;
use App\Models\Manufacturing\ComplaintResolution;
use App\Models\Sales\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Quality complaints with their communications and resolutions. Those child
 * rows have no organization column; they are reached only through a complaint
 * of the organization. The complaining contact is shown by reference only.
 */
class ComplaintService
{
    public function list(int $organizationId): LengthAwarePaginator
    {
        return Complaint::where('organization_id', $organizationId)
            ->with([$this->contactReference(), 'assignedTo'])
            ->paginate(20);
    }

    public function create(int $organizationId, array $data): Complaint
    {
        return Complaint::create([
            ...$data,
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * One of the organization's complaints; a missing id is a 404.
     */
    public function find(int $organizationId, int $id): Complaint
    {
        return Complaint::where('organization_id', $organizationId)->findOrFail($id);
    }

    /**
     * A complaint with its contact, assignee, communications and resolutions.
     */
    public function findForDisplay(int $organizationId, int $id): Complaint
    {
        return Complaint::where('organization_id', $organizationId)
            ->with([$this->contactReference(), 'assignedTo', 'communications', 'resolutions'])
            ->findOrFail($id);
    }

    public function addCommunication(Complaint $complaint, array $data, int $userId): ComplaintCommunication
    {
        return $complaint->communications()->create([
            ...$data,
            'uuid'            => (string) Str::uuid(),
            'user_id'         => $userId,
            'communicated_at' => now(),
        ]);
    }

    /**
     * Record a resolution and mark the complaint resolved.
     *
     * Both are written on the locked complaint in one transaction, so a
     * resolution is never kept for a complaint whose status did not change.
     */
    public function resolve(Complaint $complaint, array $data, int $userId): ComplaintResolution
    {
        return $complaint->lockForTransition(function (Complaint $complaint) use ($data, $userId): ComplaintResolution {
            $resolution = ComplaintResolution::create([
                ...$data,
                'uuid'            => (string) Str::uuid(),
                'complaint_id'    => $complaint->id,
                'resolution_date' => now()->toDateString(),
                'resolved_by_id'  => $userId,
            ]);

            $complaint->update([
                'status'                 => 'resolved',
                'actual_resolution_date' => now()->toDateString(),
            ]);

            return $resolution;
        });
    }

    private function contactReference(): string
    {
        return 'contact:'.implode(',', Contact::REFERENCE_COLUMNS);
    }
}
