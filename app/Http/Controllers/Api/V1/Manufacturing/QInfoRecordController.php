<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\QInfoRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QInfoRecordController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly QInfoRecordService $service) {}

    public function index(Request $request): JsonResponse
    {
        $records = $this->service->list($request->user()->organization_id, $request->query());
        return $this->success($records, 'Q-Info records retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id'                => ['nullable', 'integer', $this->ownedBy('contacts')],
            'product_id'               => ['required', 'integer', $this->ownedBy('products')],
            'inspection_type'          => 'required|in:goods_receipt,in_process,final,delivery,returns',
            'skip_lot_plan_id'         => ['nullable', 'integer', $this->ownedBy('skip_lot_sampling_plans')],
            'quality_plan_id'          => ['nullable', 'integer', $this->ownedBy('quality_plans')],
            'is_active'                => 'boolean',
            'release_required'         => 'boolean',
            'cert_required'            => 'boolean',
            'cert_type'                => 'nullable|string|max:50',
            'inspection_interval_days' => 'nullable|integer|min:1',
            'last_inspection_date'     => 'nullable|date',
            'next_inspection_date'     => 'nullable|date',
            'notes'                    => 'nullable|string',
        ]);

        $record = $this->service->create($request->user()->organization_id, $data);
        return $this->created($record, 'Q-Info record created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $record = $this->service->findForDisplay($request->user()->organization_id, $id);
        return $this->success($record, 'Q-Info record retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->service->find($request->user()->organization_id, $id);

        $data = $request->validate([
            'vendor_id'                => ['nullable', 'integer', $this->ownedBy('contacts')],
            'product_id'               => ['integer', $this->ownedBy('products')],
            'inspection_type'          => 'in:goods_receipt,in_process,final,delivery,returns',
            'skip_lot_plan_id'         => ['nullable', 'integer', $this->ownedBy('skip_lot_sampling_plans')],
            'quality_plan_id'          => ['nullable', 'integer', $this->ownedBy('quality_plans')],
            'is_active'                => 'boolean',
            'release_required'         => 'boolean',
            'cert_required'            => 'boolean',
            'cert_type'                => 'nullable|string|max:50',
            'inspection_interval_days' => 'nullable|integer|min:1',
            'last_inspection_date'     => 'nullable|date',
            'next_inspection_date'     => 'nullable|date',
            'notes'                    => 'nullable|string',
        ]);

        $updated = $this->service->update($record, $data);
        return $this->success($updated, 'Q-Info record updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->delete($this->service->find($request->user()->organization_id, $id));
        return $this->success(null, 'Q-Info record deleted.');
    }

    public function dueForInspection(Request $request): JsonResponse
    {
        $records = $this->service->getDueForInspection($request->user()->organization_id);
        return $this->success($records, 'Records due for inspection retrieved.');
    }
}
