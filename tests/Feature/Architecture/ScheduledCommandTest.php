<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Everything the scheduler runs has to survive being run.
 *
 * These execute unattended. A command that throws every night throws into a
 * log nobody reads, and the first sign is the work not having been done —
 * invoices never marked overdue, webhooks never retried, exports never
 * cleaned up.
 *
 * This says nothing about whether a command does the right thing. It says the
 * code behind it loads, its dependencies resolve and its queries run, which
 * is the class of failure that hides in something nothing calls by hand.
 */
class ScheduledCommandTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    /**
     * As scheduled in routes/console.php, with the same arguments.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public static function scheduled(): array
    {
        return [
            'security:cleanup-all' => ['security:cleanup-all', []],
            'financial:cleanup-idempotency' => ['financial:cleanup-idempotency', []],
            'audit-logs:cleanup' => ['audit-logs:cleanup', ['--days' => 90]],
            'reports:run-scheduled daily' => ['reports:run-scheduled', ['--schedule' => 'daily']],
            'exports:cleanup' => ['exports:cleanup', []],
            'webhooks:process retry' => ['webhooks:process', ['--retry' => true]],
            'webhooks:process cleanup' => ['webhooks:process', ['--cleanup' => true, '--days' => 30]],
            'invoices:mark-overdue' => ['invoices:mark-overdue', []],
            'bills:mark-overdue' => ['bills:mark-overdue', []],
            'erp:archive' => ['erp:archive', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    #[DataProvider('scheduled')]
    public function test_a_scheduled_command_runs(string $command, array $arguments): void
    {
        // Nothing leaves the test. A command that calls out on its way to
        // failing would otherwise reach a real service.
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Bus::fake();
        Notification::fake();

        $this->setUpOrganization('SA');

        $this->artisan($command, $arguments)->assertExitCode(0);
    }
}
