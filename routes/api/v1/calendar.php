<?php

use App\Http\Controllers\Api\V1\Calendar\CalendarController;
use App\Http\Controllers\Api\V1\Calendar\CalendarEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Calendar API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/calendar
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Calendars
    |--------------------------------------------------------------------------
    */
    Route::prefix('calendars')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('calendar.calendars.index')->middleware('check.permission:calendar.calendars.view');
        Route::post('/', [CalendarController::class, 'store'])->middleware('check.permission:calendar.calendars.create')->name('calendar.calendars.store');
        Route::get('/{calendar}', [CalendarController::class, 'show'])->name('calendar.calendars.show')->middleware('check.permission:calendar.calendars.view');
        Route::put('/{calendar}', [CalendarController::class, 'update'])->name('calendar.calendars.update')->middleware('check.permission:calendar.events.create');
        Route::delete('/{calendar}', [CalendarController::class, 'destroy'])->name('calendar.calendars.destroy')->middleware('check.permission:calendar.events.create');
    });

    /*
    |--------------------------------------------------------------------------
    | Calendar Events
    |--------------------------------------------------------------------------
    */
    Route::prefix('events')->group(function () {
        Route::get('/', [CalendarEventController::class, 'index'])->name('calendar.events.index')->middleware('check.permission:calendar.events.view');
        Route::post('/', [CalendarEventController::class, 'store'])->middleware('check.permission:calendar.events.create')->name('calendar.events.store');
        Route::get('/{calendarEvent}', [CalendarEventController::class, 'show'])->name('calendar.events.show')->middleware('check.permission:calendar.events.view');
        Route::put('/{calendarEvent}', [CalendarEventController::class, 'update'])->name('calendar.events.update')->middleware('check.permission:calendar.events.create');
        Route::delete('/{calendarEvent}', [CalendarEventController::class, 'destroy'])->name('calendar.events.destroy')->middleware('check.permission:calendar.events.create');

        // Attendees
        Route::post('/{calendarEvent}/attendees', [CalendarEventController::class, 'addAttendee'])->name('calendar.events.attendees.add')->middleware('check.permission:calendar.events.create');
        Route::delete('/{calendarEvent}/attendees/{attendee}', [CalendarEventController::class, 'removeAttendee'])->name('calendar.events.attendees.remove')->middleware('check.permission:calendar.events.create');
        Route::post('/{calendarEvent}/attendees/{attendee}/respond', [CalendarEventController::class, 'respondAttendee'])->name('calendar.events.attendees.respond')->middleware('check.permission:calendar.events.create');

        // Reminders
        Route::post('/{calendarEvent}/reminders', [CalendarEventController::class, 'setReminder'])->name('calendar.events.reminders.set')->middleware('check.permission:calendar.events.create');
        Route::delete('/{calendarEvent}/reminders/{reminder}', [CalendarEventController::class, 'removeReminder'])->name('calendar.events.reminders.remove')->middleware('check.permission:calendar.events.create');
    });
});
