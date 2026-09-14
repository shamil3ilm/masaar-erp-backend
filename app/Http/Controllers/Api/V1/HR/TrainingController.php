<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\TrainingCourse;
use App\Models\HR\TrainingEnrollment;
use App\Models\HR\TrainingNeed;
use App\Services\HR\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainingService
    ) {}

    // =========================================================================
    // Providers
    // =========================================================================

    public function indexProviders(Request $request): JsonResponse
    {
        return $this->paginated($this->trainingService->listProviders(
            ['active_only' => $request->boolean('active_only'), 'search' => $request->search],
            $request->integer('per_page', 15)
        ));
    }

    public function storeProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        return $this->created($this->trainingService->createProvider($validated));
    }

    public function showProvider(Request $request, int $id): JsonResponse
    {
        $provider = $this->trainingService->findProvider($id, ['courses']);

        if ($provider === null) {
            return $this->notFound('Training provider not found.');
        }

        return $this->success($provider);
    }

    public function updateProvider(Request $request, int $id): JsonResponse
    {
        $provider = $this->trainingService->findProvider($id);

        if ($provider === null) {
            return $this->notFound('Training provider not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $provider->update($validated);

        return $this->success($provider->fresh());
    }

    public function destroyProvider(Request $request, int $id): JsonResponse
    {
        $provider = $this->trainingService->findProvider($id);

        if ($provider === null) {
            return $this->notFound('Training provider not found.');
        }

        $provider->delete();

        return $this->success(null, 'Training provider deleted successfully.');
    }

    // =========================================================================
    // Courses
    // =========================================================================

    public function indexCourses(Request $request): JsonResponse
    {
        return $this->paginated($this->trainingService->listCourses(
            [
                'active_only' => $request->boolean('active_only'),
                'mandatory_only' => $request->boolean('mandatory_only'),
                'category' => $request->category,
                'delivery_type' => $request->delivery_type,
                'search' => $request->search,
            ],
            $this->safeSortBy($request->sort_by, ['name', 'code', 'category', 'duration_hours', 'created_at'], 'name'),
            $this->safeSortOrder($request->sort_order),
            $request->integer('per_page', 15)
        ));
    }

    public function storeCourse(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'provider_id' => ['nullable', $this->ownedBy('training_providers', $organizationId)],
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:'.implode(',', TrainingCourse::CATEGORIES),
            'delivery_type' => 'required|in:'.implode(',', TrainingCourse::DELIVERY_TYPES),
            'duration_hours' => 'nullable|numeric|min:0.5|max:9999',
            'max_participants' => 'nullable|integer|min:1',
            'is_mandatory' => 'nullable|boolean',
            'validity_months' => 'nullable|integer|min:1',
            'cost_per_participant' => 'nullable|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $organizationId;

        try {
            $course = $this->trainingService->createCourse($validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'COURSE_CREATE_ERROR', 422);
        }

        return $this->created($course->load('provider'));
    }

    public function showCourse(Request $request, int $id): JsonResponse
    {
        $course = $this->trainingService->findCourse($id, ['provider', 'sessions']);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        return $this->success($course);
    }

    public function updateCourse(Request $request, int $id): JsonResponse
    {
        $course = $this->trainingService->findCourse($id);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        $validated = $request->validate([
            'provider_id' => ['nullable', $this->ownedBy('training_providers', $course->organization_id)],
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|in:'.implode(',', TrainingCourse::CATEGORIES),
            'delivery_type' => 'sometimes|in:'.implode(',', TrainingCourse::DELIVERY_TYPES),
            'duration_hours' => 'nullable|numeric|min:0.5|max:9999',
            'max_participants' => 'nullable|integer|min:1',
            'is_mandatory' => 'nullable|boolean',
            'validity_months' => 'nullable|integer|min:1',
            'cost_per_participant' => 'nullable|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $course = $this->trainingService->updateCourse($course, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'COURSE_UPDATE_ERROR', 422);
        }

        return $this->success($course->load('provider'));
    }

    public function destroyCourse(Request $request, int $id): JsonResponse
    {
        $course = $this->trainingService->findCourse($id);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        $course->delete();

        return $this->success(null, 'Training course deleted successfully.');
    }

    // =========================================================================
    // Sessions
    // =========================================================================

    public function indexSessions(Request $request): JsonResponse
    {
        return $this->paginated($this->trainingService->listSessions(
            $request->only(['course_id', 'status', 'from', 'to']),
            $this->safeSortBy($request->sort_by, ['start_date', 'end_date', 'status', 'session_number'], 'start_date'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15)
        ));
    }

    public function storeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', $this->ownedBy('training_courses', $this->organizationId($request))],
            'trainer_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'max_participants' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $course = $this->trainingService->findCourse((int) $validated['course_id']);

        if ($course === null) {
            return $this->notFound('Training course not found.');
        }

        try {
            $session = $this->trainingService->createSession($course, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'SESSION_CREATE_ERROR', 422);
        }

        return $this->created($session->load(['course', 'course.provider']));
    }

    public function showSession(Request $request, int $id): JsonResponse
    {
        $session = $this->trainingService->findSession($id, ['course', 'course.provider', 'enrollments.employee']);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        return $this->success($session);
    }

    public function updateSession(Request $request, int $id): JsonResponse
    {
        $session = $this->trainingService->findSession($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'trainer_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'max_participants' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        try {
            $session = $this->trainingService->updateSession($session, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'SESSION_UPDATE_ERROR', 422);
        }

        return $this->success($session->load(['course', 'course.provider']));
    }

    public function startSession(Request $request, int $id): JsonResponse
    {
        $session = $this->trainingService->findSession($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        try {
            $session = $this->trainingService->startSession($session, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'SESSION_START_ERROR', 422);
        }

        return $this->success($session, 'Session started successfully.');
    }

    public function completeSession(Request $request, int $id): JsonResponse
    {
        $session = $this->trainingService->findSession($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'results' => 'required|array',
            'results.*.employee_id' => ['required', $this->ownedBy('employees', $session->organization_id)],
            'results.*.score' => 'nullable|numeric|min:0|max:100',
            'results.*.passed' => 'nullable|boolean',
            'results.*.feedback' => 'nullable|string',
            'results.*.issued_by' => 'nullable|string|max:255',
            'results.*.cert_notes' => 'nullable|string',
        ]);

        try {
            $session = $this->trainingService->completeSession($session, $validated['results'], auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'SESSION_COMPLETE_ERROR', 422);
        }

        return $this->success($session->load('enrollments'), 'Session completed successfully.');
    }

    public function cancelSession(Request $request, int $id): JsonResponse
    {
        $session = $this->trainingService->findSession($id);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        try {
            $session = $this->trainingService->cancelSession($session);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'SESSION_CANCEL_ERROR', 422);
        }

        return $this->success($session, 'Session cancelled successfully.');
    }

    // =========================================================================
    // Enrollments
    // =========================================================================

    public function enroll(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->trainingService->findSession($sessionId);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'employee_id' => ['required', $this->ownedBy('employees', $session->organization_id)],
        ]);

        try {
            $enrollment = $this->trainingService->enroll($session, (int) $validated['employee_id'], auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'ENROLLMENT_ERROR', 422);
        }

        return $this->created($enrollment->load('employee'));
    }

    public function bulkEnroll(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->trainingService->findSession($sessionId);

        if ($session === null) {
            return $this->notFound('Training session not found.');
        }

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => ['required', $this->ownedBy('employees', $session->organization_id)],
        ]);

        $result = $this->trainingService->bulkEnroll($session, $validated['employee_ids'], auth()->id());

        return $this->success($result, 'Bulk enrollment processed.');
    }

    public function indexEnrollments(Request $request): JsonResponse
    {
        return $this->paginated($this->trainingService->listEnrollments(
            $request->only(['session_id', 'employee_id', 'status']),
            $request->integer('per_page', 15)
        ));
    }

    public function cancelEnrollment(Request $request, int $id): JsonResponse
    {
        $enrollment = $this->trainingService->findEnrollment($id);

        if ($enrollment === null) {
            return $this->notFound('Enrollment not found.');
        }

        return $this->cancelled($enrollment);
    }

    /**
     * Cancel an enrollment named under its session. The route carries both
     * ids; an enrollment of another session is not found.
     */
    public function cancelSessionEnrollment(int $sessionId, int $enrollmentId): JsonResponse
    {
        $enrollment = $this->trainingService->findSessionEnrollment($sessionId, $enrollmentId);

        if ($enrollment === null) {
            return $this->notFound('Enrollment not found.');
        }

        return $this->cancelled($enrollment);
    }

    private function cancelled(TrainingEnrollment $enrollment): JsonResponse
    {
        try {
            $enrollment = $this->trainingService->cancelEnrollment($enrollment, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'ENROLLMENT_CANCEL_ERROR', 422);
        }

        return $this->success($enrollment, 'Enrollment cancelled successfully.');
    }

    // =========================================================================
    // Certifications
    // =========================================================================

    public function indexCertifications(Request $request): JsonResponse
    {
        return $this->paginated($this->trainingService->listCertifications(
            [
                'employee_id' => $request->employee_id,
                'course_id' => $request->course_id,
                'active_only' => $request->boolean('active_only'),
            ],
            $request->integer('per_page', 15)
        ));
    }

    public function storeCertification(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'enrollment_id' => ['nullable', $this->ownedBy('training_enrollments', $organizationId)],
            'employee_id' => ['required', $this->ownedBy('employees', $organizationId)],
            'course_id' => ['required', $this->ownedBy('training_courses', $organizationId)],
            'certificate_number' => 'nullable|string|max:100',
            'issued_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:issued_date',
            'issued_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $validated['organization_id'] = $organizationId;
        $validated['created_by'] = auth()->id();
        $validated['is_active'] = true;

        $certification = $this->trainingService->createCertification($validated);

        return $this->created($certification->load(['employee', 'course']));
    }

    public function issueCertificate(Request $request, int $enrollmentId): JsonResponse
    {
        $enrollment = $this->trainingService->findEnrollment($enrollmentId);

        if ($enrollment === null) {
            return $this->notFound('Enrollment not found.');
        }

        $validated = $request->validate([
            'issued_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            $certification = $this->trainingService->issueCertificate($enrollment, $validated, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'CERTIFICATE_ISSUE_ERROR', 422);
        }

        return $this->created($certification->load(['employee', 'course']));
    }

    public function expiringCertifications(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $daysAhead = $request->integer('days', 30);

        $certifications = $this->trainingService->getExpiringCertifications($orgId, $daysAhead);

        return $this->success($certifications);
    }

    // =========================================================================
    // Training Needs
    // =========================================================================

    public function indexNeeds(Request $request): JsonResponse
    {
        return $this->paginated($this->trainingService->listNeeds(
            $request->only(['employee_id', 'department_id', 'status', 'priority']),
            $this->safeSortBy($request->sort_by, ['priority', 'status', 'target_date', 'created_at'], 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15)
        ));
    }

    public function storeNeed(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            ...$this->needReferenceRules($organizationId),
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:'.implode(',', TrainingNeed::PRIORITIES),
            'target_date' => 'nullable|date',
        ]);

        $validated['organization_id'] = $organizationId;

        try {
            $need = $this->trainingService->createTrainingNeed($validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'NEED_CREATE_ERROR', 422);
        }

        return $this->created($need->load(['employee', 'department', 'course']));
    }

    public function updateNeed(Request $request, int $id): JsonResponse
    {
        $need = $this->trainingService->findNeed($id);

        if ($need === null) {
            return $this->notFound('Training need not found.');
        }

        $validated = $request->validate([
            ...$this->needReferenceRules($need->organization_id),
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:'.implode(',', TrainingNeed::PRIORITIES),
            'status' => 'sometimes|in:'.implode(',', TrainingNeed::STATUSES),
            'target_date' => 'nullable|date',
        ]);

        try {
            $need = $this->trainingService->updateTrainingNeed($need, $validated, auth()->id());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'NEED_UPDATE_ERROR', 422);
        }

        return $this->success($need->load(['employee', 'department', 'course']));
    }

    public function destroyNeed(Request $request, int $id): JsonResponse
    {
        $need = $this->trainingService->findNeed($id);

        if ($need === null) {
            return $this->notFound('Training need not found.');
        }

        $need->delete();

        return $this->success(null, 'Training need deleted successfully.');
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function mandatoryComplianceReport(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $report = $this->trainingService->getMandatoryComplianceReport($orgId);

        return $this->success($report);
    }

    public function trainingCalendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $orgId = $this->organizationId($request);
        $sessions = $this->trainingService->getTrainingCalendar($orgId, $validated['from'], $validated['to']);

        return $this->success($sessions);
    }

    /**
     * An exists rule for a row of the given organization. Users carry no
     * tenant scope, so the organization is checked for them as for any table.
     */
    private function ownedBy(string $table, ?int $organizationId): Exists
    {
        return Rule::exists($table, 'id')->where('organization_id', $organizationId);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function needReferenceRules(?int $organizationId): array
    {
        return [
            'employee_id' => ['nullable', $this->ownedBy('employees', $organizationId)],
            'department_id' => ['nullable', $this->ownedBy('departments', $organizationId)],
            'course_id' => ['nullable', $this->ownedBy('training_courses', $organizationId)],
            'identified_by' => ['nullable', $this->ownedBy('users', $organizationId)],
        ];
    }
}
