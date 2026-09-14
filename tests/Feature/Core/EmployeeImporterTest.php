<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ImportJob;
use App\Models\HR\Employee;
use App\Models\HR\SalaryComponent;
use App\Models\HR\SalaryStructure;
use App\Models\HR\SalaryStructureComponent;
use App\Services\Core\Importers\EmployeeImporter;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The employee import could not create an employee.
 *
 * It wrote hire_date where the column is joining_date, wrote the national ID
 * and bank details through mass assignment the model deliberately refuses, and
 * put basic_salary and iban on the employee row, which has neither - salary
 * lives on a salary structure. Its duplicate check also compared the encrypted
 * national ID with =, which can never match.
 */
class EmployeeImporterTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ImportJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_EMPLOYEES,
        ]);
    }

    public function test_a_row_imports_its_dates_sensitive_fields_and_salary(): void
    {
        [$structure, $basic] = $this->defaultStructure();

        $employee = app(EmployeeImporter::class)->importRow([
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'email' => 'sara@example.com',
            'hire_date' => '2026-01-15',
            'national_id' => '1234567890',
            'bank_account_number' => '0608010167519',
            'iban' => 'SA0380000000608010167519',
            'basic_salary' => '7000',
        ], $this->job);

        $fresh = $employee->fresh();
        $this->assertSame('2026-01-15', $fresh->joining_date->toDateString());
        $this->assertSame('1234567890', $fresh->national_id);
        $this->assertSame('0608010167519', $fresh->bank_account_number);
        $this->assertSame('SA0380000000608010167519', $fresh->bank_iban);

        $salary = $fresh->currentSalary;
        $this->assertNotNull($salary);
        $this->assertSame($structure->id, $salary->salary_structure_id);
        $this->assertEquals(7000.0, (float) $salary->components()->where('salary_component_id', $basic->id)->value('amount'));
    }

    public function test_an_imported_employee_without_a_number_continues_the_employee_number_sequence(): void
    {
        $year = now()->format('Y');
        $this->assertSame("EMP-{$year}-00001", app(NumberGeneratorService::class)->generate('EMP', null, $this->organization->id));

        $employee = app(EmployeeImporter::class)->importRow([
            'first_name' => 'Omar',
            'last_name' => 'Hassan',
            'email' => 'omar@example.com',
        ], $this->job);

        $this->assertSame("EMP-{$year}-00002", $employee->employee_number);
    }

    public function test_a_salary_with_no_default_structure_is_refused_before_anyone_is_created(): void
    {
        try {
            app(EmployeeImporter::class)->importRow([
                'first_name' => 'Omar',
                'last_name' => 'Hassan',
                'email' => 'omar@example.com',
                'basic_salary' => '5000',
            ], $this->job);

            $this->fail('A basic salary with nowhere to put it should have been refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('default salary structure', $e->getMessage());
        }

        // Refused before the employee was written: ImportService records a
        // failed row but does not undo what the row already saved.
        $this->assertSame(0, Employee::where('email', 'omar@example.com')->count());
    }

    public function test_a_row_without_a_salary_needs_no_structure(): void
    {
        $employee = app(EmployeeImporter::class)->importRow([
            'first_name' => 'Lina',
            'last_name' => 'Nasser',
            'email' => 'lina@example.com',
        ], $this->job);

        $fresh = $employee->fresh();
        $this->assertSame(now()->toDateString(), $fresh->joining_date->toDateString());
        $this->assertNull($fresh->currentSalary);
    }

    public function test_a_reimport_matched_by_email_updates_the_employee(): void
    {
        $importer = app(EmployeeImporter::class);

        $first = $importer->importRow([
            'first_name' => 'Hana',
            'last_name' => 'Saeed',
            'email' => 'hana@example.com',
        ], $this->job);

        $second = $importer->importRow([
            'first_name' => 'Hana',
            'last_name' => 'Al-Saeed',
            'email' => 'hana@example.com',
        ], $this->job, ['update_existing' => true]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Al-Saeed', $first->fresh()->last_name);
        $this->assertSame(1, Employee::where('email', 'hana@example.com')->count());
    }

    /** @return array{0: SalaryStructure, 1: SalaryComponent} */
    private function defaultStructure(): array
    {
        $structure = SalaryStructure::factory()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
            'is_default' => true,
        ]);

        $basic = SalaryComponent::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'BASIC',
            'type' => SalaryComponent::TYPE_EARNING,
            'category' => SalaryComponent::CATEGORY_BASIC,
            'calculation_type' => 'fixed',
            'default_value' => 0,
        ]);

        SalaryStructureComponent::create([
            'salary_structure_id' => $structure->id,
            'salary_component_id' => $basic->id,
            'calculation_type' => 'fixed',
            'value' => 0,
        ]);

        return [$structure, $basic];
    }
}
