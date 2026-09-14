<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Editing an invoice checks the locked row, so an invoice sent by a
 * concurrent request is not rewritten through a copy loaded while it was a draft.
 */
class InvoiceUpdateTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_an_invoice_sent_meanwhile_is_not_updated_through_a_stale_draft(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.invoices.view', 'sales.invoices.edit']);

        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $invoice = Invoice::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'currency_code' => 'SAR',
            'notes' => 'as sent',
        ]);
        $stale = Invoice::findOrFail($invoice->id);

        Invoice::findOrFail($invoice->id)->transitionTo(Invoice::STATUS_SENT);

        try {
            app(InvoiceService::class)->update($stale, ['notes' => 'rewritten after sending']);
            $this->fail('A sent invoice must not be updated.');
        } catch (\InvalidArgumentException) {
        }

        $fresh = $invoice->fresh();
        $this->assertSame('as sent', $fresh->notes);
        $this->assertSame(Invoice::STATUS_SENT, $fresh->status);
    }
}
