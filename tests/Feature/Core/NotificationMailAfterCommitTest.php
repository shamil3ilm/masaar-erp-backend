<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Notification;
use App\Services\Core\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A notification's email goes out once the transaction that raised it has
 * committed, and never for one that rolls back.
 */
class NotificationMailAfterCommitTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        config(['mail.default' => 'array']);

        $this->service = app(NotificationService::class);
    }

    public function test_no_mail_is_sent_for_a_notification_whose_transaction_rolls_back(): void
    {
        try {
            DB::transaction(function (): void {
                $this->notify();

                throw new \RuntimeException('The change that raised the notification failed.');
            });
        } catch (\RuntimeException) {
        }

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_the_mail_is_sent_once_the_transaction_commits(): void
    {
        DB::transaction(fn () => $this->notify());

        $this->assertCount(1, $this->sentMessages());
    }

    private function notify(): void
    {
        $this->service->send(
            user: $this->user,
            type: Notification::TYPE_SYSTEM_ALERT,
            title: 'Recurring invoice created',
            message: 'An invoice was created from a recurring profile.',
            channels: ['database', 'email'],
        );
    }

    /** @return array<int, mixed> */
    private function sentMessages(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }
}
