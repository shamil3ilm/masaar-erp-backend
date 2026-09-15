<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\IpAllowlistRule;
use App\Models\Core\Organization;
use App\Models\Core\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the IP allowlist endpoints: listing, adding, changing and deleting the
 * organization's rules and checking an address against them. A rule scoped to
 * a role names a role of the caller's organization.
 */
class IpAllowlistEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->token = JWTAuth::fromUser($this->user);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_rules_are_listed_allow_before_deny_for_the_organization(): void
    {
        $deny = $this->rule(['rule_type' => 'deny', 'rule_name' => 'Deny']);
        $allow = $this->rule(['rule_type' => 'allow', 'rule_name' => 'Allow']);
        $this->rule(['organization_id' => $this->otherOrg->id, 'rule_type' => 'allow']);

        $this->apiGet('/security/ip-allowlist')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('data.0.id', $allow->id)
            ->assertJsonPath('data.1.id', $deny->id);
    }

    public function test_a_rule_is_added_changed_and_deleted(): void
    {
        $role = Role::factory()->create(['organization_id' => $this->organization->id]);

        $created = $this->apiPost('/security/ip-allowlist', [
            'rule_name' => 'Office',
            'cidr_notation' => '203.0.113.0/24',
            'rule_type' => 'allow',
            'applies_to' => 'specific_role',
            'role_id' => $role->id,
        ]);

        $created->assertStatus(201)
            ->assertJsonPath('data.rule_name', 'Office')
            ->assertJsonPath('data.role_id', $role->id)
            ->assertJsonPath('data.created_by', $this->user->id)
            ->assertJsonPath('data.organization_id', $this->organization->id);
        $id = $created->json('data.id');

        $this->apiPut("/security/ip-allowlist/{$id}", ['rule_name' => 'Head office', 'active' => false])
            ->assertOk()
            ->assertJsonPath('data.rule_name', 'Head office')
            ->assertJsonPath('data.active', false);

        $this->apiDelete("/security/ip-allowlist/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Rule deleted');
        $this->assertSoftDeleted('ip_allowlist_rules', ['id' => $id]);
    }

    public function test_another_organizations_role_is_refused(): void
    {
        $foreignRole = Role::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/security/ip-allowlist', [
            'rule_name' => 'Office',
            'ip_address' => '203.0.113.7',
            'rule_type' => 'allow',
            'applies_to' => 'specific_role',
            'role_id' => $foreignRole->id,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('role_id', $response->json('errors') ?? []);
        $this->assertSame(0, IpAllowlistRule::count());
    }

    public function test_another_organizations_rule_is_not_found(): void
    {
        $foreign = $this->rule(['organization_id' => $this->otherOrg->id]);

        $this->apiPut("/security/ip-allowlist/{$foreign->id}", ['rule_name' => 'X'])->assertNotFound();
        $this->apiDelete("/security/ip-allowlist/{$foreign->id}")->assertNotFound();
        $this->assertNotSoftDeleted('ip_allowlist_rules', ['id' => $foreign->id]);
    }

    public function test_an_address_is_checked_against_the_rules(): void
    {
        $this->rule(['rule_type' => 'deny', 'ip_address' => '203.0.113.9']);

        $this->apiPost('/security/ip-allowlist/check', ['ip' => '203.0.113.9'])
            ->assertOk()
            ->assertJsonPath('data', ['ip' => '203.0.113.9', 'access' => 'denied']);
        $this->apiPost('/security/ip-allowlist/check', ['ip' => '198.51.100.1'])
            ->assertOk()
            ->assertJsonPath('data.access', 'allowed');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(array $attributes = []): IpAllowlistRule
    {
        return IpAllowlistRule::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $this->organization->id,
            'rule_name' => 'Rule',
            'ip_address' => '198.51.100.200',
            'rule_type' => 'allow',
            'applies_to' => 'all',
            'active' => true,
            'created_by' => $this->user->id,
        ], $attributes));
    }
}
