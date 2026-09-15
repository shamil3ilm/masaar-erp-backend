<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the per-user module restrictions: reading, setting and clearing which
 * of the organization's modules a user may open. Only users of the caller's
 * organization are found.
 */
class ModuleUserAccessEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.users.view', 'core.users.edit']);
    }

    public function test_a_users_modules_are_restricted_read_and_cleared(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiPut("/modules/users/{$member->id}/access", ['modules' => ['sales', 'no-such-module']])
            ->assertOk()
            ->assertJsonPath('message', 'User module access updated successfully.')
            ->assertJsonPath('data.user_id', $member->id)
            ->assertJsonPath('data.modules', ['core', 'sales']);

        $read = $this->apiGet("/modules/users/{$member->id}/access")
            ->assertOk()
            ->assertJsonPath('data.has_restrictions', true);
        $this->assertEqualsCanonicalizing(['core', 'sales'], array_values($read->json('data.modules')));

        $this->apiDelete("/modules/users/{$member->id}/access")
            ->assertOk()
            ->assertJsonPath('message', 'User now has access to all organization modules.');

        $this->apiGet("/modules/users/{$member->id}/access")
            ->assertOk()
            ->assertJsonPath('data.has_restrictions', false);
        $this->assertNull($member->fresh()->module_access);
    }

    public function test_another_organizations_user_is_not_found(): void
    {
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiGet("/modules/users/{$foreign->id}/access")->assertNotFound();
        $this->apiPut("/modules/users/{$foreign->id}/access", ['modules' => ['sales']])->assertNotFound();
        $this->apiDelete("/modules/users/{$foreign->id}/access")->assertNotFound();
        $this->assertNull($foreign->fresh()->module_access);
    }
}
