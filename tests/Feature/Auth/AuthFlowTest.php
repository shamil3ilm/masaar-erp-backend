<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FALaravel\Google2FA;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The public authentication endpoints: login, two-factor challenge, password
 * reset, email verification and password change.
 *
 * Besides the responses each flow returns, these tests hold the rule that an
 * unauthenticated caller cannot tell from a response whether an email is
 * registered, verified, deactivated or deleted.
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PASSWORD = 'Secret-Pass-123!';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->setUpOrganization();
    }

    // ----------------------------------------------------------------
    // Login
    // ----------------------------------------------------------------

    public function test_login_returns_a_token_and_the_user(): void
    {
        $user = $this->account('ali@example.com');

        $this->postJson('/api/v1/auth/login', ['email' => ' ALI@example.com ', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'ali@example.com')
            ->assertJsonPath('data.user.organization.id', $this->organization->id)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.two_factor_secret');

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_refuses_a_wrong_password_an_unknown_email_and_a_deleted_account_alike(): void
    {
        $this->account('ali@example.com');
        $this->account('gone@example.com')->delete();

        $responses = [
            $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'wrong-password']),
            $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => self::PASSWORD]),
            $this->postJson('/api/v1/auth/login', ['email' => 'gone@example.com', 'password' => self::PASSWORD]),
        ];

        foreach ($responses as $response) {
            $response->assertStatus(401)
                ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
                ->assertJsonPath('error.message', 'Invalid credentials.');
        }
    }

    public function test_login_reports_an_inactive_account_only_to_the_holder_of_its_password(): void
    {
        $this->account('idle@example.com', ['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'idle@example.com', 'password' => 'wrong-password'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->postJson('/api/v1/auth/login', ['email' => 'idle@example.com', 'password' => self::PASSWORD])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');
    }

    public function test_login_is_locked_after_repeated_failures(): void
    {
        $this->account('ali@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => 'wrong-password'])
                ->assertStatus(401);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => self::PASSWORD])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    // ----------------------------------------------------------------
    // Two-factor challenge
    // ----------------------------------------------------------------

    public function test_login_with_two_factor_returns_a_challenge_instead_of_a_token(): void
    {
        $this->twoFactorAccount('ali@example.com');

        $this->postJson('/api/v1/auth/login', ['email' => 'ali@example.com', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.requires_2fa', true)
            ->assertJsonMissingPath('data.token')
            ->assertJsonStructure(['data' => ['challenge_token']]);
    }

    public function test_a_challenge_is_completed_with_a_totp_code_once(): void
    {
        $secret = $this->twoFactorAccount('ali@example.com');
        $challenge = $this->challengeFor('ali@example.com');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/2fa/verify', ['challenge_token' => $challenge, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->postJson('/api/v1/auth/2fa/verify', ['challenge_token' => $challenge, 'code' => $code])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'CHALLENGE_TOKEN_REPLAYED');
    }

    public function test_a_recovery_code_completes_one_challenge_and_is_then_spent(): void
    {
        $this->twoFactorAccount('ali@example.com', ['AAAA-BBBBBB', 'CCCC-DDDDDD']);

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $this->challengeFor('ali@example.com'),
            'code' => 'AAAA-BBBBBB',
        ])->assertOk();

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $this->challengeFor('ali@example.com'),
            'code' => 'AAAA-BBBBBB',
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_OTP');

        $codes = User::where('email', 'ali@example.com')->first()->two_factor_recovery_codes;
        $this->assertCount(1, $codes);
        $this->assertTrue(Hash::check('CCCC-DDDDDD', $codes[0]));
    }

    public function test_a_tampered_or_expired_challenge_is_refused(): void
    {
        $user = $this->account('ali@example.com');

        $this->postJson('/api/v1/auth/2fa/verify', ['challenge_token' => 'not-encrypted', 'code' => '123456'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_CHALLENGE_TOKEN');

        $expired = encrypt(json_encode(['user_id' => $user->id, 'expires' => now()->subMinute()->timestamp]));

        $this->postJson('/api/v1/auth/2fa/verify', ['challenge_token' => $expired, 'code' => '123456'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'CHALLENGE_TOKEN_EXPIRED');
    }

    // ----------------------------------------------------------------
    // Password reset
    // ----------------------------------------------------------------

    public function test_forgot_password_answers_the_same_for_known_and_unknown_emails(): void
    {
        $this->account('ali@example.com');

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ali@example.com'])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'ali@example.com']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@example.com']);
    }

    public function test_forgot_password_is_limited_per_email(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    public function test_reset_password_with_the_emailed_code_changes_the_password_once(): void
    {
        $user = $this->account('ali@example.com');
        $this->resetCode('ali@example.com', '654321');

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('ali@example.com', '654321'))
            ->assertOk()
            ->assertJsonPath('message', 'Password has been reset successfully. Please login with your new password.');

        $this->assertTrue(Hash::check('New-Pass-456!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'ali@example.com']);
        $this->assertDatabaseHas('password_changes', ['user_id' => $user->id]);

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('ali@example.com', '654321'))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_RESET_TOKEN');
    }

    public function test_reset_password_reports_an_expired_code(): void
    {
        $this->account('ali@example.com');
        $this->resetCode('ali@example.com', '654321', now()->subMinutes(61));

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('ali@example.com', '654321'))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'RESET_TOKEN_EXPIRED');
    }

    public function test_reset_password_answers_an_unknown_or_deleted_email_or_a_guess_at_an_expired_code_like_a_wrong_code(): void
    {
        $this->account('ali@example.com');
        $this->resetCode('ali@example.com', '654321');
        $this->account('gone@example.com')->delete();
        $this->resetCode('gone@example.com', '654321');
        $this->account('late@example.com');
        $this->resetCode('late@example.com', '654321', now()->subMinutes(61));

        $wrongCode = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('ali@example.com', '000000'));
        $unknown = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('nobody@example.com', '654321'));
        $deleted = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('gone@example.com', '654321'));
        $expiredGuess = $this->postJson('/api/v1/auth/reset-password', $this->resetPayload('late@example.com', '000000'));

        foreach ([$unknown, $deleted, $expiredGuess] as $response) {
            $this->assertSame($wrongCode->status(), $response->status());
            $this->assertSame($wrongCode->json('error'), $response->json('error'));
        }
    }

    // ----------------------------------------------------------------
    // Email verification
    // ----------------------------------------------------------------

    public function test_verify_email_with_the_emailed_code_marks_the_email_verified(): void
    {
        $user = $this->account('ali@example.com', [
            'email_verified_at' => null,
            'email_verification_code' => Hash::make('112233'),
            'email_verification_code_sent_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/email/verify', ['email' => 'ali@example.com', 'code' => '112233'])
            ->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_verification_code);
    }

    public function test_verify_email_reports_an_expired_code(): void
    {
        $this->account('ali@example.com', [
            'email_verified_at' => null,
            'email_verification_code' => Hash::make('112233'),
            'email_verification_code_sent_at' => now()->subMinutes(61),
        ]);

        $this->postJson('/api/v1/auth/email/verify', ['email' => 'ali@example.com', 'code' => '112233'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VERIFICATION_CODE_EXPIRED');
    }

    public function test_verify_email_answers_every_email_without_a_matching_code_alike(): void
    {
        $this->account('pending@example.com', [
            'email_verified_at' => null,
            'email_verification_code' => Hash::make('112233'),
            'email_verification_code_sent_at' => now(),
        ]);
        $this->account('verified@example.com', ['email_verified_at' => now()]);
        $this->account('unsent@example.com', ['email_verified_at' => null, 'email_verification_code' => null]);
        $this->account('stale@example.com', [
            'email_verified_at' => null,
            'email_verification_code' => Hash::make('112233'),
            'email_verification_code_sent_at' => now()->subMinutes(61),
        ]);

        $wrongCode = $this->postJson('/api/v1/auth/email/verify', ['email' => 'pending@example.com', 'code' => '000000']);
        $wrongCode->assertStatus(400)->assertJsonPath('error.code', 'INVALID_VERIFICATION_CODE');

        foreach (['nobody@example.com', 'verified@example.com', 'unsent@example.com', 'stale@example.com'] as $email) {
            $response = $this->postJson('/api/v1/auth/email/verify', ['email' => $email, 'code' => '000000']);

            $this->assertSame($wrongCode->status(), $response->status(), $email);
            $this->assertSame($wrongCode->json('error'), $response->json('error'), $email);
        }
    }

    public function test_verify_email_limits_code_guesses_per_email(): void
    {
        $this->account('ali@example.com', [
            'email_verified_at' => null,
            'email_verification_code' => Hash::make('112233'),
            'email_verification_code_sent_at' => now(),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/auth/email/verify', ['email' => 'ali@example.com', 'code' => '000000'])
                ->assertStatus(400);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson('/api/v1/auth/email/verify', ['email' => 'ali@example.com', 'code' => '112233'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');

        $this->assertNull(User::where('email', 'ali@example.com')->first()->email_verified_at);
    }

    public function test_resend_verification_sends_a_new_code_to_an_unverified_account(): void
    {
        $user = $this->account('ali@example.com', ['email_verified_at' => null]);

        $this->postJson('/api/v1/auth/email/resend', ['email' => 'ali@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account with that email exists, a verification code has been sent.');

        $this->assertNotNull($user->fresh()->email_verification_code);
        Notification::assertSentTo($user, \App\Notifications\Auth\EmailVerificationNotification::class);
    }

    public function test_resend_verification_answers_every_email_alike(): void
    {
        $this->account('verified@example.com', ['email_verified_at' => now()]);
        $recent = $this->account('recent@example.com', [
            'email_verified_at' => null,
            'email_verification_code' => Hash::make('112233'),
            'email_verification_code_sent_at' => now(),
        ]);

        $unknown = $this->postJson('/api/v1/auth/email/resend', ['email' => 'nobody@example.com'])->assertOk();

        foreach (['verified@example.com', 'recent@example.com'] as $email) {
            $response = $this->postJson('/api/v1/auth/email/resend', ['email' => $email]);

            $this->assertSame($unknown->status(), $response->status(), $email);
            $this->assertSame($unknown->json('message'), $response->json('message'), $email);
        }

        Notification::assertNotSentTo($recent, \App\Notifications\Auth\EmailVerificationNotification::class);
    }

    // ----------------------------------------------------------------
    // Password change
    // ----------------------------------------------------------------

    public function test_change_password_replaces_the_password_and_returns_a_new_token(): void
    {
        $user = $this->account('ali@example.com');
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrong-password',
                'new_password' => 'New-Pass-456!',
                'new_password_confirmation' => 'New-Pass-456!',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_PASSWORD');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => self::PASSWORD,
                'new_password' => 'New-Pass-456!',
                'new_password_confirmation' => 'New-Pass-456!',
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertTrue(Hash::check('New-Pass-456!', $user->fresh()->password));
        $this->assertDatabaseHas('password_changes', ['user_id' => $user->id]);
    }

    // ----------------------------------------------------------------
    // Registration
    // ----------------------------------------------------------------

    public function test_registration_creates_its_own_organization_with_an_admin_branch_and_modules(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Sara Hassan',
            'email' => 'Sara@Example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name' => 'Beta Corp',
            'country_code' => 'AE',
            'organization_id' => $this->organization->id,
        ])->assertCreated()
            ->assertJsonPath('message', 'Registration successful')
            ->assertJsonPath('data.user.email', 'sara@example.com')
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'sara@example.com')->first();
        $organization = Organization::find($user->organization_id);

        $this->assertNotSame($this->organization->id, $organization->id);
        $this->assertSame($organization->id, $response->json('data.user.organization.id'));
        $this->assertSame('AED', $organization->base_currency);
        $this->assertSame('VAT', $organization->tax_scheme);
        $this->assertSame('Asia/Dubai', $user->timezone);
        $this->assertSame(8, DB::table('organization_modules')->where('organization_id', $organization->id)->count());
        $this->assertSame(1, DB::table('user_branches')->where('user_id', $user->id)->count());
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function account(string $email, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'email' => $email,
            'password' => self::PASSWORD,
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    /**
     * @param  list<string>  $recoveryCodes
     */
    private function twoFactorAccount(string $email, array $recoveryCodes = []): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        $this->account($email, [
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $recoveryCodes),
        ]);

        return $secret;
    }

    private function challengeFor(string $email): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD])
            ->assertOk()
            ->json('data.challenge_token');
    }

    private function resetCode(string $email, string $code, ?\DateTimeInterface $createdAt = null): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($code),
            'created_at' => $createdAt ?? now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(string $email, string $code): array
    {
        return [
            'email' => $email,
            'token' => $code,
            'password' => 'New-Pass-456!',
            'password_confirmation' => 'New-Pass-456!',
        ];
    }
}
