<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Core\Organization;
use App\Models\CRM\ServiceTicket;
use App\Models\CRM\SlaPolicy;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the service ticket and SLA policy lists, and shows a ticket's contact
 * by reference only.
 */
class ServiceTicketTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'crm.service_tickets.view',
            'crm.service_tickets.edit',
            'crm.sla_policies.view',
            'crm.sla_policies.create',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
        $this->contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => '300000000000003',
        ]);
    }

    public function test_index_filters_by_status_and_search_and_shows_the_contact_by_reference(): void
    {
        $older = $this->ticket(['subject' => 'Printer jam', 'created_at' => now()->subDays(2)]);
        $newer = $this->ticket(['subject' => 'Printer offline', 'created_at' => now()->subDay()]);
        $this->ticket(['subject' => 'Printer closed', 'status' => ServiceTicket::STATUS_CLOSED]);
        $this->ticket(['subject' => 'Login']);
        $this->ticket(['subject' => 'Printer abroad', 'organization_id' => $this->other->id, 'contact_id' => null]);

        $response = $this->apiGet('/crm/service-tickets?status=open&search=Printer');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($response->json('data.0.contact')));
    }

    public function test_show_and_update_show_the_contact_by_reference(): void
    {
        $ticket = $this->ticket();

        $shown = $this->apiGet("/crm/service-tickets/{$ticket->id}")->assertOk();
        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($shown->json('data.contact')));

        $updated = $this->apiPut("/crm/service-tickets/{$ticket->id}", ['subject' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Renamed');
        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($updated->json('data.contact')));
    }

    public function test_sla_policies_list_the_organizations_active_policies_by_priority_and_store_one(): void
    {
        $high = $this->policy(['priority' => 'high']);
        $critical = $this->policy(['priority' => 'critical']);
        $this->policy(['priority' => 'low', 'is_active' => false]);
        SlaPolicy::create([...$this->policyAttributes(), 'organization_id' => $this->other->id]);

        $response = $this->apiGet('/crm/sla-policies?active=true');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$critical->id, $high->id], array_column($response->json('data'), 'id'));

        $this->apiPost('/crm/sla-policies', [
            'name' => 'Gold',
            'priority' => 'high',
            'first_response_hours' => 2,
            'resolution_hours' => 8,
        ])->assertCreated()->assertJsonPath('data.organization_id', $this->organization->id);
    }

    private function ticket(array $attributes = []): ServiceTicket
    {
        static $number = 0;
        $number++;

        return ServiceTicket::unguarded(fn () => ServiceTicket::create([
            'organization_id' => $this->organization->id,
            'ticket_number' => "TKT-{$number}",
            'subject' => 'Help',
            'description' => 'Something broke',
            'status' => ServiceTicket::STATUS_OPEN,
            'contact_id' => $this->contact->id,
            'created_by' => $this->user->id,
            ...$attributes,
        ]));
    }

    private function policy(array $attributes = []): SlaPolicy
    {
        return SlaPolicy::create([
            ...$this->policyAttributes(),
            'organization_id' => $this->organization->id,
            ...$attributes,
        ]);
    }

    private function policyAttributes(): array
    {
        return [
            'name' => 'Standard',
            'priority' => 'medium',
            'first_response_hours' => 4,
            'resolution_hours' => 24,
            'is_active' => true,
        ];
    }
}
