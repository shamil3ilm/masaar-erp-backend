<?php

declare(strict_types=1);

namespace Tests\Feature\Ecommerce;

use App\Models\Ecommerce\OnlinePayment;
use App\Models\Ecommerce\PaymentGateway;
use App\Models\Sales\Invoice;
use App\Services\Ecommerce\OnlinePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * A gateway callback moves a payment forward only, on the locked payment:
 * a retried or late callback never takes a captured payment back to
 * authorized or failed, and a payment is refunded once.
 */
class OnlinePaymentTransitionTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private OnlinePaymentService $service;
    private OnlinePayment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $gateway = PaymentGateway::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'supported_currencies' => ['SAR'],
        ]);

        $this->service = app(OnlinePaymentService::class);
        $this->payment = $this->service->create([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
            'payable_type' => Invoice::class,
            'payable_id' => 1,
            'currency_code' => 'SAR',
            'amount' => 100,
        ]);
    }

    public function test_an_authorization_arriving_after_the_capture_does_not_undo_it(): void
    {
        $stale = OnlinePayment::findOrFail($this->payment->id);

        $this->service->processCallback($this->payment, ['status' => 'captured', 'transaction_id' => 'TXN-1']);
        $this->service->processCallback($stale, ['status' => 'authorized', 'transaction_id' => 'TXN-1']);

        $this->assertSame(OnlinePayment::STATUS_CAPTURED, $this->payment->fresh()->status);
    }

    public function test_a_failure_arriving_after_the_capture_does_not_undo_it(): void
    {
        $stale = OnlinePayment::findOrFail($this->payment->id);

        $this->service->processCallback($this->payment, ['status' => 'captured', 'transaction_id' => 'TXN-1']);
        $this->service->processCallback($stale, ['status' => 'declined', 'message' => 'Card declined']);

        $payment = $this->payment->fresh();
        $this->assertSame(OnlinePayment::STATUS_CAPTURED, $payment->status);
        $this->assertNull($payment->failure_reason);
    }

    public function test_a_retried_capture_callback_changes_nothing(): void
    {
        $this->service->processCallback($this->payment, ['status' => 'captured', 'transaction_id' => 'TXN-1']);

        $payment = $this->service->processCallback($this->payment->fresh(), ['status' => 'captured', 'transaction_id' => 'TXN-1']);

        $this->assertSame(OnlinePayment::STATUS_CAPTURED, $payment->status);
    }

    public function test_a_payment_is_refunded_once(): void
    {
        $this->service->processCallback($this->payment, ['status' => 'captured', 'transaction_id' => 'TXN-1']);
        $captured = $this->payment->fresh();
        $stale = OnlinePayment::findOrFail($this->payment->id);

        $this->service->refund($captured, 100, 'Order cancelled');

        $this->assertRejected(fn () => $this->service->refund($stale, 100, 'Order cancelled'));
        $this->assertSame(OnlinePayment::STATUS_REFUNDED, $this->payment->fresh()->status);
    }
}
