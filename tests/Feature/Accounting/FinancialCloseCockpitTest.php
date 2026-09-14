<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FinancialCloseTask;
use App\Models\Accounting\FinancialClosePeriod;
use App\Models\Accounting\FinancialCloseTemplate;
use App\Models\Accounting\FinancialCloseTemplateTask;
use App\Services\Accounting\FinancialCloseCockpitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class FinancialCloseCockpitTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private FinancialCloseCockpitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->service = app(FinancialCloseCockpitService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeTemplate(int $taskCount = 3): FinancialCloseTemplate
    {
        $template = FinancialCloseTemplate::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        for ($i = 1; $i <= $taskCount; $i++) {
            FinancialCloseTemplateTask::factory()->create([
                'financial_close_template_id' => $template->id,
                'task_name'                   => "Task {$i}",
                'sort_order'                  => $i,
            ]);
        }

        return $template->load('tasks');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getPeriodProgress() — existing, regression guard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_period_progress_returns_correct_counts(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => FinancialClosePeriod::STATUS_OPEN,
        ]);

        FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);
        FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_COMPLETED,
        ]);

        $progress = $this->service->getPeriodProgress($period);

        $this->assertEquals(2, $progress['total']);
        $this->assertEquals(1, $progress['pending']);
        $this->assertEquals(1, $progress['completed']);
        $this->assertEquals(50.0, $progress['percent_complete']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // schedulePeriod() — FI-10 automated scheduling
    // ─────────────────────────────────────────────────────────────────────────

    public function test_schedule_period_creates_period_with_tasks(): void
    {
        $template = $this->makeTemplate(3);

        $period = $this->service->schedulePeriod([
            'organization_id'             => $this->organization->id,
            'financial_close_template_id' => $template->id,
            'fiscal_year'                 => 2025,
            'period'                      => 3,
            'close_type'                  => 'month_end',
            'due_date'                    => '2025-03-31',
        ]);

        $this->assertEquals(3, $period->tasks->count());
    }

    public function test_schedule_period_assigns_due_dates_to_tasks(): void
    {
        $template = $this->makeTemplate(3);

        $period = $this->service->schedulePeriod([
            'organization_id'             => $this->organization->id,
            'financial_close_template_id' => $template->id,
            'fiscal_year'                 => 2025,
            'period'                      => 3,
            'close_type'                  => 'month_end',
            'due_date'                    => '2025-03-31',
        ]);

        $dueDates = $period->tasks->pluck('due_date')->filter()->values();

        $this->assertEquals(3, $dueDates->count());
    }

    public function test_schedule_period_last_task_has_period_due_date(): void
    {
        $template = $this->makeTemplate(3);

        $period = $this->service->schedulePeriod([
            'organization_id'             => $this->organization->id,
            'financial_close_template_id' => $template->id,
            'fiscal_year'                 => 2025,
            'period'                      => 3,
            'close_type'                  => 'month_end',
            'due_date'                    => '2025-03-31',
        ]);

        $tasks   = $period->tasks->sortBy('sort_order')->values();
        $lastDue = $tasks->last()->due_date;

        $this->assertEquals('2025-03-31', $lastDue->format('Y-m-d'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // blockDependents() — FI-10 dependency chain enforcement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_block_dependents_marks_downstream_tasks_blocked(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $critical = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'task_name'                 => 'Critical',
            'status'                    => FinancialCloseTask::STATUS_IN_PROGRESS,
        ]);

        $dependent = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'task_name'                 => 'Dependent',
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);

        // Wire dependency: dependent depends on critical
        \Illuminate\Support\Facades\DB::table('financial_close_task_dependencies')->insert([
            'financial_close_task_id' => $dependent->id,
            'depends_on_task_id'      => $critical->id,
        ]);

        $this->service->blockDependents($critical);

        $this->assertEquals(
            FinancialCloseTask::STATUS_BLOCKED,
            $dependent->fresh()->status
        );
    }

    public function test_block_dependents_does_not_affect_in_progress_tasks(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $critical = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_IN_PROGRESS,
        ]);

        $inProgress = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_IN_PROGRESS,
        ]);

        \Illuminate\Support\Facades\DB::table('financial_close_task_dependencies')->insert([
            'financial_close_task_id' => $inProgress->id,
            'depends_on_task_id'      => $critical->id,
        ]);

        $this->service->blockDependents($critical);

        // In-progress tasks should not be changed
        $this->assertEquals(
            FinancialCloseTask::STATUS_IN_PROGRESS,
            $inProgress->fresh()->status
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // propagateBlocked() — unblocking when dependencies resolve
    // ─────────────────────────────────────────────────────────────────────────

    public function test_propagate_blocked_restores_pending_when_dependencies_done(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $dep1 = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_COMPLETED,
        ]);

        $dep2 = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_COMPLETED,
        ]);

        $blocked = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_BLOCKED,
        ]);

        \Illuminate\Support\Facades\DB::table('financial_close_task_dependencies')->insert([
            ['financial_close_task_id' => $blocked->id, 'depends_on_task_id' => $dep1->id],
            ['financial_close_task_id' => $blocked->id, 'depends_on_task_id' => $dep2->id],
        ]);

        $this->service->propagateBlocked($dep2);

        $this->assertEquals(
            FinancialCloseTask::STATUS_PENDING,
            $blocked->fresh()->status
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Existing task lifecycle — regression guard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_start_task_transitions_pending_to_in_progress(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $task = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);

        $this->service->startTask($task, $this->user->id);

        $this->assertEquals(FinancialCloseTask::STATUS_IN_PROGRESS, $task->fresh()->status);
    }

    public function test_close_period_requires_all_tasks_done(): void
    {
        $period = FinancialClosePeriod::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->closePeriod($period, $this->user->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP endpoints
    // ─────────────────────────────────────────────────────────────────────────

    private function actAsCloseManager(): void
    {
        $this->setUpAuthenticatedUser([
            'accounting.financial-close.view',
            'accounting.financial-close.manage',
        ]);
    }

    private function makePeriod(int $organizationId, array $overrides = []): FinancialClosePeriod
    {
        return FinancialClosePeriod::factory()->create(array_merge([
            'organization_id' => $organizationId,
        ], $overrides));
    }

    public function test_templates_endpoint_lists_active_templates_with_tasks(): void
    {
        $this->actAsCloseManager();
        $this->makeTemplate(2);
        FinancialCloseTemplate::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active'       => false,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/financial-close/templates?active_only=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.tasks');
    }

    public function test_store_template_creates_template_with_tasks(): void
    {
        $this->actAsCloseManager();

        $this->withToken($this->token)
            ->postJson('/api/v1/financial-close/templates', [
                'name'       => 'Month end',
                'close_type' => 'month_end',
                'tasks'      => [
                    ['task_name' => 'Accruals', 'task_type' => 'journal'],
                    ['task_name' => 'Bank rec', 'task_type' => 'reconciliation', 'sort_order' => 7],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Month end')
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonCount(2, 'data.tasks');

        $sortOrders = FinancialCloseTemplateTask::orderBy('sort_order')->pluck('sort_order')->all();
        $this->assertSame([0, 7], $sortOrders);
    }

    public function test_store_template_keeps_nothing_when_a_task_fails(): void
    {
        $this->actAsCloseManager();

        $created = 0;
        FinancialCloseTemplateTask::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new RuntimeException('Simulated failure on the second template task.');
            }
        });

        $this->withToken($this->token)
            ->postJson('/api/v1/financial-close/templates', [
                'name'       => 'Month end',
                'close_type' => 'month_end',
                'tasks'      => [
                    ['task_name' => 'Accruals', 'task_type' => 'journal'],
                    ['task_name' => 'Bank rec', 'task_type' => 'reconciliation'],
                ],
            ])
            ->assertStatus(500);

        $this->assertSame(0, FinancialCloseTemplate::withTrashed()->count());
        $this->assertSame(0, FinancialCloseTemplateTask::count());
    }

    public function test_periods_endpoint_filters_and_paginates(): void
    {
        $this->actAsCloseManager();
        $this->makePeriod($this->organization->id, ['fiscal_year' => 2025, 'period' => 1, 'status' => FinancialClosePeriod::STATUS_OPEN]);
        $this->makePeriod($this->organization->id, ['fiscal_year' => 2025, 'period' => 2, 'status' => FinancialClosePeriod::STATUS_CLOSED]);
        $this->makePeriod($this->organization->id, ['fiscal_year' => 2024, 'period' => 3, 'status' => FinancialClosePeriod::STATUS_OPEN]);

        $this->withToken($this->token)
            ->getJson('/api/v1/financial-close/periods?fiscal_year=2025&status=open')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', 1)
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_show_period_includes_tasks(): void
    {
        $this->actAsCloseManager();
        $period = $this->makePeriod($this->organization->id);
        FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'assigned_to'               => $this->user->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/financial-close/periods/' . $period->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.assigned_to.id', $this->user->id);
    }

    public function test_start_task_endpoint_returns_the_started_task(): void
    {
        $this->actAsCloseManager();
        $period = $this->makePeriod($this->organization->id);
        $task   = FinancialCloseTask::factory()->create(['financial_close_period_id' => $period->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/financial-close/tasks/' . $task->id . '/start')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Task started.')
            ->assertJsonPath('data.status', FinancialCloseTask::STATUS_IN_PROGRESS);
    }

    public function test_skip_task_endpoint_rejects_an_invalid_transition(): void
    {
        $this->actAsCloseManager();
        $period = $this->makePeriod($this->organization->id);
        $task   = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $period->id,
            'status'                    => FinancialCloseTask::STATUS_COMPLETED,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/financial-close/tasks/' . $task->id . '/skip', ['reason' => 'n/a'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TRANSITION');
    }

    public function test_task_endpoints_return_404_for_another_organizations_task(): void
    {
        $this->actAsCloseManager();
        $otherOrg    = \App\Models\Core\Organization::factory()->create();
        $otherPeriod = $this->makePeriod($otherOrg->id);
        $otherTask   = FinancialCloseTask::factory()->create([
            'financial_close_period_id' => $otherPeriod->id,
            'status'                    => FinancialCloseTask::STATUS_PENDING,
        ]);
        $base = '/api/v1/financial-close/tasks/' . $otherTask->id;

        $this->withToken($this->token)->postJson($base . '/start')->assertStatus(404);
        $this->withToken($this->token)->postJson($base . '/complete')->assertStatus(404);
        $this->withToken($this->token)->postJson($base . '/skip', ['reason' => 'x'])->assertStatus(404);
        $this->withToken($this->token)->putJson($base . '/assign', ['assigned_to' => $this->user->id])->assertStatus(404);

        $fresh = $otherTask->fresh();
        $this->assertSame(FinancialCloseTask::STATUS_PENDING, $fresh->status);
        $this->assertNull($fresh->assigned_to);
    }

    public function test_period_endpoints_return_404_for_another_organizations_period(): void
    {
        $this->actAsCloseManager();
        $otherOrg    = \App\Models\Core\Organization::factory()->create();
        $otherPeriod = $this->makePeriod($otherOrg->id);
        $base        = '/api/v1/financial-close/periods/' . $otherPeriod->id;

        $this->withToken($this->token)->getJson($base)->assertStatus(404);
        $this->withToken($this->token)->getJson($base . '/progress')->assertStatus(404);
        $this->withToken($this->token)->postJson($base . '/close')->assertStatus(404);
        $this->withToken($this->token)->postJson($base . '/sign-off')->assertStatus(404);
    }
}
