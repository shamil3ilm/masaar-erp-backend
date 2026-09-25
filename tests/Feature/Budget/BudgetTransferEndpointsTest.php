<?php

declare(strict_types=1);

namespace Tests\Feature\Budget;

use App\Models\Budget\Budget;
use App\Models\Budget\BudgetLine;
use App\Models\Budget\BudgetTransfer;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Services\Budget\BudgetTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Budget transfers: a request to move appropriation from one budget line to
 * another, submitted, then approved and posted or rejected.
 */
class BudgetTransferEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PERMISSIONS = [
        'budget.transfers.view', 'budget.transfers.create', 'budget.transfers.edit', 'budget.transfers.approve',
    ];

    private Organization $otherOrganization;

    private BudgetLine $from;

    private BudgetLine $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'budget',
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $this->setUpAuthenticatedUser(self::PERMISSIONS);

        $this->otherOrganization = Organization::factory()->create();

        $budget = $this->budget($this->organization->id);
        $this->from = $this->line($budget, 1000);
        $this->to = $this->line($budget, 200);
    }

    public function test_a_transfer_is_created_submitted_and_approved_moving_the_amount(): void
    {
        $transfer = $this->apiPost('/budget/budget-transfers', [
            'from_budget_line_id' => $this->from->id,
            'to_budget_line_id' => $this->to->id,
            'amount' => 300,
            'reason' => 'Reallocation',
        ])->assertCreated()
            ->assertJsonPath('data.status', BudgetTransfer::STATUS_DRAFT)
            ->assertJsonPath('data.from_budget_line.id', $this->from->id)
            ->json('data');

        $this->apiPost("/budget/budget-transfers/{$transfer['id']}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', BudgetTransfer::STATUS_SUBMITTED);

        $this->apiPost("/budget/budget-transfers/{$transfer['id']}/review", ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', BudgetTransfer::STATUS_POSTED);

        $this->assertEquals(700, $this->from->fresh()->total_amount);
        $this->assertEquals(500, $this->to->fresh()->total_amount);
    }

    public function test_a_rejected_transfer_moves_nothing(): void
    {
        $transfer = $this->submittedTransfer(300);

        $this->apiPost("/budget/budget-transfers/{$transfer->id}/review", ['action' => 'reject', 'reason' => 'Not now'])
            ->assertOk()
            ->assertJsonPath('data.status', BudgetTransfer::STATUS_REJECTED)
            ->assertJsonPath('data.rejection_reason', 'Not now');

        $this->assertEquals(1000, $this->from->fresh()->total_amount);
    }

    public function test_store_refuses_a_line_of_another_organization(): void
    {
        $foreignLine = $this->line($this->budget($this->otherOrganization->id), 1000);

        $this->apiPost('/budget/budget-transfers', [
            'from_budget_line_id' => $foreignLine->id,
            'to_budget_line_id' => $this->to->id,
            'amount' => 300,
            'reason' => 'Probe',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('budget_transfers')->count());
    }

    public function test_show_returns_a_transfer_and_not_another_organizations(): void
    {
        $transfer = $this->submittedTransfer(100);
        $foreign = BudgetTransfer::withoutGlobalScopes()->create(array_merge(
            $transfer->only(['from_budget_id', 'from_budget_line_id', 'to_budget_id', 'to_budget_line_id', 'amount', 'reason', 'status', 'requested_by']),
            ['organization_id' => $this->otherOrganization->id, 'transfer_number' => 'BT-FOREIGN'],
        ));

        $this->apiGet("/budget/budget-transfers/{$transfer->id}")
            ->assertOk()
            ->assertJsonPath('data.requester.name', $this->user->name);

        $this->apiGet("/budget/budget-transfers/{$foreign->id}")->assertNotFound();
    }

    public function test_the_list_shows_requesters_by_name_only(): void
    {
        $this->submittedTransfer(100);

        $requester = $this->apiGet('/budget/budget-transfers')->assertOk()->json('data.data.0.requester');

        $this->assertSame(['id', 'name'], array_keys($requester));
    }

    public function test_approving_from_two_stale_copies_posts_the_transfer_once(): void
    {
        $this->actingAs($this->user, 'api');
        $transfer = $this->submittedTransfer(300);
        $service = app(BudgetTransferService::class);

        $first = BudgetTransfer::find($transfer->id);
        $second = BudgetTransfer::find($transfer->id);

        $service->approve($first, $this->user);

        try {
            $service->approve($second, $this->user);
            $this->fail('A transfer already posted was approved again.');
        } catch (\LogicException) {
            // Refused on the locked row.
        }

        $this->assertEquals(700, $this->from->fresh()->total_amount);
        $this->assertEquals(500, $this->to->fresh()->total_amount);
    }

    private function submittedTransfer(float $amount): BudgetTransfer
    {
        return BudgetTransfer::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'transfer_number' => 'BT-'.uniqid(),
            'from_budget_id' => $this->from->budget_id,
            'from_budget_line_id' => $this->from->id,
            'to_budget_id' => $this->to->budget_id,
            'to_budget_line_id' => $this->to->id,
            'amount' => $amount,
            'reason' => 'Reallocation',
            'status' => BudgetTransfer::STATUS_SUBMITTED,
            'requested_by' => $this->user->id,
        ]);
    }

    private function budget(int $organizationId): Budget
    {
        return Budget::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'name' => 'Operations',
            'status' => Budget::STATUS_ACTIVE,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'total_amount' => 0,
        ]);
    }

    private function line(Budget $budget, float $total): BudgetLine
    {
        return BudgetLine::create([
            'budget_id' => $budget->id,
            'name' => 'Line '.uniqid(),
            'total_amount' => $total,
            'committed_amount' => 0,
            'actual_amount' => 0,
        ]);
    }
}
