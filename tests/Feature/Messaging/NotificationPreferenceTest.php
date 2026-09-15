<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Core\Organization;
use App\Models\Messaging\NotificationPreference;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the notification preference endpoints and keeps preferences on the
 * caller's own contacts.
 */
class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $contact;

    private Contact $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['messaging.preferences.view', 'messaging.preferences.manage']);
        $this->actingAs($this->user, 'api');

        $this->contact = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->theirs = Contact::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    }

    public function test_a_contact_without_preferences_shows_the_defaults(): void
    {
        $this->apiGet("/messaging/preferences/contacts/{$this->contact->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Default notification preferences.')
            ->assertJsonPath('data.contact_id', $this->contact->id)
            ->assertJsonPath('data.preferred_channel', 'email');
    }

    public function test_preferences_are_saved_once_per_contact(): void
    {
        $this->apiPut("/messaging/preferences/contacts/{$this->contact->id}", ['sms_enabled' => false])->assertOk();
        $this->apiPut("/messaging/preferences/contacts/{$this->contact->id}", ['email_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.contact_id', $this->contact->id);

        $preference = NotificationPreference::where('contact_id', $this->contact->id)->sole();
        $this->assertFalse($preference->sms_enabled);
        $this->assertFalse($preference->email_enabled);
        $this->assertSame($this->organization->id, $preference->organization_id);

        $this->apiGet("/messaging/preferences/contacts/{$this->contact->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Success')
            ->assertJsonPath('data.sms_enabled', false);
    }

    public function test_unsubscribe_and_resubscribe(): void
    {
        $this->apiPost("/messaging/preferences/contacts/{$this->contact->id}/resubscribe")->assertNotFound();

        $this->apiPost("/messaging/preferences/contacts/{$this->contact->id}/unsubscribe", ['reason' => 'Too many'])
            ->assertOk()
            ->assertJsonPath('data.unsubscribe_reason', 'Too many');
        $this->assertNotNull(NotificationPreference::where('contact_id', $this->contact->id)->sole()->unsubscribed_at);

        $this->apiPost("/messaging/preferences/contacts/{$this->contact->id}/resubscribe")->assertOk();
        $this->assertNull(NotificationPreference::where('contact_id', $this->contact->id)->sole()->unsubscribed_at);
    }

    public function test_another_organizations_contact_is_not_found(): void
    {
        $this->apiPut("/messaging/preferences/contacts/{$this->theirs->id}", ['sms_enabled' => false])->assertNotFound();
        $this->apiPost("/messaging/preferences/contacts/{$this->theirs->id}/unsubscribe")->assertNotFound();

        $this->assertSame(0, NotificationPreference::withoutGlobalScopes()->count());
    }
}
