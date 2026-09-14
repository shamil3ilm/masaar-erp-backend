<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\OffCyclePayrollItem;
use App\Models\HR\OffCyclePayrollRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Off-cycle payroll runs: a draft collects items, then is processed once into
 * totals or cancelled.
 *
 * Every change re-reads the run under a row lock and checks its status there,
 * so two requests acting on the same run cannot both pass the draft check, and
 * an item cannot be added to a run that another request is processing.
 */
class OffCyclePayrollService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = OffCyclePayrollRun::query()
            ->with(['processor'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['run_type']), fn($q) => $q->where('run_type', $filters['run_type']))
            ->when(isset($filters['run_date_from']), fn($q) => $q->whereDate('run_date', '>=', $filters['run_date_from']))
            ->when(isset($filters['run_date_to']), fn($q) => $q->whereDate('run_date', '<=', $filters['run_date_to']))
            ->orderBy('run_date', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * A run of the current organization; the tenant scope turns another
     * organization's id into a not-found.
     */
    public function find(int|string $id): OffCyclePayrollRun
    {
        return OffCyclePayrollRun::findOrFail($id);
    }

    public function findWithItems(int|string $id): OffCyclePayrollRun
    {
        return OffCyclePayrollRun::with(['items.employee', 'processor'])->findOrFail($id);
    }

    public function create(array $data): OffCyclePayrollRun
    {
        return OffCyclePayrollRun::create([
            'run_type'  => $data['run_type'] ?? OffCyclePayrollRun::RUN_TYPE_BONUS,
            'run_name'  => $data['run_name'],
            'run_date'  => $data['run_date'],
            'notes'     => $data['notes'] ?? null,
            'status'    => OffCyclePayrollRun::STATUS_DRAFT,
        ]);
    }

    public function update(OffCyclePayrollRun $run, array $data): OffCyclePayrollRun
    {
        return $run->lockForTransition(function (OffCyclePayrollRun $run) use ($data): OffCyclePayrollRun {
            $this->ensureDraft($run, 'Only draft runs can be updated.');

            $run->update($data);

            return $run;
        });
    }

    public function delete(OffCyclePayrollRun $run): void
    {
        $run->lockForTransition(function (OffCyclePayrollRun $run): void {
            $this->ensureDraft($run, 'Only draft runs can be deleted.');

            $run->delete();
        });
    }

    public function addItem(OffCyclePayrollRun $run, array $data): OffCyclePayrollItem
    {
        return $run->lockForTransition(function (OffCyclePayrollRun $run) use ($data): OffCyclePayrollItem {
            $this->ensureDraft($run, 'Items can only be added to draft runs.');

            return OffCyclePayrollItem::create([
                'organization_id'          => $run->organization_id,
                'off_cycle_payroll_run_id' => $run->id,
                'employee_id'              => $data['employee_id'],
                'component_code'           => $data['component_code'],
                'component_name'           => $data['component_name'],
                'amount'                   => $data['amount'],
                'tax_amount'               => $data['tax_amount'] ?? 0,
                'net_amount'               => $data['net_amount'],
                'notes'                    => $data['notes'] ?? null,
            ]);
        });
    }

    public function removeItem(OffCyclePayrollRun $run, int $itemId): void
    {
        $run->lockForTransition(function (OffCyclePayrollRun $run) use ($itemId): void {
            $this->ensureDraft($run, 'Items can only be removed from draft runs.');

            OffCyclePayrollItem::where('id', $itemId)
                ->where('off_cycle_payroll_run_id', $run->id)
                ->delete();
        });
    }

    /**
     * Totals the run's items and completes it, recording who processed it.
     */
    public function process(OffCyclePayrollRun $run): OffCyclePayrollRun
    {
        return $run->lockForTransition(function (OffCyclePayrollRun $run): OffCyclePayrollRun {
            if (! $run->canProcess()) {
                throw new InvalidArgumentException('Only draft runs can be processed.');
            }

            $items = $run->items()->get();

            $run->update([
                'status'         => OffCyclePayrollRun::STATUS_COMPLETED,
                'total_gross'    => $items->sum('amount'),
                'total_net'      => $items->sum('net_amount'),
                'employee_count' => $items->pluck('employee_id')->unique()->count(),
                'processed_by'   => auth()->id(),
                'processed_at'   => now(),
            ]);

            return $run->fresh();
        });
    }

    public function cancel(OffCyclePayrollRun $run): OffCyclePayrollRun
    {
        return $run->lockForTransition(function (OffCyclePayrollRun $run): OffCyclePayrollRun {
            if (! $run->canCancel()) {
                throw new InvalidArgumentException('This run cannot be cancelled.');
            }

            $run->update(['status' => OffCyclePayrollRun::STATUS_CANCELLED]);

            return $run->fresh();
        });
    }

    private function ensureDraft(OffCyclePayrollRun $run, string $message): void
    {
        if (! $run->isDraft()) {
            throw new InvalidArgumentException($message);
        }
    }
}
