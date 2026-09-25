<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ERP\BusinessRuleException;
use App\Jobs\RunFraudChecksJob;
use App\Models\Core\UserEvent;
use App\Models\User;
use App\Services\Core\UserEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Checks a password login and records its outcome.
 *
 * Every refusal a caller without the password can reach reads the same:
 * an unknown email, a deleted account and a wrong password all answer
 * "Invalid credentials.", and the password is hashed in each case so the
 * response time does not tell them apart either. Only a caller who knows the
 * password learns that the account is deactivated.
 */
final class LoginService
{
    /** Compared against when no account matches, so the refusal costs one hash check like a wrong password. */
    private static ?string $placeholderHash = null;

    public function __construct(
        private readonly LoginAttemptService $attempts,
        private readonly UserEventService $events,
    ) {}

    /**
     * The account the email and password belong to.
     *
     * @throws BusinessRuleException when the email or IP is locked out, the
     *                               credentials do not match an account, or the account is inactive
     */
    public function authenticate(string $email, string $password, string $ipAddress): User
    {
        $rateCheck = $this->attempts->isAllowed($email, $ipAddress);

        if (! $rateCheck['allowed']) {
            throw new BusinessRuleException($rateCheck['message'], $rateCheck['reason'], 429);
        }

        $user = User::withTrashed()->where('email', $email)->first();
        $passwordMatches = Hash::check($password, $user?->password ?? self::placeholderHash());

        if ($user === null || $user->trashed() || ! $passwordMatches) {
            $this->attempts->recordAttempt($email, $ipAddress, false);

            throw new BusinessRuleException('Invalid credentials.', 'INVALID_CREDENTIALS', 401);
        }

        if (! $user->is_active) {
            $this->attempts->recordAttempt($email, $ipAddress, false);

            throw new BusinessRuleException('Your account is not active. Please contact support.', 'ACCOUNT_INACTIVE', 401);
        }

        $this->attempts->recordAttempt($email, $ipAddress, true);

        return $user;
    }

    /**
     * Stamps the login on the account, tracks it and queues the geographic
     * fraud check. A failure to queue the check is logged and does not block
     * the login.
     */
    public function recordSuccessfulLogin(User $user, Request $request): void
    {
        $user->recordLogin();

        $this->events->track(UserEvent::USER_LOGIN, ['email' => $user->email], $user->id, $user->organization_id, $request);

        try {
            RunFraudChecksJob::dispatch(
                'login',
                $user->id,
                [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'country_code' => $request->header('CF-IPCountry') ?? $request->header('X-Country-Code'),
                    'email' => $user->email,
                ],
                $user->organization_id,
                $user->id,
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Fraud check dispatch failed for login', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private static function placeholderHash(): string
    {
        return self::$placeholderHash ??= Hash::make(Str::random(40));
    }
}
