<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Inventory\WaveManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaveController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private WaveManagementService $waveService
    ) {}

    // -------------------------------------------------------------------------
    // Putaway Rules
    // -------------------------------------------------------------------------

    /**
     * List putaway rules for the authenticated organisation.
     */
    public function putawayIndex(Request $request): JsonResponse
    {
        $filters = ['active_only' => $request->boolean('active_only')];

        if ($request->has('warehouse_id')) {
            $filters['warehouse_id'] = $request->integer('warehouse_id');
        }

        return $this->paginated($this->waveService->paginatePutawayRules($filters, $request->integer('per_page', 25)));
    }

    /**
     * Create a putaway rule.
     */
    public function putawayStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
            ...$this->putawayReferenceRules(),
            'warehouse_zone' => 'nullable|string|max:100',
            'priority' => 'sometimes|integer|min:1|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $rule = $this->waveService->createPutawayRule($validated, $request->user()->id);

        return $this->created($rule->load(['warehouse', 'product', 'productCategory', 'preferredLocation']));
    }

    /**
     * Update a putaway rule.
     */
    public function putawayUpdate(Request $request, int $id): JsonResponse
    {
        $rule = $this->waveService->findPutawayRuleOrFail($id);

        $validated = $request->validate([
            'warehouse_id' => ['sometimes', 'integer', $this->ownedBy('warehouses')],
            ...$this->putawayReferenceRules(),
            'warehouse_zone' => 'nullable|string|max:100',
            'priority' => 'sometimes|integer|min:1|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success($this->waveService->updatePutawayRule($rule, $validated));
    }

    /**
     * Delete a putaway rule.
     */
    public function putawayDestroy(int $id): JsonResponse
    {
        $this->waveService->deletePutawayRule($this->waveService->findPutawayRuleOrFail($id));

        return $this->success([], 'Putaway rule deleted successfully.');
    }

    /**
     * Suggest a putaway location for a product.
     */
    public function putawaySuggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
            'product_id' => ['required', 'integer', $this->ownedBy('products')],
            'category_id' => ['required', 'integer', $this->ownedBy('categories')],
        ]);

        $location = $this->waveService->getPutawayLocation(
            $validated['warehouse_id'],
            $validated['product_id'],
            $validated['category_id'],
        );

        if ($location === null) {
            return $this->success(null, 'No matching putaway rule found.');
        }

        return $this->success($location);
    }

    // -------------------------------------------------------------------------
    // Wave Plans
    // -------------------------------------------------------------------------

    /**
     * List wave plans.
     */
    public function waveIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'wave_type', 'from_date', 'to_date']);

        if ($request->has('warehouse_id')) {
            $filters['warehouse_id'] = $request->integer('warehouse_id');
        }

        return $this->paginated($this->waveService->paginateWaves($filters, $request->integer('per_page', 20)));
    }

    /**
     * Create a wave plan.
     */
    public function waveStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
            'wave_number' => 'nullable|string|max:50',
            'wave_type' => 'sometimes|in:outbound,replenishment,returns',
            'planned_date' => 'required|date',
            'orders' => 'required|array|min:1',
            'orders.*.order_type' => 'required|in:sales_order,stock_transfer,purchase_return',
            'orders.*.order_id' => 'required|integer|min:1',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $wave = $this->waveService->createWavePlan(
            data: collect($validated)->except('orders')->all(),
            orderIds: $validated['orders'],
            userId: $request->user()->id,
        );

        return $this->created($wave->load(['warehouse', 'waveOrders']));
    }

    /**
     * Show a wave plan.
     */
    public function waveShow(int $id): JsonResponse
    {
        return $this->success(
            $this->waveService->findWaveOrFail($id, ['warehouse', 'waveOrders', 'pickingLists.lines', 'creator'])
        );
    }

    /**
     * Release a wave plan and generate picking lists.
     */
    public function waveRelease(Request $request, int $id): JsonResponse
    {
        $wave = $this->waveService->releaseWave($this->waveService->findWaveOrFail($id), $request->user()->id);

        return $this->success($wave->load(['pickingLists.lines']), 'Wave plan released successfully.');
    }

    /**
     * Complete a wave plan.
     */
    public function waveComplete(Request $request, int $id): JsonResponse
    {
        $wave = $this->waveService->findWaveOrFail($id);

        if ($wave->isCompleted()) {
            return $this->success($wave, 'Wave plan is already completed.');
        }

        return $this->success($this->waveService->completeWave($wave, $request->user()->id), 'Wave plan completed successfully.');
    }

    // -------------------------------------------------------------------------
    // Picking Lists
    // -------------------------------------------------------------------------

    /**
     * List picking lists.
     */
    public function pickingListIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);

        foreach (['warehouse_id', 'picker_id', 'wave_id'] as $id) {
            if ($request->has($id)) {
                $filters[$id] = $request->integer($id);
            }
        }

        return $this->paginated($this->waveService->paginatePickingLists($filters, $request->integer('per_page', 20)));
    }

    /**
     * Show a picking list with its lines.
     */
    public function pickingListShow(int $id): JsonResponse
    {
        return $this->success($this->waveService->findPickingListOrFail($id, [
            'wave',
            'warehouse',
            'picker',
            'lines' => fn ($q) => $q->orderBy('sort_order'),
            'lines.product',
            'lines.variant',
            'lines.fromLocation',
            'lines.toLocation',
        ]));
    }

    /**
     * Assign a picker to a picking list.
     */
    public function pickingListAssign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'picker_id' => ['required', 'integer', $this->ownedBy('users')],
        ]);

        $list = $this->waveService->assignPicker(
            $this->waveService->findPickingListOrFail($id),
            $validated['picker_id'],
            $request->user()->id
        );

        return $this->success($list->load('picker'), 'Picker assigned successfully.');
    }

    /**
     * Start a picking list.
     */
    public function pickingListStart(Request $request, int $id): JsonResponse
    {
        $list = $this->waveService->startPicking($this->waveService->findPickingListOrFail($id), $request->user()->id);

        return $this->success($list, 'Picking started.');
    }

    /**
     * Complete a picking list.
     */
    public function pickingListComplete(Request $request, int $id): JsonResponse
    {
        $list = $this->waveService->completePicking($this->waveService->findPickingListOrFail($id), $request->user()->id);

        return $this->success($list, 'Picking list completed.');
    }

    /**
     * Pick a line (record picked quantity).
     */
    public function pickLine(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string|max:500',
        ]);

        $line = $this->waveService->recordPick(
            $this->waveService->findPickingLineOrFail($id),
            (float) $validated['quantity'],
            $validated['notes'] ?? null,
            $request->user()->id
        );

        return $this->success(
            $line->load(['product', 'fromLocation', 'toLocation']),
            'Pick recorded successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------

    /**
     * Get wave & picking statistics for the organisation.
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $stats = $this->waveService->getWaveStats(
            $this->organizationId($request),
            $validated['from'],
            $validated['to'],
        );

        return $this->success($stats);
    }

    /**
     * Rules for the product, category and location a putaway rule points to;
     * each must belong to the caller's organization.
     *
     * @return array<string, mixed>
     */
    private function putawayReferenceRules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', $this->ownedBy('products')],
            'product_category_id' => ['nullable', 'integer', $this->ownedBy('categories')],
            'preferred_location_id' => ['nullable', 'integer', $this->ownedLocation()],
        ];
    }
}
