<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\VendorAdvanceClearingResource;
use App\Http\Resources\Purchase\VendorAdvanceRequestResource;
use App\Models\Purchase\VendorAdvanceRequest;
use App\Services\Purchase\BillService;
use App\Services\Purchase\VendorAdvanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAdvanceController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private VendorAdvanceService $vendorAdvanceService,
        private BillService $billService,
    ) {}

    /**
     * List vendor advance requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $requests = $this->vendorAdvanceService->list(
            $request->only(['status', 'contact_id', 'purchase_order_id', 'search']),
            $this->safeSortBy($request->sort_by, ['request_number', 'requested_amount', 'status', 'created_at'], 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($requests, VendorAdvanceRequestResource::class);
    }

    /**
     * Create a new vendor advance request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', $this->ownedBy('contacts')],
            'purchase_order_id' => ['nullable', $this->ownedBy('purchase_orders')],
            'request_number' => 'nullable|string|max:30',
            'requested_amount' => 'required|numeric|min:0.01',
            'currency_code' => 'required|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'purpose' => 'nullable|string',
            'notes' => 'nullable|string',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        try {
            $advanceRequest = $this->vendorAdvanceService->createRequest($validated);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created(new VendorAdvanceRequestResource($advanceRequest), 'Advance request created successfully.');
    }

    /**
     * Show a vendor advance request.
     */
    public function show(VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        return $this->success(
            new VendorAdvanceRequestResource(
                $vendorAdvance->load(['contact', 'purchaseOrder', 'requester', 'approver', 'payments.clearings.bill'])
            )
        );
    }

    /**
     * Approve a vendor advance request.
     */
    public function approve(VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        try {
            $advanceRequest = $this->vendorAdvanceService->approveRequest($vendorAdvance);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new VendorAdvanceRequestResource($advanceRequest), 'Advance request approved successfully.');
    }

    /**
     * Record an advance payment.
     */
    public function recordPayment(Request $request, VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'bank_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            $payment = $this->vendorAdvanceService->recordPayment($vendorAdvance, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created($payment->toArray(), 'Advance payment recorded successfully.');
    }

    /**
     * Clear an advance payment against a bill.
     */
    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'advance_payment_id' => 'required|exists:vendor_advance_payments,id',
            'bill_id' => ['required', $this->ownedBy('bills')],
            'amount' => 'required|numeric|min:0.01',
        ]);

        // Advance payments carry no organization column to scope the rule with;
        // another organization's payment is not found here instead.
        try {
            $payment = $this->vendorAdvanceService->findPayment((int) $validated['advance_payment_id']);
        } catch (ModelNotFoundException) {
            return $this->error('Advance payment not found.', 'NOT_FOUND', 404);
        }

        $bill = $this->billService->find((int) $validated['bill_id']);

        try {
            $clearing = $this->vendorAdvanceService->clearAgainstBill($payment, $bill, (float) $validated['amount']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred.', 'SERVER_ERROR', 500);
        }

        return $this->created(new VendorAdvanceClearingResource($clearing), 'Advance cleared against bill successfully.');
    }

    /**
     * List clearings for a vendor advance request.
     */
    public function indexClearings(VendorAdvanceRequest $vendorAdvance): JsonResponse
    {
        return $this->success(
            VendorAdvanceClearingResource::collection($this->vendorAdvanceService->clearingsFor($vendorAdvance))
        );
    }
}
