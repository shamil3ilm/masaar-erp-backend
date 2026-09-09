<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Core\PortalSession;
use App\Models\Core\PortalUser;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * One customer cannot reach another's documents through the portal.
 *
 * The portal carries no auth middleware — it answers to its own session token
 * rather than a staff JWT — so every handler calls resolvePortalUser itself
 * and each one decides for itself whether the record belongs to the caller.
 * Some check in the controller and some in the service. That holds today, and
 * holds by convention rather than by anything enforcing it, which is why it
 * is worth a test rather than a reading.
 */
class PortalIsolationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private PortalUser $alice;

    private string $aliceToken;

    private Contact $bobContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');

        [$this->alice, $this->aliceToken] = $this->portalUser('alice@example.com');

        $bob = $this->portalUser('bob@example.com');
        $this->bobContact = Contact::findOrFail($bob[0]->contact_id);
    }

    public function test_it_refuses_a_request_with_no_token(): void
    {
        $this->getJson('/api/v1/portal/invoices')->assertStatus(401);
    }

    public function test_it_refuses_an_unknown_token(): void
    {
        $this->getJson('/api/v1/portal/invoices', [
            'Authorization' => 'Bearer '.Str::random(64),
        ])->assertStatus(401);
    }

    public function test_it_serves_the_callers_own_invoices(): void
    {
        $this->asAlice('/api/v1/portal/invoices')->assertStatus(200);
    }

    public function test_one_customer_cannot_read_anothers_invoice(): void
    {
        $bobInvoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->bobContact->id,
        ]);

        $this->asAlice("/api/v1/portal/invoices/{$bobInvoice->id}/detail")
            ->assertStatus(403);
    }

    public function test_one_customer_cannot_accept_anothers_quotation(): void
    {
        $bobQuotation = Quotation::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->bobContact->id,
            'status' => Quotation::STATUS_SENT,
        ]);

        $response = $this->postJson(
            "/api/v1/portal/quotations/{$bobQuotation->id}/accept",
            [],
            ['Authorization' => 'Bearer '.$this->aliceToken]
        );

        $this->assertNotSame(200, $response->baseResponse->getStatusCode());

        $this->assertSame(
            Quotation::STATUS_SENT,
            $bobQuotation->refresh()->status,
            'A quotation belonging to another customer was accepted.'
        );
    }

    public function test_one_customer_cannot_decline_anothers_quotation(): void
    {
        $bobQuotation = Quotation::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->bobContact->id,
            'status' => Quotation::STATUS_SENT,
        ]);

        $this->postJson(
            "/api/v1/portal/quotations/{$bobQuotation->id}/decline",
            [],
            ['Authorization' => 'Bearer '.$this->aliceToken]
        );

        $this->assertSame(Quotation::STATUS_SENT, $bobQuotation->refresh()->status);
    }

    private function asAlice(string $uri): TestResponse
    {
        return $this->getJson($uri, ['Authorization' => 'Bearer '.$this->aliceToken]);
    }

    /**
     * @return array{0: PortalUser, 1: string}
     */
    private function portalUser(string $email): array
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $user = PortalUser::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $contact->id,
            'email' => $email,
            'password_hash' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        $token = Str::random(64);

        PortalSession::create([
            'organization_id' => $this->organization->id,
            'portal_user_id' => $user->id,
            'session_token' => $token,
            'expires_at' => now()->addHours(4),
            'last_activity_at' => now(),
        ]);

        return [$user, $token];
    }
}
