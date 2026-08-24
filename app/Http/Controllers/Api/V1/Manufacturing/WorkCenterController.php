<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Manufacturing\WorkCenter;
use App\Services\Manufacturing\CapacityPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for work centers and their calendar exceptions.
 *
 * Capacity planning and reporting for these work centers lives in
 * CapacityController.
 */
class WorkCenterController extends Controller
{
    public function __construct(
        private readonly CapacityPlanningService $capacityService,
    ) {}

    /**
     * List work centers, filtered by search term, type, or active state.
     */
    public function index(Request $request): JsonResponse
    {
        $workCenters = WorkCenter::with(['exceptions', 'createdBy'])
            ->when($request->search, fn ($q, $search) => $q->where(
                fn ($inner) => $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
            ))
            ->when($request->type, fn ($q, $type) => $q->ofType($type))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->orderBy(
                $this->safeSortBy($request->sort_by, ['code', 'name', 'work_center_type', 'created_at'], 'name'),
                $this->safeSortOrder($request->sort_order, 'asc')
            )
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($workCenters, null);
    }

    /**
     * Create a work center.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(required: true));

        $validated['organization_id'] = auth()->user()->organization_id;

        $workCenter = $this->capacityService->createWorkCenter($validated, auth()->id());

        return $this->created($workCenter, 'Work center created successfully.');
    }

    /**
     * Show a work center with its calendar exceptions, load, and requirements.
     */
    public function show(WorkCenter $workCenter): JsonResponse
    {
        $workCenter->load(['exceptions', 'loads', 'capacityRequirements', 'createdBy']);

        return $this->success($workCenter);
    }

    /**
     * Update a work center.
     */
    public function update(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate($this->rules(required: false, ignoreId: $workCenter->id));

        $updated = $this->capacityService->updateWorkCenter($workCenter, $validated, auth()->id());

        return $this->success($updated, 'Work center updated successfully.');
    }

    /**
     * Soft-delete a work center.
     */
    public function destroy(WorkCenter $workCenter): JsonResponse
    {
        $workCenter->delete();

        return $this->success(null, 'Work center deleted successfully.');
    }

    /**
     * Add or replace a calendar exception (a day with non-standard hours).
     */
    public function storeException(Request $request, WorkCenter $workCenter): JsonResponse
    {
        $validated = $request->validate([
            'exception_date'  => 'required|date',
            'available_hours' => 'required|numeric|min:0|max:24',
            'reason'          => 'nullable|string|max:255',
        ]);

        $exception = $this->capacityService->setException(
            $workCenter,
            $validated['exception_date'],
            (float) $validated['available_hours'],
            $validated['reason'] ?? '',
            auth()->id()
        );

        return $this->created($exception, 'Exception saved successfully.');
    }

    /**
     * Validation rules shared by store and update.
     *
     * On update, fields use `sometimes` so omitted keys keep their current
     * value instead of being overwritten with null.
     *
     * @return array<string, mixed>
     */
    private function rules(bool $required, ?int $ignoreId = null): array
    {
        $presence = $required ? 'required' : 'sometimes';

        $uniqueCode = Rule::unique('work_centers')
            ->where('organization_id', auth()->user()->organization_id);

        if ($ignoreId !== null) {
            $uniqueCode->ignore($ignoreId);
        }

        return [
            'code'               => [$presence, 'string', 'max:50', $uniqueCode],
            'name'               => [$presence, 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'work_center_type'   => ['sometimes', 'in:machine,labor,assembly,inspection,other'],
            'capacity_per_day'   => ['sometimes', 'numeric', 'min:0', 'max:24'],
            'efficiency_percent' => ['sometimes', 'numeric', 'min:1', 'max:200'],
            'calendar_type'      => ['sometimes', 'in:5day,6day,7day'],
            'cost_per_hour'      => ['nullable', 'numeric', 'min:0'],
            'currency_code'      => ['sometimes', 'string', 'size:3'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }
}
