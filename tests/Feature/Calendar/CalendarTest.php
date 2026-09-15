<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\Calendar\Calendar;
use App\Models\Calendar\CalendarEvent;
use App\Models\Calendar\CalendarEventAttendee;
use App\Models\Calendar\CalendarEventReminder;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the calendar and event endpoints, keeps events and invitations inside
 * the caller's organization, and changes an event's attendees and reminders
 * only through that event.
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    private User $outsider;

    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'calendar.calendars.view',
            'calendar.calendars.create',
            'calendar.events.view',
            'calendar.events.create',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
        $this->outsider = User::factory()->create(['organization_id' => $this->other->id]);
        $this->calendar = $this->calendarFor($this->organization, $this->user, ['name' => 'Beta']);
    }

    public function test_calendars_list_by_name_within_the_organization_and_page_on_request(): void
    {
        $alpha = $this->calendarFor($this->organization, $this->user, ['name' => 'Alpha']);
        $this->calendarFor($this->other, $this->outsider, ['name' => 'Aardvark']);

        $list = $this->apiGet('/calendar/calendars')->assertOk();
        $this->assertSame([$alpha->id, $this->calendar->id], array_column($list->json('data'), 'id'));

        $this->apiGet('/calendar/calendars?per_page=1')->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 2);
    }

    public function test_deleting_a_calendar_removes_its_events_and_another_organizations_calendar_is_not_found(): void
    {
        $event = $this->eventIn($this->calendar);
        $theirs = $this->calendarFor($this->other, $this->outsider);

        $this->apiDelete("/calendar/calendars/{$theirs->getRouteKey()}")->assertNotFound();

        $this->apiDelete("/calendar/calendars/{$this->calendar->getRouteKey()}")->assertOk();
        $this->assertNull(Calendar::find($this->calendar->id));
        $this->assertNull(CalendarEvent::find($event->id));
    }

    public function test_events_list_by_start_filtered_by_calendar_and_status(): void
    {
        $later = $this->eventIn($this->calendar, ['start_at' => now()->addDays(2)]);
        $sooner = $this->eventIn($this->calendar, ['start_at' => now()->addDay()]);
        $this->eventIn($this->calendar, ['status' => CalendarEvent::STATUS_CANCELLED]);
        $this->eventIn($this->calendarFor($this->organization, $this->user));

        $response = $this->apiGet("/calendar/events?calendar_id={$this->calendar->id}&status=confirmed")->assertOk();

        $this->assertSame([$sooner->id, $later->id], array_column($response->json('data'), 'id'));
    }

    public function test_an_event_cannot_use_another_organizations_calendar_or_invite_its_users(): void
    {
        $theirs = $this->calendarFor($this->other, $this->outsider);

        $this->apiPost('/calendar/events', [
            'calendar_id' => $theirs->id,
            'title' => 'Borrowed',
            'start_at' => now()->addDay()->toDateTimeString(),
            'attendees' => [['user_id' => $this->outsider->id]],
        ])->assertStatus(422)->assertJsonValidationErrors(['calendar_id', 'attendees.0.user_id']);

        $event = $this->eventIn($this->calendar);
        $this->apiPut("/calendar/events/{$event->getRouteKey()}", ['calendar_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['calendar_id']);
        $this->apiPost("/calendar/events/{$event->getRouteKey()}/attendees", ['user_id' => $this->outsider->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->assertSame(1, CalendarEvent::withoutGlobalScopes()->count());
        $this->assertSame(0, CalendarEventAttendee::count());
    }

    public function test_attendees_and_reminders_are_changed_only_through_their_own_event(): void
    {
        $event = $this->eventIn($this->calendar);
        $theirEvent = $this->eventIn($this->calendarFor($this->other, $this->outsider), ['organization_id' => $this->other->id, 'created_by' => $this->outsider->id]);
        $theirAttendee = CalendarEventAttendee::create(['event_id' => $theirEvent->id, 'user_id' => $this->outsider->id, 'role' => 'attendee', 'status' => 'pending']);
        $theirReminder = CalendarEventReminder::create(['event_id' => $theirEvent->id, 'method' => 'email', 'minutes_before' => 10]);

        $this->apiPost("/calendar/events/{$event->getRouteKey()}/attendees/{$theirAttendee->id}/respond", ['status' => 'declined'])
            ->assertNotFound();
        $this->apiDelete("/calendar/events/{$event->getRouteKey()}/reminders/{$theirReminder->id}")->assertNotFound();
        $this->assertSame('pending', $theirAttendee->fresh()->status);
        $this->assertNotNull($theirReminder->fresh());

        $attendee = $this->apiPost("/calendar/events/{$event->getRouteKey()}/attendees", ['user_id' => $this->user->id])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $this->user->id)
            ->json('data.id');
        $this->apiPost("/calendar/events/{$event->getRouteKey()}/attendees/{$attendee}/respond", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $reminder = $this->apiPost("/calendar/events/{$event->getRouteKey()}/reminders", ['reminder_minutes' => 30])
            ->assertCreated()
            ->assertJsonPath('data.minutes_before', 30)
            ->json('data.id');
        $this->apiDelete("/calendar/events/{$event->getRouteKey()}/reminders/{$reminder}")->assertOk();
        $this->assertNull(CalendarEventReminder::find($reminder));
    }

    private function calendarFor(Organization $organization, User $owner, array $attributes = []): Calendar
    {
        return Calendar::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'name' => 'Calendar',
            'type' => Calendar::TYPE_PERSONAL,
            ...$attributes,
        ]);
    }

    private function eventIn(Calendar $calendar, array $attributes = []): CalendarEvent
    {
        return CalendarEvent::factory()->create([
            'organization_id' => $calendar->organization_id,
            'calendar_id' => $calendar->id,
            'created_by' => $this->user->id,
            'status' => CalendarEvent::STATUS_CONFIRMED,
            'related_type' => null,
            'related_id' => null,
            ...$attributes,
        ]);
    }
}
