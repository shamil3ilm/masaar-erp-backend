<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Manufacturing\KanbanCard;
use App\Models\Manufacturing\KanbanControlCycle;
use App\Models\Manufacturing\KanbanSupplyArea;
use App\Services\Manufacturing\KanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KanbanController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private KanbanService $kanbanService
    ) {}

    // ── Supply Areas ──────────────────────────────────────────────────────────

    /**
     * GET kanban/supply-areas
     */
    public function indexSupplyAreas(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->kanbanService->paginateSupplyAreas($request->search, $request->integer('per_page', 20)),
            null
        );
    }

    /**
     * POST kanban/supply-areas
     */
    public function storeSupplyArea(Request $request): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $validated = $request->validate([
            'code'        => [
                'required', 'string', 'max:20',
                Rule::unique('kanban_supply_areas')->where('organization_id', $orgId),
            ],
            'name'        => 'required|string|max:100',
            'warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
            'location_id'  => ['nullable', 'integer', $this->ownedLocation()],
        ]);

        $area = $this->kanbanService->createSupplyArea(array_merge($validated, ['organization_id' => $orgId]));

        return $this->success($area, 'Supply area created.', 201);
    }

    /**
     * GET kanban/supply-areas/{kanbanSupplyArea}
     */
    public function showSupplyArea(KanbanSupplyArea $kanbanSupplyArea): JsonResponse
    {
        return $this->success($kanbanSupplyArea->load(['warehouse', 'location', 'controlCycles.product']));
    }

    /**
     * PUT kanban/supply-areas/{kanbanSupplyArea}
     */
    public function updateSupplyArea(Request $request, KanbanSupplyArea $kanbanSupplyArea): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $validated = $request->validate([
            'code'        => [
                'sometimes', 'string', 'max:20',
                Rule::unique('kanban_supply_areas')->where('organization_id', $orgId)->ignore($kanbanSupplyArea->id),
            ],
            'name'        => 'sometimes|string|max:100',
            'warehouse_id' => ['sometimes', 'integer', $this->ownedBy('warehouses')],
            'location_id'  => ['nullable', 'integer', $this->ownedLocation()],
        ]);

        return $this->success(
            $this->kanbanService->updateSupplyArea($kanbanSupplyArea, $validated),
            'Supply area updated.'
        );
    }

    /**
     * DELETE kanban/supply-areas/{kanbanSupplyArea}
     */
    public function destroySupplyArea(KanbanSupplyArea $kanbanSupplyArea): JsonResponse
    {
        $this->kanbanService->deleteSupplyArea($kanbanSupplyArea);

        return $this->success(null, 'Supply area deleted.');
    }

    // ── Control Cycles ────────────────────────────────────────────────────────

    /**
     * GET kanban/control-cycles
     */
    public function indexControlCycles(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->kanbanService->paginateControlCycles(
                [
                    'active_only' => $request->boolean('active_only', true),
                    'product_id' => $request->product_id,
                    'supply_area_id' => $request->supply_area_id,
                ],
                $request->integer('per_page', 20)
            ),
            null
        );
    }

    /**
     * POST kanban/control-cycles
     */
    public function storeControlCycle(Request $request): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $validated = $request->validate([
            'product_id'                   => ['required', 'integer', $this->ownedBy('products')],
            'supply_area_id'               => ['required', 'integer', $this->ownedBy('kanban_supply_areas')],
            'replenishment_strategy'       => 'required|in:production,purchase,stock_transfer',
            'number_of_cards'              => 'required|integer|min:1|max:100',
            'replenishment_quantity'       => 'required|numeric|min:0.0001',
            'safety_stock_quantity'        => 'nullable|numeric|min:0',
            'replenishment_lead_time_days' => 'nullable|integer|min:1',
            'source_vendor_id'             => ['nullable', 'integer', $this->ownedBy('contacts')],
            'source_warehouse_id'          => ['nullable', 'integer', $this->ownedBy('warehouses')],
            'is_active'                    => 'nullable|boolean',
        ]);

        $cycle = $this->kanbanService->createControlCycle(
            array_merge($validated, ['organization_id' => $orgId])
        );

        return $this->success(
            $cycle->load(['product', 'supplyArea', 'cards']),
            'Control cycle created.',
            201
        );
    }

    /**
     * GET kanban/control-cycles/{kanbanControlCycle}
     */
    public function showControlCycle(KanbanControlCycle $kanbanControlCycle): JsonResponse
    {
        return $this->success($kanbanControlCycle->load(['product', 'supplyArea', 'cards']));
    }

    /**
     * PUT kanban/control-cycles/{kanbanControlCycle}
     */
    public function updateControlCycle(Request $request, KanbanControlCycle $kanbanControlCycle): JsonResponse
    {
        $validated = $request->validate([
            'replenishment_strategy'       => 'sometimes|in:production,purchase,stock_transfer',
            'replenishment_quantity'       => 'sometimes|numeric|min:0.0001',
            'safety_stock_quantity'        => 'nullable|numeric|min:0',
            'replenishment_lead_time_days' => 'nullable|integer|min:1',
            'source_vendor_id'             => ['nullable', 'integer', $this->ownedBy('contacts')],
            'source_warehouse_id'          => ['nullable', 'integer', $this->ownedBy('warehouses')],
            'is_active'                    => 'nullable|boolean',
        ]);

        return $this->success(
            $this->kanbanService->updateControlCycle($kanbanControlCycle, $validated),
            'Control cycle updated.'
        );
    }

    /**
     * DELETE kanban/control-cycles/{kanbanControlCycle}
     */
    public function destroyControlCycle(KanbanControlCycle $kanbanControlCycle): JsonResponse
    {
        $this->kanbanService->deleteControlCycle($kanbanControlCycle);

        return $this->success(null, 'Control cycle deleted.');
    }

    // ── Cards ─────────────────────────────────────────────────────────────────

    /**
     * GET kanban/control-cycles/{kanbanControlCycle}/cards
     */
    public function cards(KanbanControlCycle $kanbanControlCycle): JsonResponse
    {
        return $this->success($kanbanControlCycle->cards()->orderBy('card_number')->get());
    }

    /**
     * POST kanban/cards/{kanbanCard}/empty
     */
    public function signalEmpty(KanbanCard $kanbanCard): JsonResponse
    {
        $card = $this->kanbanService->cardOfOrganization($kanbanCard);

        try {
            $this->kanbanService->signalEmpty($card);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->success($card->fresh(), 'Card signalled as empty; replenishment triggered.');
    }

    /**
     * POST kanban/cards/{kanbanCard}/full
     * Body: { "quantity": 100.0 }
     */
    public function signalFull(Request $request, KanbanCard $kanbanCard): JsonResponse
    {
        $card = $this->kanbanService->cardOfOrganization($kanbanCard);

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        try {
            $this->kanbanService->signalFull($card, (float) $validated['quantity']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->success($card->fresh(), 'Card signalled as full.');
    }

    // ── Board View ────────────────────────────────────────────────────────────

    /**
     * GET kanban/board
     */
    public function board(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        if ($orgId === null) {
            return $this->error('Organization context required.', 'NO_ORGANIZATION', 422);
        }

        $board = $this->kanbanService->getBoardView($orgId);

        return $this->success([
            'cycles' => $board,
            'count'  => count($board),
        ]);
    }
}
