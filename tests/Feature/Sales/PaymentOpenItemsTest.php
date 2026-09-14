<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentAllocation;
use App\Models\Sales\PaymentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins open items, open-item clearing, allocation of several invoices at once
 * and the payment summary.
 */
class PaymentOpenItemsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.payments.view', 'sales.payments.allocate']);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);
    }

    public function test_open_items_lists_the_customers_unpaid_invoices_oldest_first(): void
    {
        $sent = $this->invoice(Invoice::STATUS_SENT, '2025-02-01', 200);
        $partial = $this->invoice(Invoice::STATUS_PARTIAL, '2025-01-01', 300);
        $this->invoice(Invoice::STATUS_DRAFT, '2025-01-05', 50);
        $this->invoice(Invoice::STATUS_PAID, '2025-01-06', 0);

        $otherCustomer = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->invoice(Invoice::STATUS_SENT, '2025-01-02', 70, $otherCustomer);

        $response = $this->apiGet("/sales/payments-received/open-items?customer_id={$this->customer->id}");

        $response->assertOk();
        $this->assertSame([$partial->id, $sent->id], array_column($response->json('data'), 'id'));
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'invoice_number', 'invoice_date', 'due_date', 'total', 'amount_paid', 'amount_due', 'status', 'currency_code'],
            array_keys($response->json('data.0'))
        );
    }

    public function test_open_items_refuses_a_customer_of_another_organization(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiGet("/sales/payments-received/open-items?customer_id={$foreign->id}")
            ->assertStatus(422);
    }

    public function test_clearing_applies_an_unallocated_payment_to_the_selected_invoice(): void
    {
        $this->payment(300);
        $invoice = $this->invoice(Invoice::STATUS_SENT, '2025-01-01', 200);

        $response = $this->apiPost('/sales/payments-received/clear-open-items', [
            'customer_id' => $this->customer->id,
            'invoice_ids' => [$invoice->id],
            'clearing_date' => '2025-02-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.clearing_date', '2025-02-01')
            ->assertJsonPath('data.cleared', [$invoice->id])
            ->assertJsonPath('data.unmatched', []);
        $this->assertEquals(0, $invoice->fresh()->amount_due);
    }

    public function test_clearing_refuses_a_customer_of_another_organization(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $invoice = $this->invoice(Invoice::STATUS_SENT, '2025-01-01', 200);

        $this->apiPost('/sales/payments-received/clear-open-items', [
            'customer_id' => $foreign->id,
            'invoice_ids' => [$invoice->id],
        ])->assertStatus(422);

        $this->assertSame(0, PaymentAllocation::count());
    }

    public function test_allocating_several_invoices_is_all_or_nothing(): void
    {
        $payment = $this->payment(1000);
        $first = $this->invoice(Invoice::STATUS_SENT, '2025-01-01', 300);
        $second = $this->invoice(Invoice::STATUS_SENT, '2025-01-02', 100);

        $this->apiPost("/sales/payments-received/{$payment->id}/allocate", [
            'allocations' => [
                ['invoice_id' => $first->id, 'amount' => 300],
                ['invoice_id' => $second->id, 'amount' => 200],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, PaymentAllocation::count());
        $this->assertEquals(300, $first->fresh()->amount_due);
    }

    public function test_allocating_several_invoices_reports_what_is_left_unallocated(): void
    {
        $payment = $this->payment(1000);
        $first = $this->invoice(Invoice::STATUS_SENT, '2025-01-01', 300);
        $second = $this->invoice(Invoice::STATUS_SENT, '2025-01-02', 100);

        $response = $this->apiPost("/sales/payments-received/{$payment->id}/allocate", [
            'allocations' => [
                ['invoice_id' => $first->id, 'amount' => 300],
                ['invoice_id' => $second->id, 'amount' => 100],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data.allocations'));
        $this->assertEquals(600, $response->json('data.unallocated_amount'));
    }

    public function test_the_summary_bounds_its_totals_by_date_but_not_its_method_breakdown(): void
    {
        $this->payment(100, '2025-01-10', PaymentReceived::METHOD_CASH);
        $this->payment(150, '2025-03-10', PaymentReceived::METHOD_CASH);
        $this->payment(500, '2025-01-12', PaymentReceived::METHOD_BANK_TRANSFER, PaymentReceived::STATUS_PENDING);

        $response = $this->apiGet('/sales/payments-received/summary?start_date=2025-01-01&end_date=2025-01-31');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total_payments'));
        $this->assertEquals(100, $response->json('data.total_amount'));
        $this->assertEquals(2, $response->json('data.by_method.cash.count'));
        $this->assertEquals(250, $response->json('data.by_method.cash.total'));
        $this->assertArrayNotHasKey('bank_transfer', $response->json('data.by_method'));
    }

    private function invoice(string $status, string $date, float $due, ?Contact $customer = null): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'status' => $status,
            'invoice_date' => $date,
            'currency_code' => 'SAR',
            'total' => max($due, 1),
            'amount_paid' => max($due, 1) - $due,
            'amount_due' => $due,
        ]);
    }

    private function payment(
        float $amount,
        string $date = '2025-01-01',
        string $method = PaymentReceived::METHOD_CASH,
        string $status = PaymentReceived::STATUS_COMPLETED,
    ): PaymentReceived {
        return PaymentReceived::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'payment_date' => $date,
            'payment_method' => $method,
            'status' => $status,
            'amount' => $amount,
            'base_amount' => $amount,
            'currency_code' => 'SAR',
        ]);
    }
}
