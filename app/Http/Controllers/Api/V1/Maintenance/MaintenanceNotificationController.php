<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Maintenance\MaintenanceNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceNotificationController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly MaintenanceNotificationService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list(
            $request->user()->organization_id,
            $request->only(['status', 'notification_type', 'priority', 'equipment_id', 'breakdown', 'per_page']),
        );

        return $this->paginated($result);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notification_type' => 'required|in:M1,M2,M3,S1,S4',
            'short_text' => 'required|string|max:200',
            'long_text' => 'nullable|string',
            'equipment_id' => ['nullable', 'integer', $this->ownedBy('equipment')],
            'functional_location_code' => 'nullable|string|max:50',
            'priority' => 'required|in:1_very_high,2_high,3_medium,4_low',
            'malfunction_start_date' => 'nullable|date',
            'malfunction_end_date' => 'nullable|date|after_or_equal:malfunction_start_date',
            'breakdown' => 'boolean',
            'production_stop' => 'boolean',
            'damage_code' => 'nullable|string|max:20',
            'cause_code' => 'nullable|string|max:20',
            'cause_text' => 'nullable|string',
            'responsible_id' => ['nullable', 'integer', $this->ownedBy('users')],
            'items' => 'nullable|array',
            'items.*.short_text' => 'required|string|max:200',
            'items.*.long_text' => 'nullable|string',
            'items.*.damage_code' => 'nullable|string|max:20',
            'items.*.cause_code' => 'nullable|string|max:20',
        ]);

        $notification = $this->service->create(
            $request->user()->organization_id,
            $data,
            $request->user()->id,
        );

        return $this->created($notification);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return $this->success($this->service->find($request->user()->organization_id, $uuid));
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'short_text' => 'sometimes|string|max:200',
            'long_text' => 'nullable|string',
            'priority' => 'sometimes|in:1_very_high,2_high,3_medium,4_low',
            'damage_code' => 'nullable|string|max:20',
            'cause_code' => 'nullable|string|max:20',
            'cause_text' => 'nullable|string',
            'task_text' => 'nullable|string',
            'responsible_id' => ['nullable', 'integer', $this->ownedBy('users')],
        ]);

        return $this->success($this->service->update($request->user()->organization_id, $uuid, $data));
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'completion_text' => 'nullable|string',
        ]);

        $notification = $this->service->complete(
            $request->user()->organization_id,
            $uuid,
            $data,
            $request->user()->id,
        );

        return $this->success($notification);
    }

    public function addTask(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'description' => 'required|string|max:200',
            'details' => 'nullable|string',
            'assigned_to' => ['nullable', 'integer', $this->ownedBy('users')],
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
        ]);

        return $this->created($this->service->addTask($request->user()->organization_id, $uuid, $data));
    }

    public function byEquipment(Request $request, int $equipmentId): JsonResponse
    {
        return $this->success($this->service->getByEquipment($request->user()->organization_id, $equipmentId));
    }
}
