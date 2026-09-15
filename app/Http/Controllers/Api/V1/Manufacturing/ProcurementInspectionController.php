<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\ProcurementInspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProcurementInspectionController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ProcurementInspectionService $inspectionService,
    ) {}

    // -------------------------------------------------------------------------
    // Configs
    // -------------------------------------------------------------------------

    public function configs(Request $request): JsonResponse
    {
        $filters = [
            ...$this->filledFilters($request, ['product_id', 'vendor_id']),
            'active_only' => $request->boolean('active_only'),
        ];

        $paginator = $this->inspectionService->listConfigs(
            $this->organizationId($request),
            $filters,
            $this->perPage($request),
        );

        return $this->paginated($paginator);
    }

    public function storeConfig(Request $request): JsonResponse
    {
        $validated = $request->validate($this->configRules());

        $config = $this->inspectionService->createConfig($this->organizationId($request), $validated);

        return $this->created($config);
    }

    public function updateConfig(Request $request, int $id): JsonResponse
    {
        $config = $this->inspectionService->findConfig($this->organizationId($request), $id);

        if ($config === null) {
            return $this->notFound('Procurement inspection config not found.');
        }

        $validated = $request->validate($this->configRules());

        return $this->success($this->inspectionService->updateConfig($config, $validated));
    }

    // -------------------------------------------------------------------------
    // Inspections
    // -------------------------------------------------------------------------

    public function inspections(Request $request): JsonResponse
    {
        $paginator = $this->inspectionService->listInspections(
            $this->organizationId($request),
            $this->filledFilters($request, ['status', 'vendor_id', 'product_id', 'purchase_order_id']),
            $this->perPage($request),
        );

        return $this->paginated($paginator);
    }

    public function showInspection(Request $request, int $id): JsonResponse
    {
        $inspection = $this->inspectionService->findInspectionForDisplay($this->organizationId($request), $id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        return $this->success($inspection);
    }

    public function createInspection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['nullable', 'integer', $this->ownedBy('purchase_orders')],
            'goods_receipt_id'  => ['nullable', 'integer', $this->ownedBy('goods_receipts')],
            'product_id'        => ['required', 'integer', $this->ownedBy('products')],
            'vendor_id'         => ['nullable', 'integer', $this->ownedBy('contacts')],
            'inspection_lot_id' => ['nullable', 'integer', $this->ownedBy('inspection_lots')],
            'quantity_received' => 'required|numeric|min:0.0001',
            'notes'             => 'nullable|string',
        ]);

        return $this->created($this->inspectionService->createInspection($validated));
    }

    public function recordResults(Request $request, int $id): JsonResponse
    {
        $inspection = $this->inspectionService->findInspection($this->organizationId($request), $id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        // Checked before validation so a finished inspection is reported as such;
        // the service checks again on the locked inspection.
        if (! $inspection->canRecordResults()) {
            return $this->invalidStatus('Results can only be recorded on pending or in-progress inspections.');
        }

        $validated = $request->validate([
            'quantity_accepted'     => 'required|numeric|min:0',
            'quantity_rejected'     => 'required|numeric|min:0',
            'inspected_by'          => ['nullable', 'integer', $this->ownedBy('users')],
            'characteristics'       => 'nullable|array',
            'characteristics.*.characteristic_name' => 'required_with:characteristics|string|max:100',
            'characteristics.*.specification_min'   => 'nullable|string|max:50',
            'characteristics.*.specification_max'   => 'nullable|string|max:50',
            'characteristics.*.actual_value'        => 'nullable|string|max:100',
            'characteristics.*.is_within_spec'      => 'nullable|boolean',
            'characteristics.*.defect_description'  => 'nullable|string',
        ]);

        try {
            $this->inspectionService->recordResults($inspection, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->invalidStatus($e->getMessage());
        }

        return $this->success($inspection->fresh(['results', 'product', $this->inspectionService->vendorReference()]));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $inspection = $this->inspectionService->findInspection($this->organizationId($request), $id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        try {
            $this->inspectionService->approveInspection($inspection);
        } catch (InvalidArgumentException $e) {
            return $this->invalidStatus($e->getMessage());
        }

        return $this->success($inspection->fresh());
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $inspection = $this->inspectionService->findInspection($this->organizationId($request), $id);

        if ($inspection === null) {
            return $this->notFound('Procurement inspection not found.');
        }

        // Checked before validation so a decided inspection is reported as such;
        // the service checks again on the locked inspection.
        if (! $inspection->isAwaitingDecision()) {
            return $this->invalidStatus('Only completed inspections can be rejected.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->inspectionService->rejectInspection($inspection, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->invalidStatus($e->getMessage());
        }

        return $this->success($inspection->fresh());
    }

    public function vendorQualityScore(Request $request, int $vendorId): JsonResponse
    {
        $score = $this->inspectionService->getVendorQualityScore($vendorId);

        return $this->success(['vendor_id' => $vendorId, ...$score]);
    }

    /**
     * @return array<string, mixed>
     */
    private function configRules(): array
    {
        return [
            'product_id'                     => ['nullable', 'integer', $this->ownedBy('products')],
            'vendor_id'                      => ['nullable', 'integer', $this->ownedBy('contacts')],
            'inspection_required'            => 'boolean',
            'sampling_percentage'            => 'numeric|min:0.01|max:100',
            'auto_approve_below_defect_rate' => 'nullable|numeric|min:0|max:100',
            'quality_plan_id'                => ['nullable', 'integer', $this->ownedBy('quality_plans')],
            'is_active'                      => 'boolean',
        ];
    }

    /**
     * The named query parameters that were given a value.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function filledFilters(Request $request, array $keys): array
    {
        return array_filter(
            $request->only($keys),
            fn (string $key): bool => $request->filled($key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->input('per_page', 20), 100);
    }

    private function invalidStatus(string $message): JsonResponse
    {
        return $this->error($message, 'INVALID_STATUS', 422);
    }
}
