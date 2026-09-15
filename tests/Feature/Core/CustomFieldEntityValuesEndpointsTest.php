<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\CustomFieldDefinition;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins custom field values on a record: a value saved for a record is read
 * back for it, a record is found only in the caller's organization, and only
 * the supported entity types are accepted, never an arbitrary model class.
 */
class CustomFieldEntityValuesEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.settings.view', 'core.custom-fields.manage']);
    }

    public function test_a_saved_value_is_read_back_for_the_record(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->organization->id]);
        CustomFieldDefinition::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => 'customer',
            'field_name' => 'loyalty_tier',
            'field_type' => 'text',
            'default_value' => 'none',
            'is_active' => true,
        ]);

        $this->apiPost('/custom-fields/entity/values', [
            'entity_type' => 'customer',
            'entity_id' => $contact->id,
            'fields' => ['loyalty_tier' => 'gold'],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Custom field values saved successfully.');

        $this->apiGet("/custom-fields/entity/values?entity_type=customer&entity_id={$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.0.definition.field_name', 'loyalty_tier')
            ->assertJsonPath('data.0.value', 'gold');
    }

    public function test_only_supported_entity_types_in_the_organization_are_accepted(): void
    {
        $foreignUser = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $foreignContact = Contact::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost('/custom-fields/entity/values', [
            'entity_type' => User::class,
            'entity_id' => $foreignUser->id,
            'fields' => ['name' => 'x'],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_ENTITY_TYPE');

        $this->apiPost('/custom-fields/entity/values', [
            'entity_type' => 'customer',
            'entity_id' => $foreignContact->id,
            'fields' => ['loyalty_tier' => 'gold'],
        ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ENTITY_NOT_FOUND');
    }
}
