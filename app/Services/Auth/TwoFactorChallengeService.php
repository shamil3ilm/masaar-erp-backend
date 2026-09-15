<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Google2FA;

/**
 * The second step of a login for an account with two-factor authentication.
 *
 * A correct password yields an encrypted challenge instead of a token. The
 * challenge lives ten minutes and is spent by its first verification attempt,
 * right or wrong, so every code guess needs the password again. A recovery
 * code is removed on the locked user row, so two logins racing with the same
 * code cannot both use it.
 */
final class TwoFactorChallengeService
{
    private const LIFETIME_MINUTES = 10;

    /** How long a spent challenge is remembered; it outlives the challenge itself. */
    private const SPENT_TTL_SECONDS = 600;

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function requiresChallenge(User $user): bool
    {
        return $user->two_factor_enabled && $user->two_factor_secret !== null;
    }

    public function issue(User $user): string
    {
        return encrypt(json_encode([
            'user_id' => $user->id,
            'expires' => now()->addMinutes(self::LIFETIME_MINUTES)->timestamp,
        ]));
    }

    /**
     * The user who passed the challenge with a TOTP or recovery code.
     *
     * @throws BusinessRuleException when the challenge is invalid, expired or
     *                               spent, the user is gone, or the code does not match
     */
    public function complete(string $challengeToken, string $code): User
    {
        $payload = $this->payloadOf($challengeToken);

        // Cache::add stores the key only if it is absent, so exactly one
        // request can spend a challenge.
        if (! Cache::add('twofa_token_used:'.hash('sha256', $challengeToken), true, self::SPENT_TTL_SECONDS)) {
            throw new BusinessRuleException('Challenge token already used.', 'CHALLENGE_TOKEN_REPLAYED', 401);
        }

        $user = User::find($payload['user_id']);

        if ($user === null || ! $user->is_active) {
            throw new BusinessRuleException('User not found or inactive.', 'USER_NOT_FOUND', 404);
        }

        if ($user->two_factor_secret && ! $user->two_factor_confirmed_at) {
            throw new BusinessRuleException('Two-factor authentication setup is incomplete.', 'TWO_FACTOR_SETUP_INCOMPLETE', 403);
        }

        $verified = ($user->two_factor_secret !== null && $this->google2fa->verifyKey($user->two_factor_secret, $code))
            || $this->spendRecoveryCode($user, $code);

        if (! $verified) {
            throw new BusinessRuleException('Invalid verification code.', 'INVALID_OTP', 422);
        }

        return $user;
    }

    /**
     * @return array{user_id: int, expires: int}
     */
    private function payloadOf(string $challengeToken): array
    {
        try {
            $payload = json_decode(decrypt($challengeToken), true);
        } catch (\Exception) {
            throw new BusinessRuleException('Invalid or tampered challenge token.', 'INVALID_CHALLENGE_TOKEN', 400);
        }

        if (! isset($payload['user_id'], $payload['expires'])) {
            throw new BusinessRuleException('Invalid challenge token structure.', 'INVALID_CHALLENGE_TOKEN', 400);
        }

        if (now()->timestamp > $payload['expires']) {
            throw new BusinessRuleException('Challenge token has expired. Please log in again.', 'CHALLENGE_TOKEN_EXPIRED', 401);
        }

        return $payload;
    }

    /**
     * Removes the matching recovery code from the locked user row.
     */
    private function spendRecoveryCode(User $user, string $code): bool
    {
        return $user->lockForTransition(function (User $locked) use ($code): bool {
            $codes = $locked->two_factor_recovery_codes;

            if (! is_array($codes)) {
                return false;
            }

            foreach ($codes as $index => $hashed) {
                if (Hash::check($code, $hashed)) {
                    unset($codes[$index]);
                    $locked->two_factor_recovery_codes = array_values($codes);
                    $locked->save();

                    return true;
                }
            }

            return false;
        });
    }
}
