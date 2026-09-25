<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\HR\EmployeeService;
use App\Services\HR\GosiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GosiController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private GosiService $gosiService,
        private EmployeeService $employeeService,
    ) {}

    /**
     * List GOSI contributions for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $contributions = $this->gosiService->listContributions(
            auth()->user()->organization_id,
            [
                'employee_id' => $request->employee_id,
                'year'        => $request->year,
                'month'       => $request->month,
                'status'      => $request->status,
            ],
            $request->integer('per_page', 20)
        );

        return $this->paginated($contributions);
    }

    /**
     * Calculate GOSI contributions for an employee for a given period.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees')],
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $employee = $this->employeeService->find((int) $validated['employee_id']);

        return $this->tryAction(
            fn() => $this->gosiService->calculateContributions($employee, (int) $validated['year'], (int) $validated['month'])->load('employee'),
            'GOSI contribution calculated successfully.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Submit all draft contributions for an organization for a period.
     */
    public function submitPeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $this->gosiService->submitPeriodForOrganization(
            auth()->user()->organization_id,
            (int) $validated['year'],
            (int) $validated['month']
        );

        return $this->success(null, 'GOSI contributions submitted for the period.');
    }
}
