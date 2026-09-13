<?php

declare(strict_types=1);

namespace App\Services\Core\Importers;

use App\Models\Core\ImportJob;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\HR\SalaryStructure;
use App\Services\Core\ImporterInterface;
use App\Services\HR\EmployeeService;
use Illuminate\Support\Carbon;

class EmployeeImporter implements ImporterInterface
{
    public function importRow(array $data, ImportJob $importJob, array $options = []): mixed
    {
        // Check for existing employee
        $existing = null;
        if ($options['update_existing'] ?? false) {
            $existing = Employee::where('organization_id', $importJob->organization_id)
                ->where(function ($query) use ($data) {
                    if (!empty($data['email'])) {
                        $query->orWhere('email', $data['email']);
                    }
                    if (!empty($data['employee_number'])) {
                        $query->orWhere('employee_number', $data['employee_number']);
                    }
                    // Not national_id: it is encrypted with a fresh IV on every
                    // write, so comparing it with = can never match.
                })
                ->first();
        }

        // Salary lives on a salary structure. A row with a salary and nowhere
        // to put it is refused before anything is written - the department
        // and designation below included - because ImportService records a
        // failed row but does not undo what the row already saved.
        $salary = null;
        if (! empty($data['basic_salary']) && $data['basic_salary'] > 0) {
            $salary = $this->salaryTarget($importJob);
        }

        // Resolve department
        $departmentId = null;
        if (!empty($data['department'])) {
            $department = Department::firstOrCreate(
                [
                    'organization_id' => $importJob->organization_id,
                    'name' => $data['department'],
                ],
                [
                    'code' => strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $data['department']), 0, 10)),
                    'is_active' => true,
                ]
            );
            $departmentId = $department->id;
        }

        // Resolve designation
        $designationId = null;
        if (!empty($data['designation'])) {
            $designation = Designation::firstOrCreate(
                [
                    'organization_id' => $importJob->organization_id,
                    'name' => $data['designation'],
                ],
                [
                    'is_active' => true,
                ]
            );
            $designationId = $designation->id;
        }

        // Generate employee number if not provided
        $employeeNumber = $data['employee_number'] ?? null;
        if (!$employeeNumber && !$existing) {
            $lastEmployee = Employee::where('organization_id', $importJob->organization_id)
                ->orderByDesc('id')
                ->first();
            $nextNum = $lastEmployee ? ((int) preg_replace('/\D/', '', $lastEmployee->employee_number)) + 1 : 1;
            $employeeNumber = 'EMP' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
        }

        $employeeData = [
            'organization_id' => $importJob->organization_id,
            'employee_number' => $employeeNumber,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            // The column is joining_date. A re-import that gives no date keeps
            // the one on record rather than resetting it to today.
            'joining_date' => $data['hire_date'] ?? ($existing ? null : now()->format('Y-m-d')),
            'bank_name' => $data['bank_name'] ?? null,
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'employment_status' => 'active',
            'is_active' => true,
        ];

        if ($existing) {
            $existing->update(array_filter($employeeData, fn ($v) => $v !== null));
            $employee = $existing;
        } else {
            $employee = Employee::create($employeeData);
        }

        // Encrypted, and kept out of mass assignment on purpose. The import is a
        // deliberate write of them, so it sets them by name.
        $sensitive = array_filter([
            'national_id' => $data['national_id'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_iban' => $data['iban'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($sensitive !== []) {
            $employee->forceFill($sensitive)->save();
        }

        if ($salary !== null) {
            app(EmployeeService::class)->assignSalary(
                $employee,
                $salary['structure'],
                [$salary['code'] => $data['basic_salary']],
                Carbon::parse($data['hire_date'] ?? now()),
                'Imported',
            );
        }

        return $employee;
    }

    /**
     * The organisation's default salary structure, and the code of its basic
     * component, for an imported basic salary.
     *
     * @return array{structure: SalaryStructure, code: string}
     */
    private function salaryTarget(ImportJob $importJob): array
    {
        $structure = SalaryStructure::where('organization_id', $importJob->organization_id)
            ->default()
            ->active()
            ->first();

        $basic = $structure?->basicComponent();

        if ($basic === null) {
            throw new \InvalidArgumentException(
                'A basic salary needs a default salary structure with a basic component, and this organisation has none.'
            );
        }

        return ['structure' => $structure, 'code' => $basic->code];
    }
}
