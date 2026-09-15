<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\PortalUser;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the customer portal's registration, profile and invoice detail.
 *
 * Registration is public, so it links an account to a contact only when the
 * email given is the contact's own; otherwise anyone could open a portal
 * account on any customer by id and read its documents. The profile embeds
 * the contact by its reference columns and email, never its tax number.
 */
class CustomerPortalEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PASSWORD = 'portal-pass-1';

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'buyer@acme.test',
            'tax_number' => '300000000000003',
        ]);
    }

    public function test_registration_needs_the_contacts_own_email(): void
    {
        $this->register($this->contact->id, 'attacker@example.test')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REGISTRATION_FAILED');
        $this->assertSame(0, PortalUser::count());

        $this->register($this->contact->id, 'Buyer@Acme.test')
            ->assertStatus(201)
            ->assertJsonPath('message', 'Portal account created successfully.');
        $this->assertSame($this->contact->id, (int) PortalUser::sole()->contact_id);
    }

    public function test_an_unknown_contact_and_a_contact_without_email_are_refused_alike(): void
    {
        $withoutEmail = Contact::factory()->create(['organization_id' => $this->organization->id, 'email' => null]);

        foreach ([999999, $withoutEmail->id] as $contactId) {
            $this->register($contactId, 'someone@example.test')
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'REGISTRATION_FAILED')
                ->assertJsonPath('error.message', 'Registration could not be completed.');
        }

        $this->assertSame(0, PortalUser::count());
    }

    public function test_the_profile_embeds_the_contact_without_its_tax_number(): void
    {
        $token = $this->signIn();

        $this->getJson('/api/v1/portal/profile', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.email', 'buyer@acme.test')
            ->assertJsonPath('data.contact.id', $this->contact->id)
            ->assertJsonPath('data.contact.email', 'buyer@acme.test')
            ->assertJsonMissingPath('data.contact.tax_number');
    }

    public function test_an_invoice_is_shown_only_to_its_customer(): void
    {
        $token = $this->signIn();
        $own = Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->contact->id]);
        $other = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);

        $this->getJson("/api/v1/portal/invoices/{$own->id}", ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.id', $own->id);

        $this->getJson("/api/v1/portal/invoices/{$other->id}", ['Authorization' => "Bearer {$token}"])
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Invoice not found.');
    }

    private function register(int $contactId, string $email): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/portal/register', [
            'contact_id' => $contactId,
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ]);
    }

    private function signIn(): string
    {
        $this->register($this->contact->id, 'buyer@acme.test')->assertStatus(201);

        return $this->postJson('/api/v1/portal/login', [
            'organization_id' => $this->organization->id,
            'email' => 'buyer@acme.test',
            'password' => self::PASSWORD,
        ])->assertOk()->json('data.token');
    }
}
