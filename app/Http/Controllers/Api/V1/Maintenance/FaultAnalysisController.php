<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Maintenance\MaintenanceRca;
use App\Services\Maintenance\FaultAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaultAnalysisController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly FaultAnalysisService $service) {}

    // -------------------------------------------------------------------------
    // Fault Codes
    // -------------------------------------------------------------------------

    public function indexFaultCodes(Request $request): JsonResponse
    {
        return $this->success($this->service->activeFaultCodes($request->user()->organization_id));
    }

    public function storeFaultCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'description' => 'required|string|max:255',
            'fault_type' => 'required|in:mechanical,electrical,hydraulic,software,operator,wear,other',
            'cause' => 'nullable|string',
            'recommended_action' => 'nullable|string',
        ]);

        return $this->created($this->service->createFaultCode($request->user()->organization_id, $validated));
    }

    // -------------------------------------------------------------------------
    // Root Cause Analysis
    // -------------------------------------------------------------------------

    public function indexRca(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginateAnalyses($request->user()->organization_id, [
            'status' => $request->filled('status') ? $request->input('status') : null,
            'equipment_id' => $request->filled('equipment_id') ? $request->integer('equipment_id') : null,
        ], $request->integer('per_page', 15)));
    }

    public function storeRca(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'maintenance_order_id' => ['required', 'integer', $this->ownedBy('maintenance_orders')],
            'equipment_id' => ['required', 'integer', $this->ownedBy('equipment')],
            'fault_code_id' => ['nullable', 'integer', $this->ownedBy('maintenance_fault_codes')],
            'rca_method' => 'required|in:5_why,fishbone,fault_tree,fmea,other',
            'whys' => 'nullable|array',
            'root_cause' => 'nullable|string',
            'corrective_actions' => 'nullable|string',
            'preventive_actions' => 'nullable|string',
            'assigned_to' => ['nullable', 'integer', $this->ownedBy('users')],
            'target_date' => 'nullable|date',
        ]);

        return $this->created($this->service->createAnalysis($request->user()->organization_id, $validated));
    }

    public function updateRca(Request $request, MaintenanceRca $rca): JsonResponse
    {
        $validated = $request->validate([
            'root_cause' => 'sometimes|string',
            'contributing_factors' => 'sometimes|string',
            'corrective_actions' => 'sometimes|string',
            'preventive_actions' => 'sometimes|string',
            'status' => 'sometimes|in:open,in_progress,closed',
            'closed_date' => 'sometimes|nullable|date',
        ]);

        return $this->success($this->service->updateAnalysis($rca, $validated));
    }
}
