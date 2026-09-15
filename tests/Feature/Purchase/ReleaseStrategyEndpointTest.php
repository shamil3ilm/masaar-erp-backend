<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchase\ReleaseStrategy;
use App\Models\Purchase\ReleaseStrategyApproval;
use App\Models\Purchase\ReleaseStrategyLevel;
use App\Models\User;
use App\Services\Purchase\ReleaseStrategyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Release strategy endpoints: approving the last level approves the document
 * it releases, and a decision is recorded on the locked approval.
 */
class ReleaseStrategyEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.release-strategies.view', 'purchase.release-strategies.manage',
            'purchase.release-approvals.view', 'purchase.release-approvals.manage',
        ]);
    }

    public function test_approving_the_last_level_approves_the_requisition(): void
    {
        $requisition = $this->requisition(PurchaseRequisition::STATUS_PENDING_APPROVAL);
        $approval = $this->approval($this->organization, $requisition->id);

        $this->apiPost("/purchase/release-approvals/{$approval->id}/approve", ['comments' => 'Fine'])
            ->assertOk()
            ->assertJsonPath('data.fully_released', true)
            ->assertJsonPath('data.approval.status', ReleaseStrategyApproval::STATUS_APPROVED);

        $requisition->refresh();

        $this->assertSame(PurchaseRequisition::STATUS_APPROVED, $requisition->status);
        $this->assertSame($this->user->id, $requisition->approved_by);
    }

    public function test_approving_a_stale_copy_of_a_rejected_approval_is_refused(): void
    {
        $requisition = $this->requisition(PurchaseRequisition::STATUS_PENDING_APPROVAL);
        $approval = $this->approval($this->organization, $requisition->id);
        $staleCopy = ReleaseStrategyApproval::withoutGlobalScopes()->findOrFail($approval->id);

        $this->apiPost("/purchase/release-approvals/{$approval->id}/reject", ['comments' => 'No budget'])
            ->assertOk()
            ->assertJsonPath('data.status', ReleaseStrategyApproval::STATUS_REJECTED);

        try {
            app(ReleaseStrategyService::class)->approve($staleCopy, $this->user, null);
            $this->fail('A rejected approval was approved from a stale copy.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('This approval has already been acted upon.', $e->getMessage());
        }

        $this->assertSame(ReleaseStrategyApproval::STATUS_REJECTED, $approval->fresh()->status);
    }

    public function test_approving_another_organizations_approval_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $approval = $this->approval($other, 999);

        $this->apiPost("/purchase/release-approvals/{$approval->id}/approve")->assertNotFound();

        $this->assertSame(ReleaseStrategyApproval::STATUS_PENDING, $approval->fresh()->status);
    }

    public function test_removing_a_level_of_another_strategy_is_refused(): void
    {
        $strategy = $this->strategy($this->organization);
        $otherLevel = $this->level($this->strategy($this->organization));

        $this->apiDelete("/purchase/release-strategies/{$strategy->id}/levels/{$otherLevel->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Level does not belong to this strategy.');

        $this->assertNotNull(ReleaseStrategyLevel::withoutGlobalScopes()->find($otherLevel->id));
    }

    private function strategy(Organization $organization): ReleaseStrategy
    {
        return ReleaseStrategy::create([
            'organization_id' => $organization->id,
            'name' => 'Requisitions',
            'document_type' => ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_REQUISITION,
            'is_active' => true,
        ]);
    }

    private function level(ReleaseStrategy $strategy): ReleaseStrategyLevel
    {
        return ReleaseStrategyLevel::create([
            'organization_id' => $strategy->organization_id,
            'release_strategy_id' => $strategy->id,
            'level' => 1,
            'role' => 'manager',
            'label' => 'Manager',
        ]);
    }

    private function approval(Organization $organization, int $documentId): ReleaseStrategyApproval
    {
        $strategy = $this->strategy($organization);

        return ReleaseStrategyApproval::create([
            'organization_id' => $organization->id,
            'release_strategy_id' => $strategy->id,
            'level_id' => $this->level($strategy)->id,
            'document_type' => ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_REQUISITION,
            'document_id' => $documentId,
            'status' => ReleaseStrategyApproval::STATUS_PENDING,
        ]);
    }

    private function requisition(string $status): PurchaseRequisition
    {
        return PurchaseRequisition::create([
            'organization_id' => $this->organization->id,
            'requisition_number' => 'PR-'.fake()->unique()->numerify('#####'),
            'requisition_date' => now()->toDateString(),
            'status' => $status,
            'requested_by' => User::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
    }
}
