<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\AppraisalCycle;
use App\Models\HR\AppraisalReviewer;
use App\Models\HR\AppraisalTemplate;
use App\Models\HR\AppraisalTemplateQuestion;
use App\Models\HR\AppraisalTemplateSection;
use App\Models\HR\Employee;
use App\Models\HR\PerformanceAppraisal;
use App\Models\HR\PerformanceGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Appraisal cycles, templates, appraisals, goals and 360° reviewers: listings,
 * lookups and the references a goal, review or reviewer may make, inside the
 * caller's organization and the appraisal's own template.
 */
class PerformanceEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/performance';

    private Organization $otherOrganization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.performance.cycles.view', 'hr.performance.cycles.create', 'hr.performance.cycles.edit', 'hr.performance.cycles.manage',
            'hr.performance.templates.view', 'hr.performance.templates.create', 'hr.performance.templates.edit', 'hr.performance.templates.delete',
            'hr.performance.appraisals.view', 'hr.performance.appraisals.self-review', 'hr.performance.appraisals.manager-review',
            'hr.performance.appraisals.acknowledge',
            'hr.performance.goals.view', 'hr.performance.goals.create', 'hr.performance.goals.edit', 'hr.performance.goals.delete',
            'hr.performance.goals.update-progress',
            'hr.appraisals.view', 'hr.appraisals.manage', 'hr.appraisals.review',
        ]);

        $this->otherOrganization = Organization::factory()->create();
        $this->employee = $this->employee($this->organization);
    }

    public function test_cycles_are_filtered_by_status_and_name(): void
    {
        $wanted = $this->cycle($this->organization, 'Annual 2026', AppraisalCycle::STATUS_ACTIVE);
        $this->cycle($this->organization, 'Annual 2025', AppraisalCycle::STATUS_COMPLETED);
        $this->cycle($this->organization, 'Probation', AppraisalCycle::STATUS_ACTIVE);
        $this->cycle($this->otherOrganization, 'Annual theirs', AppraisalCycle::STATUS_ACTIVE);

        $response = $this->apiGet("{$this->baseUrl}/cycles?status=active&search=Annual");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_cycle_is_not_found(): void
    {
        $theirs = $this->cycle($this->otherOrganization, 'Theirs', AppraisalCycle::STATUS_DRAFT);

        $this->apiGet("{$this->baseUrl}/cycles/{$theirs->id}")->assertNotFound();
        $this->apiGet("{$this->baseUrl}/cycles/{$theirs->id}/statistics")->assertNotFound();
    }

    public function test_cycle_statistics_count_the_cycles_appraisals(): void
    {
        $cycle = $this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE);
        $this->appraisal($cycle, $this->employee);
        $this->appraisal($cycle, $this->employee($this->organization), null, PerformanceAppraisal::STATUS_COMPLETED);

        $this->apiGet("{$this->baseUrl}/cycles/{$cycle->id}/statistics")
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    public function test_templates_are_filtered_to_active_by_name(): void
    {
        $this->template($this->organization, 'Sales');
        $this->template($this->organization, 'Engineering');
        $this->template($this->organization, 'Retired', ['is_active' => false]);

        $response = $this->apiGet("{$this->baseUrl}/templates?active_only=1");

        $this->assertSame(['Engineering', 'Sales'], array_column($response->json('data'), 'name'));
    }

    /**
     * One default per organization: marking a template default clears the
     * flag on the organization's other templates and on no one else's.
     */
    public function test_marking_a_template_default_clears_the_organizations_other_default(): void
    {
        $previous = $this->template($this->organization, 'Old default', ['is_default' => true]);
        $theirs = $this->template($this->otherOrganization, 'Their default', ['is_default' => true]);
        $template = $this->template($this->organization, 'New default');

        $this->apiPut("{$this->baseUrl}/templates/{$template->id}", ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($previous->fresh()->is_default);
        $this->assertTrue(AppraisalTemplate::withoutGlobalScopes()->find($theirs->id)->is_default);
    }

    public function test_a_template_used_by_an_open_cycle_is_not_deleted(): void
    {
        $template = $this->template($this->organization, 'In use');
        $this->appraisal($this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE), $this->employee, $template);

        $this->deleteJson("/api/v1{$this->baseUrl}/templates/{$template->id}", [], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TEMPLATE_IN_USE');

        $this->assertNotNull($template->fresh());
    }

    public function test_appraisals_are_filtered_by_cycle_and_status(): void
    {
        $cycle = $this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE);
        $wanted = $this->appraisal($cycle, $this->employee);
        $this->appraisal($cycle, $this->employee($this->organization), null, PerformanceAppraisal::STATUS_COMPLETED);
        $this->appraisal($this->cycle($this->organization, 'Other', AppraisalCycle::STATUS_ACTIVE), $this->employee);

        $response = $this->apiGet("{$this->baseUrl}/appraisals?appraisal_cycle_id={$cycle->id}&status=pending");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.employee.id', $this->employee->id);
    }

    public function test_a_self_review_answers_the_appraisals_own_questions(): void
    {
        $template = $this->template($this->organization, 'Annual');
        $question = $this->question($template);
        $appraisal = $this->appraisal($this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE), $this->employee, $template);

        $this->apiPost("{$this->baseUrl}/appraisals/{$appraisal->id}/self-review", [
            'responses' => [['question_id' => $question->id, 'rating' => 4]],
        ])->assertOk();

        $this->assertSame(PerformanceAppraisal::STATUS_SELF_REVIEW_SUBMITTED, $appraisal->fresh()->status);
    }

    /**
     * Question rows carry no organization; the only questions an appraisal can
     * be answered on are those of its own template.
     */
    public function test_a_review_cannot_answer_a_question_of_another_template(): void
    {
        $template = $this->template($this->organization, 'Annual');
        $this->question($template);
        $foreign = $this->question($this->template($this->otherOrganization, 'Theirs'));
        $appraisal = $this->appraisal($this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE), $this->employee, $template);

        foreach (['self-review', 'manager-review'] as $review) {
            $this->apiPost("{$this->baseUrl}/appraisals/{$appraisal->id}/{$review}", [
                'responses' => [['question_id' => $foreign->id, 'rating' => 4]],
            ])->assertStatus(422)->assertJsonValidationErrors('responses.0.question_id');
        }

        $this->assertSame(PerformanceAppraisal::STATUS_PENDING, $appraisal->fresh()->status);
    }

    public function test_goals_are_filtered_by_employee(): void
    {
        $wanted = $this->goal($this->employee, 'Close Q1');
        $this->goal($this->employee($this->organization), 'Someone else');

        $response = $this->apiGet("{$this->baseUrl}/goals?employee_id={$this->employee->id}");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_goal_cannot_name_another_organizations_employee_or_cycle(): void
    {
        $this->apiPost("{$this->baseUrl}/goals", [
            'employee_id' => $this->employee($this->otherOrganization)->id,
            'title' => 'Grow',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->apiPost("{$this->baseUrl}/goals", [
            'employee_id' => $this->employee->id,
            'appraisal_cycle_id' => $this->cycle($this->otherOrganization, 'Theirs', AppraisalCycle::STATUS_ACTIVE)->id,
            'title' => 'Grow',
        ])->assertStatus(422)->assertJsonValidationErrors('appraisal_cycle_id');

        $this->apiPost("{$this->baseUrl}/goals", ['employee_id' => $this->employee->id, 'title' => 'Grow'])->assertCreated();
        $this->assertSame(1, PerformanceGoal::withoutGlobalScopes()->count());
    }

    public function test_goal_progress_is_recorded(): void
    {
        $goal = $this->goal($this->employee, 'Close Q1');

        $this->apiPost("{$this->baseUrl}/goals/{$goal->id}/update-progress", ['progress_percent' => 40])
            ->assertOk()
            ->assertJsonPath('data.goal.progress_percent', 40);
    }

    public function test_a_reviewer_cannot_be_another_organizations_employee(): void
    {
        $appraisal = $this->appraisal($this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE), $this->employee);

        $this->apiPost("/hr/appraisals/{$appraisal->id}/reviewers", [
            'reviewers' => [['employee_id' => $this->employee($this->otherOrganization)->id, 'type' => AppraisalReviewer::TYPES[0]]],
        ])->assertStatus(422)->assertJsonValidationErrors('reviewers.0.employee_id');

        $this->assertSame(0, AppraisalReviewer::count());
    }

    public function test_a_reviewer_of_another_appraisal_is_not_found(): void
    {
        $cycle = $this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE);
        $appraisal = $this->appraisal($cycle, $this->employee);
        $other = $this->appraisal($cycle, $this->employee($this->organization));
        $reviewer = AppraisalReviewer::create([
            'appraisal_id' => $other->id,
            'reviewer_id' => $this->employee->id,
            'reviewer_type' => AppraisalReviewer::TYPES[0],
            'status' => AppraisalReviewer::STATUS_PENDING,
        ]);

        $this->apiPost("/hr/appraisals/{$appraisal->id}/reviewers/{$reviewer->id}/decline")->assertNotFound();
        $this->assertSame(AppraisalReviewer::STATUS_PENDING, $reviewer->fresh()->status);
    }

    public function test_a_360_review_cannot_answer_a_question_of_another_template(): void
    {
        $template = $this->template($this->organization, 'Annual');
        $this->question($template);
        $foreign = $this->question($this->template($this->otherOrganization, 'Theirs'));
        $appraisal = $this->appraisal($this->cycle($this->organization, 'Annual', AppraisalCycle::STATUS_ACTIVE), $this->employee, $template);
        $reviewer = AppraisalReviewer::create([
            'appraisal_id' => $appraisal->id,
            'reviewer_id' => $this->employee($this->organization)->id,
            'reviewer_type' => AppraisalReviewer::TYPES[0],
            'status' => AppraisalReviewer::STATUS_PENDING,
        ]);

        $this->apiPost("/hr/appraisals/{$appraisal->id}/reviewers/{$reviewer->id}/submit", [
            'overall_rating' => 4,
            'responses' => [['question_id' => $foreign->id, 'rating' => 4]],
        ])->assertStatus(422)->assertJsonValidationErrors('responses.0.question_id');

        $this->assertSame(AppraisalReviewer::STATUS_PENDING, $reviewer->fresh()->status);
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function cycle(Organization $organization, string $name, string $status): AppraisalCycle
    {
        return AppraisalCycle::forceCreate([
            'organization_id' => $organization->id,
            'name' => $name,
            'review_period_start' => '2026-01-01',
            'review_period_end' => '2026-12-31',
            'status' => $status,
        ]);
    }

    private function template(Organization $organization, string $name, array $overrides = []): AppraisalTemplate
    {
        return AppraisalTemplate::create(array_merge([
            'organization_id' => $organization->id,
            'name' => $name,
            'rating_scale' => 5,
            'is_default' => false,
            'is_active' => true,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    private function question(AppraisalTemplate $template): AppraisalTemplateQuestion
    {
        $section = AppraisalTemplateSection::create([
            'appraisal_template_id' => $template->id,
            'name' => 'Delivery',
            'weight_percent' => 100,
        ]);

        return AppraisalTemplateQuestion::create([
            'appraisal_template_section_id' => $section->id,
            'question' => 'Did they deliver?',
            'question_type' => 'rating',
        ]);
    }

    private function appraisal(
        AppraisalCycle $cycle,
        Employee $employee,
        ?AppraisalTemplate $template = null,
        string $status = PerformanceAppraisal::STATUS_PENDING,
    ): PerformanceAppraisal {
        return PerformanceAppraisal::forceCreate([
            'organization_id' => $cycle->organization_id,
            'appraisal_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'appraisal_template_id' => $template?->id,
            'status' => $status,
        ]);
    }

    private function goal(Employee $employee, string $title): PerformanceGoal
    {
        return PerformanceGoal::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'title' => $title,
            'status' => PerformanceGoal::STATUS_ACTIVE,
            'progress_percent' => 0,
            'created_by' => $this->user->id,
        ]);
    }
}
