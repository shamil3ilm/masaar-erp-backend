<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\PurchaseOrderResource;
use App\Http\Resources\Purchase\RfqQuoteResource;
use App\Http\Resources\Purchase\RfqResource;
use App\Models\Purchase\RfqHeader;
use App\Services\Purchase\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RfqController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private RfqService $rfqService
    ) {}

    /**
     * List RFQs with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $rfqs = $this->rfqService->list(
            $request->only(['status', 'search', 'start_date', 'end_date']),
            $this->safeSortBy($request->sort_by, ['rfq_number', 'title', 'status', 'submission_deadline', 'created_at'], 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($rfqs, RfqResource::class);
    }

    /**
     * Create a new RFQ.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'rfq_number' => 'nullable|string|max:30',
            'submission_deadline' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'delivery_address' => 'nullable|string',
            'currency_code' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['nullable', $this->ownedBy('products')],
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'items.*.notes' => 'nullable|string',
            'items.*.sort_order' => 'nullable|integer',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        try {
            $rfq = $this->rfqService->createRfq($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new RfqResource($rfq), 'RFQ created successfully.');
    }

    /**
     * Show an RFQ with full details.
     */
    public function show(RfqHeader $rfq): JsonResponse
    {
        return $this->success(
            new RfqResource($rfq->load(['items.product', 'vendors.contact', 'quotes.lines', 'creator']))
        );
    }

    /**
     * Update a draft RFQ.
     */
    public function update(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'submission_deadline' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'delivery_address' => 'nullable|string',
            'currency_code' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
        ]);

        return $this->tryAction(
            fn () => new RfqResource($this->rfqService->update($rfq, $validated)),
            'RFQ updated successfully.'
        );
    }

    /**
     * Send RFQ to vendors.
     */
    public function sendToVendors(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'vendor_ids' => 'required|array|min:1',
            'vendor_ids.*' => ['required', $this->ownedBy('contacts')],
        ]);

        return $this->tryAction(
            fn () => new RfqResource($this->rfqService->sendToVendors($rfq, $validated['vendor_ids'])),
            'RFQ sent to vendors successfully.'
        );
    }

    /**
     * Record a vendor quote against an RFQ.
     */
    public function recordQuote(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'rfq_vendor_id' => 'required|integer',
            'quote_number' => 'nullable|string|max:100',
            'quote_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'currency_code' => 'required|string|size:3',
            'total_amount' => 'nullable|numeric|min:0',
            'delivery_days' => 'nullable|integer|min:0',
            'payment_terms' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            // Items carry no organization column; an item of this RFQ is one of the caller's.
            'lines.*.rfq_item_id' => ['required', Rule::exists('rfq_items', 'id')->where('rfq_id', $rfq->id)],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
            'lines.*.line_total' => 'required|numeric|min:0',
            'lines.*.delivery_days' => 'nullable|integer|min:0',
            'lines.*.notes' => 'nullable|string',
        ]);

        try {
            $quote = $this->rfqService->recordQuote($rfq, (int) $validated['rfq_vendor_id'], $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new RfqQuoteResource($quote), 'Quote recorded successfully.');
    }

    /**
     * Get vendor quote comparison matrix.
     */
    public function compareQuotes(RfqHeader $rfq): JsonResponse
    {
        return $this->success($this->rfqService->compareQuotes($rfq));
    }

    /**
     * Award an RFQ to a vendor quote.
     */
    public function awardQuote(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'quote_id' => 'required|integer',
        ]);

        return $this->tryAction(
            fn () => new RfqQuoteResource($this->rfqService->awardQuote($rfq, (int) $validated['quote_id'])),
            'Quote awarded successfully.'
        );
    }

    /**
     * Convert an awarded quote to a Purchase Order.
     */
    public function convertToPo(Request $request, RfqHeader $rfq): JsonResponse
    {
        $validated = $request->validate([
            'quote_id' => 'required|integer',
        ]);

        try {
            $purchaseOrder = $this->rfqService->convertToPurchaseOrder($rfq, (int) $validated['quote_id']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created(new PurchaseOrderResource($purchaseOrder), 'Purchase order created from RFQ successfully.');
    }
}
