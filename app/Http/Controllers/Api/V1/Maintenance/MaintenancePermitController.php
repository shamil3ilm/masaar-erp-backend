<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Maintenance\MaintenancePermitService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MaintenancePermitController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly MaintenancePermitService $service) {}

    public function index(Request $request): JsonResponse
    {
        $permits = $this->service->list($request->user()->organization_id, $request->query());

        return $this->success($permits, 'Maintenance permits retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'maintenance_order_id' => ['nullable', 'integer', $this->ownedBy('maintenance_orders')],
            'permit_number' => 'required|string|max:50',
            'permit_type' => 'required|in:hot_work,confined_space,electrical_isolation,height_work,chemical,general',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'location' => 'nullable|string|max:255',
            'work_description' => 'nullable|string',
            'hazards_identified' => 'nullable|string',
            'precautions_required' => 'nullable|string',
            'requested_by' => ['nullable', 'integer', $this->ownedBy('users')],
        ]);

        $permit = $this->service->create($request->user()->organization_id, $data);

        return $this->created($permit, 'Maintenance permit created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $permit = $this->service->findWithDetails($request->user()->organization_id, $id);

        return $this->success($permit, 'Permit retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $permit = $this->service->findOrFail($request->user()->organization_id, $id);

        $data = $request->validate([
            'permit_number' => 'string|max:50',
            'permit_type' => 'in:hot_work,confined_space,electrical_isolation,height_work,chemical,general',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'work_description' => 'nullable|string',
            'hazards_identified' => 'nullable|string',
            'precautions_required' => 'nullable|string',
        ]);

        return $this->attempt(fn () => $this->service->update($permit, $data), 'Permit updated.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $permit = $this->service->findOrFail($request->user()->organization_id, $id);

        return $this->attempt(fn () => $this->service->approve($permit, $request->user()->id), 'Permit approved.');
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $permit = $this->service->findOrFail($request->user()->organization_id, $id);

        return $this->attempt(fn () => $this->service->activate($permit), 'Permit activated.');
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $permit = $this->service->findOrFail($request->user()->organization_id, $id);

        return $this->attempt(fn () => $this->service->suspend($permit), 'Permit suspended.');
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $permit = $this->service->findOrFail($request->user()->organization_id, $id);

        return $this->attempt(fn () => $this->service->close($permit, $request->user()->id), 'Permit closed.');
    }

    public function addSafetyCheck(Request $request, int $id): JsonResponse
    {
        $permit = $this->service->findOrFail($request->user()->organization_id, $id);

        $data = $request->validate([
            'check_description' => 'required|string|max:255',
            'is_mandatory' => 'boolean',
            'remarks' => 'nullable|string',
            'sort_order' => 'integer|min:0',
        ]);

        return $this->created($this->service->addSafetyCheck($permit, $data), 'Safety check added.');
    }

    public function completeSafetyCheck(Request $request, int $id, int $checkId): JsonResponse
    {
        $permit = $this->service->findOrFail($request->user()->organization_id, $id);

        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $data = $request->has('remarks') ? ['remarks' => $request->input('remarks')] : [];

        return $this->attempt(
            fn () => $this->service->completeSafetyCheck($permit, $checkId, $data, $request->user()->id),
            'Safety check completed.'
        );
    }

    /**
     * Run a permit change and report a refused one as OPERATION_FAILED. A row
     * that is not found is a RuntimeException too, and still answers 404.
     */
    private function attempt(callable $change, string $message): JsonResponse
    {
        try {
            return $this->success($change(), $message);
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 'OPERATION_FAILED', 422);
        }
    }
}
