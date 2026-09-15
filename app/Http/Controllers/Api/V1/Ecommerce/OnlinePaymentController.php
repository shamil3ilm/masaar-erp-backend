<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Ecommerce\OnlinePayment;
use App\Services\Ecommerce\OnlinePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlinePaymentController extends Controller
{
    public function __construct(
        private OnlinePaymentService $paymentService
    ) {}

    /**
     * List online payments.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'from_date', 'to_date']);

        if ($request->has('gateway_id')) {
            $filters['gateway_id'] = $request->integer('gateway_id');
        }

        return $this->paginated($this->paymentService->paginate($filters, $request->integer('per_page', 15)));
    }

    /**
     * Show a payment.
     */
    public function show(OnlinePayment $onlinePayment): JsonResponse
    {
        return $this->success($this->paymentService->present($onlinePayment));
    }

    /**
     * Process a payment callback.
     */
    public function callback(Request $request, OnlinePayment $onlinePayment): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
            'transaction_id' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:30',
            'card_brand' => 'nullable|string|max:50',
            'card_last4' => 'nullable|string|size:4',
            'fee_amount' => 'nullable|numeric|min:0',
            'failure_reason' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:500',
        ]);

        $payment = $this->paymentService->processCallback($onlinePayment, $validated);

        return $this->success($payment, 'Payment callback processed.');
    }

    /**
     * Refund a payment.
     */
    public function refund(Request $request, OnlinePayment $onlinePayment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
        ]);

        $payment = $this->paymentService->refund(
            $onlinePayment,
            $validated['amount'] ?? null,
            $validated['reason'] ?? null
        );

        return $this->success($payment, 'Payment refunded successfully.');
    }

    /**
     * Get payment status.
     */
    public function status(OnlinePayment $onlinePayment): JsonResponse
    {
        $status = $this->paymentService->getStatus($onlinePayment);

        return $this->success($status);
    }
}
