<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\BillPaymentAllocation;
use App\Models\Purchase\PaymentMade;
use App\Models\Purchase\SupplierCredit;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Payment-made endpoints not pinned elsewhere: tenant scoping of the ids a
 * request names, deleting a pending payment, allocating to several bills as
 * one change, and the summary.
 */
class PaymentMadeEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/payments-made';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'purchase.payments.view', 'purchase.payments.create',
            'purchase.payments.delete', 'purchase.payments.allocate',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_refuses_another_organizations_supplier(): void
    {
        $other = Organization::factory()->create();
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => $other->id]);

        $response = $this->apiPost($this->baseUrl, [
            'supplier_id' => $foreignSupplier->id,
            'amount' => 100,
            'payment_method' => PaymentMade::METHOD_BANK_TRANSFER,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('supplier_id');
        $this->assertSame(0, PaymentMade::count());
    }

    public function test_allocate_refuses_another_organizations_bill(): void
    {
        $other = Organization::factory()->create();
        $foreignBill = Bill::factory()->approved()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);

        $this->apiPost("{$this->baseUrl}/{$this->payment(1000)->id}/allocate", [
            'allocations' => [['bill_id' => $foreignBill->id, 'amount' => 100]],
        ])->assertStatus(422)->assertJsonValidationErrors('allocations.0.bill_id');
    }

    public function test_deleting_a_pending_payment_takes_its_allocations_off_the_bills(): void
    {
        $bill = $this->bill(1000);

        $created = $this->apiPost($this->baseUrl, [
            'supplier_id' => $this->supplier->id,
            'amount' => 1500,
            'payment_method' => PaymentMade::METHOD_BANK_TRANSFER,
            'allocations' => [['bill_id' => $bill->id, 'amount' => 1000]],
        ])->assertCreated();

        $paymentId = $created->json('data.id');
        $this->assertSame(PaymentMade::STATUS_PENDING, PaymentMade::findOrFail($paymentId)->status);
        $this->assertSame(Bill::STATUS_PAID, $bill->fresh()->status);

        $this->apiDelete("{$this->baseUrl}/{$paymentId}")->assertOk();

        $bill->refresh();
        $this->assertSoftDeleted('payments_made', ['id' => $paymentId]);
        $this->assertSame(0, BillPaymentAllocation::where('payment_made_id', $paymentId)->count());
        $this->assertEqualsWithDelta(1000.0, (float) $bill->amount_due, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $bill->amount_paid, 0.0001);
        $this->assertSame(Bill::STATUS_APPROVED, $bill->status);
        $this->assertFalse((bool) SupplierCredit::where('source_id', $paymentId)->value('is_active'));
    }

    public function test_an_overpayment_without_a_currency_takes_the_payments_default_currency(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'supplier_id' => $this->supplier->id,
            'amount' => 250,
            'payment_method' => PaymentMade::METHOD_CASH,
        ]);

        $response->assertCreated();
        $this->assertSame(
            PaymentMade::findOrFail($response->json('data.id'))->currency_code,
            SupplierCredit::where('source_id', $response->json('data.id'))->value('currency_code')
        );
    }

    public function test_a_completed_payment_is_not_deleted(): void
    {
        $payment = $this->payment(500);

        $this->apiDelete("{$this->baseUrl}/{$payment->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message','Only pending payments can be deleted.');
    }

    public function test_allocations_to_several_bills_are_recorded_all_or_nothing(): void
    {
        $payment = $this->payment(5000);
        $first = $this->bill(1000);
        $second = $this->bill(500);

        $this->apiPost("{$this->baseUrl}/{$payment->id}/allocate", [
            'allocations' => [
                ['bill_id' => $first->id, 'amount' => 1000],
                ['bill_id' => $second->id, 'amount' => 600],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, BillPaymentAllocation::where('payment_made_id', $payment->id)->count());
        $this->assertEqualsWithDelta(1000.0, (float) $first->fresh()->amount_due, 0.0001);
    }

    public function test_allocating_to_several_bills_records_each(): void
    {
        $payment = $this->payment(5000);
        $first = $this->bill(1000);
        $second = $this->bill(500);

        $this->apiPost("{$this->baseUrl}/{$payment->id}/allocate", [
            'allocations' => [
                ['bill_id' => $first->id, 'amount' => 1000],
                ['bill_id' => $second->id, 'amount' => 200],
            ],
        ])->assertOk()->assertJsonCount(2, 'data.allocations');

        $this->assertSame(Bill::STATUS_PAID, $first->fresh()->status);
        $this->assertEqualsWithDelta(300.0, (float) $second->fresh()->amount_due, 0.0001);
    }

    public function test_flat_allocation_requires_bill_and_amount(): void
    {
        $this->apiPost("{$this->baseUrl}/{$this->payment(100)->id}/allocate", [])
            ->assertStatus(422)
            ->assertJsonPath('error.message','Either bill_id and amount, or allocations array is required.');
    }

    public function test_flat_allocation_to_another_suppliers_bill_is_refused(): void
    {
        $bill = $this->bill(100, Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
        ]));

        $this->apiPost("{$this->baseUrl}/{$this->payment(100)->id}/allocate", [
            'bill_id' => $bill->id,
            'amount' => 50,
        ])->assertStatus(422)
            ->assertJsonPath('error.message','Cannot allocate payment to bills from a different supplier.');
    }

    public function test_summary_reports_pending_and_completed_payments(): void
    {
        $this->payment(300);
        $this->payment(200, PaymentMade::STATUS_PENDING);

        $this->apiGet("{$this->baseUrl}/summary")
            ->assertOk()
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.completed_count', 1)
            ->assertJsonStructure(['data' => [
                'total_count', 'pending_count', 'completed_count',
                'pending_value', 'completed_value', 'this_month_value',
            ]]);
    }

    private function payment(float $amount, string $status = PaymentMade::STATUS_COMPLETED): PaymentMade
    {
        return PaymentMade::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'amount' => $amount,
            'exchange_rate' => 1,
            'payment_date' => now()->toDateString(),
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function bill(float $total, ?Contact $supplier = null): Bill
    {
        return Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => ($supplier ?? $this->supplier)->id,
            'status' => Bill::STATUS_APPROVED,
            'total' => $total,
            'amount_due' => $total,
            'amount_paid' => 0,
            'created_by' => $this->user->id,
        ]);
    }
}
