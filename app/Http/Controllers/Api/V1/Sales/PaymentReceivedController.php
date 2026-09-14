<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\PaymentReceivedResource;
use App\Models\Sales\PaymentReceived;
use App\Services\Sales\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentReceivedController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * List payments.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentService->list([
            'customer_id' => $request->customer_id,
            'status' => $request->status,
            'payment_method' => $request->payment_method,
            'from_date' => $request->input('from_date', $request->input('start_date')),
            'to_date' => $request->input('to_date', $request->input('end_date')),
        ], $request->integer('per_page', 15));

        return $this->paginated($payments, PaymentReceivedResource::class);
    }

    /**
     * Create a new payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('organization_id', auth()->user()->organization_id)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', auth()->user()->organization_id)],
            'payment_date' => 'required|date',
            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')->where('organization_id', auth()->user()->organization_id)],
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card,online,other',
            'amount' => 'required|numeric|gt:0',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'allocations' => 'nullable|array',
            'allocations.*.invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')->where('organization_id', auth()->user()->organization_id)],
            'allocations.*.amount' => 'required|numeric|gt:0',
        ]);

        try {
            $this->paymentService->assertAllocationsWithinDue($validated['allocations'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        $payment = $this->paymentService->create(
            collect($validated)->except('allocations')->toArray(),
            $validated['allocations'] ?? []
        );

        return $this->created(new PaymentReceivedResource($payment), 'Payment created successfully.');
    }

    /**
     * Show a payment.
     */
    public function show(PaymentReceived $paymentReceived): JsonResponse
    {
        $paymentReceived->load([
            'customer',
            'bankAccount',
            'allocations.invoice',
            'journalEntry.lines',
        ]);

        return $this->success(new PaymentReceivedResource($paymentReceived));
    }

    /**
     * Complete a payment.
     */
    public function complete(PaymentReceived $paymentReceived): JsonResponse
    {
        return $this->tryAction(
            fn() => new PaymentReceivedResource($this->paymentService->complete($paymentReceived)),
            'Payment completed successfully.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Void a payment.
     */
    public function void(Request $request, PaymentReceived $paymentReceived): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        return $this->tryAction(
            fn() => new PaymentReceivedResource($this->paymentService->void($paymentReceived, $request->input('reason', ''))),
            'Payment voided successfully.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Record a bounced cheque.
     */
    public function bounce(Request $request, PaymentReceived $paymentReceived): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        return $this->tryAction(
            fn() => new PaymentReceivedResource($this->paymentService->recordBounce($paymentReceived, $request->input('reason', ''))),
            'Cheque bounce recorded.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * Allocate payment to invoices.
     */
    public function allocate(Request $request, PaymentReceived $paymentReceived): JsonResponse
    {
        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')->where('organization_id', auth()->user()->organization_id)],
            'allocations.*.amount' => 'required|numeric|gt:0',
        ]);

        return $this->tryAction(
            fn () => $this->paymentService->allocateMany($paymentReceived, $validated['allocations']),
            'Payment allocated successfully.',
            'VALIDATION_ERROR'
        );
    }

    /**
     * List open (unpaid / partially paid) invoices for a customer.
     */
    public function openItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('contacts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
        ]);

        return $this->success($this->paymentService->openInvoicesFor((int) $validated['customer_id']));
    }

    /**
     * Clear open items: apply unallocated payments to selected invoices.
     */
    public function clearOpenItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('contacts', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'clearing_date' => ['nullable', 'date'],
        ]);

        $result = $this->paymentService->clearOpenItemsFor(
            (int) $validated['customer_id'],
            $validated['invoice_ids'],
            $validated['clearing_date'] ?? now()->toDateString()
        );

        return $this->success($result, 'Open items cleared successfully.');
    }

    /**
     * Delete a pending payment.
     */
    public function destroy(PaymentReceived $paymentReceived): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->paymentService->delete($paymentReceived),
            'Payment deleted successfully.'
        );
    }

    /**
     * Get payment summary.
     *
     * A date bound applies whenever either of its keys is sent, even with an empty value.
     */
    public function summary(Request $request): JsonResponse
    {
        $range = [];

        if ($request->has('from_date') || $request->has('start_date')) {
            $range['from_date'] = $request->input('from_date', $request->input('start_date'));
        }

        if ($request->has('to_date') || $request->has('end_date')) {
            $range['to_date'] = $request->input('to_date', $request->input('end_date'));
        }

        return $this->success($this->paymentService->summary($range));
    }
}
