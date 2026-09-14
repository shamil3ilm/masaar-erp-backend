<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\PaymentRun;
use App\Models\Accounting\PaymentRunItem;
use App\Models\Purchase\Bill;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Posting a payment run marks its documents paid through their state machine.
 *
 * postItem wrote status 'paid' directly, so a bill voided or already paid
 * after the run was proposed was marked paid again with nothing to stop it.
 */
class PaymentRunPostingTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['accounting.payment-runs.manage', 'accounting.payment-runs.view']);
    }

    public function test_posting_pays_the_bill(): void
    {
        $bill = $this->approvedBill();

        $this->apiPost("/payment-runs/{$this->approvedRunFor($bill)->id}/post")->assertOk();

        $this->assertSame(Bill::STATUS_PAID, $bill->fresh()->status);
        $this->assertEquals(0, (float) $bill->fresh()->amount_due);
    }

    public function test_a_bill_voided_since_the_proposal_is_not_paid(): void
    {
        $bill = $this->approvedBill();
        $run = $this->approvedRunFor($bill);

        $bill->forceFill(['status' => Bill::STATUS_VOIDED])->saveQuietly();

        $this->apiPost("/payment-runs/{$run->id}/post")->assertStatus(422);

        $this->assertSame(Bill::STATUS_VOIDED, $bill->fresh()->status);
        $this->assertSame(PaymentRun::STATUS_APPROVED, $run->fresh()->status);
    }

    private function approvedBill(): Bill
    {
        $supplier = Contact::factory()->supplier()->create(['organization_id' => $this->organization->id]);

        return Bill::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    private function approvedRunFor(Bill $bill): PaymentRun
    {
        $run = PaymentRun::forceCreate([
            'organization_id' => $this->organization->id,
            'run_reference' => 'RUN-'.fake()->unique()->numerify('######'),
            'payment_direction' => PaymentRun::DIRECTION_OUTGOING,
            'payment_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => PaymentRun::STATUS_APPROVED,
            'total_amount' => $bill->amount_due,
            'total_items' => 1,
            'created_by' => $this->user->id,
        ]);

        PaymentRunItem::forceCreate([
            'payment_run_id' => $run->id,
            'document_type' => PaymentRunItem::DOC_TYPE_BILL,
            'document_id' => $bill->id,
            'vendor_id' => $bill->supplier_id,
            'open_amount' => $bill->amount_due,
            'payment_amount' => $bill->amount_due,
            'discount_taken' => 0,
            'due_date' => $bill->due_date,
            'status' => PaymentRunItem::STATUS_PROPOSED,
        ]);

        return $run;
    }
}
