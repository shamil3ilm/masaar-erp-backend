<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Calendar\Calendar;
use App\Services\Calendar\CalendarService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        private CalendarService $calendarService
    ) {
    }

    /**
     * List calendars with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $calendars = $this->calendarService->listCalendars(
            $request->user()->organization_id,
            [
                'type' => $request->type,
                'user_id' => $request->user_id,
                'visible_only' => $request->boolean('visible_only'),
                'search' => $request->search,
            ],
            $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at'], 'name'),
            $this->safeSortOrder($request->sort_order, 'asc'),
            $request->per_page ? (int) $request->per_page : null
        );

        return $calendars instanceof LengthAwarePaginator ? $this->paginated($calendars) : $this->success($calendars);
    }

    /**
     * Store a new calendar.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'type' => 'nullable|in:personal,team,organization,resource',
            'is_default' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
        ]);

        $calendar = $this->calendarService->create($validated, auth()->id());

        return $this->created($calendar);
    }

    /**
     * Show a specific calendar.
     */
    public function show(Calendar $calendar): JsonResponse
    {
        return $this->success($calendar->load(['user', 'events']));
    }

    /**
     * Update a calendar.
     */
    public function update(Request $request, Calendar $calendar): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'type' => 'nullable|in:personal,team,organization,resource',
            'is_default' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'timezone' => 'nullable|string|max:50',
        ]);

        $calendar->update($validated);

        return $this->success($calendar->fresh(), 'Calendar updated successfully.');
    }

    /**
     * Delete a calendar.
     */
    public function destroy(Calendar $calendar): JsonResponse
    {
        $this->calendarService->deleteCalendar($calendar);

        return $this->success(null, 'Calendar deleted successfully.');
    }
}
