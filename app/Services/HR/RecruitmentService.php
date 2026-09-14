<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Candidate;
use App\Models\HR\Employee;
use App\Models\HR\InterviewSchedule;
use App\Models\HR\JobApplication;
use App\Models\HR\JobOffer;
use App\Models\HR\JobPosting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RecruitmentService
{
    // -------------------------------------------------------------------------
    // Listings and lookups, all within the current organization
    // -------------------------------------------------------------------------

    /**
     * @param  array{status?: mixed, department_id?: mixed, employment_type?: mixed, search?: mixed}  $filters  empty values are ignored
     * @param  string  $sortBy  a column the caller has already checked against its allowlist
     */
    public function listJobPostings(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return JobPosting::with(['department', 'designation', 'creator'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['employment_type'] ?? null, fn ($q, $v) => $q->where('employment_type', $v))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $relations
     */
    public function findJobPosting(int $id, array $relations = []): JobPosting
    {
        return JobPosting::with($relations)->findOrFail($id);
    }

    /**
     * @param  array{source?: mixed, search?: mixed}  $filters  empty values are ignored
     * @param  string  $sortBy  a column the caller has already checked against its allowlist
     */
    public function listCandidates(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return Candidate::with(['applications'])
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('current_company', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $relations
     */
    public function findCandidate(int $id, array $relations = []): Candidate
    {
        return Candidate::with($relations)->findOrFail($id);
    }

    /**
     * @param  array{status?: mixed, job_posting_id?: mixed, candidate_id?: mixed}  $filters  empty values are ignored
     * @param  string  $sortBy  a column the caller has already checked against its allowlist
     */
    public function listApplications(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return JobApplication::with(['jobPosting', 'candidate', 'reviewer'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['job_posting_id'] ?? null, fn ($q, $v) => $q->where('job_posting_id', $v))
            ->when($filters['candidate_id'] ?? null, fn ($q, $v) => $q->where('candidate_id', $v))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $relations
     */
    public function findApplication(int $id, array $relations = []): JobApplication
    {
        return JobApplication::with($relations)->findOrFail($id);
    }

    public function findInterview(int $id): InterviewSchedule
    {
        return InterviewSchedule::findOrFail($id);
    }

    public function findOffer(int $id): JobOffer
    {
        return JobOffer::findOrFail($id);
    }

    // -------------------------------------------------------------------------
    // Job Postings
    // -------------------------------------------------------------------------

    public function createJobPosting(array $data, int $userId): JobPosting
    {
        return DB::transaction(function () use ($data, $userId): JobPosting {
            $data['created_by'] = $userId;
            $data['status']     = $data['status'] ?? JobPosting::STATUS_DRAFT;

            return JobPosting::create($data);
        });
    }

    public function updateJobPosting(JobPosting $posting, array $data): JobPosting
    {
        return DB::transaction(function () use ($posting, $data): JobPosting {
            $posting->update($data);

            return $posting->fresh();
        });
    }

    public function publishJobPosting(JobPosting $posting, int $userId): JobPosting
    {
        if ($posting->status === JobPosting::STATUS_CANCELLED) {
            throw new InvalidArgumentException('A cancelled job posting cannot be published.');
        }

        return DB::transaction(function () use ($posting): JobPosting {
            $posting->update([
                'status'    => JobPosting::STATUS_OPEN,
                'posted_at' => Carbon::now(),
            ]);

            return $posting->fresh();
        });
    }

    public function closeJobPosting(JobPosting $posting, int $userId): JobPosting
    {
        return DB::transaction(function () use ($posting): JobPosting {
            $posting->update([
                'status' => JobPosting::STATUS_CLOSED,
            ]);

            return $posting->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Candidates
    // -------------------------------------------------------------------------

    public function createCandidate(array $data, int $userId): Candidate
    {
        return DB::transaction(function () use ($data): Candidate {
            return Candidate::create($data);
        });
    }

    public function updateCandidate(Candidate $candidate, array $data): Candidate
    {
        return DB::transaction(function () use ($candidate, $data): Candidate {
            $candidate->update($data);

            return $candidate->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Applications
    // -------------------------------------------------------------------------

    public function applyForJob(array $data, int $userId): JobApplication
    {
        $posting = JobPosting::findOrFail($data['job_posting_id']);

        if (!$posting->isOpen()) {
            throw new RuntimeException('This job posting is not accepting applications.');
        }

        return DB::transaction(function () use ($data, $userId, $posting): JobApplication {
            // Prevent duplicate application within the same organization — checked
            // inside the transaction with a row lock to prevent race conditions.
            $exists = JobApplication::where('job_posting_id', $posting->id)
                ->where('candidate_id', $data['candidate_id'])
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw new RuntimeException('The candidate has already applied to this job posting.');
            }

            $data['created_by'] = $userId;
            $data['applied_at'] = $data['applied_at'] ?? Carbon::now();
            $data['status']     = JobApplication::STATUS_APPLIED;

            return JobApplication::create($data);
        });
    }

    public function shortlistApplication(JobApplication $application, int $userId): JobApplication
    {
        if (!$application->canAdvance()) {
            throw new RuntimeException('This application cannot be advanced in its current status.');
        }

        return DB::transaction(function () use ($application, $userId): JobApplication {
            $application->update([
                'status'      => JobApplication::STATUS_SHORTLISTED,
                'reviewed_by' => $userId,
                'reviewed_at' => Carbon::now(),
            ]);

            return $application->fresh();
        });
    }

    public function rejectApplication(JobApplication $application, string $reason, int $userId): JobApplication
    {
        if (in_array($application->status, [JobApplication::STATUS_HIRED, JobApplication::STATUS_WITHDRAWN], true)) {
            throw new RuntimeException('This application cannot be rejected in its current status.');
        }

        return DB::transaction(function () use ($application, $reason, $userId): JobApplication {
            $application->update([
                'status'           => JobApplication::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by'      => $userId,
                'reviewed_at'      => Carbon::now(),
            ]);

            return $application->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Interviews
    // -------------------------------------------------------------------------

    public function scheduleInterview(array $data, int $userId): InterviewSchedule
    {
        $application = JobApplication::findOrFail($data['job_application_id']);

        if (!$application->canAdvance()) {
            throw new RuntimeException('Cannot schedule an interview for an application in its current status.');
        }

        return DB::transaction(function () use ($data, $userId, $application): InterviewSchedule {
            $interview = InterviewSchedule::create(array_merge($data, [
                'created_by' => $userId,
                'status'     => InterviewSchedule::STATUS_SCHEDULED,
            ]));

            // Advance application status to interview_scheduled if still at an earlier stage
            if (in_array($application->status, [
                JobApplication::STATUS_APPLIED,
                JobApplication::STATUS_SCREENING,
                JobApplication::STATUS_SHORTLISTED,
            ], true)) {
                $application->update([
                    'status'      => JobApplication::STATUS_INTERVIEW_SCHEDULED,
                    'reviewed_by' => $userId,
                    'reviewed_at' => Carbon::now(),
                ]);
            }

            return $interview;
        });
    }

    public function recordInterviewFeedback(InterviewSchedule $interview, array $data, int $userId): InterviewSchedule
    {
        return DB::transaction(function () use ($interview, $data, $userId): InterviewSchedule {
            $interview->update(array_merge($data, [
                'status' => InterviewSchedule::STATUS_COMPLETED,
            ]));

            // Advance application status to interviewed
            $application = $interview->application;
            if ($application->status === JobApplication::STATUS_INTERVIEW_SCHEDULED) {
                $application->update([
                    'status'      => JobApplication::STATUS_INTERVIEWED,
                    'reviewed_by' => $userId,
                    'reviewed_at' => Carbon::now(),
                ]);
            }

            return $interview->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Offers
    // -------------------------------------------------------------------------

    public function createJobOffer(array $data, int $userId): JobOffer
    {
        $application = JobApplication::findOrFail($data['job_application_id']);

        if (!$application->canAdvance()) {
            throw new RuntimeException('Cannot create an offer for an application in its current status.');
        }

        return DB::transaction(function () use ($data, $userId, $application): JobOffer {
            $data['created_by']    = $userId;
            $data['candidate_id']  = $data['candidate_id'] ?? $application->candidate_id;
            $data['job_posting_id'] = $data['job_posting_id'] ?? $application->job_posting_id;
            $data['status']        = JobOffer::STATUS_DRAFT;

            $offer = JobOffer::create($data);

            $application->update([
                'status'      => JobApplication::STATUS_OFFER_EXTENDED,
                'reviewed_by' => $userId,
                'reviewed_at' => Carbon::now(),
            ]);

            return $offer;
        });
    }

    public function sendOffer(JobOffer $offer, int $userId): JobOffer
    {
        if ($offer->status !== JobOffer::STATUS_DRAFT) {
            throw new RuntimeException('Only a draft offer can be sent.');
        }

        return DB::transaction(function () use ($offer): JobOffer {
            $offer->update([
                'status'  => JobOffer::STATUS_SENT,
                'sent_at' => Carbon::now(),
            ]);

            return $offer->fresh();
        });
    }

    /**
     * Accept an offer, fill one vacancy of its posting and hire the
     * application, all in one transaction. The offer and the posting are
     * locked first, so the offer is accepted once and the posting's vacancies
     * are counted as they are now; when no vacancy is left nothing is changed.
     */
    public function acceptOffer(JobOffer $offer, int $userId): JobOffer
    {
        DB::transaction(function () use ($offer, $userId): void {
            $offer = JobOffer::lockForUpdate()->findOrFail($offer->id);

            if (!$offer->accept($userId)) {
                throw new RuntimeException('This offer cannot be accepted. It may already be responded to or expired.');
            }

            $posting = JobPosting::lockForUpdate()->findOrFail($offer->job_posting_id);

            if ($posting->remainingVacancies() <= 0) {
                throw new RuntimeException('No open positions remaining for this job posting.');
            }

            $posting->increment('filled_count');

            // Close the posting once its last vacancy is filled
            if ($posting->fresh()->remainingVacancies() <= 0) {
                $posting->update(['status' => JobPosting::STATUS_CLOSED]);
            }

            // Mark the application as hired
            $offer->application()->update([
                'status'      => JobApplication::STATUS_HIRED,
                'reviewed_by' => $userId,
                'reviewed_at' => Carbon::now(),
            ]);
        });

        return $offer->fresh();
    }

    public function declineOffer(JobOffer $offer, string $reason, int $userId): JobOffer
    {
        if (!$offer->decline($reason, $userId)) {
            throw new RuntimeException('This offer cannot be declined in its current state.');
        }

        return $offer->fresh();
    }

    // -------------------------------------------------------------------------
    // Convert to Employee
    // -------------------------------------------------------------------------

    public function convertToEmployee(JobApplication $application, array $employeeData, int $userId): Employee
    {
        if (!$application->isHired()) {
            throw new RuntimeException('Only hired applications can be converted to employees.');
        }

        $candidate = $application->candidate;

        return DB::transaction(function () use ($application, $candidate, $employeeData, $userId): Employee {
            $baseData = [
                'organization_id' => $application->organization_id,
                'first_name'      => $candidate->first_name,
                'last_name'       => $candidate->last_name,
                'email'           => $candidate->email,
                'phone'           => $candidate->phone,
                'created_by'      => $userId,
            ];

            // Offer-derived defaults (joining date)
            $offer = $application->offer;
            if ($offer && $offer->joining_date) {
                $baseData['joining_date'] = $offer->joining_date;
            }

            // Merge caller-supplied data (highest priority)
            $merged = array_merge($baseData, $employeeData);

            if (empty($merged['display_name'])) {
                $merged['display_name'] = trim("{$merged['first_name']} {$merged['last_name']}");
            }

            if (empty($merged['employment_status'])) {
                $merged['employment_status'] = Employee::STATUS_ACTIVE;
            }

            return Employee::create($merged);
        });
    }
}
