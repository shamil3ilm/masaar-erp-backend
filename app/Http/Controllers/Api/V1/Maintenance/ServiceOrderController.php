<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceServiceOrder;
use App\Services\Maintenance\MaintenanceServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceOrderController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly MaintenanceServiceOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginate($request->user()->organization_id, [
            'status' => $request->filled('status') ? $request->input('status') : null,
            'vendor_id' => $request->filled('vendor_id') ? $request->integer('vendor_id') : null,
        ], $request->integer('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['nullable', 'integer', $this->ownedBy('equipment')],
            'maintenance_order_id' => ['nullable', 'integer', $this->ownedBy('maintenance_orders')],
            'vendor_id' => ['nullable', 'integer', $this->ownedBy('contacts')],
            'service_type' => 'required|in:repair,inspection,installation,calibration,overhaul',
            'description' => 'required|string',
            'requested_date' => 'required|date',
            'due_date' => 'required|date',
            'estimated_cost' => 'nullable|numeric|min:0',
            'sla_response_hours' => 'nullable|string',
            'sla_resolution_hours' => 'nullable|string',
        ]);

        return $this->created($this->service->create(
            $request->user()->organization_id,
            $request->user()->id,
            $validated
        ));
    }

    public function show(MaintenanceServiceOrder $serviceOrder): JsonResponse
    {
        return $this->success($serviceOrder->load('equipment', 'maintenanceOrder'));
    }

    public function update(Request $request, MaintenanceServiceOrder $serviceOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:draft,issued,confirmed,in_progress,completed,cancelled',
            'actual_cost' => 'sometimes|numeric|min:0',
            'completed_date' => 'sometimes|nullable|date',
            'vendor_notes' => 'sometimes|nullable|string',
        ]);

        return $this->success($this->service->update($serviceOrder, $validated));
    }

    public function destroy(MaintenanceServiceOrder $serviceOrder): JsonResponse
    {
        $this->service->delete($serviceOrder);

        return $this->success(null, 'Service order deleted');
    }
}
