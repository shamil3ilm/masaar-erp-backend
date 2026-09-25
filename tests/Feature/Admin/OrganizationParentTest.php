<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The platform-admin endpoint that links a subsidiary to its parent.
 *
 * The link decides which other companies an inter-company asset transfer, a
 * consolidation entity or an intercompany sales order may name, so only a
 * super admin sets it, and a group stays a parent with its subsidiaries:
 * Organization::groupIds() reads membership from that one link, so a deeper
 * chain would put a grandchild in a group of its own.
 */
class OrganizationParentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $parent;

    private Organization $subsidiary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->user = User::factory()->superAdmin()->create(['organization_id' => $this->organization->id]);
        $this->token = JWTAuth::fromUser($this->user);

        $this->parent = Organization::factory()->create(['name' => 'Holding']);
        $this->subsidiary = Organization::factory()->create(['name' => 'Subsidiary']);
    }

    public function test_a_super_admin_sets_and_clears_a_parent(): void
    {
        $this->setParent($this->subsidiary, $this->parent->id)
            ->assertOk()
            ->assertJsonPath('message', 'Organization parent updated.')
            ->assertJsonPath('data.parent_organization_id', $this->parent->id);

        $this->assertSame($this->parent->id, $this->subsidiary->fresh()->parent_organization_id);
        $this->assertEqualsCanonicalizing(
            [$this->parent->id, $this->subsidiary->id],
            Organization::groupIds($this->subsidiary->id)
        );

        $this->setParent($this->subsidiary, null)
            ->assertOk()
            ->assertJsonPath('data.parent_organization_id', null);

        $this->assertNull($this->subsidiary->fresh()->parent_organization_id);
        $this->assertSame([$this->subsidiary->id], Organization::groupIds($this->subsidiary->id));
    }

    public function test_an_organization_cannot_be_its_own_parent(): void
    {
        $this->setParent($this->subsidiary, $this->subsidiary->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PARENT')
            ->assertJsonPath('error.message', 'An organization cannot be its own parent.');

        $this->assertNull($this->subsidiary->fresh()->parent_organization_id);
    }

    public function test_a_parent_below_the_organization_is_refused_as_a_cycle(): void
    {
        $this->setParent($this->subsidiary, $this->parent->id)->assertOk();

        $this->setParent($this->parent, $this->subsidiary->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PARENT')
            ->assertJsonPath('error.message', 'The proposed parent is below this organization, which would close a cycle.');

        $this->assertNull($this->parent->fresh()->parent_organization_id);
    }

    public function test_a_third_level_is_refused(): void
    {
        $this->setParent($this->subsidiary, $this->parent->id)->assertOk();

        $grandchild = Organization::factory()->create(['name' => 'Grandchild']);

        $this->setParent($grandchild, $this->subsidiary->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PARENT')
            ->assertJsonPath('error.message', 'The proposed parent already has a parent, and a group is only two levels deep.');

        $this->assertNull($grandchild->fresh()->parent_organization_id);
    }

    public function test_an_organization_that_already_has_subsidiaries_is_refused_a_parent(): void
    {
        $this->setParent($this->subsidiary, $this->parent->id)->assertOk();

        $grandparent = Organization::factory()->create(['name' => 'Grandparent']);

        $this->setParent($this->parent, $grandparent->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PARENT')
            ->assertJsonPath('error.message', 'This organization already has subsidiaries, and a group is only two levels deep.');

        $this->assertNull($this->parent->fresh()->parent_organization_id);
    }

    public function test_a_tenant_user_cannot_set_a_parent(): void
    {
        $this->setUpAuthenticatedUser();

        $this->setParent($this->subsidiary, $this->parent->id)->assertStatus(403);

        $this->assertNull($this->subsidiary->fresh()->parent_organization_id);
    }

    private function setParent(Organization $organization, ?int $parentId): TestResponse
    {
        return $this->putJson(
            '/api/v1/admin/organizations/'.$organization->id.'/parent',
            ['parent_organization_id' => $parentId],
            $this->adminHeaders()
        );
    }
}
