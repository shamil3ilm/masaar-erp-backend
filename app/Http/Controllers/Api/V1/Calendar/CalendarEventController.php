<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Calendar\CalendarEvent;
use App\Models\Calendar\CalendarEventAttendee;
use App\Models\Calendar\CalendarEventReminder;
use App\Services\Calendar\CalendarService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private CalendarService $calendarService
    ) {
    }

    /**
     * List events with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $events = $this->calendarService->listEvents(
            $request->user()->organization_id,
            [
                'calendar_id' => $request->calendar_id,
                'event_type' => $request->event_type,
                'status' => $request->status,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'upcoming' => $request->boolean('upcoming'),
                'all_day' => $request->boolean('all_day'),
                'recurring' => $request->boolean('recurring'),
                'search' => $request->search,
            ],
            $this->safeSortBy($request->sort_by, ['title', 'start_at', 'end_at', 'created_at', 'updated_at'], 'start_at'),
            $this->safeSortOrder($request->sort_order, 'asc'),
            $request->per_page ? (int) $request->per_page : null
        );

        return $events instanceof LengthAwarePaginator ? $this->paginated($events) : $this->success($events);
    }

    /**
     * Store a new event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calendar_id' => ['required', $this->ownedBy('calendars')],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'event_type' => 'nullable|in:event,meeting,task,reminder,holiday',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_all_day' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
            'status' => 'nullable|in:tentative,confirmed,cancelled',
            'visibility' => 'nullable|in:default,public,private',
            'color' => 'nullable|string|max:7',
            'related_type' => 'nullable|string',
            'related_id' => 'nullable|integer',
            'is_recurring' => 'nullable|boolean',
            'recurring_rule' => 'nullable|array',
            'recurring_rule.frequency' => 'required_with:recurring_rule|in:daily,weekly,monthly,yearly',
            'recurring_rule.interval' => 'nullable|integer|min:1|max:255',
            'recurring_rule.by_day' => 'nullable|array',
            'recurring_rule.by_month_day' => 'nullable|integer|min:1|max:31',
            'recurring_rule.by_month' => 'nullable|integer|min:1|max:12',
            'recurring_rule.until_date' => 'nullable|date',
            'recurring_rule.count' => 'nullable|integer|min:1',
            'attendees' => 'nullable|array',
            'attendees.*.user_id' => ['nullable', $this->ownedBy('users')],
            'attendees.*.email' => 'nullable|email',
            'attendees.*.name' => 'nullable|string|max:255',
            'attendees.*.role' => 'nullable|in:organizer,attendee,optional',
            'reminders' => 'nullable|array',
            'reminders.*.method' => 'nullable|in:notification,email,sms',
            'reminders.*.minutes_before' => 'required_with:reminders|integer|min:0',
        ]);

        $event = $this->calendarService->createEvent($validated, auth()->id());

        return $this->created($event);
    }

    /**
     * Show a specific event.
     */
    public function show(CalendarEvent $calendarEvent): JsonResponse
    {
        return $this->success(
            $calendarEvent->load(['calendar', 'creator', 'attendees.user', 'reminders', 'recurringRule'])
        );
    }

    /**
     * Update an event.
     */
    public function update(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $validated = $request->validate([
            'calendar_id' => ['sometimes', $this->ownedBy('calendars')],
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'event_type' => 'nullable|in:event,meeting,task,reminder,holiday',
            'start_at' => 'sometimes|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'is_all_day' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
            'status' => 'nullable|in:tentative,confirmed,cancelled',
            'visibility' => 'nullable|in:default,public,private',
            'color' => 'nullable|string|max:7',
            'is_recurring' => 'nullable|boolean',
            'recurring_rule' => 'nullable|array',
            'recurring_rule.frequency' => 'required_with:recurring_rule|in:daily,weekly,monthly,yearly',
            'recurring_rule.interval' => 'nullable|integer|min:1|max:255',
            'recurring_rule.by_day' => 'nullable|array',
            'recurring_rule.until_date' => 'nullable|date',
            'recurring_rule.count' => 'nullable|integer|min:1',
        ]);

        $event = $this->calendarService->updateEvent($calendarEvent, $validated);

        return $this->success($event, 'Event updated successfully.');
    }

    /**
     * Delete an event.
     */
    public function destroy(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $deleteSeries = $request->boolean('delete_series');

        $this->calendarService->deleteEvent($calendarEvent, $deleteSeries);

        return $this->success(null, 'Event deleted successfully.');
    }

    /**
     * Add an attendee to an event.
     */
    public function addAttendee(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', $this->ownedBy('users')],
            'email' => 'nullable|email|max:255',
            'name' => 'nullable|string|max:255',
            'role' => 'nullable|in:organizer,attendee,optional',
        ]);

        $attendee = $this->calendarService->addAttendee($calendarEvent, $validated);

        return $this->created($attendee->load('user'));
    }

    /**
     * Remove an attendee from an event.
     */
    public function removeAttendee(CalendarEvent $calendarEvent, CalendarEventAttendee $attendee): JsonResponse
    {
        $this->calendarService->removeAttendee($calendarEvent, $attendee->id);

        return $this->success(null, 'Attendee removed successfully.');
    }

    /**
     * Respond to an event attendance (accept/decline/tentative).
     */
    public function respondAttendee(Request $request, CalendarEvent $calendarEvent, CalendarEventAttendee $attendee): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,declined,tentative',
            'comment' => 'nullable|string|max:500',
        ]);

        return $this->success(
            $this->calendarService->respondAttendee($calendarEvent, $attendee, $validated),
            'Response recorded successfully.'
        );
    }

    /**
     * Set a reminder for an event.
     */
    public function setReminder(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'nullable|in:notification,email,sms',
            'minutes_before' => 'nullable|integer|min:0',
            'reminder_minutes' => 'nullable|integer|min:0',
        ]);

        // Accept both field names
        if (!isset($validated['minutes_before']) && isset($validated['reminder_minutes'])) {
            $validated['minutes_before'] = $validated['reminder_minutes'];
        }

        $validated['minutes_before'] = $validated['minutes_before'] ?? 0;

        $reminder = $this->calendarService->setReminder($calendarEvent, $validated);

        return $this->created($reminder);
    }

    /**
     * Remove a reminder from an event.
     */
    public function removeReminder(CalendarEvent $calendarEvent, CalendarEventReminder $reminder): JsonResponse
    {
        $this->calendarService->removeReminder($calendarEvent, $reminder);

        return $this->success(null, 'Reminder removed successfully.');
    }
}
