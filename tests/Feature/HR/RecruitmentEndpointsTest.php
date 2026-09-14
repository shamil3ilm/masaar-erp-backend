<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\HR\Candidate;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\InterviewSchedule;
use App\Models\HR\JobApplication;
use App\Models\HR\JobOffer;
use App\Models\HR\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Job postings, candidates, applications, interviews and offers: listings,
 * lookups and the references each may make, inside the caller's organization.
 */
class RecruitmentEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/recruitment';

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'hr.recruitment.view', 'hr.recruitment.create', 'hr.recruitment.edit', 'hr.recruitment.delete',
            'hr.recruitment.publish', 'hr.recruitment.convert', 'hr.recruitment.applications.create',
            'hr.recruitment.interviews.create', 'hr.recruitment.interviews.edit',
            'hr.recruitment.offers.create', 'hr.recruitment.offers.send', 'hr.recruitment.offers.manage',
        ]);

        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_postings_are_filtered_by_status_and_search(): void
    {
        $wanted = $this->posting($this->organization, 'Senior Accountant', JobPosting::STATUS_OPEN);
        $this->posting($this->organization, 'Junior Accountant', JobPosting::STATUS_DRAFT);
        $this->posting($this->organization, 'Driver', JobPosting::STATUS_OPEN);
        $this->posting($this->otherOrganization, 'Accountant theirs', JobPosting::STATUS_OPEN);

        $response = $this->apiGet("{$this->baseUrl}/job-postings?status=open&search=Accountant");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_posting_is_not_found(): void
    {
        $theirs = $this->posting($this->otherOrganization, 'Theirs', JobPosting::STATUS_OPEN);

        $this->apiGet("{$this->baseUrl}/job-postings/{$theirs->id}")->assertNotFound();
        $this->apiPost("{$this->baseUrl}/job-postings/{$theirs->id}/close")->assertNotFound();

        $this->assertSame(JobPosting::STATUS_OPEN, JobPosting::withoutGlobalScopes()->find($theirs->id)->status);
    }

    public function test_a_posting_cannot_name_another_organizations_branch_or_department(): void
    {
        $payload = ['title' => 'Accountant', 'description' => 'Books'];

        $theirBranch = Branch::factory()->create(['organization_id' => $this->otherOrganization->id]);
        $this->apiPost("{$this->baseUrl}/job-postings", $payload + ['branch_id' => $theirBranch->id])
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $theirDepartment = $this->department($this->otherOrganization);
        $this->apiPost("{$this->baseUrl}/job-postings", $payload + ['department_id' => $theirDepartment->id])
            ->assertStatus(422)->assertJsonValidationErrors('department_id');

        $posting = $this->posting($this->organization, 'Accountant', JobPosting::STATUS_DRAFT);
        $this->apiPut("{$this->baseUrl}/job-postings/{$posting->id}", ['department_id' => $theirDepartment->id])
            ->assertStatus(422)->assertJsonValidationErrors('department_id');

        $this->apiPost("{$this->baseUrl}/job-postings", $payload + ['department_id' => $this->department($this->organization)->id])
            ->assertCreated();
        $this->assertSame(2, JobPosting::withoutGlobalScopes()->count());
    }

    public function test_candidates_are_searched_by_name(): void
    {
        $wanted = $this->candidate($this->organization, 'Layla');
        $this->candidate($this->organization, 'Omar');
        $this->candidate($this->otherOrganization, 'Layla');

        $response = $this->apiGet("{$this->baseUrl}/candidates?search=Layla");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_candidate_applies_to_an_open_posting(): void
    {
        $posting = $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN);
        $candidate = $this->candidate($this->organization, 'Layla');

        $this->apiPost("{$this->baseUrl}/job-postings/{$posting->id}/apply", ['candidate_id' => $candidate->id])
            ->assertCreated()
            ->assertJsonPath('data.candidate.id', $candidate->id);
    }

    public function test_another_organizations_candidate_cannot_apply(): void
    {
        $posting = $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN);

        $this->apiPost("{$this->baseUrl}/job-postings/{$posting->id}/apply", ['candidate_id' => $this->candidate($this->otherOrganization, 'Theirs')->id])
            ->assertStatus(422)->assertJsonValidationErrors('candidate_id');

        $this->assertSame(0, JobApplication::withoutGlobalScopes()->count());
    }

    public function test_applications_are_filtered_by_posting_and_shortlisted(): void
    {
        $posting = $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN);
        $wanted = $this->application($posting, $this->candidate($this->organization, 'Layla'));
        $this->application($this->posting($this->organization, 'Driver', JobPosting::STATUS_OPEN), $this->candidate($this->organization, 'Omar'));

        $response = $this->apiGet("{$this->baseUrl}/applications?job_posting_id={$posting->id}");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));

        $this->apiPost("{$this->baseUrl}/applications/{$wanted->id}/shortlist")
            ->assertOk()
            ->assertJsonPath('data.status', JobApplication::STATUS_SHORTLISTED);
    }

    public function test_an_interview_cannot_name_another_organizations_user(): void
    {
        $application = $this->application(
            $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN),
            $this->candidate($this->organization, 'Layla')
        );
        $payload = ['interview_type' => InterviewSchedule::TYPE_PHONE, 'scheduled_at' => now()->addDays(3)->toDateTimeString()];

        $this->apiPost("{$this->baseUrl}/applications/{$application->id}/interviews", $payload + [
            'interviewers' => [User::factory()->create(['organization_id' => $this->otherOrganization->id])->id],
        ])->assertStatus(422)->assertJsonValidationErrors('interviewers.0');

        $this->apiPost("{$this->baseUrl}/applications/{$application->id}/interviews", $payload + ['interviewers' => [$this->user->id]])
            ->assertCreated();
        $this->assertSame(1, InterviewSchedule::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_interview_and_offer_are_not_found(): void
    {
        $theirApplication = $this->application(
            $this->posting($this->otherOrganization, 'Theirs', JobPosting::STATUS_OPEN),
            $this->candidate($this->otherOrganization, 'Theirs')
        );
        $theirInterview = InterviewSchedule::forceCreate([
            'organization_id' => $this->otherOrganization->id,
            'job_application_id' => $theirApplication->id,
            'interview_type' => InterviewSchedule::TYPE_PHONE,
            'scheduled_at' => now()->addDay(),
            'status' => InterviewSchedule::STATUS_SCHEDULED,
        ]);
        $theirOffer = $this->offer($theirApplication);

        $this->apiPut("{$this->baseUrl}/interviews/{$theirInterview->id}/feedback", ['feedback' => 'Great'])->assertNotFound();
        $this->apiPost("{$this->baseUrl}/offers/{$theirOffer->id}/send")->assertNotFound();

        $this->assertSame(JobOffer::STATUS_DRAFT, JobOffer::withoutGlobalScopes()->find($theirOffer->id)->status);
    }

    public function test_a_draft_offer_is_sent(): void
    {
        $offer = $this->offer($this->application(
            $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN),
            $this->candidate($this->organization, 'Layla')
        ));

        $this->apiPost("{$this->baseUrl}/offers/{$offer->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', JobOffer::STATUS_SENT);
    }

    public function test_a_hire_cannot_be_converted_into_another_organizations_department(): void
    {
        $application = $this->application(
            $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN),
            $this->candidate($this->organization, 'Layla'),
            JobApplication::STATUS_HIRED
        );

        $this->apiPost("{$this->baseUrl}/applications/{$application->id}/convert-to-employee", [
            'employee_number' => 'EMP-HIRE-1',
            'department_id' => $this->department($this->otherOrganization)->id,
        ])->assertStatus(422)->assertJsonValidationErrors('department_id');

        $theirDesignation = \App\Models\HR\Designation::create(['organization_id' => $this->otherOrganization->id, 'name' => 'Theirs', 'is_active' => true]);

        $this->apiPost("{$this->baseUrl}/applications/{$application->id}/convert-to-employee", [
            'employee_number' => 'EMP-HIRE-1',
            'designation_id' => $theirDesignation->id,
        ])->assertStatus(422)->assertJsonValidationErrors('designation_id');

        $this->assertSame(0, Employee::withoutGlobalScopes()->where('employee_number', 'EMP-HIRE-1')->count());
    }

    /**
     * A posting's capacity is its vacancies. Accepting an offer hires the
     * application and fills one vacancy; the last vacancy closes the posting.
     */
    public function test_accepting_an_offer_hires_the_application_and_fills_the_posting(): void
    {
        $posting = $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN);
        $posting->forceFill(['vacancies' => 1, 'filled_count' => 0])->save();
        $application = $this->application($posting, $this->candidate($this->organization, 'Layla'), JobApplication::STATUS_OFFER_EXTENDED);
        $offer = $this->offer($application, JobOffer::STATUS_SENT);

        $this->apiPost("{$this->baseUrl}/offers/{$offer->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', JobOffer::STATUS_ACCEPTED);

        $this->assertSame(JobApplication::STATUS_HIRED, $application->fresh()->status);
        $this->assertSame(1, $posting->fresh()->filled_count);
        $this->assertSame(JobPosting::STATUS_CLOSED, $posting->fresh()->status);
    }

    /**
     * With no vacancy left the acceptance is refused as a whole: the offer
     * stays sent and the application is not marked as accepting it.
     */
    public function test_an_offer_for_a_filled_posting_is_not_accepted(): void
    {
        $posting = $this->posting($this->organization, 'Accountant', JobPosting::STATUS_OPEN);
        $posting->forceFill(['vacancies' => 1, 'filled_count' => 1])->save();
        $application = $this->application($posting, $this->candidate($this->organization, 'Layla'), JobApplication::STATUS_OFFER_EXTENDED);
        $offer = $this->offer($application, JobOffer::STATUS_SENT);

        $this->apiPost("{$this->baseUrl}/offers/{$offer->id}/accept")->assertStatus(400);

        $this->assertSame(JobOffer::STATUS_SENT, $offer->fresh()->status);
        $this->assertSame(JobApplication::STATUS_OFFER_EXTENDED, $application->fresh()->status);
        $this->assertSame(1, $posting->fresh()->filled_count);
    }

    private function department(Organization $organization): Department
    {
        return Department::create(['organization_id' => $organization->id, 'name' => 'Finance', 'is_active' => true]);
    }

    private function posting(Organization $organization, string $title, string $status): JobPosting
    {
        return JobPosting::forceCreate([
            'organization_id' => $organization->id,
            'title' => $title,
            'description' => 'Role',
            'status' => $status,
            'vacancies' => 2,
            'created_by' => $this->user->id,
        ]);
    }

    private function candidate(Organization $organization, string $firstName): Candidate
    {
        static $number = 0;

        return Candidate::forceCreate([
            'organization_id' => $organization->id,
            'first_name' => $firstName,
            'last_name' => 'Candidate',
            'email' => 'candidate'.++$number.'@example.test',
        ]);
    }

    private function application(JobPosting $posting, Candidate $candidate, string $status = JobApplication::STATUS_APPLIED): JobApplication
    {
        return JobApplication::forceCreate([
            'organization_id' => $posting->organization_id,
            'job_posting_id' => $posting->id,
            'candidate_id' => $candidate->id,
            'status' => $status,
            'applied_at' => now(),
        ]);
    }

    private function offer(JobApplication $application, string $status = JobOffer::STATUS_DRAFT): JobOffer
    {
        return JobOffer::forceCreate([
            'organization_id' => $application->organization_id,
            'job_application_id' => $application->id,
            'candidate_id' => $application->candidate_id,
            'job_posting_id' => $application->job_posting_id,
            'offered_salary' => 12000,
            'status' => $status,
        ]);
    }
}
