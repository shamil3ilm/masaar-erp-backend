<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\CompensationReview;
use App\Models\HR\CompensationReviewItem;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\SalaryComponent;
use App\Models\HR\SalaryStructure;
use App\Models\HR\SalaryStructureComponent;
use App\Services\HR\CompensationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Applying an approved compensation review could not apply anything.
 *
 * It created the new salary with basic_salary, organization_id and notes -
 * none of them columns - and without the salary structure the table requires,
 * then marked each item with a status constant that did not exist, into a
 * column that did not allow it.
 */
class CompensationServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private SalaryStructure $structure;

    private SalaryComponent $basic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->structure = SalaryStructure::factory()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
        ]);

        $this->basic = SalaryComponent::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'BASIC',
            'type' => SalaryComponent::TYPE_EARNING,
            'category' => SalaryComponent::CATEGORY_BASIC,
            'calculation_type' => 'fixed',
            'default_value' => 0,
        ]);

        SalaryStructureComponent::create([
            'salary_structure_id' => $this->structure->id,
            'salary_component_id' => $this->basic->id,
            'calculation_type' => 'fixed',
            'value' => 0,
        ]);
    }

    public function test_applying_a_review_puts_the_proposed_salary_on_the_basic_component(): void
    {
        $employee = $this->employeeOnStructure();
        [$review, $item] = $this->approvedReviewFor($employee, 6000);

        $applied = app(CompensationService::class)->apply($review);

        $this->assertSame(1, $applied);

        $current = $employee->fresh()->currentSalary;
        $this->assertNotNull($current);
        $this->assertSame($this->structure->id, $current->salary_structure_id);
        $this->assertEquals(
            6000.0,
            (float) $current->components()->where('salary_component_id', $this->basic->id)->value('amount')
        );

        // The salary it replaced is history, not a second current salary.
        $this->assertSame(1, EmployeeSalary::where('employee_id', $employee->id)->where('is_current', true)->count());

        $this->assertSame(CompensationReviewItem::STATUS_APPLIED, $item->fresh()->status);
        $this->assertSame(CompensationReview::STATUS_APPLIED, $review->fresh()->status);
    }

    public function test_an_employee_with_no_salary_structure_is_skipped_rather_than_failing_the_review(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        [$review, $item] = $this->approvedReviewFor($employee, 6000);

        $applied = app(CompensationService::class)->apply($review);

        $this->assertSame(0, $applied);
        $this->assertNull($employee->fresh()->currentSalary);
        $this->assertSame(CompensationReviewItem::STATUS_APPROVED, $item->fresh()->status);
    }

    private function employeeOnStructure(): Employee
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $this->structure->id,
            'currency_code' => 'SAR',
            'is_current' => true,
        ]);

        return $employee;
    }

    /** @return array{0: CompensationReview, 1: CompensationReviewItem} */
    private function approvedReviewFor(Employee $employee, float $proposed): array
    {
        $review = CompensationReview::create([
            'organization_id' => $this->organization->id,
            'review_name' => 'Annual review',
            'review_date' => now()->toDateString(),
            'effective_date' => now()->toDateString(),
            'status' => CompensationReview::STATUS_APPROVED,
        ]);

        $item = CompensationReviewItem::create([
            'review_id' => $review->id,
            'employee_id' => $employee->id,
            'current_salary' => 5000,
            'proposed_salary' => $proposed,
            'status' => CompensationReviewItem::STATUS_APPROVED,
        ]);

        return [$review, $item];
    }
}
