<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\EngineeringChange;
use App\Models\Manufacturing\EngineeringChangeObject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Engineering change requests and the objects they affect.
 *
 * Submit, approve, reject and implement run on the locked change and check the
 * status there, so a stale copy cannot approve a change rejected meanwhile.
 */
class EngineeringChangeService
{
    public function list(array $filters = []): Collection
    {
        return EngineeringChange::with(['requestedBy', 'approvedBy', 'affectedObjects'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['change_type']), fn($q) => $q->where('change_type', $filters['change_type']))
            ->when(isset($filters['priority']), fn($q) => $q->where('priority', $filters['priority']))
            ->when(isset($filters['product_id']), fn($q) => $q->forProduct((int) $filters['product_id']))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * One of the organization's engineering changes.
     *
     * @param  list<string>  $with
     */
    public function findOrFail(int $id, array $with = []): EngineeringChange
    {
        return EngineeringChange::with($with)->findOrFail($id);
    }

    public function create(array $data): EngineeringChange
    {
        return EngineeringChange::create($data);
    }

    public function update(EngineeringChange $ec, array $data): EngineeringChange
    {
        $ec->update($data);

        return $ec->fresh();
    }

    public function delete(EngineeringChange $ec): void
    {
        $ec->delete();
    }

    public function submit(EngineeringChange $ec): EngineeringChange
    {
        return $ec->lockForTransition(function (EngineeringChange $ec): EngineeringChange {
            if (!$ec->canSubmit()) {
                throw ValidationException::withMessages([
                    'status' => "Engineering change cannot be submitted in its current status: {$ec->status}.",
                ]);
            }

            $ec->update(['status' => EngineeringChange::STATUS_SUBMITTED]);

            return $ec->fresh();
        });
    }

    public function approve(EngineeringChange $ec, int $approvedBy): EngineeringChange
    {
        return $ec->lockForTransition(function (EngineeringChange $ec) use ($approvedBy): EngineeringChange {
            if (!$ec->canApprove()) {
                throw ValidationException::withMessages([
                    'status' => "Engineering change cannot be approved in its current status: {$ec->status}.",
                ]);
            }

            $ec->update([
                'status' => EngineeringChange::STATUS_APPROVED,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            return $ec->fresh();
        });
    }

    public function reject(EngineeringChange $ec, int $rejectedBy, string $reason): EngineeringChange
    {
        return $ec->lockForTransition(function (EngineeringChange $ec) use ($rejectedBy, $reason): EngineeringChange {
            if (!$ec->canApprove()) {
                throw ValidationException::withMessages([
                    'status' => "Engineering change cannot be rejected in its current status: {$ec->status}.",
                ]);
            }

            $ec->update([
                'status' => EngineeringChange::STATUS_REJECTED,
                'approved_by' => $rejectedBy,
                'approved_at' => now(),
                'reason' => $ec->reason ? $ec->reason . "\nRejection reason: " . $reason : "Rejection reason: " . $reason,
            ]);

            return $ec->fresh();
        });
    }

    public function implement(EngineeringChange $ec): EngineeringChange
    {
        return $ec->lockForTransition(function (EngineeringChange $ec): EngineeringChange {
            if (!$ec->canImplement()) {
                throw ValidationException::withMessages([
                    'status' => "Engineering change cannot be implemented in its current status: {$ec->status}.",
                ]);
            }

            $ec->update([
                'status' => EngineeringChange::STATUS_IMPLEMENTED,
                'implemented_at' => now(),
            ]);

            return $ec->fresh();
        });
    }

    public function addAffectedObject(EngineeringChange $ec, array $data): EngineeringChangeObject
    {
        return $ec->affectedObjects()->create([
            'organization_id' => $ec->organization_id,
            ...$data,
        ]);
    }

    public function getChangesForObject(string $objectType, int $objectId): Collection
    {
        return EngineeringChange::whereHas('affectedObjects', function ($q) use ($objectType, $objectId): void {
            $q->where('object_type', $objectType)->where('object_id', $objectId);
        })
            ->with(['requestedBy', 'approvedBy'])
            ->orderByDesc('created_at')
            ->get();
    }
}
