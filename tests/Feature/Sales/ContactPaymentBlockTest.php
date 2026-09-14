<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins blocking a contact for payments, the type alias on the list and the
 * refusal to delete an invoiced contact, and keeps the tax number out of the
 * payment block response.
 */
class ContactPaymentBlockTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.contacts.view',
            'sales.contacts.create',
            'sales.contacts.edit',
            'sales.contacts.delete',
        ]);

        $this->contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
            'tax_number' => '300000000000003',
        ]);
    }

    public function test_blocking_records_the_reason_without_returning_the_tax_number(): void
    {
        $response = $this->apiPatch("/sales/contacts/{$this->contact->id}/payment-block", [
            'blocked' => true,
            'reason' => 'Disputed bank details',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Contact payment blocked.')
            ->assertJsonPath('data.payment_block', true)
            ->assertJsonPath('data.payment_block_reason', 'Disputed bank details');
        $this->assertArrayNotHasKey('tax_number', $response->json('data'));
    }

    public function test_unblocking_clears_the_reason(): void
    {
        $this->contact->update(['payment_block' => true, 'payment_block_reason' => 'Old reason']);

        $this->apiPatch("/sales/contacts/{$this->contact->id}/payment-block", ['blocked' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Contact payment unblocked.')
            ->assertJsonPath('data.payment_block', false)
            ->assertJsonPath('data.payment_block_reason', null);
    }

    public function test_the_type_alias_narrows_the_list(): void
    {
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $response = $this->apiGet('/sales/contacts?type=supplier');

        $response->assertOk();
        $this->assertSame([$this->contact->id], array_column($response->json('data'), 'id'));
    }

    public function test_an_invoiced_contact_is_not_deleted(): void
    {
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->contact->id,
        ]);

        $this->apiDelete("/sales/contacts/{$this->contact->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Cannot delete contact with existing invoices.');

        $this->assertNotNull(Contact::find($this->contact->id));
    }
}
