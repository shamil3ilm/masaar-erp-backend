<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\GdprConsentRecord;
use App\Models\Core\GdprDataSubjectRequest;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the GDPR endpoints: data subject requests, the processing register and
 * consent records. A consent names a contact of the caller's organization; a
 * request is processed once and a consent withdrawn once, so neither record's
 * completion or withdrawal time is overwritten.
 */
class GdprEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.gdpr.view', 'core.gdpr.manage']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_request_is_submitted_listed_and_processed(): void
    {
        $this->foreignRequest();

        $access = $this->apiPost('/gdpr/requests', $this->requestPayload('access'))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Data subject request submitted. Deadline: 30 days.')
            ->assertJsonPath('data.status', 'received')
            ->json('data');

        $this->apiGet('/gdpr/requests')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $access['id']);

        $this->apiPut("/gdpr/requests/{$access['id']}/process")
            ->assertOk()
            ->assertJsonPath('message', 'Request processed')
            ->assertJsonPath('data.status', 'completed');

        $portability = $this->apiPost('/gdpr/requests', $this->requestPayload('portability'))->json('data');

        $this->apiPut("/gdpr/requests/{$portability['id']}/process")
            ->assertOk()
            ->assertJsonPath('data.data_exported_path', "gdpr-exports/{$portability['uuid']}.json");
    }

    public function test_a_processed_request_is_not_processed_again(): void
    {
        $id = $this->apiPost('/gdpr/requests', $this->requestPayload('erasure'))->json('data.id');

        $this->apiPut("/gdpr/requests/{$id}/process")->assertOk();
        $completedAt = GdprDataSubjectRequest::findOrFail($id)->completed_at;

        $this->travel(5)->minutes();

        $this->apiPut("/gdpr/requests/{$id}/process")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'This request has already been processed.');
        $this->assertEquals($completedAt, GdprDataSubjectRequest::findOrFail($id)->completed_at);
    }

    public function test_consent_is_recorded_and_withdrawn_once(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->organization->id]);

        $id = $this->apiPost('/gdpr/consent', ['contact_id' => $contact->id, 'purpose' => 'Newsletter'])
            ->assertStatus(201)
            ->assertJsonPath('data.consent_given', true)
            ->assertJsonPath('data.contact_id', $contact->id)
            ->json('data.id');

        $this->apiDelete("/gdpr/consent/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Consent withdrawn')
            ->assertJsonPath('data.consent_given', false);
        $withdrawnAt = GdprConsentRecord::findOrFail($id)->withdrawn_at;

        $this->travel(5)->minutes();

        $this->apiDelete("/gdpr/consent/{$id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'This consent has already been withdrawn.');
        $this->assertEquals($withdrawnAt, GdprConsentRecord::findOrFail($id)->withdrawn_at);
    }

    public function test_another_organizations_contact_is_refused_for_consent(): void
    {
        $foreignContact = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/gdpr/consent', ['contact_id' => $foreignContact->id, 'purpose' => 'Newsletter'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact_id');
        $this->assertSame(0, GdprConsentRecord::count());
    }

    public function test_another_organizations_request_and_consent_are_not_found(): void
    {
        $request = $this->foreignRequest();
        $consent = GdprConsentRecord::withoutGlobalScopes()->create([
            'organization_id' => $this->otherOrg->id,
            'purpose' => 'Newsletter',
            'consent_given' => true,
            'given_at' => now(),
        ]);

        $this->apiPut("/gdpr/requests/{$request->id}/process")->assertNotFound();
        $this->apiDelete("/gdpr/consent/{$consent->id}")->assertNotFound();
        $this->assertSame('received', $request->fresh()->status);
        $this->assertTrue($consent->fresh()->consent_given);
    }

    public function test_a_processing_activity_is_stored_and_listed(): void
    {
        $this->apiPost('/gdpr/processing-register', [
            'activity_name' => 'Payroll',
            'purpose' => 'Paying staff',
            'legal_basis' => 'contract',
            'data_categories' => ['bank_details'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.activity_name', 'Payroll')
            ->assertJsonPath('data.organization_id', $this->organization->id);

        $this->apiGet('/gdpr/processing-register')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.data_categories', ['bank_details']);
    }

    /**
     * @return array<string, string>
     */
    private function requestPayload(string $type): array
    {
        return ['request_type' => $type, 'requester_name' => 'Jane Roe', 'requester_email' => 'jane@example.test'];
    }

    private function foreignRequest(): GdprDataSubjectRequest
    {
        return GdprDataSubjectRequest::withoutGlobalScopes()->create([
            'organization_id' => $this->otherOrg->id,
            'request_type' => 'access',
            'requester_name' => 'Other',
            'requester_email' => 'other@example.test',
            'status' => 'received',
            'received_at' => now(),
            'deadline_at' => now()->addDays(30),
        ]);
    }
}
