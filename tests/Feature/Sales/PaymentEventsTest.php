<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Events\Sales\InvoicePaid;
use App\Events\Sales\PaymentReceived as PaymentReceivedEvent;
use App\Models\Core\Notification;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PaymentEventsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.payments.create']);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'company_name' => 'Globex Trading',
        ]);
    }

    public function test_a_payment_settling_an_invoice_dispatches_both_events(): void
    {
        Event::fake([PaymentReceivedEvent::class, InvoicePaid::class]);

        $invoice = $this->sentInvoice(5000.00);

        $this->assertCreatedResponse($this->pay($invoice, 5000.00));

        Event::assertDispatched(PaymentReceivedEvent::class, fn (PaymentReceivedEvent $event) => $event->payment->customer_id === $this->customer->id);
        Event::assertDispatched(InvoicePaid::class, fn (InvoicePaid $event) => $event->invoice->id === $invoice->id
            && $event->amountPaid === 5000.0);
    }

    public function test_a_partial_payment_does_not_dispatch_invoice_paid(): void
    {
        Event::fake([PaymentReceivedEvent::class, InvoicePaid::class]);

        $invoice = $this->sentInvoice(5000.00);

        $this->assertCreatedResponse($this->pay($invoice, 2000.00));

        Event::assertDispatched(PaymentReceivedEvent::class);
        Event::assertNotDispatched(InvoicePaid::class);
    }

    public function test_invoice_paid_notifies_the_creator_with_the_customer_name(): void
    {
        $invoice = $this->sentInvoice(5000.00);

        $this->assertCreatedResponse($this->pay($invoice, 5000.00));

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'invoice_paid')
            ->firstOrFail();

        $this->assertSame('Globex Trading', $notification->data['customer_name']);
        $this->assertEquals(5000.0, $notification->data['amount_paid']);
    }

    private function sentInvoice(float $total): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'currency_code' => 'SAR',
            'status' => Invoice::STATUS_SENT,
            'total' => $total,
            'amount_paid' => 0,
            'amount_due' => $total,
            'created_by' => $this->user->id,
        ]);
    }

    private function pay(Invoice $invoice, float $amount): \Illuminate\Testing\TestResponse
    {
        return $this->apiPost('/sales/payments-received', [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => PaymentReceived::METHOD_BANK_TRANSFER,
            'amount' => $amount,
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0000,
            'allocations' => [
                ['invoice_id' => $invoice->id, 'amount' => $amount],
            ],
        ]);
    }
}
