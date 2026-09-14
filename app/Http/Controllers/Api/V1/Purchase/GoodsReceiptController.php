<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Api\V1\Purchase\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\GoodsReceiptResource;
use App\Models\Purchase\GoodsReceipt;
use App\Services\Purchase\BillService;
use App\Services\Purchase\GoodsReceiptService;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoodsReceiptController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private GoodsReceiptService $goodsReceiptService,
        private PurchaseOrderService $purchaseOrderService,
        private BillService $billService,
    ) {}

    /**
     * List goods receipts with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $receipts = $this->goodsReceiptService->list(
            $request->only(['status', 'purchase_order_id', 'warehouse_id', 'start_date', 'end_date', 'search']),
            $this->safeSortBy($request->sort_by, ['gr_number', 'gr_date', 'status', 'created_at'], 'gr_date'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($receipts, GoodsReceiptResource::class);
    }

    /**
     * Create a new Goods Receipt against a Purchase Order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', $this->ownedBy('purchase_orders')],
            'gr_number' => 'nullable|string|max:30',
            'gr_date' => 'nullable|date',
            'warehouse_id' => ['required', $this->ownedBy('warehouses')],
            'contact_id' => ['nullable', $this->ownedBy('contacts')],
            'notes' => 'nullable|string',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'lines' => 'required|array|min:1',
            // Order lines and locations have no organization column: a line must
            // belong to the order received and a location to the receiving warehouse.
            'lines.*.po_line_id' => [
                'nullable',
                Rule::exists('purchase_order_lines', 'id')->where('purchase_order_id', (int) $request->input('purchase_order_id')),
            ],
            'lines.*.product_id' => ['required', $this->ownedBy('products')],
            'lines.*.variant_id' => ['nullable', $this->ownedBy('product_variants')],
            'lines.*.description' => 'nullable|string|max:500',
            'lines.*.quantity_ordered' => 'nullable|numeric|min:0',
            'lines.*.quantity_received' => 'required|numeric|min:0.0001',
            'lines.*.quantity_rejected' => 'nullable|numeric|min:0',
            'lines.*.unit_id' => ['required', $this->ownedBy('units_of_measure')],
            'lines.*.unit_cost' => 'required|numeric|min:0',
            'lines.*.location_id' => [
                'nullable',
                Rule::exists('warehouse_locations', 'id')->where('warehouse_id', (int) $request->input('warehouse_id')),
            ],
            'lines.*.batch_number' => 'nullable|string|max:100',
            'lines.*.expiry_date' => 'nullable|date',
        ]);

        $purchaseOrder = $this->purchaseOrderService->find((int) $validated['purchase_order_id']);

        try {
            $gr = $this->goodsReceiptService->createGr($purchaseOrder, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created(new GoodsReceiptResource($gr), 'Goods receipt created successfully.');
    }

    /**
     * Show a goods receipt with all details.
     */
    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        return $this->success(
            new GoodsReceiptResource(
                $goodsReceipt->load([
                    'purchaseOrder',
                    'vendor',
                    'warehouse',
                    'lines.product',
                    'lines.variant',
                    'lines.unit',
                    'lines.location',
                    'creator',
                    'journalEntry',
                ])
            )
        );
    }

    /**
     * Post a draft goods receipt: update stock and generate accounting entry.
     */
    public function post(GoodsReceipt $goodsReceipt): JsonResponse
    {
        try {
            $gr = $this->goodsReceiptService->postGr($goodsReceipt);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred while posting.', 'SERVER_ERROR', 500);
        }

        $message = $gr->isInInspection()
            ? 'Goods receipt is held in quality inspection. Post it again once the inspection is resolved.'
            : 'Goods receipt posted successfully.';

        return $this->success(new GoodsReceiptResource($gr), $message);
    }

    /**
     * Reverse a posted goods receipt.
     */
    public function reverse(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $gr = $this->goodsReceiptService->reverseGr($goodsReceipt, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred while reversing.', 'SERVER_ERROR', 500);
        }

        return $this->success(new GoodsReceiptResource($gr), 'Goods receipt reversed successfully.');
    }

    /**
     * Run and return 3-way match results for a bill.
     */
    public function threeWayMatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bill_id' => ['required', $this->ownedBy('bills')],
        ]);

        $bill = $this->billService->find((int) $validated['bill_id']);

        try {
            $result = $this->goodsReceiptService->runThreeWayMatch($bill);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred during matching.', 'SERVER_ERROR', 500);
        }

        return $this->success($result);
    }
}
