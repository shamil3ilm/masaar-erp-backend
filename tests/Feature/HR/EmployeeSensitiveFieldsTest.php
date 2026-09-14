<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use App\Models\HR\PayrollCorrection;
use App\Models\HR\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * An employee's identity, tax and bank numbers leave the API only through
 * EmployeeResource, which masks them. Many HR endpoints return a record with
 * its employee eager-loaded and serialized as a plain model; those responses
 * must not carry the numbers at all, decrypted or otherwise.
 */
class EmployeeSensitiveFieldsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const NATIONAL_ID = '1098765432';

    private const PASSPORT = 'P98765432';

    private const IBAN = 'SA0380000000608010167519';

    private const ACCOUNT = '0608010167519';

    private const TAX_NUMBER = '300123456700003';

    private const SOCIAL_SECURITY = 'GOSI-445566778';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.payroll.view', 'hr.employees.view']);
    }

    public function test_a_record_with_its_employee_does_not_reveal_the_employees_numbers(): void
    {
        $employee = $this->employeeWithNumbers();

        $correction = PayrollCorrection::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'original_payroll_period_id' => PayrollPeriod::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => PayrollCorrection::STATUS_DRAFT,
            'original_amount' => 1000,
            'corrected_amount' => 1100,
            'difference_amount' => 100,
        ]);

        $response = $this->apiGet("/hr/payroll-corrections/{$correction->id}");

        $response->assertOk()->assertJsonPath('data.employee.id', $employee->id);

        foreach ($this->numbers() as $field => $value) {
            $response->assertJsonMissingPath("data.employee.{$field}");
            $this->assertStringNotContainsString($value, $response->getContent());
        }
    }

    public function test_the_employee_record_still_shows_the_numbers_masked(): void
    {
        $employee = $this->employeeWithNumbers();

        $response = $this->apiGet("/hr/employees/{$employee->id}");

        $response->assertOk();
        $this->assertStringContainsString('*', (string) $response->json('data.national_id'));
        $this->assertStringEndsWith('7519', (string) $response->json('data.bank_iban'));
        $this->assertStringNotContainsString(self::NATIONAL_ID, $response->getContent());
        $this->assertStringNotContainsString(self::IBAN, $response->getContent());
    }

    private function employeeWithNumbers(): Employee
    {
        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'tax_number' => self::TAX_NUMBER,
            'social_security_number' => self::SOCIAL_SECURITY,
        ]);

        $employee->forceFill([
            'national_id' => self::NATIONAL_ID,
            'passport_number' => self::PASSPORT,
            'bank_iban' => self::IBAN,
            'bank_account_number' => self::ACCOUNT,
        ])->save();

        return $employee;
    }

    /** @return array<string, string> */
    private function numbers(): array
    {
        return [
            'national_id' => self::NATIONAL_ID,
            'passport_number' => self::PASSPORT,
            'bank_iban' => self::IBAN,
            'bank_account_number' => self::ACCOUNT,
            'tax_number' => self::TAX_NUMBER,
            'social_security_number' => self::SOCIAL_SECURITY,
        ];
    }
}
