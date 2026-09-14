<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Exceptions\ERP\ValidationException;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Services\Sales\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Voiding a credit note checks the locked note, so credit applied by a
 * concurrent request is never voided out from under the invoice it paid.
 */
class CreditNoteTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private CreditNoteService $service;
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.credit-notes.view', 'sales.credit-notes.void']);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $this->service = app(CreditNoteService::class);
    }

    public function test_a_stale_credit_note_applied_meanwhile_cannot_be_voided(): void
    {
        $note = $this->approvedCreditNote(500);
        $invoice = Invoice::factory()->sent()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'total' => 1000,
            'amount_due' => 1000,
            'currency_code' => 'SAR',
        ]);
        $stale = CreditNote::findOrFail($note->id);

        $this->service->applyToInvoice($note, $invoice, 200);

        $this->assertRejected(fn () => $this->service->void($stale));

        $fresh = $note->fresh();
        $this->assertSame(CreditNote::STATUS_APPROVED, $fresh->status);
        $this->assertSame(0, bccomp((string) $fresh->available_amount, '300', 2));
    }

    public function test_a_voided_credit_note_cannot_be_voided_again(): void
    {
        $note = $this->approvedCreditNote(500);
        $stale = CreditNote::findOrFail($note->id);

        $voided = $this->service->void($note);

        $this->assertRejected(fn () => $this->service->void($stale));
        $this->assertSame($voided->updated_at->toJSON(), $note->fresh()->updated_at->toJSON());
    }

    public function test_a_credit_note_cannot_credit_more_than_its_invoice(): void
    {
        $invoice = Invoice::factory()->sent()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'total' => 100,
            'amount_due' => 100,
            'currency_code' => 'SAR',
        ]);

        try {
            // 90 plus 15% VAT is 103.50.
            $this->service->create([
                'organization_id' => $this->organization->id,
                'contact_id' => $this->customer->id,
                'invoice_id' => $invoice->id,
                'credit_note_type' => CreditNote::TYPE_SALES,
                'credit_note_date' => now()->toDateString(),
                'currency_code' => 'SAR',
                'items' => [['description' => 'Returned goods', 'quantity' => '1', 'unit_price' => '90', 'tax_rate' => '15']],
            ], $this->user->id);
            $this->fail('A credit note of 103.50 was accepted against an invoice of 100.');
        } catch (ValidationException $e) {
            $this->assertSame('Credit note total exceeds invoice total.', $e->getMessage());
        }

        $this->assertSame(0, CreditNote::count());
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (ApiException) {
            return;
        }

        $this->fail('The transition was expected to be rejected.');
    }

    private function approvedCreditNote(float $total): CreditNote
    {
        return CreditNote::factory()->approved()->salesType()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'contact_id' => $this->customer->id,
            'currency_code' => 'SAR',
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'base_total' => $total,
            'applied_amount' => 0,
            'available_amount' => $total,
            'created_by' => $this->user->id,
        ]);
    }
}
