<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\TrainingCertification;
use App\Models\HR\TrainingCourse;
use App\Models\HR\TrainingEnrollment;
use App\Models\HR\TrainingNeed;
use App\Models\HR\TrainingProvider;
use App\Models\HR\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Training providers, courses, sessions, enrollments, certifications and
 * needs: listings, lookups and the references each may make, inside the
 * caller's organization.
 */
class TrainingEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/training';

    private Organization $otherOrganization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.training.view', 'hr.training.manage', 'hr.training.reports']);

        $this->otherOrganization = Organization::factory()->create();
        $this->employee = $this->employee($this->organization);
    }

    public function test_providers_are_filtered_to_active_and_searched(): void
    {
        $wanted = $this->provider($this->organization, 'Acme Academy');
        $this->provider($this->organization, 'Acme Old', ['is_active' => false]);
        $this->provider($this->organization, 'Other Institute');
        $this->provider($this->otherOrganization, 'Acme Theirs');

        $response = $this->apiGet("{$this->baseUrl}/providers?active_only=1&search=Acme");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_provider_is_not_found(): void
    {
        $theirs = $this->provider($this->otherOrganization, 'Theirs');

        $this->apiGet("{$this->baseUrl}/providers/{$theirs->id}")->assertNotFound();
    }

    public function test_courses_are_filtered_by_category(): void
    {
        $wanted = $this->course($this->organization, 'SAFE-1', ['category' => 'safety']);
        $this->course($this->organization, 'LEAD-1', ['category' => 'leadership']);

        $response = $this->apiGet("{$this->baseUrl}/courses?category=safety");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_course_cannot_name_another_organizations_provider(): void
    {
        $this->apiPost("{$this->baseUrl}/courses", [
            'provider_id' => $this->provider($this->otherOrganization, 'Theirs')->id,
            'code' => 'SAFE-1',
            'name' => 'Fire safety',
            'category' => 'safety',
            'delivery_type' => 'in_person',
        ])->assertStatus(422)->assertJsonValidationErrors('provider_id');

        $this->assertSame(0, TrainingCourse::withoutGlobalScopes()->count());
    }

    public function test_a_session_is_scheduled_for_a_course_of_the_organization_only(): void
    {
        $payload = ['start_date' => '2026-03-02 09:00:00', 'end_date' => '2026-03-02 17:00:00'];

        $this->apiPost("{$this->baseUrl}/sessions", $payload + ['course_id' => $this->course($this->otherOrganization, 'THEIRS')->id])
            ->assertStatus(422)->assertJsonValidationErrors('course_id');

        $course = $this->course($this->organization, 'SAFE-1');
        $this->apiPost("{$this->baseUrl}/sessions", $payload + ['course_id' => $course->id])
            ->assertCreated()
            ->assertJsonPath('data.course.id', $course->id);
    }

    public function test_sessions_are_filtered_by_course(): void
    {
        $course = $this->course($this->organization, 'SAFE-1');
        $wanted = $this->trainingSession($course);
        $this->trainingSession($this->course($this->organization, 'LEAD-1'));

        $response = $this->apiGet("{$this->baseUrl}/sessions?course_id={$course->id}");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_an_employee_of_the_organization_is_enrolled(): void
    {
        $session = $this->trainingSession($this->course($this->organization, 'SAFE-1'));

        $this->apiPost("{$this->baseUrl}/sessions/{$session->id}/enroll", ['employee_id' => $this->employee->id])
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $this->employee->id);
    }

    public function test_another_organizations_employee_cannot_be_enrolled(): void
    {
        $session = $this->trainingSession($this->course($this->organization, 'SAFE-1'));
        $stranger = $this->employee($this->otherOrganization);

        $this->apiPost("{$this->baseUrl}/sessions/{$session->id}/enroll", ['employee_id' => $stranger->id])
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->apiPost("{$this->baseUrl}/sessions/{$session->id}/bulk-enroll", ['employee_ids' => [$this->employee->id, $stranger->id]])
            ->assertStatus(422)->assertJsonValidationErrors('employee_ids.1');

        $this->assertSame(0, TrainingEnrollment::withoutGlobalScopes()->count());
    }

    /**
     * The nested route names both the session and the enrollment. It cancels
     * that enrollment of that session, and nothing else.
     */
    public function test_the_nested_route_cancels_the_named_enrollment_of_the_session(): void
    {
        $course = $this->course($this->organization, 'SAFE-1');
        $first = $this->trainingSession($course);
        $second = $this->trainingSession($course);
        $enrollment = $this->enrollment($second, $this->employee);
        $otherEnrollment = $this->enrollment($first, $this->employee($this->organization));
        $this->assertNotSame($second->id, $enrollment->id);

        $this->deleteJson("/api/v1{$this->baseUrl}/sessions/{$first->id}/enrollments/{$enrollment->id}", [], $this->authHeaders())
            ->assertNotFound();

        $this->deleteJson("/api/v1{$this->baseUrl}/sessions/{$second->id}/enrollments/{$enrollment->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', TrainingEnrollment::STATUS_CANCELLED);

        $this->assertSame(TrainingEnrollment::STATUS_ENROLLED, $otherEnrollment->fresh()->status);
    }

    public function test_only_a_scheduled_session_is_cancelled(): void
    {
        $course = $this->course($this->organization, 'SAFE-1');
        $scheduled = $this->trainingSession($course);
        $completed = $this->trainingSession($course, TrainingSession::STATUS_COMPLETED);

        $this->apiPost("{$this->baseUrl}/sessions/{$scheduled->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', TrainingSession::STATUS_CANCELLED);

        $this->apiPost("{$this->baseUrl}/sessions/{$completed->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SESSION_CANCEL_ERROR');
    }

    public function test_enrollments_are_filtered_by_employee(): void
    {
        $session = $this->trainingSession($this->course($this->organization, 'SAFE-1'));
        $wanted = $this->enrollment($session, $this->employee);
        $this->enrollment($session, $this->employee($this->organization));

        $response = $this->apiGet("{$this->baseUrl}/enrollments?employee_id={$this->employee->id}");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_certification_cannot_name_another_organizations_employee_or_course(): void
    {
        $payload = ['issued_date' => '2026-03-02'];

        $this->apiPost("{$this->baseUrl}/certifications", $payload + [
            'employee_id' => $this->employee($this->otherOrganization)->id,
            'course_id' => $this->course($this->organization, 'SAFE-1')->id,
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->apiPost("{$this->baseUrl}/certifications", $payload + [
            'employee_id' => $this->employee->id,
            'course_id' => $this->course($this->otherOrganization, 'THEIRS')->id,
        ])->assertStatus(422)->assertJsonValidationErrors('course_id');

        $this->assertSame(0, TrainingCertification::withoutGlobalScopes()->count());
    }

    public function test_a_need_cannot_name_another_organizations_department_or_user(): void
    {
        $payload = ['title' => 'Excel skills', 'priority' => TrainingNeed::PRIORITIES[0]];

        $this->apiPost("{$this->baseUrl}/needs", $payload + [
            'department_id' => Department::create(['organization_id' => $this->otherOrganization->id, 'name' => 'Theirs', 'is_active' => true])->id,
        ])->assertStatus(422)->assertJsonValidationErrors('department_id');

        $this->apiPost("{$this->baseUrl}/needs", $payload + [
            'identified_by' => User::factory()->create(['organization_id' => $this->otherOrganization->id])->id,
        ])->assertStatus(422)->assertJsonValidationErrors('identified_by');

        $this->apiPost("{$this->baseUrl}/needs", $payload + ['employee_id' => $this->employee->id, 'identified_by' => $this->user->id])
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $this->employee->id);
        $this->assertSame(1, TrainingNeed::withoutGlobalScopes()->count());
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function provider(Organization $organization, string $name, array $overrides = []): TrainingProvider
    {
        return TrainingProvider::forceCreate(array_merge([
            'organization_id' => $organization->id,
            'name' => $name,
            'is_active' => true,
        ], $overrides));
    }

    private function course(Organization $organization, string $code, array $overrides = []): TrainingCourse
    {
        return TrainingCourse::forceCreate(array_merge([
            'organization_id' => $organization->id,
            'code' => $code,
            'name' => "Course {$code}",
            'category' => 'safety',
            'delivery_type' => 'in_person',
            'is_active' => true,
        ], $overrides));
    }

    private function trainingSession(TrainingCourse $course, string $status = TrainingSession::STATUS_SCHEDULED): TrainingSession
    {
        static $number = 0;

        return TrainingSession::forceCreate([
            'organization_id' => $course->organization_id,
            'course_id' => $course->id,
            'session_number' => 'TS-TEST-'.++$number,
            'start_date' => '2026-03-02 09:00:00',
            'end_date' => '2026-03-02 17:00:00',
            'status' => $status,
        ]);
    }

    private function enrollment(TrainingSession $session, Employee $employee): TrainingEnrollment
    {
        return TrainingEnrollment::forceCreate([
            'organization_id' => $session->organization_id,
            'session_id' => $session->id,
            'employee_id' => $employee->id,
            'status' => TrainingEnrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);
    }
}
