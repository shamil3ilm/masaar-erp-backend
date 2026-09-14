<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Employee;
use App\Models\HR\EmployeeShiftAssignment;
use App\Models\HR\Shift;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    /**
     * Assign a shift to an employee, closing any currently open assignment first.
     */
    public function assignShift(Employee $employee, Shift $shift, array $data): EmployeeShiftAssignment
    {
        if ($shift->organization_id !== $employee->organization_id) {
            throw new \InvalidArgumentException('Shift and employee must belong to the same organization.');
        }

        $effectiveFrom = $data['effective_from'] ?? now()->toDateString();

        return DB::transaction(function () use ($employee, $shift, $effectiveFrom, $data) {
            $effectiveTo = $data['effective_to'] ?? null;

            // Lock and check for any overlapping assignment for this employee
            $overlap = EmployeeShiftAssignment::where('employee_id', $employee->id)
                ->where(function ($query) use ($effectiveFrom, $effectiveTo) {
                    $query->where(function ($q) use ($effectiveFrom, $effectiveTo) {
                        // Existing assignment starts within the new range
                        $q->where('effective_from', '>=', $effectiveFrom);
                        if ($effectiveTo !== null) {
                            $q->where('effective_from', '<=', $effectiveTo);
                        }
                    })->orWhere(function ($q) use ($effectiveFrom, $effectiveTo) {
                        // Existing open assignment that would overlap
                        $q->where('effective_from', '<=', $effectiveFrom)
                            ->where(function ($q2) use ($effectiveFrom) {
                                $q2->whereNull('effective_to')
                                    ->orWhere('effective_to', '>=', $effectiveFrom);
                            });
                    });
                })
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw new \RuntimeException('Employee already has a shift assignment overlapping this period.');
            }

            // Close any existing open assignment that overlaps
            EmployeeShiftAssignment::where('employee_id', $employee->id)
                ->whereNull('effective_to')
                ->where('effective_from', '<=', $effectiveFrom)
                ->update(['effective_to' => $effectiveFrom]);

            return EmployeeShiftAssignment::create([
                'organization_id' => $employee->organization_id,
                'employee_id' => $employee->id,
                'shift_id' => $shift->id,
                'effective_from' => $effectiveFrom,
                'effective_to' => $data['effective_to'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * The organization's shifts by name.
     */
    public function list(int $organizationId, bool $activeOnly, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Shift::where('organization_id', $organizationId)
            ->when($activeOnly, fn($q) => $q->active())
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * A shift of the given organization; any other id is a not-found.
     */
    public function findForOrganization(int $organizationId, int $id): Shift
    {
        return Shift::where('organization_id', $organizationId)->findOrFail($id);
    }

    /**
     * A shift of the current organization; the tenant scope turns another
     * organization's id into a not-found.
     */
    public function find(int $id): Shift
    {
        return Shift::findOrFail($id);
    }

    /**
     * Creates an active shift for the organization.
     */
    public function create(array $data, int $organizationId): Shift
    {
        return Shift::create(array_merge($this->withCodeColumn($data), [
            'organization_id' => $organizationId,
            'is_active' => true,
        ]));
    }

    public function update(Shift $shift, array $data): Shift
    {
        $shift->update($this->withCodeColumn($data));

        return $shift->fresh();
    }

    /**
     * Deletes a shift that no employee is currently assigned to.
     *
     * @throws \InvalidArgumentException when an employee is assigned to it
     */
    public function delete(Shift $shift): void
    {
        if ($shift->assignments()->current()->exists()) {
            throw new \InvalidArgumentException('Cannot delete a shift that is currently assigned to employees.');
        }

        $shift->delete();
    }

    /**
     * The API calls the shift's code shift_code; the column is code. Shift
     * guards only its id, so an unmapped shift_code would reach the insert.
     */
    private function withCodeColumn(array $data): array
    {
        if (array_key_exists('shift_code', $data)) {
            $data['code'] = $data['shift_code'];
            unset($data['shift_code']);
        }

        return $data;
    }

    /**
     * Retrieve the currently active shift for an employee (null if none).
     */
    public function getActiveShift(Employee $employee): ?Shift
    {
        $assignment = EmployeeShiftAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('organization_id', $employee->organization_id)
            ->current()
            ->latest('effective_from')
            ->first();

        return $assignment?->shift;
    }
}
