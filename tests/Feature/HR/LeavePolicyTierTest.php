<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Leave\LeavePolicy;
use App\Models\HR\Leave\LeaveTier;
use App\Models\HR\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Leave policies and the tiers of their leave types. A tier carries no
 * organization of its own; it belongs to the organization of its leave type.
 */
class LeavePolicyTierTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/leave-management';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.leave.view', 'hr.leave.manage']);
    }

    public function test_policies_are_listed_by_name_as_a_list_or_a_page(): void
    {
        $this->policy(['name' => 'Zeta']);
        $this->policy(['name' => 'Alpha']);
        $this->policy(['name' => 'Beta', 'is_active' => false]);
        $this->policy(['name' => 'Aaa'], Organization::factory()->create());

        $list = $this->apiGet("{$this->baseUrl}/policies?active_only=1");
        $list->assertOk();
        $this->assertSame(['Alpha', 'Zeta'], array_column($list->json('data'), 'name'));

        $page = $this->apiGet("{$this->baseUrl}/policies?active_only=1&per_page=1");
        $this->assertPaginatedResponse($page);
        $this->assertSame(['Alpha'], array_column($page->json('data'), 'name'));
    }

    public function test_a_policy_is_deleted(): void
    {
        $policy = $this->policy();

        $this->apiDelete("{$this->baseUrl}/policies/{$policy->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Leave policy deleted successfully.');

        $this->assertDatabaseMissing('leave_policies', ['id' => $policy->id]);
    }

    public function test_a_tier_is_assigned_with_its_approvers(): void
    {
        $type = $this->leaveType($this->policy());

        $this->apiPost("{$this->baseUrl}/tiers/leave-types/{$type->id}/assign", [
            'name' => 'Senior staff',
            'entitled_days' => 30,
            'approvers' => [['user_id' => $this->user->id, 'approval_level' => 1]],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.leave_type_id', $type->id)
            ->assertJsonCount(1, 'data.approvers');
    }

    public function test_a_tier_approver_must_belong_to_the_organization(): void
    {
        $type = $this->leaveType($this->policy());
        $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("{$this->baseUrl}/tiers/leave-types/{$type->id}/assign", [
            'name' => 'Senior staff',
            'entitled_days' => 30,
            'approvers' => [['user_id' => $outsider->id]],
        ])->assertStatus(422);

        $this->assertSame(0, LeaveTier::count());
    }

    public function test_a_tier_is_updated_and_deleted(): void
    {
        $tier = $this->tier($this->leaveType($this->policy()));

        $this->apiPut("{$this->baseUrl}/tiers/{$tier->id}", ['entitled_days' => 25])
            ->assertOk()
            ->assertJsonPath('data.entitled_days', '25.00');

        $this->apiDelete("{$this->baseUrl}/tiers/{$tier->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Leave tier deleted successfully.');

        $this->assertDatabaseMissing('leave_tiers', ['id' => $tier->id]);
    }

    public function test_another_organizations_tier_cannot_be_changed_or_deleted(): void
    {
        $other = Organization::factory()->create();
        $theirs = $this->tier($this->leaveType($this->policy([], $other)), 'Theirs');

        $this->apiPut("{$this->baseUrl}/tiers/{$theirs->id}", ['name' => 'Taken'])->assertNotFound();
        $this->apiDelete("{$this->baseUrl}/tiers/{$theirs->id}")->assertNotFound();

        $this->assertDatabaseHas('leave_tiers', ['id' => $theirs->id, 'name' => 'Theirs']);
    }

    private function policy(array $overrides = [], ?Organization $organization = null): LeavePolicy
    {
        return LeavePolicy::create(array_merge([
            'organization_id' => ($organization ?? $this->organization)->id,
            'name' => 'Standard',
            'is_active' => true,
        ], $overrides));
    }

    private function leaveType(LeavePolicy $policy): LeaveType
    {
        return LeaveType::factory()->create([
            'organization_id' => $policy->organization_id,
            'leave_policy_id' => $policy->id,
        ]);
    }

    private function tier(LeaveType $type, string $name = 'Standard tier'): LeaveTier
    {
        return LeaveTier::create([
            'leave_type_id' => $type->id,
            'name' => $name,
            'entitled_days' => 21,
        ]);
    }
}
