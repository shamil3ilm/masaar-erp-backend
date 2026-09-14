<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\Ecommerce\OnlinePayment;
use App\Models\Ecommerce\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnlinePaymentService
{
    public function __construct() {}

    /**
     * Create a new online payment.
     */
    public function create(array $data): OnlinePayment
    {
        return DB::transaction(function () use ($data) {
            $gateway = PaymentGateway::findOrFail($data['gateway_id']);

            if (!$gateway->is_active) {
                throw new \InvalidArgumentException('Payment gateway is not active.');
            }

            if (!$gateway->supportsCurrency($data['currency_code'])) {
                throw new \InvalidArgumentException("Gateway does not support currency: {$data['currency_code']}");
            }

            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['status'] = OnlinePayment::STATUS_PENDING;
            $data['net_amount'] = $data['net_amount'] ?? bcsub((string) $data['amount'], (string) ($data['fee_amount'] ?? 0), 2);

            return OnlinePayment::create($data);
        });
    }

    /**
     * Process a payment callback from the gateway.
     *
     * Runs on the locked payment and only moves it forward. Gateways retry
     * callbacks and deliver them out of order, so a callback repeating the
     * current status, or naming one the payment has already passed, leaves the
     * payment as it is rather than failing or undoing a capture.
     */
    public function processCallback(OnlinePayment $payment, array $callbackData): OnlinePayment
    {
        $status = $this->mapGatewayStatus($callbackData['status'] ?? 'failed');

        return $payment->lockForTransition(function (OnlinePayment $payment) use ($status, $callbackData): OnlinePayment {
            if (! $payment->canMoveTo($status)) {
                if ($payment->status !== $status) {
                    Log::info('Online payment callback ignored: it does not move the payment forward.', [
                        'payment_id'      => $payment->id,
                        'status'          => $payment->status,
                        'callback_status' => $status,
                    ]);
                }

                return $payment;
            }

            $updateData = [
                'status' => $status,
                'external_payment_id' => $callbackData['transaction_id'] ?? $payment->external_payment_id,
                'gateway_response' => $callbackData,
            ];

            if (isset($callbackData['payment_method'])) {
                $updateData['payment_method'] = $callbackData['payment_method'];
            }

            if (isset($callbackData['card_brand'])) {
                $updateData['card_brand'] = $callbackData['card_brand'];
            }

            if (isset($callbackData['card_last4'])) {
                $updateData['card_last4'] = $callbackData['card_last4'];
            }

            if (isset($callbackData['fee_amount'])) {
                $updateData['fee_amount'] = $callbackData['fee_amount'];
                $updateData['net_amount'] = bcsub((string) $payment->amount, (string) $callbackData['fee_amount'], 2);
            }

            if ($status === OnlinePayment::STATUS_FAILED) {
                $updateData['failure_reason'] = $callbackData['failure_reason'] ?? $callbackData['message'] ?? 'Payment failed';
            }

            $payment->update($updateData);

            return $payment->fresh();
        });
    }

    /**
     * Refund an online payment, once, on the locked payment.
     */
    public function refund(OnlinePayment $payment, ?float $amount = null, ?string $reason = null): OnlinePayment
    {
        return $payment->lockForTransition(function (OnlinePayment $payment) use ($amount, $reason): OnlinePayment {
            if (! $payment->canBeRefunded()) {
                throw new \InvalidArgumentException('Payment cannot be refunded in its current state.');
            }

            $refundAmount = $amount ?? (float) $payment->amount;

            if ($refundAmount > (float) $payment->amount) {
                throw new \InvalidArgumentException('Refund amount cannot exceed payment amount.');
            }

            // Gateway-specific refund logic would be called here
            $payment->update([
                'status' => OnlinePayment::STATUS_REFUNDED,
                'gateway_response' => array_merge(
                    $payment->gateway_response ?? [],
                    [
                        'refund' => [
                            'amount' => $refundAmount,
                            'reason' => $reason,
                            'refunded_at' => now()->toISOString(),
                        ],
                    ]
                ),
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Get payment status from the gateway.
     */
    public function getStatus(OnlinePayment $payment): array
    {
        return [
            'id' => $payment->uuid,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency_code,
            'payment_method' => $payment->payment_method,
            'external_payment_id' => $payment->external_payment_id,
            'gateway' => $payment->gateway->name,
            'created_at' => $payment->created_at->toISOString(),
        ];
    }

    /**
     * Map gateway status to internal status.
     */
    protected function mapGatewayStatus(string $gatewayStatus): string
    {
        return match (strtolower($gatewayStatus)) {
            'success', 'captured', 'paid' => OnlinePayment::STATUS_CAPTURED,
            'authorized' => OnlinePayment::STATUS_AUTHORIZED,
            'refunded' => OnlinePayment::STATUS_REFUNDED,
            default => OnlinePayment::STATUS_FAILED,
        };
    }
}
