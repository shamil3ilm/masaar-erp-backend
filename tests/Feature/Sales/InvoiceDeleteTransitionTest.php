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
 * Deleting an invoice checks the locked row, so an invoice sent by a
 * concurrent request is not deleted through a copy loaded while it was a draft.
 */
class InvoiceDeleteTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_an_invoice_sent_meanwhile_is_not_deleted_through_a_stale_draft(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.invoices.view', 'sales.invoices.delete']);

        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $invoice = Invoice::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
        ]);
        $stale = Invoice::findOrFail($invoice->id);

        Invoice::findOrFail($invoice->id)->transitionTo(Invoice::STATUS_SENT);

        try {
            app(InvoiceService::class)->delete($stale);
            $this->fail('A sent invoice must not be deleted.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertNotNull(Invoice::find($invoice->id));
    }

    public function test_a_draft_invoice_is_deleted(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.invoices.view', 'sales.invoices.delete']);

        $invoice = Invoice::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);

        $this->apiDelete("/sales/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Invoice deleted successfully.');

        $this->assertNull(Invoice::find($invoice->id));
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }
}
