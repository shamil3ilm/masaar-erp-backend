<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\ManagerDelegation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A manager's delegations: created for a colleague, listed and revoked by
 * that manager only.
 */
class ManagerDelegationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/manager/delegations';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.delegations.view', 'hr.delegations.manage']);
    }

    public function test_a_delegation_is_created_for_a_colleague(): void
    {
        $colleague = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiPost($this->baseUrl, [
            'delegate_id' => $colleague->id,
            'delegation_type' => 'full',
            'valid_from' => '2026-06-01',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.manager_id', $this->user->id)
            ->assertJsonPath('data.delegate.id', $colleague->id);
    }

    public function test_a_delegation_cannot_name_a_user_of_another_organization(): void
    {
        $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $response = $this->apiPost($this->baseUrl, [
            'delegate_id' => $outsider->id,
            'delegation_type' => 'leave_approval',
            'valid_from' => '2026-06-01',
        ]);

        $response->assertStatus(422);
        $this->assertStringNotContainsString($outsider->email, $response->getContent());
        $this->assertSame(0, ManagerDelegation::withoutGlobalScopes()->count());
    }

    public function test_a_manager_lists_their_own_delegations_latest_first(): void
    {
        $colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $earlier = $this->delegation($this->user, $colleague, '2026-01-01');
        $later = $this->delegation($this->user, $colleague, '2026-03-01');
        $this->delegation($colleague, $this->user, '2026-05-01');

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $this->assertSame([$later->id, $earlier->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.delegate.id', $colleague->id);
    }

    public function test_a_manager_revokes_only_their_own_delegation(): void
    {
        $colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $own = $this->delegation($this->user, $colleague, '2026-01-01');
        $theirs = $this->delegation($colleague, $this->user, '2026-01-01');

        $this->apiDelete("{$this->baseUrl}/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Delegation revoked.');

        $this->apiDelete("{$this->baseUrl}/{$theirs->id}")->assertNotFound();
        $this->assertTrue($theirs->fresh()->is_active);
    }

    private function delegation(User $manager, User $delegate, string $validFrom): ManagerDelegation
    {
        return ManagerDelegation::create([
            'organization_id' => $this->organization->id,
            'manager_id' => $manager->id,
            'delegate_id' => $delegate->id,
            'delegation_type' => ManagerDelegation::TYPE_FULL,
            'valid_from' => $validFrom,
            'is_active' => true,
        ]);
    }
}
