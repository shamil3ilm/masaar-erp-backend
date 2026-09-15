<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Inventory\CrossDockingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CrossDockingController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly CrossDockingService $crossDockingService,
    ) {}

    /**
     * GET /inventory/cross-docking
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->crossDockingService->paginateOrders(
            $request->user()->organization_id,
            $request->only(['warehouse_id', 'status']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($orders);
    }

    /**
     * POST /inventory/cross-docking
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id'          => ['required', 'integer', $this->ownedBy('warehouses')],
            'inbound_source_type'   => 'required|in:purchase_order,transfer_order,return',
            'inbound_source_id'     => 'required|integer',
            'outbound_dest_type'    => 'required|in:sales_order,transfer_order,delivery',
            'outbound_dest_id'      => 'required|integer',
            'planned_date'          => 'required|date',
            'dock_door_id'          => ['nullable', 'integer', $this->ownedBy('dock_doors')],
            'notes'                 => 'nullable|string',
            'lines'                 => 'required|array|min:1',
            'lines.*.product_id'    => ['required', 'integer', $this->ownedBy('products')],
            'lines.*.quantity'      => 'required|numeric|min:0.0001',
            'lines.*.unit_id'       => ['nullable', 'integer', $this->ownedBy('units_of_measure')],
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['created_by']      = $request->user()->id;

        $order = $this->crossDockingService->createCrossDockingOrder($validated);

        return $this->created($order, 'Cross-docking order created.');
    }

    /**
     * GET /inventory/cross-docking/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->crossDockingService->findOrderOrFail(
            $request->user()->organization_id,
            $id,
            ['lines.product', 'warehouse', 'creator']
        );

        return $this->success($order);
    }

    /**
     * PUT /inventory/cross-docking/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $order = $this->crossDockingService->findOrderOrFail($request->user()->organization_id, $id);

        $validated = $request->validate([
            'planned_date'  => 'sometimes|date',
            'dock_door_id'  => ['nullable', 'integer', $this->ownedBy('dock_doors')],
            'notes'         => 'nullable|string',
        ]);

        return $this->success($this->crossDockingService->updateOrder($order, $validated), 'Cross-docking order updated.');
    }

    /**
     * DELETE /inventory/cross-docking/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $order = $this->crossDockingService->findOrderOrFail($request->user()->organization_id, $id);

        try {
            $this->crossDockingService->deleteOrder($order);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success(null, 'Cross-docking order deleted.');
    }

    /**
     * POST /inventory/cross-docking/{id}/start
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $order = $this->crossDockingService->findOrderOrFail($request->user()->organization_id, $id);

        try {
            $this->crossDockingService->startTransfer($order);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success($order->fresh(), 'Cross-docking transfer started.');
    }

    /**
     * POST /inventory/cross-docking/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $order = $this->crossDockingService->findOrderOrFail($request->user()->organization_id, $id);

        try {
            $this->crossDockingService->complete($order);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success($order->fresh(), 'Cross-docking order completed.');
    }

    /**
     * POST /inventory/cross-docking/lines/{lineId}/transfer
     */
    public function transferLine(Request $request, int $lineId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        $line = $this->crossDockingService->findLineOrFail($request->user()->organization_id, $lineId);

        try {
            $this->crossDockingService->transferLine($line, (float) $validated['quantity']);
        } catch (RuntimeException $e) {
            return $this->success(null, $e->getMessage(), 422);
        }

        return $this->success($line->fresh(), 'Line transfer recorded.');
    }

    /**
     * GET /inventory/cross-docking/opportunities
     */
    public function opportunities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', $this->ownedBy('warehouses')],
        ]);

        $opportunities = $this->crossDockingService->identifyOpportunities(
            (int) $validated['warehouse_id']
        );

        return $this->success($opportunities);
    }
}
