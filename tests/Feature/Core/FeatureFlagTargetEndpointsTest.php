<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Branch;
use App\Models\Core\FeatureFlagTarget;
use App\Models\Core\Organization;
use App\Models\Core\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins feature flag targeting: adding a target with its notes, listing it on
 * the flag and removing it. A user, branch or role target names one of the
 * caller's organization; a target of another organization or flag is not found.
 */
class FeatureFlagTargetEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.feature-flags.manage', 'core.feature-flags.view']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_target_is_added_with_notes_listed_and_removed(): void
    {
        $member = User::factory()->create(['organization_id' => $this->organization->id]);

        $id = $this->apiPost('/feature-flags/beta_reports/targets', [
            'target_type' => 'user',
            'target_id' => $member->id,
            'notes' => 'Pilot group',
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Target added successfully')
            ->assertJsonPath('data.target_id', $member->id)
            ->assertJsonPath('data.notes', 'Pilot group')
            ->json('data.id');

        $this->apiGet('/feature-flags/beta_reports')->assertOk()->assertJsonCount(1, 'data.targets');

        $this->apiDelete("/feature-flags/other_flag/targets/{$id}")->assertNotFound();
        $this->apiDelete("/feature-flags/beta_reports/targets/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Target removed successfully');
        $this->assertNull(FeatureFlagTarget::find($id));
    }

    public function test_a_target_refuses_another_organizations_user_branch_and_role(): void
    {
        $foreign = [
            'user' => User::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'branch' => Branch::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'role' => Role::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ];

        foreach ($foreign as $type => $id) {
            $this->apiPost('/feature-flags/beta_reports/targets', ['target_type' => $type, 'target_id' => $id])
                ->assertStatus(422)
                ->assertJsonValidationErrors('target_id');
        }

        $this->apiPost('/feature-flags/beta_reports/targets', ['target_type' => 'percentage', 'percentage' => 25])
            ->assertStatus(201);
        $this->assertSame(1, FeatureFlagTarget::count());
    }

    public function test_another_organizations_target_is_not_found(): void
    {
        $foreign = FeatureFlagTarget::create([
            'organization_id' => $this->otherOrg->id,
            'flag_key' => 'beta_reports',
            'target_type' => 'percentage',
            'percentage' => 10,
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->apiDelete("/feature-flags/beta_reports/targets/{$foreign->id}")->assertNotFound();
        $this->assertNotNull(FeatureFlagTarget::find($foreign->id));
    }
}
