<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Contract;
use App\Models\Purchase\ContractRelease;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContractService
{
    public function __construct(
        private NumberGeneratorService $numberGenerator
    ) {}

    /**
     * A page of contracts matching the filters, with contact and creator loaded.
     *
     * The sort column and direction are expected already checked against an
     * allowlist by the caller.
     *
     * @param  array<string, mixed>  $filters  status, contract_type, contact_id, search, expiring_in_days
     */
    public function list(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return Contract::with(['contact', 'creator'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['contract_type'] ?? null, fn ($q, $type) => $q->where('contract_type', $type))
            ->when($filters['contact_id'] ?? null, fn ($q, $id) => $q->where('contact_id', $id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('contract_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($filters['expiring_in_days'] ?? null, fn ($q, $days) => $q->expiringSoon((int) $days))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Create a new contract with lines.
     */
    public function createContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['contract_number'])) {
                $data['contract_number'] = $this->numberGenerator->generate('CON');
            }

            $lines = $data['lines'] ?? [];
            $milestones = $data['milestones'] ?? [];
            unset($data['lines'], $data['milestones']);

            $data['created_by'] = $data['created_by'] ?? auth()->id();
            $data['status'] = Contract::STATUS_DRAFT;

            $contract = Contract::create($data);

            foreach ($lines as $index => $lineData) {
                $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                $contract->lines()->create($lineData);
            }

            foreach ($milestones as $milestoneData) {
                $contract->milestones()->create($milestoneData);
            }

            return $contract->load(['lines', 'milestones', 'contact']);
        });
    }

    /**
     * Update a draft contract, checked on the locked row.
     */
    public function update(Contract $contract, array $data): Contract
    {
        return $contract->lockForTransition(function (Contract $contract) use ($data): Contract {
            $this->assertDraft($contract, 'Only draft contracts can be updated.');

            $contract->update($data);

            return $contract->fresh(['contact', 'lines']);
        });
    }

    /**
     * Delete a draft contract with its lines and milestones, all or nothing.
     */
    public function delete(Contract $contract): void
    {
        $contract->lockForTransition(function (Contract $contract): void {
            $this->assertDraft($contract, 'Only draft contracts can be deleted.');

            $contract->lines()->delete();
            $contract->milestones()->delete();
            $contract->delete();
        });
    }

    /**
     * Activate a draft contract, checked on the locked row.
     */
    public function activateContract(Contract $contract): Contract
    {
        return $contract->lockForTransition(function (Contract $contract): Contract {
            if (! $contract->canBeActivated()) {
                throw new \InvalidArgumentException('Only draft contracts can be activated.');
            }

            $contract->update([
                'status' => Contract::STATUS_ACTIVE,
                'signed_date' => $contract->signed_date ?? now()->toDateString(),
            ]);

            return $contract->fresh();
        });
    }

    /**
     * Create a release order against an active contract.
     *
     * The contract is locked so two releases cannot both fit the remaining value.
     */
    public function createRelease(Contract $contract, array $data): ContractRelease
    {
        return $contract->lockForTransition(function (Contract $contract) use ($data): ContractRelease {
            if (! $contract->isActive()) {
                throw new \InvalidArgumentException('Releases can only be created against active contracts.');
            }

            if ($contract->total_value !== null) {
                $totalReleased = $contract->releases()
                    ->whereIn('status', ['pending', 'fulfilled'])
                    ->sum('amount');
                $newTotal = bcadd((string) $totalReleased, (string) $data['amount'], 4);

                if (bccomp($newTotal, (string) $contract->total_value, 4) > 0) {
                    throw new \InvalidArgumentException('Release amount exceeds remaining contract value.');
                }
            }

            $data['release_date'] = $data['release_date'] ?? now()->toDateString();
            $data['status'] = 'pending';

            return $contract->releases()->create($data);
        });
    }

    /**
     * The contract's releases, newest release date first.
     *
     * @return Collection<int, ContractRelease>
     */
    public function releasesOf(Contract $contract): Collection
    {
        return $contract->releases()->orderBy('release_date', 'desc')->get();
    }

    /**
     * Terminate a draft or active contract, checked on the locked row.
     */
    public function terminateContract(Contract $contract, array $data): Contract
    {
        return $contract->lockForTransition(function (Contract $contract) use ($data): Contract {
            if (! $contract->canBeTerminated()) {
                throw new \InvalidArgumentException('Contract cannot be terminated in its current status.');
            }

            $contract->update([
                'status' => Contract::STATUS_TERMINATED,
                'notes' => trim(($contract->notes ?? '') . "\n\nTerminated: " . ($data['reason'] ?? '')),
            ]);

            return $contract->fresh();
        });
    }

    /**
     * Get contracts expiring within the given number of days for an organization.
     */
    public function checkExpiringContracts(Organization $organization, int $days = 30): Collection
    {
        return Contract::where('organization_id', $organization->id)
            ->expiringSoon($days)
            ->with(['contact'])
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Expire contracts that have passed their end date.
     */
    public function expireOverdueContracts(): int
    {
        return Contract::where('status', Contract::STATUS_ACTIVE)
            ->where('end_date', '<', now()->toDateString())
            ->update(['status' => Contract::STATUS_EXPIRED]);
    }

    private function assertDraft(Contract $contract, string $message): void
    {
        if ($contract->status !== Contract::STATUS_DRAFT) {
            throw new \InvalidArgumentException($message);
        }
    }
}
