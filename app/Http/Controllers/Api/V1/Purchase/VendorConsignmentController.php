<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Api\V1\Purchase\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\VendorConsignmentSettlementResource;
use App\Http\Resources\Purchase\VendorConsignmentStockResource;
use App\Services\Purchase\VendorConsignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorConsignmentController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private VendorConsignmentService $service,
    ) {}

    /**
     * List all vendor consignment stock records for the organization.
     */
    public function stockIndex(Request $request): JsonResponse
    {
        $stocks = $this->service->listStocks(
            $request->only(['vendor_id', 'product_id', 'warehouse_id', 'active_only']),
            $request->integer('per_page', 25),
        );

        return $this->paginated($stocks, VendorConsignmentStockResource::class);
    }

    /**
     * Show a single consignment stock record with its receipts and withdrawals.
     */
    public function stockShow(int $id): JsonResponse
    {
        return $this->success(new VendorConsignmentStockResource($this->service->findStock($id)));
    }

    /**
     * Record a vendor consignment receipt.
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'             => ['required', $this->ownedBy('contacts')],
            'product_id'            => ['required', $this->ownedBy('products')],
            'warehouse_id'          => ['required', $this->ownedBy('warehouses')],
            // Locations carry no organization column; one in the caller's warehouse is the caller's.
            'warehouse_location_id' => [
                'nullable',
                Rule::exists('warehouse_locations', 'id')->where('warehouse_id', $request->input('warehouse_id')),
            ],
            'purchase_order_id'     => ['nullable', $this->ownedBy('purchase_orders')],
            'receipt_date'          => ['required', 'date'],
            'quantity_received'     => ['required', 'numeric', 'gt:0'],
            'vendor_price'          => ['required', 'numeric', 'min:0'],
            'currency_code'         => ['required', 'string', 'size:3'],
            'unit_id'               => ['nullable', $this->ownedBy('units_of_measure')],
            'vendor_delivery_note'  => ['nullable', 'string', 'max:100'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->created($this->service->receiveConsignmentStock($validated));
    }

    /**
     * Record a withdrawal of vendor consignment stock.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_consignment_stock_id' => ['required', $this->ownedBy('vendor_consignment_stocks')],
            'withdrawal_date'             => ['required', 'date'],
            'quantity_withdrawn'          => ['required', 'numeric', 'gt:0'],
            'withdrawal_type'             => [
                'required',
                Rule::in(['production', 'sales', 'transfer', 'scrapping']),
            ],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id'   => ['nullable', 'integer'],
            'unit_id'        => ['nullable', $this->ownedBy('units_of_measure')],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->created($this->service->withdrawConsignmentStock($validated));
    }

    /**
     * List consignment settlements with optional filters.
     */
    public function settlements(Request $request): JsonResponse
    {
        $settlements = $this->service->listSettlements(
            $request->only(['vendor_id', 'status', 'from', 'to']),
            $request->integer('per_page', 25),
        );

        return $this->paginated($settlements, VendorConsignmentSettlementResource::class);
    }

    /**
     * Create a new consignment settlement for a vendor and period.
     */
    public function createSettlement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'   => ['required', $this->ownedBy('contacts')],
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
        ]);

        $settlement = $this->service->createSettlement(
            (int) $validated['vendor_id'],
            $validated['period_from'],
            $validated['period_to']
        );

        return $this->created(new VendorConsignmentSettlementResource($settlement));
    }

    /**
     * Submit a draft settlement and create a vendor bill.
     */
    public function submitSettlement(int $id): JsonResponse
    {
        $settlement = $this->service->submitSettlement($this->service->findSettlement($id));

        return $this->success(new VendorConsignmentSettlementResource($settlement));
    }
}
