<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Manufacturing\BomTemplateResource;
use App\Models\Manufacturing\BomTemplate;
use App\Services\Manufacturing\BomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BomController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private BomService $bomService
    ) {
    }

    /**
     * List BOM templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $boms = $this->bomService->paginate(
            $request->only(['status', 'product_id', 'effective', 'search']),
            $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at', 'status'], 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($boms, BomTemplateResource::class);
    }

    /**
     * Store a new BOM template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bom_number' => 'nullable|string|max:50',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'product_id' => ['required', $this->ownedBy('products')],
            'variant_id' => ['nullable', $this->ownedVariant()],
            'output_quantity' => 'required|numeric|min:0.0001',
            'output_unit_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'default_warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_labor_cost' => 'nullable|numeric|min:0',
            'overhead_cost' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string',
            ...$this->lineAndOperationRules(required: true),
        ]);

        try {
            $bom = $this->bomService->create(
                collect($validated)->except(['lines', 'operations'])->toArray(),
                $validated['lines'],
                $validated['operations'] ?? [],
                auth()->id()
            );
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new BomTemplateResource($bom), 'BOM template created successfully.');
    }

    /**
     * Show a specific BOM template.
     */
    public function show(BomTemplate $bom): JsonResponse
    {
        return $this->success(new BomTemplateResource(
            $bom->load(['product', 'variant', 'outputUnit', 'defaultWarehouse', 'lines.product', 'lines.unit', 'operations', 'createdBy'])
        ));
    }

    /**
     * Update a BOM template.
     */
    public function update(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'output_quantity' => 'sometimes|numeric|min:0.0001',
            'output_unit_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'default_warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'estimated_hours' => 'nullable|numeric|min:0',
            'estimated_labor_cost' => 'nullable|numeric|min:0',
            'overhead_cost' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'notes' => 'nullable|string',
            ...$this->lineAndOperationRules(required: false),
        ]);

        return $this->tryAction(
            fn() => new BomTemplateResource($this->bomService->update(
                $bom,
                collect($validated)->except(['lines', 'operations'])->toArray(),
                $validated['lines'] ?? null,
                $validated['operations'] ?? null
            )),
            'BOM template updated successfully.'
        );
    }

    /**
     * Delete a draft BOM template.
     */
    public function destroy(BomTemplate $bom): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->bomService->delete($bom),
            'BOM template deleted successfully.'
        );
    }

    /**
     * Activate or deactivate a BOM template.
     * PATCH /bom-templates/{bom}/active  {"active": true|false}
     */
    public function setActive(Request $request, BomTemplate $bom): JsonResponse
    {
        $activate = $request->boolean('active');

        return $this->tryAction(
            fn() => new BomTemplateResource(
                $activate ? $this->bomService->activate($bom) : $this->bomService->deactivate($bom)
            ),
            $activate ? 'BOM template activated successfully.' : 'BOM template deactivated successfully.',
        );
    }

    /**
     * Duplicate a BOM template.
     */
    public function duplicate(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:200',
            'product_id' => ['nullable', $this->ownedBy('products')],
        ]);

        try {
            $newBom = $this->bomService->duplicate($bom, $validated, auth()->id());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new BomTemplateResource($newBom), 'BOM template duplicated successfully.');
    }

    /**
     * Get cost breakdown for a BOM template.
     */
    public function costBreakdown(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'nullable|numeric|min:0.0001',
        ]);

        $quantity = (float) ($validated['quantity'] ?? $bom->output_quantity);
        $breakdown = $this->bomService->getCostBreakdown($bom, $quantity);

        return $this->success($breakdown);
    }

    /**
     * Check material availability for production.
     */
    public function checkAvailability(Request $request, BomTemplate $bom): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
        ]);

        $availability = $this->bomService->checkAvailability(
            $bom,
            (float) $validated['quantity'],
            $validated['warehouse_id'] ?? null
        );

        return $this->success($availability);
    }

    /**
     * Get BOMs for a specific product.
     */
    public function forProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', $this->ownedBy('products')],
            'active_only' => 'nullable|boolean',
        ]);

        $boms = $this->bomService->getForProduct(
            (int) $validated['product_id'],
            $validated['active_only'] ?? true
        );

        return $this->success(BomTemplateResource::collection($boms));
    }

    /**
     * Rules for a BOM's lines and operations; lines are required on create.
     *
     * @return array<string, mixed>
     */
    private function lineAndOperationRules(bool $required): array
    {
        return [
            'lines' => ($required ? 'required' : 'sometimes').'|array|min:1',
            'lines.*.product_id' => ['required', $this->ownedBy('products')],
            'lines.*.variant_id' => ['nullable', $this->ownedVariant()],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'lines.*.wastage_percentage' => 'nullable|numeric|min:0|max:100',
            'lines.*.is_critical' => 'nullable|boolean',
            'lines.*.warehouse_id' => ['nullable', $this->ownedBy('warehouses')],
            'operations' => ($required ? 'nullable' : 'sometimes').'|array',
            'operations.*.name' => 'required|string|max:100',
            'operations.*.description' => 'nullable|string',
            'operations.*.instructions' => 'nullable|string',
            'operations.*.estimated_minutes' => 'nullable|integer|min:0',
            'operations.*.labor_cost_per_hour' => 'nullable|numeric|min:0',
            'operations.*.workstation' => 'nullable|string|max:100',
            'operations.*.required_skills' => 'nullable|array',
            'operations.*.is_subcontracted' => 'nullable|boolean',
        ];
    }
}
