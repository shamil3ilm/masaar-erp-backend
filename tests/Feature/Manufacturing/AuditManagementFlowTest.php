<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\AuditChecklist;
use App\Models\Manufacturing\AuditFinding;
use App\Models\Manufacturing\AuditPlan;
use App\Models\Manufacturing\AuditReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Audit plans are reached only within the organization, their checklist items
 * and findings only through their own plan, and a report completes its plan
 * in the same transaction.
 */
class AuditManagementFlowTest extends TestCase
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

    public function test_a_report_completes_its_plan(): void
    {
        $plan = $this->plan();

        $this->apiPost("/manufacturing/audit-plans/{$plan->id}/report", $this->report())
            ->assertCreated()
            ->assertJsonPath('data.audit_plan_id', $plan->id);

        $this->assertSame('completed', $plan->fresh()->status);
    }

    public function test_a_report_is_not_kept_when_completing_the_plan_fails(): void
    {
        $plan = $this->plan();

        AuditPlan::updating(function (): void {
            throw new RuntimeException('Completing the plan failed.');
        });

        $this->apiPost("/manufacturing/audit-plans/{$plan->id}/report", $this->report())->assertStatus(500);

        $this->assertSame(0, AuditReport::where('audit_plan_id', $plan->id)->count());
    }

    public function test_checklist_items_and_findings_are_reached_only_through_their_own_plan(): void
    {
        $plan = $this->plan();
        $other = $this->plan();
        $item = AuditChecklist::create(['audit_plan_id' => $plan->id, 'item_number' => '1.1', 'question' => 'Calibrated?']);
        $finding = AuditFinding::create([
            'audit_plan_id' => $plan->id,
            'finding_number' => 'F-1',
            'finding_type' => 'minor_nc',
            'description' => 'Label missing',
        ]);

        $this->apiPut("/manufacturing/audit-plans/{$other->id}/checklists/{$item->id}", ['response' => 'yes'])->assertNotFound();
        $this->apiPost("/manufacturing/audit-plans/{$other->id}/findings/{$finding->id}/close")->assertNotFound();

        $this->apiPut("/manufacturing/audit-plans/{$plan->id}/checklists/{$item->id}", ['response' => 'partial'])
            ->assertOk()
            ->assertJsonPath('data.response', 'partial');
        $this->apiPost("/manufacturing/audit-plans/{$plan->id}/findings/{$finding->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->apiGet("/manufacturing/audit-plans/{$plan->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.checklists')
            ->assertJsonCount(1, 'data.findings');
    }

    public function test_another_organizations_lead_auditor_is_refused(): void
    {
        $this->apiPost('/manufacturing/audit-plans', [
            'plan_number' => 'AUD-X-1',
            'title' => 'Supplier audit',
            'audit_type' => 'supplier',
            'planned_start' => '2026-04-01',
            'planned_end' => '2026-04-02',
            'lead_auditor_id' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['lead_auditor_id']);
    }

    public function test_another_organizations_plan_is_not_found(): void
    {
        $theirs = AuditPlan::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/manufacturing/audit-plans/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/audit-plans/{$theirs->id}/checklists", ['item_number' => '1', 'question' => 'Q'])
            ->assertNotFound();
        $this->apiPost("/manufacturing/audit-plans/{$theirs->id}/findings", [
            'finding_number' => 'F-1',
            'finding_type' => 'observation',
            'description' => 'Note',
        ])->assertNotFound();
        $this->apiPost("/manufacturing/audit-plans/{$theirs->id}/report", $this->report())->assertNotFound();

        $this->assertSame(0, AuditReport::where('audit_plan_id', $theirs->id)->count());
    }

    private function plan(): AuditPlan
    {
        return AuditPlan::factory()->create(['organization_id' => $this->organization->id, 'status' => 'in_progress']);
    }

    private function report(): array
    {
        return [
            'report_date' => '2026-04-03',
            'executive_summary' => 'Two minor findings.',
            'overall_rating' => 'satisfactory',
        ];
    }
}
