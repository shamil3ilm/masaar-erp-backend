<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{

    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallets = $this->walletService->list(
            $request->user()->organization_id,
            ['wallet_type' => $request->wallet_type, 'active_only' => $request->boolean('active_only')],
            $request->integer('per_page', 20)
        );

        return $this->paginated($wallets);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->walletService->findWithContact($request->user()->organization_id, $id));
    }

    public function balance(Request $request, int $contactId): JsonResponse
    {
        if (! $this->walletService->hasContact($request->user()->organization_id, $contactId)) {
            return $this->notFound('Contact not found.');
        }

        $balances = $this->walletService->getBalance(
            $request->user()->organization_id,
            $contactId,
            $request->currency_code
        );

        return $this->success($balances);
    }

    public function credit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
        ]);

        $wallet = $this->walletService->find($request->user()->organization_id, $id);

        try {
            $transaction = $this->walletService->creditActive(
                $wallet,
                (float) $request->amount,
                $request->description
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'WALLET_INACTIVE', 422);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($transaction, 'Wallet credited successfully.');
    }

    public function debit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
        ]);

        $wallet = $this->walletService->find($request->user()->organization_id, $id);

        try {
            $transaction = $this->walletService->debitActive(
                $wallet,
                (float) $request->amount,
                $request->description
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'WALLET_INACTIVE', 422);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($transaction, 'Wallet debited successfully.');
    }

    public function statement(Request $request, int $id): JsonResponse
    {
        $wallet = $this->walletService->find($request->user()->organization_id, $id);

        $transactions = $this->walletService->getStatement(
            $wallet,
            $request->from_date,
            $request->to_date,
            $request->integer('per_page', 20)
        );

        return $this->success($transactions);
    }

    public function adjust(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string|max:500',
        ]);

        $wallet = $this->walletService->find($request->user()->organization_id, $id);

        try {
            $transaction = $this->walletService->adjustBalance(
                $wallet,
                (float) $request->amount,
                $request->description,
                $request->user()->id
            );
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($transaction, 'Wallet adjusted successfully.');
    }
}
