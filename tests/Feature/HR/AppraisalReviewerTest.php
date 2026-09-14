<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\AppraisalCycle;
use App\Models\HR\AppraisalReviewer;
use App\Models\HR\Employee;
use App\Models\HR\PerformanceAppraisal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Reviewers are added to an appraisal's 360° review cycle.
 */
class AppraisalReviewerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private PerformanceAppraisal $appraisal;

    private Employee $peer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['hr.appraisals.view', 'hr.appraisals.manage']);

        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $this->peer = Employee::factory()->create(['organization_id' => $this->organization->id]);

        $cycle = AppraisalCycle::forceCreate([
            'organization_id' => $this->organization->id,
            'name' => 'Annual review',
            'review_period_start' => '2026-01-01',
            'review_period_end' => '2026-12-31',
        ]);

        $this->appraisal = PerformanceAppraisal::forceCreate([
            'organization_id' => $this->organization->id,
            'appraisal_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_adding_a_reviewer_creates_a_pending_review_once(): void
    {
        $payload = ['reviewers' => [['employee_id' => $this->peer->id, 'type' => AppraisalReviewer::TYPE_PEER]]];

        $this->apiPost("/hr/appraisals/{$this->appraisal->id}/reviewers", $payload)->assertOk();
        $this->apiPost("/hr/appraisals/{$this->appraisal->id}/reviewers", $payload)->assertOk();

        $reviewer = AppraisalReviewer::sole();
        $this->assertSame($this->appraisal->id, $reviewer->appraisal_id);
        $this->assertSame($this->peer->id, $reviewer->reviewer_id);
        $this->assertSame(AppraisalReviewer::STATUS_PENDING, $reviewer->status);

        $this->apiGet("/hr/appraisals/{$this->appraisal->id}/reviewers")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
