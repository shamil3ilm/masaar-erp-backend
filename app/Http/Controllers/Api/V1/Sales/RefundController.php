<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{

    public function __construct(
        protected RefundService $refundService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $refunds = $this->refundService->list(
            $request->user()->organization_id,
            $request->only(['status', 'refund_type', 'contact_id', 'from_date', 'to_date']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($refunds);
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $request->validate([
            'refund_type' => 'required|in:customer_refund,supplier_refund',
            'contact_id' => ['required', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'amount' => 'required|numeric|min:0.01',
            'currency_code' => 'nullable|string|size:3',
            'refund_method' => 'required|in:original_payment_method,bank_transfer,cash,wallet,credit_note',
            'refund_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('organization_id', $orgId)],
            'sales_return_id' => ['nullable', Rule::exists('sales_returns', 'id')->where('organization_id', $orgId)],
            'payment_received_id' => ['nullable', Rule::exists('payments_received', 'id')->where('organization_id', $orgId)],
        ]);

        try {
            $refund = $this->refundService->create(
                array_merge($request->all(), ['organization_id' => $orgId]),
                $request->user()->id
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($refund, 'Refund created successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->refundService->findWithDetails($request->user()->organization_id, $id));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $refund = $this->refundService->find($request->user()->organization_id, $id);

        try {
            $refund = $this->refundService->approve($refund, $request->user()->id);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($refund, 'Refund approved.');
    }

    public function process(Request $request, int $id): JsonResponse
    {
        $refund = $this->refundService->find($request->user()->organization_id, $id);

        try {
            $refund = $this->refundService->process(
                $refund,
                $request->user()->id,
                $request->transaction_reference
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($refund, 'Refund processed successfully.');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $refund = $this->refundService->find($request->user()->organization_id, $id);

        try {
            $refund = $this->refundService->cancel($refund);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($refund, 'Refund cancelled.');
    }
}
