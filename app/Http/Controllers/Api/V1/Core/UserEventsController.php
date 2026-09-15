<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\UserEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserEventsController extends Controller
{
    public function __construct(private readonly UserEventService $events) {}

    /**
     * List user events for the authenticated organisation.
     *
     * Supported query parameters:
     *   event_type  string  Filter by event type
     *   user_id     int     Filter by user
     *   from        date    Lower bound on created_at (inclusive)
     *   to          date    Upper bound on created_at (inclusive)
     */
    public function index(Request $request): JsonResponse
    {
        $events = $this->events->list($request->user()->organization_id, $this->onlyUserId($request), [
            'event_type' => $request->filled('event_type') ? (string) $request->string('event_type') : null,
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'from' => $request->filled('from') ? $request->input('from') : null,
            'to' => $request->filled('to') ? $request->input('to') : null,
        ]);

        return $this->success($events->items(), 'Events retrieved successfully');
    }

    /**
     * Return event counts grouped by type and day for the last 30 days.
     */
    public function summary(Request $request): JsonResponse
    {
        $rows = $this->events->summary($request->user()->organization_id, $this->onlyUserId($request));

        return $this->success($rows, 'Event summary retrieved successfully');
    }

    /**
     * The routes are open to every signed-in user as their own record; only a
     * holder of core.users.view reads other users' events, which carry their
     * IP address and browser.
     */
    private function onlyUserId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasPermission('core.users.view') ? null : $user->id;
    }
}
