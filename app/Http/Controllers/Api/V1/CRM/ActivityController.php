<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\CRM;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\CRM\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly ActivityService $activities) {}

    /**
     * List activities for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->activities->paginate(
            $request->user()->organization_id,
            $request->only(['activity_type', 'status', 'priority', 'assigned_to', 'related_type', 'related_id', 'search']),
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Create a new activity.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_type' => ['required', 'string', 'in:call,email,meeting,task,note,follow_up'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'related_type' => ['nullable', 'string'],
            'related_id' => ['nullable', 'integer'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_all_day' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'call_direction' => ['nullable', 'string', 'in:inbound,outbound'],
            'call_result' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:500'],
            'meeting_link' => ['nullable', 'string', 'max:500'],
            'assigned_to' => ['nullable', 'integer', $this->ownedBy('users')],
            'attendees' => ['nullable', 'array'],
            'reminder_datetime' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $activity = $this->activities->create($request->user()->organization_id, $request->user()->id, $validated);

        return $this->created($activity, 'Activity created successfully');
    }

    /**
     * Show a specific activity.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $activity = $this->activities->find($request->user()->organization_id, $id);

        return $activity ? $this->success($activity) : $this->notFound('Activity not found');
    }

    /**
     * Update an activity.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $activity = $this->activities->find($request->user()->organization_id, $id);

        if (! $activity) {
            return $this->notFound('Activity not found');
        }

        try {
            $this->activities->assertEditable($activity);

            $validated = $request->validate([
                'subject' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_datetime' => ['nullable', 'date'],
                'end_datetime' => ['nullable', 'date'],
                'duration_minutes' => ['nullable', 'integer', 'min:0'],
                'priority' => ['nullable', 'string', 'in:low,medium,high'],
                'location' => ['nullable', 'string', 'max:500'],
                'meeting_link' => ['nullable', 'string', 'max:500'],
                'assigned_to' => ['nullable', 'integer', $this->ownedBy('users')],
                'attendees' => ['nullable', 'array'],
                'reminder_datetime' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
            ]);

            return $this->success($this->activities->update($activity, $validated), 'Activity updated successfully');
        } catch (BusinessRuleException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
        }
    }

    /**
     * Complete an activity.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $activity = $this->activities->find($request->user()->organization_id, $id);

        if (! $activity) {
            return $this->notFound('Activity not found');
        }

        try {
            $this->activities->assertCompletable($activity);

            $validated = $request->validate([
                'outcome' => ['nullable', 'string'],
            ]);

            return $this->success(
                $this->activities->complete($activity, $validated['outcome'] ?? null),
                'Activity completed successfully'
            );
        } catch (BusinessRuleException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
        }
    }
}
