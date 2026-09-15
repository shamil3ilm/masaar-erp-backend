<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\PhysicalInventoryDocumentResource;
use App\Models\Inventory\PhysicalInventoryDocument;
use App\Services\Inventory\PhysicalInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhysicalInventoryController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private PhysicalInventoryService $service
    ) {}

    /**
     * List physical inventory documents.
     */
    public function index(Request $request): JsonResponse
    {
        $documents = $this->service->list(
            $request->only(['status', 'warehouse_id', 'inventory_type', 'start_date', 'end_date']),
            $request->integer('per_page', 15)
        );

        return $this->paginated($documents, PhysicalInventoryDocumentResource::class);
    }

    /**
     * Create a new physical inventory document (auto-populates lines from stock levels).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', $this->ownedBy('warehouses')],
            'count_date' => 'required|date',
            'inventory_type' => 'nullable|in:full,cycle,spot',
            'assigned_to' => ['nullable', $this->ownedBy('users')],
            'document_number' => 'nullable|string|max:30',
        ]);

        try {
            $document = $this->service->createDocument($validated);
        } catch (\Exception $e) {
            report($e);
            return $this->error('Failed to create physical inventory document.', 'SERVER_ERROR', 500);
        }

        return $this->created(
            new PhysicalInventoryDocumentResource($document),
            'Physical inventory document created successfully.'
        );
    }

    /**
     * Show a specific physical inventory document.
     */
    public function show(PhysicalInventoryDocument $physicalInventoryDocument): JsonResponse
    {
        return $this->success(
            new PhysicalInventoryDocumentResource(
                $physicalInventoryDocument->load([
                    'lines.product',
                    'lines.variant',
                    'lines.warehouseLocation',
                    'warehouse',
                    'assignee',
                    'poster',
                ])
            )
        );
    }

    /**
     * Update header fields of a physical inventory document (only editable documents).
     */
    public function update(Request $request, PhysicalInventoryDocument $physicalInventoryDocument): JsonResponse
    {
        if (!$physicalInventoryDocument->isEditable()) {
            return $this->error('Document cannot be edited in its current status.', 'VALIDATION_ERROR', 422);
        }

        $validated = $request->validate([
            'count_date' => 'sometimes|date',
            'inventory_type' => 'nullable|in:full,cycle,spot',
            'assigned_to' => ['nullable', $this->ownedBy('users')],
        ]);

        try {
            $document = $this->service->updateHeader($physicalInventoryDocument, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(
            new PhysicalInventoryDocumentResource($document),
            'Physical inventory document updated successfully.'
        );
    }

    /**
     * Cancel a physical inventory document.
     */
    public function destroy(PhysicalInventoryDocument $physicalInventoryDocument): JsonResponse
    {
        try {
            $this->service->cancel($physicalInventoryDocument);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(null, 'Physical inventory document cancelled.');
    }

    /**
     * Enter counted quantities for inventory lines.
     */
    public function enterCounts(Request $request, PhysicalInventoryDocument $physicalInventoryDocument): JsonResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.line_id' => 'required|integer',
            'lines.*.counted_quantity' => 'required|numeric|min:0',
        ]);

        try {
            $document = $this->service->enterCounts($physicalInventoryDocument, $validated['lines']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('Failed to save counts.', 'SERVER_ERROR', 500);
        }

        return $this->success(
            new PhysicalInventoryDocumentResource($document),
            'Counts saved successfully.'
        );
    }

    /**
     * Post adjustments and finalise the physical inventory document.
     */
    public function postAdjustments(PhysicalInventoryDocument $physicalInventoryDocument): JsonResponse
    {
        try {
            $document = $this->service->postAdjustments($physicalInventoryDocument);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('Failed to post adjustments.', 'SERVER_ERROR', 500);
        }

        return $this->success(
            new PhysicalInventoryDocumentResource($document),
            'Physical inventory adjustments posted successfully.'
        );
    }
}
