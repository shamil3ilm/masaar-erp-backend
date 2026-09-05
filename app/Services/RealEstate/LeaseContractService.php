<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\RealEstate\ContractCondition;
use App\Models\RealEstate\ContractOption;
use App\Models\RealEstate\RentalContract;
use App\Models\RealEstate\RentalUnit;
use App\Services\Core\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The lease contract lifecycle: creation, activation, termination, rent
 * escalations, and contract options (renewal and break).
 */
class LeaseContractService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    public function listContracts(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = RentalContract::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['contract_type'])) {
            $query->where('contract_type', $filters['contract_type']);
        }

        return $query->with(['rentalUnit.building', 'conditions', 'securityDeposit'])
            ->orderByDesc('start_date')
            ->paginate(20);
    }

    public function createContract(int $organizationId, array $data): RentalContract
    {
        return DB::transaction(function () use ($organizationId, $data) {
            $unit = RentalUnit::where('organization_id', $organizationId)
                ->findOrFail($data['rental_unit_id']);

            if (! $unit->isVacant() && ($data['contract_type'] ?? 'lease_out') === 'lease_out') {
                throw new InvalidArgumentException("Unit '{$unit->code}' is not vacant.");
            }

            $contractNumber = $this->numberGenerator->generate('LEASE', null, $organizationId);

            $conditions = $data['conditions'] ?? [];
            $options    = $data['options'] ?? [];
            unset($data['conditions'], $data['options']);

            $contract = RentalContract::create(array_merge($data, [
                'organization_id' => $organizationId,
                'contract_number' => $contractNumber,
                'status'          => 'draft',
                'created_by'      => Auth::id(),
            ]));

            foreach ($conditions as $condition) {
                $contract->conditions()->create($condition);
            }

            foreach ($options as $option) {
                $contract->options()->create($option);
            }

            return $contract->load(['conditions', 'options', 'rentalUnit']);
        });
    }

    public function activateContract(RentalContract $contract): RentalContract
    {
        if ($contract->status !== 'draft') {
            throw new InvalidArgumentException('Only draft contracts can be activated.');
        }

        return DB::transaction(function () use ($contract) {
            $contract->update(['status' => 'active']);

            if ($contract->isLeaseOut()) {
                $contract->rentalUnit->update(['status' => 'occupied']);
            }

            return $contract->fresh(['rentalUnit', 'conditions']);
        });
    }

    public function terminateContract(RentalContract $contract, array $data): RentalContract
    {
        if (! in_array($contract->status, ['active', 'notice_given'], true)) {
            throw new InvalidArgumentException('Contract must be active or in notice period to terminate.');
        }

        return DB::transaction(function () use ($contract, $data) {
            $contract->update(array_merge(['status' => 'terminated'], $data));

            if ($contract->isLeaseOut()) {
                $contract->rentalUnit->update(['status' => 'vacant']);
            }

            return $contract->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Rent escalation
    // -------------------------------------------------------------------------

    /**
     * Raise the rent by closing the current condition and opening a new one, so
     * the rent history stays intact.
     */
    public function applyEscalation(ContractCondition $condition, float $newRate): ContractCondition
    {
        return DB::transaction(function () use ($condition, $newRate) {
            $condition->update([
                'valid_to'  => now()->toDateString(),
                'is_active' => false,
            ]);

            return ContractCondition::create([
                'contract_id'          => $condition->contract_id,
                'condition_type'       => $condition->condition_type,
                'description'          => $condition->description,
                'amount'               => $newRate,
                'basis'                => $condition->basis,
                'valid_from'           => now()->addDay()->toDateString(),
                'escalation_type'      => $condition->escalation_type,
                'escalation_rate'      => $condition->escalation_rate,
                'escalation_index'     => $condition->escalation_index,
                'escalation_frequency' => $condition->escalation_frequency,
                'next_escalation_date' => $this->computeNextEscalationDate(
                    now()->addDay(),
                    $condition->escalation_frequency
                ),
                'is_taxable' => $condition->is_taxable,
                'is_active'  => true,
            ]);
        });
    }

    /** Conditions due for escalation today or already overdue. */
    public function getDueEscalations(int $organizationId): Collection
    {
        return ContractCondition::whereHas('contract', fn ($q) => $q->where('organization_id', $organizationId)->where('status', 'active'))
            ->where('is_active', true)
            ->whereNotNull('next_escalation_date')
            ->whereNotIn('escalation_type', ['none', ''])
            ->where('next_escalation_date', '<=', now()->toDateString())
            ->with('contract.rentalUnit')
            ->limit(200)
            ->get();
    }

    public function getUpcomingEscalations(int $organizationId, int $withinDays = 30): Collection
    {
        return ContractCondition::whereHas('contract', fn ($q) => $q->where('organization_id', $organizationId)->where('status', 'active'))
            ->where('is_active', true)
            ->whereNotNull('next_escalation_date')
            ->where('next_escalation_date', '<=', now()->addDays($withinDays)->toDateString())
            ->with('contract.rentalUnit')
            ->orderBy('next_escalation_date')
            ->limit(200)
            ->get();
    }

    private function computeNextEscalationDate(Carbon $from, ?string $frequency): ?string
    {
        return match ($frequency) {
            'annual'    => $from->addYear()->toDateString(),
            'biennial'  => $from->addYears(2)->toDateString(),
            'quarterly' => $from->addMonths(3)->toDateString(),
            default     => null,
        };
    }

    // -------------------------------------------------------------------------
    // Contract options
    // -------------------------------------------------------------------------

    /**
     * Exercise a renewal or break option.
     *
     * Renewal extends the end date and, when a new rent was agreed, escalates
     * the base rent condition. Break moves the contract into its notice period.
     */
    public function exerciseOption(ContractOption $option): ContractOption
    {
        if (! $option->isExercisable()) {
            throw new InvalidArgumentException('This option cannot be exercised (expired, already exercised, or outside exercise window).');
        }

        return DB::transaction(function () use ($option) {
            $option->update([
                'status'       => 'exercised',
                'exercised_at' => now()->toDateString(),
            ]);

            $contract = $option->contract;

            if ($option->option_type === 'renewal') {
                $newEndDate = $contract->end_date
                    ? Carbon::parse($contract->end_date)->addMonths($option->new_term_months ?? 12)->toDateString()
                    : null;

                $contract->update(['end_date' => $newEndDate]);

                if ($option->new_rent_amount !== null) {
                    $baseRentCondition = $contract->conditions()
                        ->where('condition_type', 'base_rent')
                        ->where('is_active', true)
                        ->first();

                    if ($baseRentCondition) {
                        $this->applyEscalation($baseRentCondition, (float) $option->new_rent_amount);
                    }
                }
            }

            if ($option->option_type === 'break') {
                $contract->update(['status' => 'notice_given', 'notice_date' => now()->toDateString()]);
            }

            return $option->fresh();
        });
    }

    public function getExpiringContracts(int $organizationId, int $withinDays = 90): Collection
    {
        return RentalContract::where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays($withinDays)->toDateString())
            ->with(['rentalUnit', 'options'])
            ->orderBy('end_date')
            ->limit(200)
            ->get();
    }
}
