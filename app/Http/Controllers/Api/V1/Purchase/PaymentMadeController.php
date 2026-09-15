<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\PaymentMadeResource;
use App\Models\Purchase\PaymentMade;
use App\Services\Purchase\PaymentMadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMadeController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private PaymentMadeService $paymentMadeService
    ) {
    }

    /**
     * List payments made with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentMadeService->list(
            $request->only(['status', 'supplier_id', 'payment_method', 'start_date', 'end_date', 'search']),
            $this->safeSortBy($request->sort_by, ['payment_number', 'payment_date', 'amount', 'status', 'created_at', 'updated_at'], 'payment_date'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($payments, PaymentMadeResource::class);
    }

    /**
     * Store a new payment made.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', $this->ownedBy('contacts')],
            'payment_number' => 'nullable|string|max:50',
            'payment_date' => 'nullable|date',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'bank_account_id' => ['nullable', $this->ownedBy('bank_accounts')],
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card,online,other',
            'amount' => 'required|numeric|min:0.01',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'allocations' => 'nullable|array',
            'allocations.*.bill_id' => ['required', $this->ownedBy('bills')],
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $payment = $this->paymentMadeService->create(
                collect($validated)->except('allocations')->toArray(),
                $validated['allocations'] ?? []
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created(new PaymentMadeResource($payment), 'Payment created successfully.');
    }

    /**
     * Show a specific payment made.
     */
    public function show(PaymentMade $paymentMade): JsonResponse
    {
        return $this->success(new PaymentMadeResource(
            $paymentMade->load(['supplier', 'bankAccount', 'allocations.bill', 'journalEntry.lines'])
        ));
    }

    /**
     * Delete a pending payment.
     */
    public function destroy(PaymentMade $paymentMade): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->paymentMadeService->delete($paymentMade),
            'Payment deleted successfully.',
        );
    }

    /**
     * Complete/confirm a payment.
     */
    public function complete(PaymentMade $paymentMade): JsonResponse
    {
        try {
            $payment = $this->paymentMadeService->complete($paymentMade, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PaymentMadeResource($payment), 'Payment completed successfully.');
    }

    /**
     * Void a payment.
     */
    public function void(Request $request, PaymentMade $paymentMade): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $payment = $this->paymentMadeService->void($paymentMade, $validated['reason'] ?? '');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success(new PaymentMadeResource($payment), 'Payment voided successfully.');
    }

    /**
     * Allocate payment to bills.
     */
    public function allocate(Request $request, PaymentMade $paymentMade): JsonResponse
    {
        $validated = $request->validate([
            'bill_id' => ['nullable', $this->ownedBy('bills')],
            'amount' => 'nullable|numeric|min:0.01',
            'allocations' => 'nullable|array',
            'allocations.*.bill_id' => ['required', $this->ownedBy('bills')],
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        // Both formats are accepted: an allocations array, or a flat bill_id and amount.
        $allocations = $validated['allocations'] ?? [];

        if (empty($allocations)) {
            if (empty($validated['bill_id']) || empty($validated['amount'])) {
                return $this->error('Either bill_id and amount, or allocations array is required.', 'VALIDATION_ERROR', 422);
            }

            $allocations = [['bill_id' => $validated['bill_id'], 'amount' => $validated['amount']]];
        }

        return $this->tryAction(
            fn () => new PaymentMadeResource($this->paymentMadeService->allocateMany($paymentMade, $allocations)),
            'Payment allocated successfully.',
        );
    }

    /**
     * Get supplier statement.
     */
    public function supplierStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', $this->ownedBy('contacts')],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $statement = $this->paymentMadeService->getSupplierStatement(
            (int) $validated['supplier_id'],
            isset($validated['start_date']) ? new \DateTime($validated['start_date']) : null,
            isset($validated['end_date']) ? new \DateTime($validated['end_date']) : null
        );

        return $this->success($statement);
    }

    /**
     * Get payments summary/stats.
     */
    public function summary(Request $request): JsonResponse
    {
        return $this->success(
            $this->paymentMadeService->summary($request->supplier_id ? (int) $request->supplier_id : null)
        );
    }
}
