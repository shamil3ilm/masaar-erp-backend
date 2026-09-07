<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\HR\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * An employee can see their own record.
 *
 * These endpoints read $user->employee, and User had no such relation. With
 * strict attribute access on — everywhere but production — that threw, so
 * every one of them answered 500. In production it read as null instead, and
 * the handler's own guard turned that into "No employee record found" for
 * everybody. Self-service was unreachable either way, and nothing here had a
 * test to say so.
 */
class EmployeeSelfServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['core.test.act']);
    }

    public function test_profile_returns_the_linked_employee(): void
    {
        $employee = $this->employeeForCurrentUser();

        $this->apiGet('/hr/me/profile')
            ->assertOk()
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.user.id', $this->user->id);
    }

    public function test_an_account_with_no_employee_is_told_so(): void
    {
        $this->apiGet('/hr/me/profile')
            ->assertNotFound();
    }

    public function test_the_relation_finds_only_this_users_employee(): void
    {
        $mine = $this->employeeForCurrentUser();

        Employee::create([
            'organization_id' => $this->organization->id,
            'first_name' => 'Someone', 'last_name' => 'Else',
            'employee_number' => 'EMP-OTHER',
        ]);

        $this->assertSame($mine->id, $this->user->fresh()->employee->id);
    }

    private function employeeForCurrentUser(): Employee
    {
        return Employee::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'first_name' => 'Test', 'last_name' => 'Employee',
            'employee_number' => 'EMP-1',
        ]);
    }
}
