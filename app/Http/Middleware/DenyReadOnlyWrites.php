<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A role granted only read permissions may not write.
 *
 * check.permission guards 895 of the 1,901 write endpoints. On the rest,
 * auth:api and check.organization are the only gates, so any authenticated
 * member of an organization can post to them whatever role they hold.
 *
 * The Viewer role makes the consequence concrete. It is a system role
 * described as "Read-only access to all features" and is seeded with every
 * permission ending in .view and nothing else. On a guarded endpoint that
 * works: it holds no .create, so it is refused. On an unguarded one nothing
 * asks, and it can create an off-cycle payroll run.
 *
 * Gating each endpoint individually is the real fix, and it needs a permission
 * chosen per endpoint and assigned to the right roles - product decisions,
 * roughly a thousand of them. This closes the hole those decisions leave open
 * in the meantime, by the narrowest rule that does it: someone who has been
 * granted no ability to change anything cannot change anything.
 *
 * Deliberately not a role-name check. A custom read-only role would not be
 * called "viewer", and asking what a user can do is the question that matters
 * rather than what their role is named.
 */
class DenyReadOnlyWrites
{
    private const WRITES = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::WRITES, true)) {
            return $next($request);
        }

        $user = auth('api')->user();

        if (! $user || $user->is_super_admin) {
            return $next($request);
        }

        if ($this->holdsAnyWritePermission($user)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Your role has read-only access.',
            ],
        ], 403);
    }

    /**
     * Whether any role the user holds carries a permission that is not a view.
     *
     * A user with no roles at all is treated as read-only: they have been
     * granted nothing, so they may not change anything.
     */
    private function holdsAnyWritePermission(mixed $user): bool
    {
        return $user->roles()
            ->whereHas('permissions', fn ($q) => $q->where('slug', 'not like', '%.view'))
            ->exists();
    }
}
