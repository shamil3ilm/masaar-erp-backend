<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\BusinessPartner;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins business partner creation and merging. A partner links a contact of
 * the caller's organization, and only the organization's partners are merged.
 */
class BusinessPartnerEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.business-partners.view', 'core.business-partners.manage']);
    }

    public function test_partners_are_created_with_roles_and_merged(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->organization->id]);

        $target = $this->apiPost('/business-partners', ['name' => 'Acme', 'contact_id' => $contact->id, 'roles' => ['FLCU00']])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Business partner created')
            ->assertJsonPath('data.contact_id', $contact->id)
            ->json('data.id');
        $source = $this->apiPost('/business-partners', ['name' => 'Acme Supply', 'roles' => ['FLVN00']])
            ->assertStatus(201)
            ->json('data.id');

        $this->apiPost("/business-partners/{$target}/merge", ['source_id' => $source])
            ->assertOk()
            ->assertJsonPath('message', 'Business partners merged')
            ->assertJsonCount(2, 'data.roles');
        $this->assertFalse((bool) BusinessPartner::findOrFail($source)->is_active);
    }

    public function test_another_organizations_contact_and_partner_are_refused(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreignContact = Contact::factory()->create(['organization_id' => $otherOrg->id]);

        $this->apiPost('/business-partners', ['name' => 'Acme', 'contact_id' => $foreignContact->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact_id');
        $this->assertSame(0, BusinessPartner::count());

        $target = $this->apiPost('/business-partners', ['name' => 'Acme'])->assertStatus(201)->json('data.id');
        $foreign = BusinessPartner::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'bp_number' => 'BP-FOREIGN',
            'bp_category' => BusinessPartner::CATEGORY_ORG,
            'name' => 'Foreign',
            'is_active' => true,
        ]);

        $this->apiPost("/business-partners/{$target}/merge", ['source_id' => $foreign->id])->assertNotFound();
        $this->assertTrue((bool) BusinessPartner::withoutGlobalScopes()->findOrFail($foreign->id)->is_active);
    }
}
