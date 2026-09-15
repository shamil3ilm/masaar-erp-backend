<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Calendar\Calendar;
use App\Models\Calendar\CalendarEvent;
use App\Models\Calendar\CalendarEventAttendee;
use App\Models\Calendar\CalendarEventReminder;
use App\Models\Calendar\CalendarRecurringRule;
use Illuminate\Support\Facades\DB;

class CalendarService
{
    /**
     * The organization's calendars; a page of them when a page size is given.
     *
     * @param  array{type?: ?string, user_id?: mixed, visible_only?: bool, search?: ?string}  $filters
     */
    public function listCalendars(
        int $organizationId,
        array $filters,
        string $sortBy,
        string $sortOrder,
        ?int $perPage,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection {
        $query = Calendar::with(['user'])
            ->where('organization_id', $organizationId)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->ofType($type))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->forUser((int) $id))
            ->when($filters['visible_only'] ?? false, fn ($q) => $q->visible())
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy($sortBy, $sortOrder);

        return $perPage !== null ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Delete a calendar together with its events.
     */
    public function deleteCalendar(Calendar $calendar): void
    {
        DB::transaction(function () use ($calendar) {
            $calendar->events()->delete();
            $calendar->delete();
        });
    }

    /**
     * The organization's events; a page of them when a page size is given.
     *
     * @param  array{calendar_id?: mixed, event_type?: ?string, status?: ?string, start_date?: ?string, end_date?: ?string,
     *     upcoming?: bool, all_day?: bool, recurring?: bool, search?: ?string}  $filters
     */
    public function listEvents(
        int $organizationId,
        array $filters,
        string $sortBy,
        string $sortOrder,
        ?int $perPage,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection {
        $query = CalendarEvent::with(['calendar', 'creator', 'attendees', 'reminders'])
            ->where('organization_id', $organizationId)
            ->when($filters['calendar_id'] ?? null, fn ($q, $id) => $q->forCalendar((int) $id))
            ->when($filters['event_type'] ?? null, fn ($q, $type) => $q->ofType($type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(($filters['start_date'] ?? null) && ($filters['end_date'] ?? null), fn ($q) => $q->inDateRange($filters['start_date'], $filters['end_date']))
            ->when($filters['upcoming'] ?? false, fn ($q) => $q->upcoming())
            ->when($filters['all_day'] ?? false, fn ($q) => $q->allDay())
            ->when($filters['recurring'] ?? false, fn ($q) => $q->recurring())
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($inner) => $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
            ))
            ->orderBy($sortBy, $sortOrder);

        return $perPage !== null ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Record an attendee's response. The attendee is looked up through the
     * event, so an attendee of another event, or organization, is not found.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function respondAttendee(CalendarEvent $event, CalendarEventAttendee $attendee, array $data): CalendarEventAttendee
    {
        $attendee = $event->attendees()->whereKey($attendee->id)->firstOrFail();

        $attendee->update([
            'status' => $data['status'],
            'comment' => $data['comment'] ?? null,
            'responded_at' => now(),
        ]);

        return $attendee->fresh('user');
    }

    /**
     * Remove a reminder, looked up through its event.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function removeReminder(CalendarEvent $event, CalendarEventReminder $reminder): void
    {
        $event->reminders()->whereKey($reminder->id)->firstOrFail()->delete();
    }

    /**
     * Create a new calendar.
     */
    public function create(array $data, int $userId): Calendar
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['type'] = $data['type'] ?? Calendar::TYPE_PERSONAL;
            $data['user_id'] = $data['user_id'] ?? $userId;

            $calendar = Calendar::create($data);

            return $calendar;
        });
    }

    /**
     * Get events for a date range, optionally filtered by calendar.
     */
    public function getEvents(
        ?int $calendarId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $eventType = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = CalendarEvent::with(['calendar', 'creator', 'attendees', 'reminders'])
            ->when($calendarId, fn($q, $id) => $q->forCalendar($id))
            ->when($startDate && $endDate, fn($q) => $q->inDateRange($startDate, $endDate))
            ->when($eventType, fn($q, $type) => $q->ofType($type))
            ->orderBy('start_at');

        return $query->get();
    }

    /**
     * Create a new calendar event.
     */
    public function createEvent(array $data, int $userId): CalendarEvent
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $data['created_by'] ?? $userId;
            $data['status'] = $data['status'] ?? CalendarEvent::STATUS_CONFIRMED;

            $event = CalendarEvent::create($data);

            // Create recurring rule if recurring
            if (!empty($data['is_recurring']) && !empty($data['recurring_rule'])) {
                CalendarRecurringRule::create(array_merge(
                    $data['recurring_rule'],
                    ['event_id' => $event->id]
                ));
            }

            // Add attendees if provided
            if (!empty($data['attendees'])) {
                foreach ($data['attendees'] as $attendee) {
                    $attendee['event_id'] = $event->id;
                    CalendarEventAttendee::create($attendee);
                }
            }

            // Add reminders if provided
            if (!empty($data['reminders'])) {
                foreach ($data['reminders'] as $reminder) {
                    $reminder['event_id'] = $event->id;
                    CalendarEventReminder::create($reminder);
                }
            }

            return $event->load(['calendar', 'creator', 'attendees', 'reminders', 'recurringRule']);
        });
    }

    /**
     * Update a calendar event.
     */
    public function updateEvent(CalendarEvent $event, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($event, $data) {
            if ($event->isCancelled()) {
                throw new \InvalidArgumentException('Cancelled events cannot be updated.');
            }

            $event->update($data);

            // Update recurring rule if provided
            if (isset($data['recurring_rule'])) {
                if ($event->recurringRule) {
                    $event->recurringRule->update($data['recurring_rule']);
                } elseif (!empty($data['is_recurring'])) {
                    CalendarRecurringRule::create(array_merge(
                        $data['recurring_rule'],
                        ['event_id' => $event->id]
                    ));
                }
            }

            return $event->fresh(['calendar', 'creator', 'attendees', 'reminders', 'recurringRule']);
        });
    }

    /**
     * Delete a calendar event.
     */
    public function deleteEvent(CalendarEvent $event, bool $deleteRecurringSeries = false): bool
    {
        return DB::transaction(function () use ($event, $deleteRecurringSeries) {
            if ($deleteRecurringSeries && $event->isRecurring()) {
                // Delete all child events in the series
                $event->childEvents()->delete();
            }

            return (bool) $event->delete();
        });
    }

    /**
     * Add an attendee to an event.
     */
    public function addAttendee(CalendarEvent $event, array $data): CalendarEventAttendee
    {
        return DB::transaction(function () use ($event, $data) {
            $data['event_id'] = $event->id;
            $data['status'] = $data['status'] ?? CalendarEventAttendee::STATUS_PENDING;

            return CalendarEventAttendee::create($data);
        });
    }

    /**
     * Remove an attendee from an event.
     */
    public function removeAttendee(CalendarEvent $event, int $attendeeId): bool
    {
        return DB::transaction(function () use ($event, $attendeeId) {
            return (bool) $event->attendees()->where('id', $attendeeId)->delete();
        });
    }

    /**
     * Set a reminder for an event.
     */
    public function setReminder(CalendarEvent $event, array $data): CalendarEventReminder
    {
        return DB::transaction(function () use ($event, $data) {
            $data['event_id'] = $event->id;
            $data['method'] = $data['method'] ?? CalendarEventReminder::METHOD_NOTIFICATION;

            return CalendarEventReminder::create($data);
        });
    }
}
