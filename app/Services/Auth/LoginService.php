<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ERP\BusinessRuleException;
use App\Jobs\RunFraudChecksJob;
use App\Models\Core\LoginHistory;
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

        $country = $this->countryOf($request);

        // Written here, and nowhere else, which is why it was never written at
        // all: login_history has the user, the status and the indexes for
        // "where has this account been seen from", a read endpoint, and no
        // writer. login_attempts holds email, IP and success and drives
        // lockout; it is not a history and carries no user.
        //
        // Successes only for now. A failed login carries no account when the
        // email is unknown, and the question this feeds - has this user been
        // here before - is about logins that worked. Failures continue to be
        // recorded in login_attempts.
        LoginHistory::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => (string) $request->ip(),
            'country_code' => $country,
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'status' => LoginHistory::STATUS_SUCCESS,
            'attempted_at' => now(),
        ]);

        $this->events->track(UserEvent::USER_LOGIN, ['email' => $user->email], $user->id, $user->organization_id, $request);

        try {
            RunFraudChecksJob::dispatch(
                'login',
                $user->id,
                [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'country_code' => $country,
                    'email' => $user->email,
                ],
                $user->organization_id,
                $user->id,
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Fraud check dispatch failed for login', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The country a request came from, as the edge reported it.
     *
     * Cloudflare's header first, then a generic one a reverse proxy may set.
     * Two upper-case letters or nothing: the column holds two characters and
     * the value is a header, so a proxy sending "XX, YY" or a hostile client
     * sending anything at all must not reach it. Cloudflare answers XX for an
     * address it cannot place, which is not a country either.
     */
    private function countryOf(Request $request): ?string
    {
        $country = strtoupper(trim(
            (string) ($request->header('CF-IPCountry') ?? $request->header('X-Country-Code') ?? '')
        ));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 && $country !== 'XX'
            ? $country
            : null;
    }

    private static function placeholderHash(): string
    {
        return self::$placeholderHash ??= Hash::make(Str::random(40));
    }
}
