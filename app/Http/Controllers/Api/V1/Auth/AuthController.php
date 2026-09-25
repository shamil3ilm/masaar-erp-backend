<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Core\UserEvent;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\LoginService;
use App\Services\Auth\PasswordService;
use App\Services\Auth\RegistrationService;
use App\Services\Auth\TokenBlacklistService;
use App\Services\Auth\TwoFactorChallengeService;
use App\Services\Core\UserEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ReportsBusinessRules;

    public function __construct(
        private readonly LoginService $logins,
        private readonly TwoFactorChallengeService $twoFactorChallenges,
        private readonly RegistrationService $registrations,
        private readonly PasswordService $passwords,
        private readonly EmailVerificationService $emailVerifications,
        private readonly TokenBlacklistService $tokenBlacklistService,
        private readonly UserEventService $userEvents,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->logins->authenticate($this->normalizeEmail($request->email), $request->password, $request->ip());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        // An account with two-factor authentication gets a challenge, not a token.
        if ($this->twoFactorChallenges->requiresChallenge($user)) {
            return $this->success(
                ['requires_2fa' => true, 'challenge_token' => $this->twoFactorChallenges->issue($user)],
                '2FA verification required.'
            );
        }

        $token = auth('api')->login($user);
        $this->logins->recordSuccessfulLogin($user, $request);

        return $this->respondWithToken($token, $user);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code' => 'required|string',
        ]);

        try {
            $user = $this->twoFactorChallenges->complete($request->challenge_token, $request->code);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        $token = auth('api')->login($user);
        $user->recordLogin();

        return $this->respondWithToken($token, $user, 'Login successful');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registrations->register(
            array_merge($request->validated(), ['email' => $this->normalizeEmail($request->email)]),
            $request,
        );

        $token = auth('api')->login($user);

        return $this->respondWithToken($token, $user, 'Registration successful', 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (! $user) {
            return $this->unauthorized('User not found');
        }

        $user->load(['organization', 'branches', 'roles.permissions']);

        return $this->success([
            'user' => new UserResource($user),
            'permissions' => $user->getAllPermissions(),
            'default_branch' => $user->getDefaultBranch()?->only(['id', 'uuid', 'name', 'code']),
        ]);
    }

    public function refresh(): JsonResponse
    {
        try {
            // The old token is blacklisted before the new one is issued.
            $this->tokenBlacklistService->blacklistCurrentToken('refresh');

            $token = auth('api')->refresh();
            $user = auth('api')->user();

            return $this->respondWithToken($token, $user, 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->error('Token refresh failed. Please login again.', 'TOKEN_REFRESH_FAILED', 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if ($user) {
            $this->userEvents->track(UserEvent::USER_LOGOUT, [], $user->id, $user->organization_id, $request);
        }

        try {
            $this->tokenBlacklistService->blacklistCurrentToken('logout');
            auth('api')->logout();
        } catch (\Exception $e) {
            // An already invalid token needs no logout.
        }

        return $this->success(null, 'Successfully logged out');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $this->passwords->sendResetCode($this->normalizeEmail($request->email));
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'If an account with that email exists, a password reset code has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $this->passwords->resetWithCode($this->normalizeEmail($request->email), $request->token, $request->password);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Password has been reset successfully. Please login with your new password.');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        try {
            $this->emailVerifications->verify($this->normalizeEmail($request->email), $request->code, $request);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Email verified successfully.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $this->emailVerifications->resend($this->normalizeEmail($request->email));

        return $this->success(null, 'If an account with that email exists, a verification code has been sent.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        try {
            $this->passwords->change($user, $request->current_password, $request->new_password);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        // The request's own token was invalidated with the others; issue a new one.
        auth('api')->logout();
        $token = auth('api')->login($user);

        return $this->respondWithToken(
            $token,
            $user,
            'Password changed successfully. All other sessions have been logged out.'
        );
    }

    protected function respondWithToken(
        string $token,
        User $user,
        string $message = 'Login successful',
        int $statusCode = 200
    ): JsonResponse {
        // UserResource emits `user.organization` (null for super-admins); the
        // frontend sets its tenant context from it after authentication.
        $user->loadMissing('organization');

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ], $message, $statusCode);
    }

    protected function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
