<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Services\Sales\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A credit note refers only to the caller's own contacts, invoices and
 * products, shows its contact without the tax number, and is approved
 * against the locked row.
 */
class CreditNoteScopingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Invoice $invoice;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.credit-notes.view',
            'sales.credit-notes.create',
            'sales.credit-notes.approve',
            'sales.credit-notes.apply',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'tax_number' => '300000000000003',
        ]);

        $this->invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'total' => 1000,
            'amount_due' => 1000,
            'currency_code' => 'SAR',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_contact_of_another_organization_is_refused(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/credit-notes', $this->payload(['contact_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, CreditNote::count());
    }

    public function test_an_invoice_of_another_organization_is_refused(): void
    {
        $foreign = Invoice::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $this->apiPost('/sales/credit-notes', $this->payload(['invoice_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, CreditNote::count());
    }

    public function test_a_product_of_another_organization_is_refused(): void
    {
        $foreign = Product::factory()->create(['organization_id' => $this->otherOrg->id]);

        $payload = $this->payload();
        $payload['items'][0]['product_id'] = $foreign->id;

        $this->apiPost('/sales/credit-notes', $payload)->assertStatus(422);

        $this->assertSame(0, CreditNote::count());
    }

    public function test_applying_to_an_invoice_of_another_organization_is_refused(): void
    {
        $note = $this->approvedNote();
        $foreign = Invoice::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        $this->apiPost("/sales/credit-notes/{$note->id}/apply", [
            'invoice_id' => $foreign->id,
            'amount' => 10,
        ])->assertStatus(422);

        $this->assertEquals(0, $note->fresh()->applied_amount);
    }

    public function test_the_list_and_the_note_show_the_contact_without_its_tax_number(): void
    {
        $note = $this->approvedNote();

        $shown = $this->apiGet("/sales/credit-notes/{$note->id}")->assertOk();
        $this->assertSame($this->customer->contact_name, $shown->json('data.contact.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.contact'));
        $this->assertArrayNotHasKey('customer_tax_number', $shown->json('data.invoice'));

        $listed = $this->apiGet('/sales/credit-notes')->assertOk();
        $this->assertSame($this->customer->company_name, $listed->json('data.0.contact.company_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.contact'));
    }

    public function test_a_note_voided_meanwhile_is_not_approved_through_a_stale_draft(): void
    {
        $note = CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'status' => CreditNote::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);
        $stale = CreditNote::findOrFail($note->id);
        $service = app(CreditNoteService::class);

        $service->void($note);

        try {
            $service->approve($stale, $this->user->id);
            $this->fail('A voided credit note must not be approved.');
        } catch (ApiException) {
        }

        $this->assertSame(CreditNote::STATUS_VOIDED, $note->fresh()->status);
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'credit_note_type' => CreditNote::TYPE_SALES,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'credit_note_date' => now()->toDateString(),
            'currency_code' => 'SAR',
            'items' => [
                ['description' => 'Returned item', 'quantity' => 1, 'unit_price' => 10],
            ],
        ], $overrides);
    }

    private function approvedNote(): CreditNote
    {
        return CreditNote::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'status' => CreditNote::STATUS_APPROVED,
            'total' => 100,
            'available_amount' => 100,
            'applied_amount' => 0,
            'created_by' => $this->user->id,
        ]);
    }
}
