<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\CapaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CapaController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly CapaService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->list($request->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'capa_number'       => ['required', 'string', 'max:50', Rule::unique('capa_records', 'capa_number')->where('organization_id', $request->user()->organization_id)],
            'capa_type'         => 'required|in:corrective,preventive',
            'problem_statement' => 'required|string',
            'root_cause'        => 'nullable|string',
            'priority'          => 'required|in:critical,high,medium,low',
            'owner_id'          => ['nullable', 'integer', $this->ownedBy('users')],
            'target_close_date' => 'nullable|date',
            'source_type'       => 'nullable|string',
            'source_id'         => 'nullable|integer',
        ]);

        return $this->created($this->service->create($request->user()->organization_id, $data));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $capa = $this->service->find($request->user()->organization_id, $id, ['owner', 'actions', 'effectivenessReviews']);

        return $this->success($capa);
    }

    public function addAction(Request $request, int $capaId): JsonResponse
    {
        $data = $request->validate([
            'action_number'  => 'required|string|max:20',
            'description'    => 'required|string',
            'assigned_to_id' => ['nullable', 'integer', $this->ownedBy('users')],
            'due_date'       => 'required|date',
        ]);

        $capa = $this->service->find($request->user()->organization_id, $capaId);

        return $this->created($this->service->addAction($capa, $data));
    }

    public function completeAction(Request $request, int $capaId, int $actionId): JsonResponse
    {
        $capa = $this->service->find($request->user()->organization_id, $capaId);

        $data = $request->validate(['completion_notes' => 'nullable|string']);

        $action = $this->service->completeAction($capa, $actionId, $data['completion_notes'] ?? null);

        return $this->success($action, 'Action completed');
    }

    public function addEffectivenessReview(Request $request, int $capaId): JsonResponse
    {
        $data = $request->validate([
            'review_date'   => 'required|date',
            'effectiveness' => 'required|in:effective,partially_effective,not_effective',
            'evidence'      => 'nullable|string',
            'conclusions'   => 'nullable|string',
        ]);

        $capa = $this->service->find($request->user()->organization_id, $capaId);

        return $this->created($this->service->addEffectivenessReview($capa, $data, $request->user()->id));
    }
}
