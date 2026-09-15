<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\GoodsIssueResource;
use App\Models\Inventory\GoodsIssue;
use App\Services\Inventory\GoodsIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoodsIssueController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private GoodsIssueService $goodsIssueService
    ) {}

    /**
     * List Goods Issues for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        $goodsIssues = $this->goodsIssueService->list(
            $request->only(['warehouse_id', 'status', 'movement_type', 'from_date', 'to_date']),
            $request->integer('per_page', 15)
        );

        return $this->paginated($goodsIssues, GoodsIssueResource::class);
    }

    /**
     * Create a new Goods Issue in draft status.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gi_date'          => 'required|date',
            'movement_type'    => ['required', Rule::in($this->movementTypes())],
            'warehouse_id'     => ['required', 'integer', $this->ownedBy('warehouses')],
            'branch_id'        => ['nullable', 'integer', $this->ownedBy('branches')],
            'reference_type'   => 'nullable|string|max:100',
            'reference_id'     => 'nullable|integer',
            'notes'            => 'nullable|string|max:2000',
            'lines'            => 'required|array|min:1',
            'lines.*.product_id'    => ['required', 'integer', $this->ownedBy('products')],
            'lines.*.quantity'      => 'required|numeric|min:0.0001',
            ...$this->lineReferenceRules(),
        ]);

        try {
            $gi = $this->goodsIssueService->create($validated, auth()->id());

            return $this->created(
                new GoodsIssueResource($gi),
                'Goods Issue created successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single Goods Issue.
     */
    public function show(GoodsIssue $goodsIssue): JsonResponse
    {
        $goodsIssue->load([
            'lines.product',
            'lines.variant',
            'lines.unit',
            'lines.warehouse',
            'lines.location',
            'warehouse',
            'branch',
            'journalEntry',
            'creator',
            'postedBy',
            'reversedBy',
        ]);

        return $this->success(new GoodsIssueResource($goodsIssue));
    }

    /**
     * Update a draft Goods Issue.
     */
    public function update(Request $request, GoodsIssue $goodsIssue): JsonResponse
    {
        if (!$goodsIssue->isDraft()) {
            return $this->error('Only draft Goods Issues can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'gi_date'        => 'sometimes|date',
            'movement_type'  => ['sometimes', Rule::in($this->movementTypes())],
            'warehouse_id'   => ['sometimes', 'integer', $this->ownedBy('warehouses')],
            'branch_id'      => ['nullable', 'integer', $this->ownedBy('branches')],
            'reference_type' => 'nullable|string|max:100',
            'reference_id'   => 'nullable|integer',
            'notes'          => 'nullable|string|max:2000',
            'lines'          => 'nullable|array|min:1',
            'lines.*.product_id'    => ['required_with:lines', 'integer', $this->ownedBy('products')],
            'lines.*.quantity'      => 'required_with:lines|numeric|min:0.0001',
            ...$this->lineReferenceRules(),
        ]);

        try {
            $gi = $this->goodsIssueService->update(
                $goodsIssue,
                collect($validated)->except('lines')->toArray(),
                $validated['lines'] ?? null
            );

            return $this->success(
                new GoodsIssueResource($gi),
                'Goods Issue updated successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Post a draft Goods Issue (deducts stock and creates GL journal entry).
     */
    public function post(GoodsIssue $goodsIssue): JsonResponse
    {
        if (!$goodsIssue->canBePosted()) {
            return $this->error(
                'Goods Issue cannot be posted. Ensure it is in draft status and has at least one line.',
                'INVALID_STATUS',
                422
            );
        }

        try {
            $gi = $this->goodsIssueService->post($goodsIssue, auth()->id());

            return $this->success(
                new GoodsIssueResource($gi),
                'Goods Issue posted successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'POSTING_FAILED', 422);
        }
    }

    /**
     * Reverse a posted Goods Issue (restores stock and reverses GL entry).
     */
    public function reverse(Request $request, GoodsIssue $goodsIssue): JsonResponse
    {
        if (!$goodsIssue->canBeReversed()) {
            return $this->error(
                'Only posted Goods Issues can be reversed.',
                'INVALID_STATUS',
                422
            );
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $gi = $this->goodsIssueService->reverse(
                $goodsIssue,
                $validated['reason'],
                auth()->id()
            );

            return $this->success(
                new GoodsIssueResource($gi),
                'Goods Issue reversed successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * @return list<string>
     */
    private function movementTypes(): array
    {
        return [
            GoodsIssue::MOVEMENT_SALES_DELIVERY,
            GoodsIssue::MOVEMENT_PRODUCTION_ISSUE,
            GoodsIssue::MOVEMENT_SCRAPPING,
            GoodsIssue::MOVEMENT_TRANSFER,
            GoodsIssue::MOVEMENT_OTHER,
        ];
    }

    /**
     * Rules for the optional rows a line points to; each must belong to the
     * caller's organization.
     *
     * @return array<string, mixed>
     */
    private function lineReferenceRules(): array
    {
        return [
            'lines.*.variant_id'    => ['nullable', 'integer', $this->ownedVariant()],
            'lines.*.warehouse_id'  => ['nullable', 'integer', $this->ownedBy('warehouses')],
            'lines.*.location_id'   => ['nullable', 'integer', $this->ownedLocation()],
            'lines.*.batch_id'      => ['nullable', 'integer', $this->ownedBy('inventory_batches')],
            'lines.*.unit_id'       => ['nullable', 'integer', $this->ownedBy('units_of_measure')],
            'lines.*.unit_cost'     => 'nullable|numeric|min:0',
            'lines.*.serial_number' => 'nullable|string|max:100',
            'lines.*.notes'         => 'nullable|string|max:255',
        ];
    }
}
