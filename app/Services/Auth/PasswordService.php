<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\User;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Notifications\Auth\PasswordResetNotification;
use App\Services\Core\OtpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Replaces an account's password, either with an emailed reset code or by
 * the signed-in user who knows the current one.
 *
 * Both paths record a password change, which invalidates every token issued
 * before it. A reset attempt that cannot succeed answers the same whether the
 * email is unknown, deleted, has no code or was given a wrong one; that a code
 * has expired is reported only to a caller who presents that code.
 */
final class PasswordService
{
    private const CODE_LIFETIME_MINUTES = 60;

    private const RESET_REQUESTS_PER_HOUR = 3;

    private const RESET_ATTEMPTS_PER_TEN_MINUTES = 10;

    public function __construct(
        private readonly TokenBlacklistService $tokens,
    ) {}

    /**
     * Emails a six-digit reset code when the email belongs to an account; does
     * nothing, silently, when it does not.
     *
     * @throws BusinessRuleException when the email has asked for too many codes
     */
    public function sendResetCode(string $email): void
    {
        $this->throttle(
            'password_reset_request:'.md5($email),
            self::RESET_REQUESTS_PER_HOUR,
            now()->addHour(),
            'Too many password reset requests. Try again later.',
        );

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $code = OtpService::generateCode();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        $user->notify(new PasswordResetNotification($code));
    }

    /**
     * Sets the password when the code matches the one emailed, and spends the code.
     *
     * @throws BusinessRuleException when attempts are exhausted or the code is wrong or expired
     */
    public function resetWithCode(string $email, string $code, string $password): void
    {
        $this->throttle(
            'password_reset_verify:'.md5($email),
            self::RESET_ATTEMPTS_PER_TEN_MINUTES,
            now()->addMinutes(10),
            'Too many reset attempts. Try again later.',
        );

        $user = User::where('email', $email)->first();

        if ($user === null) {
            throw $this->invalidCode();
        }

        // The token row is locked so two requests cannot both spend one code.
        // The outcome is returned rather than thrown so removing an expired
        // code commits.
        $outcome = DB::transaction(function () use ($user, $email, $code, $password): string {
            $record = DB::table('password_reset_tokens')->where('email', $email)->lockForUpdate()->first();

            if ($record === null) {
                return 'invalid';
            }

            $matches = Hash::check($code, $record->token);

            if (Carbon::parse($record->created_at)->addMinutes(self::CODE_LIFETIME_MINUTES)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                return $matches ? 'expired' : 'invalid';
            }

            if (! $matches) {
                return 'invalid';
            }

            $user->password = $password;
            $user->save();

            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $this->tokens->blacklistAllUserTokens($user->id, 'password_reset');

            return 'reset';
        });

        match ($outcome) {
            'reset' => $user->notify(new PasswordChangedNotification()),
            'expired' => throw new BusinessRuleException('Reset code has expired. Please request a new one.', 'RESET_TOKEN_EXPIRED', 400),
            default => throw $this->invalidCode(),
        };
    }

    /**
     * Replaces the signed-in user's password and invalidates their tokens,
     * the one making this request included.
     *
     * @throws BusinessRuleException when the current password is wrong
     */
    public function change(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new BusinessRuleException('Current password is incorrect', 'INVALID_PASSWORD', 400);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $user->password = $newPassword;
            $user->save();

            $this->tokens->blacklistAllUserTokens($user->id, 'password_change');
            $this->tokens->blacklistCurrentToken('password_change');
        });

        $user->notify(new PasswordChangedNotification());
    }

    private function invalidCode(): BusinessRuleException
    {
        return new BusinessRuleException('Invalid or expired reset code.', 'INVALID_RESET_TOKEN', 400);
    }

    /**
     * Counts one request against the key's limit for the window.
     */
    private function throttle(string $key, int $limit, \DateTimeInterface $window, string $message): void
    {
        if ((int) Cache::get($key, 0) >= $limit) {
            throw new BusinessRuleException($message, 'TOO_MANY_REQUESTS', 429);
        }

        Cache::add($key, 0, $window);
        Cache::increment($key);
    }
}
