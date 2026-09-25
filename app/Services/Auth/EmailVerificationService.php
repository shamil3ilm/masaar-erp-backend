<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Core\UserEvent;
use App\Models\User;
use App\Notifications\Auth\EmailVerificationNotification;
use App\Services\Core\OtpService;
use App\Services\Core\UserEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Confirms an account's email with a six-digit code sent to it.
 *
 * Both endpoints are public, so neither may reveal whether an email is
 * registered or already verified: a verification without a matching code
 * always reads "Invalid verification code.", and a resend always reports that
 * a code was sent. Guesses are limited per email so the code cannot be
 * brute-forced from many addresses.
 */
final class EmailVerificationService
{
    private const CODE_LIFETIME_MINUTES = 60;

    private const RESEND_INTERVAL_MINUTES = 2;

    private const ATTEMPTS_PER_TEN_MINUTES = 10;

    public function __construct(
        private readonly UserEventService $events,
    ) {}

    /**
     * Marks the email verified when the code matches the one sent.
     *
     * @throws BusinessRuleException when guesses are exhausted or the code is wrong or expired
     */
    public function verify(string $email, string $code, Request $request): void
    {
        $this->countAttempt($email);

        $user = User::where('email', $email)->first();

        if ($user === null || $user->email_verified_at !== null || $user->email_verification_code === null) {
            throw $this->invalidCode();
        }

        $matches = Hash::check($code, $user->email_verification_code);

        if ($this->isExpired($user)) {
            $user->email_verification_code = null;
            $user->email_verification_code_sent_at = null;
            $user->save();

            throw $matches
                ? new BusinessRuleException('Verification code has expired. Please request a new one.', 'VERIFICATION_CODE_EXPIRED', 400)
                : $this->invalidCode();
        }

        if (! $matches) {
            throw $this->invalidCode();
        }

        $user->email_verified_at = now();
        $user->email_verification_code = null;
        $user->email_verification_code_sent_at = null;
        $user->save();

        $this->events->track(UserEvent::EMAIL_VERIFIED, [], $user->id, $user->organization_id, $request);
    }

    /**
     * Sends a new code to an unverified account unless one went out in the
     * last two minutes. Unknown and verified emails are ignored silently.
     */
    public function resend(string $email): void
    {
        $user = User::where('email', $email)->first();

        if ($user === null || $user->email_verified_at !== null || $this->sentRecently($user)) {
            return;
        }

        $code = OtpService::generateCode();

        $user->email_verification_code = Hash::make($code);
        $user->email_verification_code_sent_at = now();
        $user->save();

        $user->notify(new EmailVerificationNotification($code));
    }

    private function isExpired(User $user): bool
    {
        return $user->email_verification_code_sent_at !== null
            && Carbon::parse($user->email_verification_code_sent_at)->addMinutes(self::CODE_LIFETIME_MINUTES)->isPast();
    }

    private function sentRecently(User $user): bool
    {
        return $user->email_verification_code_sent_at !== null
            && ! Carbon::parse($user->email_verification_code_sent_at)->addMinutes(self::RESEND_INTERVAL_MINUTES)->isPast();
    }

    private function countAttempt(string $email): void
    {
        $key = 'email_verify_attempts:'.md5($email);

        if ((int) Cache::get($key, 0) >= self::ATTEMPTS_PER_TEN_MINUTES) {
            throw new BusinessRuleException('Too many verification attempts. Try again later.', 'TOO_MANY_REQUESTS', 429);
        }

        Cache::add($key, 0, now()->addMinutes(10));
        Cache::increment($key);
    }

    private function invalidCode(): BusinessRuleException
    {
        return new BusinessRuleException('Invalid verification code.', 'INVALID_VERIFICATION_CODE', 400);
    }
}
