<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\OffCyclePayrollItem;
use App\Models\HR\OffCyclePayrollRun;
use App\Services\HR\OffCyclePayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * Off-cycle payroll runs: a draft collects items, then is processed once into
 * totals or cancelled, and only inside the organization that owns it.
 */
class OffCyclePayrollTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/off-cycle-payroll';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.payroll.view', 'hr.payroll.process']);
    }

    public function test_runs_are_listed_latest_run_date_first_within_the_organization(): void
    {
        $this->offCycleRun(['run_date' => '2026-01-10', 'run_name' => 'January bonus']);
        $this->offCycleRun(['run_date' => '2026-03-10', 'run_name' => 'March bonus']);
        $this->offCycleRun(['run_name' => 'Theirs'], Organization::factory()->create());

        $response = $this->apiGet($this->baseUrl);

        $this->assertPaginatedResponse($response);
        $this->assertSame(['March bonus', 'January bonus'], array_column($response->json('data'), 'run_name'));
    }

    public function test_a_created_run_starts_as_a_draft(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'run_type' => 'bonus',
            'run_name' => 'Eid bonus',
            'run_date' => '2026-04-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', OffCyclePayrollRun::STATUS_DRAFT)
            ->assertJsonPath('data.run_name', 'Eid bonus')
            ->assertJsonPath('data.processor', null);
    }

    public function test_a_run_shows_its_items_with_their_employees(): void
    {
        $run = $this->offCycleRun();
        $employee = $this->employee();
        $this->item($run, $employee, 250);

        $this->apiGet("{$this->baseUrl}/{$run->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonPath('data.items.0.employee.id', $employee->id);
    }

    public function test_another_organizations_run_is_not_found(): void
    {
        $theirs = $this->offCycleRun([], Organization::factory()->create());

        $this->apiGet("{$this->baseUrl}/{$theirs->id}")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/{$theirs->id}/process")->assertNotFound();
    }

    public function test_only_a_draft_run_can_be_updated(): void
    {
        $draft = $this->offCycleRun();
        $completed = $this->offCycleRun(['status' => OffCyclePayrollRun::STATUS_COMPLETED]);

        $this->apiPut("{$this->baseUrl}/{$draft->id}", ['run_name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.run_name', 'Renamed');

        $this->apiPut("{$this->baseUrl}/{$completed->id}", ['run_name' => 'Renamed'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_only_a_draft_run_can_be_deleted(): void
    {
        $draft = $this->offCycleRun();
        $completed = $this->offCycleRun(['status' => OffCyclePayrollRun::STATUS_COMPLETED]);

        $this->apiDelete("{$this->baseUrl}/{$draft->id}")->assertNoContent();
        $this->assertSoftDeleted($draft);

        $this->apiDelete("{$this->baseUrl}/{$completed->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_items_are_added_to_and_removed_from_a_draft(): void
    {
        $run = $this->offCycleRun();
        $employee = $this->employee();

        $response = $this->apiPost("{$this->baseUrl}/{$run->id}/items", $this->itemPayload($employee));

        $response->assertStatus(201)->assertJsonPath('data.employee.id', $employee->id);

        $itemId = $response->json('data.id');

        $this->apiDelete("{$this->baseUrl}/{$run->id}/items/{$itemId}")->assertNoContent();
        $this->assertDatabaseMissing('off_cycle_payroll_items', ['id' => $itemId]);
    }

    public function test_an_item_cannot_name_another_organizations_employee(): void
    {
        $run = $this->offCycleRun();
        $theirs = $this->employee(Organization::factory()->create());

        $this->apiPost("{$this->baseUrl}/{$run->id}/items", $this->itemPayload($theirs))
            ->assertStatus(422);

        $this->assertDatabaseMissing('off_cycle_payroll_items', ['employee_id' => $theirs->id]);
    }

    public function test_processing_totals_the_items_and_completes_the_run(): void
    {
        $run = $this->offCycleRun();
        $employee = $this->employee();
        $this->item($run, $employee, 100);
        $this->item($run, $employee, 200);

        $this->apiPost("{$this->baseUrl}/{$run->id}/process")
            ->assertOk()
            ->assertJsonPath('data.status', OffCyclePayrollRun::STATUS_COMPLETED)
            ->assertJsonPath('data.employee_count', 1)
            ->assertJsonPath('data.total_gross', '300.0000')
            ->assertJsonPath('data.processor.id', $this->user->id)
            ->assertJsonPath('data.items.0.employee.id', $employee->id);

        $this->apiPost("{$this->baseUrl}/{$run->id}/process")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_draft_is_cancelled_but_a_completed_run_is_not(): void
    {
        $draft = $this->offCycleRun();
        $completed = $this->offCycleRun(['status' => OffCyclePayrollRun::STATUS_COMPLETED]);

        $this->apiPost("{$this->baseUrl}/{$draft->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OffCyclePayrollRun::STATUS_CANCELLED);

        $this->apiPost("{$this->baseUrl}/{$completed->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_run_processed_through_a_stale_copy_is_rejected(): void
    {
        $this->actingAs($this->user, 'api');
        $service = app(OffCyclePayrollService::class);

        $run = $this->offCycleRun();
        $this->item($run, $this->employee(), 100);
        $stale = OffCyclePayrollRun::findOrFail($run->id);

        $service->cancel($run);

        $this->assertRejected(fn () => $service->process($stale));
        $this->assertSame(OffCyclePayrollRun::STATUS_CANCELLED, $run->fresh()->status);
    }

    private function offCycleRun(array $overrides = [], ?Organization $organization = null): OffCyclePayrollRun
    {
        return OffCyclePayrollRun::create(array_merge([
            'organization_id' => ($organization ?? $this->organization)->id,
            'run_type' => OffCyclePayrollRun::RUN_TYPE_BONUS,
            'run_name' => 'Bonus run',
            'run_date' => '2026-02-01',
            'status' => OffCyclePayrollRun::STATUS_DRAFT,
        ], $overrides));
    }

    private function employee(?Organization $organization = null): Employee
    {
        return Employee::factory()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'branch_id' => $organization === null ? $this->branch->id : null,
        ]);
    }

    private function item(OffCyclePayrollRun $run, Employee $employee, float $amount): OffCyclePayrollItem
    {
        return OffCyclePayrollItem::create([
            'organization_id' => $run->organization_id,
            'off_cycle_payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'component_code' => 'BONUS',
            'component_name' => 'Bonus',
            'amount' => $amount,
            'net_amount' => $amount,
        ]);
    }

    /** @return array<string, mixed> */
    private function itemPayload(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'component_code' => 'BONUS',
            'component_name' => 'Bonus',
            'amount' => 500,
            'net_amount' => 500,
        ];
    }
}
