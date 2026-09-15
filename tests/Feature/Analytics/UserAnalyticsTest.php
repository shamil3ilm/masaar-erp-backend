<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Models\Analytics\UserActivityLog;
use App\Models\Analytics\UserClusterAssignment;
use App\Models\Analytics\UserFeatureUsage;
use App\Models\Analytics\UserSessionExtended;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the user analytics endpoints and keeps them within the caller's
 * organization.
 */
class UserAnalyticsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['analytics.users.view']);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
        $this->outsider = User::factory()->create(['organization_id' => $this->other->id]);
    }

    public function test_activity_logs_filter_by_user_and_module_newest_first(): void
    {
        $older = $this->activity($this->user, 'sales', now()->subDay());
        $newer = $this->activity($this->user, 'sales', now());
        $this->activity($this->user, 'hr', now());
        $this->activity($this->outsider, 'sales', now(), $this->other);

        $response = $this->apiGet("/analytics/activity?user_id={$this->user->id}&module=sales")->assertOk();

        $response->assertJsonPath('meta.per_page', 50);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_feature_usage_and_sessions_stay_within_the_organization(): void
    {
        $older = $this->usage($this->user, 'invoices', now()->subDays(2)->toDateString());
        $newer = $this->usage($this->user, 'quotations', now()->subDay()->toDateString());
        $this->usage($this->outsider, 'invoices', now()->toDateString(), $this->other);

        $usage = $this->apiGet('/analytics/features?module=sales')->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($usage->json('data'), 'id'));

        $firstSession = $this->sessionFor($this->user, now()->subHour());
        $lastSession = $this->sessionFor($this->user, now());
        $this->sessionFor($this->outsider, now(), $this->other);

        $sessions = $this->apiGet('/analytics/sessions')->assertOk();
        $this->assertSame([$lastSession->id, $firstSession->id], array_column($sessions->json('data'), 'id'));
    }

    public function test_cluster_distribution_counts_the_organizations_users_and_user_views_refuse_other_organizations(): void
    {
        $colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->assign($this->user, 'power_user', now()->subDay());
        $this->assign($colleague, 'power_user', now());
        $this->assign($this->user, 'inactive', now());
        $this->assign($this->outsider, 'inactive', now(), $this->other);
        $this->assign($this->outsider, 'inactive', now()->subDay(), $this->other);

        $distribution = $this->apiGet('/analytics/clusters')->assertOk();
        $this->assertSame(
            [['cluster_name' => 'power_user', 'user_count' => 2], ['cluster_name' => 'inactive', 'user_count' => 1]],
            array_map(fn (array $row) => ['cluster_name' => $row['cluster_name'], 'user_count' => (int) $row['user_count']], $distribution->json('data'))
        );

        $clusters = $this->apiGet("/analytics/users/{$this->user->id}/clusters")->assertOk();
        $this->assertSame(['inactive', 'power_user'], array_column($clusters->json('data'), 'cluster_name'));
        $this->assertEqualsCanonicalizing(['cluster_name', 'algorithm', 'confidence', 'assigned_at', 'expires_at'], array_keys($clusters->json('data.0')));

        $this->apiGet("/analytics/users/{$this->outsider->id}/clusters")->assertNotFound();
        $this->apiGet("/analytics/users/{$this->outsider->id}/dimensions")->assertNotFound();
    }

    private function activity(User $user, string $module, $at, ?Organization $organization = null): UserActivityLog
    {
        return UserActivityLog::unguarded(fn () => UserActivityLog::create([
            'user_id' => $user->id,
            'organization_id' => ($organization ?? $this->organization)->id,
            'method' => 'GET',
            'module' => $module,
            'response_status' => 200,
            'created_at' => $at,
        ]));
    }

    private function usage(User $user, string $feature, string $date, ?Organization $organization = null): UserFeatureUsage
    {
        return UserFeatureUsage::create([
            'user_id' => $user->id,
            'organization_id' => ($organization ?? $this->organization)->id,
            'module' => 'sales',
            'feature' => $feature,
            'usage_date' => $date,
            'access_count' => 1,
        ]);
    }

    private function sessionFor(User $user, $startedAt, ?Organization $organization = null): UserSessionExtended
    {
        return UserSessionExtended::create([
            'user_id' => $user->id,
            'organization_id' => ($organization ?? $this->organization)->id,
            'session_token_hash' => hash('sha256', uniqid('', true)),
            'started_at' => $startedAt,
        ]);
    }

    private function assign(User $user, string $cluster, $assignedAt, ?Organization $organization = null): UserClusterAssignment
    {
        return UserClusterAssignment::create([
            'user_id' => $user->id,
            'organization_id' => ($organization ?? $this->organization)->id,
            'cluster_name' => $cluster,
            'dimensions' => [],
            'assigned_at' => $assignedAt,
        ]);
    }
}
