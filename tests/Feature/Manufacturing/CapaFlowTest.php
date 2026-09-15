<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\CapaAction;
use App\Models\Manufacturing\CapaEffectivenessReview;
use App\Models\Manufacturing\CapaRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * CAPA records, their actions and effectiveness reviews are reached only within
 * the organization, assign only its users, and an effective review closes its
 * CAPA in the same transaction.
 */
class CapaFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);
    }

    public function test_an_action_is_completed_only_through_its_own_capa(): void
    {
        $capa = $this->capa();
        $other = $this->capa();
        $action = $this->actionOn($capa);

        $this->apiPost("/manufacturing/capas/{$other->id}/actions/{$action->id}/complete")->assertNotFound();
        $this->assertSame('pending', $action->fresh()->status);

        $this->apiPost("/manufacturing/capas/{$capa->id}/actions/{$action->id}/complete", ['completion_notes' => 'Done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completion_notes', 'Done');
    }

    public function test_an_effective_review_closes_the_capa_and_another_leaves_it_open(): void
    {
        $capa = $this->capa();

        $this->apiPost("/manufacturing/capas/{$capa->id}/effectiveness-reviews", $this->review('not_effective'))
            ->assertCreated();
        $this->assertSame('open', $capa->fresh()->status);

        $this->apiPost("/manufacturing/capas/{$capa->id}/effectiveness-reviews", $this->review('effective'))
            ->assertCreated()
            ->assertJsonPath('data.reviewed_by_id', $this->user->id);
        $this->assertSame('closed', $capa->fresh()->status);

        $this->apiGet("/manufacturing/capas/{$capa->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.effectiveness_reviews');
    }

    public function test_a_review_is_not_kept_when_closing_the_capa_fails(): void
    {
        $capa = $this->capa();

        CapaRecord::updating(function (): void {
            throw new RuntimeException('Closing the CAPA failed.');
        });

        $this->apiPost("/manufacturing/capas/{$capa->id}/effectiveness-reviews", $this->review('effective'))
            ->assertStatus(500);

        $this->assertSame(0, CapaEffectivenessReview::where('capa_record_id', $capa->id)->count());
    }

    public function test_another_organizations_users_are_refused(): void
    {
        $this->apiPost('/manufacturing/capas', [
            'capa_number' => 'CAPA-X-1',
            'capa_type' => 'corrective',
            'problem_statement' => 'Problem',
            'priority' => 'high',
            'owner_id' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['owner_id']);

        $this->apiPost("/manufacturing/capas/{$this->capa()->id}/actions", [
            'action_number' => 'ACT-1',
            'description' => 'Retrain',
            'due_date' => now()->addWeek()->toDateString(),
            'assigned_to_id' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to_id']);
    }

    public function test_another_organizations_capa_is_not_found(): void
    {
        $theirs = CapaRecord::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $action = $this->actionOn($theirs);

        $this->apiGet("/manufacturing/capas/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/capas/{$theirs->id}/actions", [
            'action_number' => 'ACT-1',
            'description' => 'Retrain',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertNotFound();
        $this->apiPost("/manufacturing/capas/{$theirs->id}/actions/{$action->id}/complete")->assertNotFound();
        $this->apiPost("/manufacturing/capas/{$theirs->id}/effectiveness-reviews", $this->review('effective'))
            ->assertNotFound();

        $this->assertSame('pending', $action->fresh()->status);
        $this->assertSame('open', CapaRecord::withoutGlobalScopes()->find($theirs->id)->status);
    }

    private function capa(): CapaRecord
    {
        return CapaRecord::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function actionOn(CapaRecord $capa): CapaAction
    {
        return CapaAction::create([
            'capa_record_id' => $capa->id,
            'action_number' => 'ACT-'.fake()->unique()->numerify('###'),
            'description' => 'Retrain operators',
            'due_date' => now()->addWeek()->toDateString(),
            'status' => 'pending',
        ]);
    }

    private function review(string $effectiveness): array
    {
        return [
            'review_date' => now()->toDateString(),
            'effectiveness' => $effectiveness,
        ];
    }
}
