<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The welcome and verification notifications of a registration are sent once
 * the transaction creating the account has committed, so a registration that
 * rolls back emails nobody.
 */
class RegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_notifications_are_sent_after_the_account_is_committed(): void
    {
        $baseline = DB::transactionLevel();
        $levels = [];

        // Records the transaction depth each notification is sent at, and stops
        // the send: only the timing is under test.
        Event::listen(NotificationSending::class, function (NotificationSending $event) use (&$levels): bool {
            $levels[class_basename($event->notification)] = DB::transactionLevel();

            return false;
        });

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sara Hassan',
            'email' => 'sara@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organization_name' => 'Beta Corp',
            'country_code' => 'SA',
        ])->assertCreated();

        $this->assertSame(['WelcomeNotification' => $baseline, 'VerifyEmail' => $baseline], $levels);
    }
}
