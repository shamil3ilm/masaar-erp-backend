<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\AuditManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditManagementController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly AuditManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->list($request->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_number'      => 'required|string|max:50|unique:audit_plans',
            'title'            => 'required|string|max:255',
            'audit_type'       => 'required|in:internal,supplier,customer,regulatory,certification',
            'planned_start'    => 'required|date',
            'planned_end'      => 'required|date|after_or_equal:planned_start',
            'lead_auditor_id'  => ['nullable', 'integer', $this->ownedBy('users')],
            'scope'            => 'nullable|string',
            'objectives'       => 'nullable|string',
        ]);

        return $this->created($this->service->create($request->user()->organization_id, $data));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = $this->service->find($request->user()->organization_id, $id, ['leadAuditor', 'checklists', 'findings']);

        return $this->success($plan);
    }

    public function addChecklist(Request $request, int $planId): JsonResponse
    {
        $data = $request->validate([
            'item_number' => 'required|string|max:20',
            'question'    => 'required|string',
        ]);

        $plan = $this->service->find($request->user()->organization_id, $planId);

        return $this->created($this->service->addChecklistItem($plan, $data));
    }

    public function updateChecklist(Request $request, int $planId, int $checklistId): JsonResponse
    {
        $plan = $this->service->find($request->user()->organization_id, $planId);
        $item = $this->service->findChecklistItem($plan, $checklistId);

        $data = $request->validate([
            'response' => 'required|in:yes,no,partial,na',
            'remarks'  => 'nullable|string',
        ]);

        return $this->success($this->service->answerChecklistItem($item, $data), 'Checklist item updated');
    }

    public function addFinding(Request $request, int $planId): JsonResponse
    {
        $data = $request->validate([
            'finding_number'          => 'required|string|max:30',
            'finding_type'            => 'required|in:major_nc,minor_nc,observation,positive',
            'description'             => 'required|string',
            'requirement_reference'   => 'nullable|string',
            'evidence'                => 'nullable|string',
            'due_date'                => 'nullable|date',
        ]);

        $plan = $this->service->find($request->user()->organization_id, $planId);

        return $this->created($this->service->addFinding($plan, $data));
    }

    public function closeFinding(Request $request, int $planId, int $findingId): JsonResponse
    {
        $plan = $this->service->find($request->user()->organization_id, $planId);

        return $this->success($this->service->closeFinding($plan, $findingId), 'Finding closed');
    }

    public function createReport(Request $request, int $planId): JsonResponse
    {
        $data = $request->validate([
            'report_date'       => 'required|date',
            'executive_summary' => 'nullable|string',
            'conclusions'       => 'nullable|string',
            'overall_rating'    => 'nullable|in:satisfactory,needs_improvement,unsatisfactory',
        ]);

        $plan = $this->service->find($request->user()->organization_id, $planId);

        return $this->created($this->service->createReport($plan, $data));
    }
}
