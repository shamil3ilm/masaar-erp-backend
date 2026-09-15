<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\JobMonitor;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the super admin job monitor: the list covers the caller's organization
 * and platform jobs, a job is shown with its logs, and only a failed job with
 * attempts left is retried.
 */
class JobMonitorEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_jobs_are_listed_for_the_organization_and_platform(): void
    {
        $own = $this->job($this->organization->id, JobMonitor::STATUS_COMPLETED, now()->subHour());
        $platform = $this->job(null, JobMonitor::STATUS_QUEUED, now());
        $this->job(Organization::factory()->create()->id, JobMonitor::STATUS_QUEUED, now());

        $this->assertSame(
            [$platform->id, $own->id],
            array_column($this->apiGet('/job-monitor')->assertOk()->json('data'), 'id')
        );
        $this->apiGet('/job-monitor?status=completed')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_a_job_is_shown_with_logs_and_a_failed_one_retried_once_allowed(): void
    {
        $failed = $this->job($this->organization->id, JobMonitor::STATUS_FAILED, now(), ['attempts' => 1, 'max_attempts' => 3]);
        $done = $this->job($this->organization->id, JobMonitor::STATUS_COMPLETED, now());

        $this->apiGet("/job-monitor/{$failed->id}")->assertOk()->assertJsonPath('data.id', $failed->id);

        $this->apiPost("/job-monitor/{$failed->id}/retry")
            ->assertOk()
            ->assertJsonPath('message', 'Job queued for retry')
            ->assertJsonPath('data.status', JobMonitor::STATUS_RETRYING);

        $this->apiGet("/job-monitor/{$failed->id}/logs")->assertOk()->assertJsonPath('meta.total', 1);

        $this->apiPost("/job-monitor/{$done->id}/retry")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only failed jobs can be retried.');
        $this->apiGet('/job-monitor/999999')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function job(?int $organizationId, string $status, \DateTimeInterface $queuedAt, array $attributes = []): JobMonitor
    {
        return JobMonitor::forceCreate(array_merge([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'job_class' => 'App\\Jobs\\ExampleJob',
            'job_name' => 'Example',
            'queue_name' => 'default',
            'status' => $status,
            'attempts' => 0,
            'max_attempts' => 3,
            'queued_at' => $queuedAt,
        ], $attributes));
    }
}
