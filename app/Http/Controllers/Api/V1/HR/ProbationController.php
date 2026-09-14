<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\ProbationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProbationController extends Controller
{
    public function __construct(
        private readonly ProbationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->service->list($request->only([
            'status', 'employee_id', 'per_page',
        ]));

        return $this->paginated($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('organization_id', auth()->user()->organization_id),
            ],
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
            'review_date' => 'nullable|date',
        ]);

        $period = $this->service->create($validated);

        return $this->created($period->load('employee'), 'Probation period created.');
    }

    public function show(string $id): JsonResponse
    {
        return $this->success($this->service->findWithRelations($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $period = $this->service->find($id);

        $validated = $request->validate([
            'start_date'  => 'sometimes|date',
            'end_date'    => 'sometimes|date|after:start_date',
            'review_date' => 'nullable|date',
        ]);

        return $this->tryAction(
            fn() => $this->service->update($period, $validated)->load('employee'),
            'Probation period updated.',
            'INVALID_STATUS'
        );
    }

    public function extend(Request $request, string $id): JsonResponse
    {
        $period = $this->service->find($id);

        $validated = $request->validate([
            'new_end_date' => 'required|date|after:' . $period->end_date->toDateString(),
            'reason'       => 'nullable|string',
        ]);

        return $this->tryAction(
            fn() => $this->service->extend($period, $validated['new_end_date'], $validated['reason'] ?? null)->load('employee'),
            'Probation period extended.',
            'INVALID_STATUS'
        );
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $period = $this->service->find($id);

        $validated = $request->validate([
            'outcome'     => 'required|in:confirmed,extended,terminated',
            'reviewer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', auth()->user()->organization_id),
            ],
            'notes'       => 'required|string',
        ]);

        return $this->tryAction(
            fn() => $this->service->complete(
                $period,
                $validated['outcome'],
                (int) $validated['reviewer_id'],
                $validated['notes']
            )->load(['employee', 'reviewer']),
            'Probation period completed.',
            'INVALID_STATUS'
        );
    }

    public function waive(string $id): JsonResponse
    {
        $period = $this->service->find($id);

        return $this->tryAction(
            fn() => $this->service->waive($period),
            'Probation period waived.',
            'INVALID_STATUS'
        );
    }

    public function dueSoon(Request $request): JsonResponse
    {
        $orgId     = auth()->user()->organization_id;
        $daysAhead = $request->integer('days_ahead', 30);
        $periods   = $this->service->getDueSoon($orgId, $daysAhead);

        return $this->success($periods);
    }
}
