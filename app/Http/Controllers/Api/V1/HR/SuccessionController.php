<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\KeyPosition;
use App\Models\HR\SuccessionCandidate;
use App\Models\HR\SuccessionPoolActivity;
use App\Services\HR\EmployeeService;
use App\Services\HR\SuccessionPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuccessionController extends Controller
{
    public function __construct(
        private SuccessionPlanningService $successionService,
        private EmployeeService $employeeService,
    ) {}

    /**
     * List key positions.
     */
    public function indexKeyPositions(Request $request): JsonResponse
    {
        return $this->paginated($this->successionService->listPositions(
            [
                'criticality' => $request->criticality,
                'department_id' => $request->department_id,
                'active_only' => $request->boolean('active_only', true),
            ],
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Create a key position.
     */
    public function storeKeyPosition(Request $request): JsonResponse
    {
        $organizationId = auth()->user()->organization_id;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('organization_id', $organizationId)],
            'criticality' => 'required|in:critical,high,medium',
            'current_holder_id' => ['nullable', Rule::exists('employees', 'id')->where('organization_id', $organizationId)],
            'target_fill_date' => 'nullable|date',
            'min_successors' => 'integer|min:1|max:10',
            'notes' => 'nullable|string|max:1000',
        ]);

        $position = $this->successionService->createPosition($validated, $organizationId, (int) auth()->id());

        return $this->created($position->load('department', 'currentHolder'), 'Key position created successfully.');
    }

    /**
     * Show a key position with candidates.
     */
    public function showKeyPosition(KeyPosition $keyPosition): JsonResponse
    {
        $position = $keyPosition->load([
            'department',
            'currentHolder',
            'activeCandidates.employee',
            'activeCandidates.activities',
        ]);

        return $this->success($position);
    }

    /**
     * Update a key position.
     */
    public function updateKeyPosition(Request $request, KeyPosition $keyPosition): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'criticality' => 'in:critical,high,medium',
            'current_holder_id' => ['nullable', Rule::exists('employees', 'id')->where('organization_id', $keyPosition->organization_id)],
            'target_fill_date' => 'nullable|date',
            'min_successors' => 'integer|min:1|max:10',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $keyPosition->update($validated);

        return $this->success($keyPosition->fresh(), 'Key position updated successfully.');
    }

    /**
     * List candidates for a position.
     */
    public function indexCandidates(Request $request, KeyPosition $keyPosition): JsonResponse
    {
        return $this->paginated($this->successionService->listCandidates(
            $keyPosition,
            [
                'readiness' => $request->readiness,
                'active_only' => $request->boolean('active_only', true),
            ],
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Nominate a candidate for a key position.
     */
    public function nominateCandidate(Request $request, KeyPosition $keyPosition): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('organization_id', $keyPosition->organization_id)],
            'readiness' => 'required|in:ready_now,one_two_years,three_five_years',
            'performance_rating' => 'nullable|integer|min:1|max:5',
            'potential_rating' => 'nullable|integer|min:1|max:5',
            'nomination_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $employee = $this->employeeService->find((int) $validated['employee_id']);

        try {
            $candidate = $this->successionService->nominateCandidate($keyPosition, $employee, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created($candidate->load('employee', 'keyPosition'), 'Candidate nominated successfully.');
    }

    /**
     * Update the readiness of a candidate.
     */
    public function updateReadiness(Request $request, SuccessionCandidate $candidate): JsonResponse
    {
        $validated = $request->validate([
            'readiness' => 'required|in:ready_now,one_two_years,three_five_years',
            'performance_rating' => 'nullable|integer|min:1|max:5',
            'potential_rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:1000',
        ]);

        return $this->tryAction(
            fn() => $this->successionService->updateReadiness($candidate, $validated['readiness'], $validated),
            'Candidate readiness updated.'
        );
    }

    /**
     * Deactivate a candidate.
     */
    public function deactivateCandidate(Request $request, SuccessionCandidate $candidate): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $candidate = $this->successionService->deactivateCandidate($candidate, $validated['reason'] ?? null);

        return $this->success($candidate, 'Candidate deactivated.');
    }

    /**
     * Add a development activity to a candidate.
     */
    public function addActivity(Request $request, SuccessionCandidate $candidate): JsonResponse
    {
        $validated = $request->validate([
            'activity_type' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'target_date' => 'nullable|date',
        ]);

        $activity = $this->successionService->addActivity($candidate, $validated);

        return $this->created($activity, 'Activity added successfully.');
    }

    /**
     * Update an activity status.
     */
    public function updateActivity(Request $request, SuccessionPoolActivity $activity): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:planned,in_progress,completed,cancelled',
            'completed_date' => 'nullable|date',
            'outcome' => 'nullable|string|max:2000',
        ]);

        $activity = $this->successionService->updateActivityStatus($activity, $validated['status'], $validated);

        return $this->success($activity, 'Activity updated.');
    }

    /**
     * Get the succession summary for the organization.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->successionService->getSuccessionSummary(auth()->user()->organization_id);

        return $this->success($summary);
    }
}
