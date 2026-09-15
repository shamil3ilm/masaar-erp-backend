<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\SensitiveRevealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensitiveAccessController extends Controller
{
    public function __construct(private readonly SensitiveRevealService $reveals) {}

    /**
     * Verify the user's password and issue a short-lived access token for a specific resource.
     */
    public function requestAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'resource_type' => ['required', 'string', 'in:contact,employee'],
            'resource_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($this->reveals->isLockedOut($user)) {
            return $this->error(
                'Too many failed attempts. Try again in '.SensitiveRevealService::LOCKOUT_MINUTES.' minutes.',
                'OPERATION_FAILED',
                429
            );
        }

        if (! $this->reveals->verifyPassword($user, $validated['password'])) {
            return $this->error('Invalid password.', 'OPERATION_FAILED', 401);
        }

        $token = $this->reveals->issueToken($user, $validated['resource_type'], $validated['resource_id'], $request->ip());

        return $this->success([
            'access_token' => $token,
            'expires_in' => SensitiveRevealService::TOKEN_TTL_SECONDS,
        ], 'Access token issued.');
    }

    /**
     * Return unmasked sensitive fields for a resource after validating the access token.
     * The token is spent before the resource is looked up, so it is single-use either way.
     */
    public function reveal(Request $request, string $resourceType, string $resourceId): JsonResponse
    {
        $token = $request->header('X-Sensitive-Token') ?? $request->input('access_token');

        if (! $token) {
            return $this->error('Access token is required.', 'OPERATION_FAILED', 422);
        }

        $user = $request->user();

        if (! $this->reveals->consumeToken($user, $token, $resourceType, $resourceId)) {
            return $this->error('Invalid or expired access token.', 'OPERATION_FAILED', 401);
        }

        $fields = $this->reveals->reveal($user, $resourceType, $resourceId, $request->ip());

        if ($fields === null) {
            return $this->error('Resource not found.', 'OPERATION_FAILED', 404);
        }

        return $this->success(['fields' => $fields], 'Sensitive data retrieved.');
    }
}
