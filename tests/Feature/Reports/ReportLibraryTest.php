<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Core\Organization;
use App\Models\Reports\ReportExecution;
use App\Models\Reports\SavedReport;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins who sees, changes and runs saved reports and exports: the owner edits
 * and deletes, colleagues run shared reports, and nothing crosses into another
 * organization.
 */
class ReportLibraryTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private User $colleague;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['reports.exports.view', 'reports.exports.manage', 'core.exports.view']);

        $this->colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->other = Organization::factory()->create();
    }

    public function test_saved_reports_list_the_users_own_and_shared_reports_by_name(): void
    {
        $mine = $this->report($this->user, ['name' => 'B mine']);
        $shared = $this->report($this->colleague, ['name' => 'A shared', 'is_shared' => true]);
        $this->report($this->colleague, ['name' => 'C private']);
        $this->report(User::factory()->create(['organization_id' => $this->other->id]), ['name' => 'A other', 'is_shared' => true]);

        $response = $this->apiGet('/reports/saved')->assertOk();

        $this->assertSame([$shared->id, $mine->id], array_column($response->json('data'), 'id'));
    }

    public function test_only_the_owner_changes_a_report_and_colleagues_run_shared_ones(): void
    {
        $shared = $this->report($this->colleague, ['is_shared' => true, 'parameters' => ['as_of_date' => '2026-08-31']]);
        $private = $this->report($this->colleague);
        $mine = $this->report($this->user);

        $this->apiPut("/reports/saved/{$shared->id}", ['name' => 'Taken'])->assertNotFound();
        $this->apiDelete("/reports/saved/{$shared->id}")->assertNotFound();
        $this->apiPost("/reports/saved/{$private->id}/run")->assertNotFound();
        $this->apiPost("/reports/saved/{$shared->id}/run")->assertOk()->assertJsonPath('data.as_of_date', '2026-08-31');

        $this->apiPut("/reports/saved/{$mine->id}", ['schedule_frequency' => 'daily'])
            ->assertOk()
            ->assertJsonPath('data.schedule_frequency', 'daily');
        $this->assertNotNull($mine->fresh()->next_run_at);

        $this->apiDelete("/reports/saved/{$mine->id}")->assertOk();
        $this->assertNull(SavedReport::find($mine->id));
    }

    public function test_history_lists_the_users_executions_and_another_organizations_file_is_not_found(): void
    {
        $older = $this->execution($this->organization, $this->user, ['created_at' => now()->subDay()]);
        $newer = $this->execution($this->organization, $this->user);
        $this->execution($this->organization, $this->colleague);
        $theirs = $this->execution($this->other, User::factory()->create(['organization_id' => $this->other->id]));

        $response = $this->apiGet('/reports/history')->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));

        $this->apiGet("/reports/download/{$theirs->id}")->assertNotFound();
    }

    public function test_the_invoice_export_refuses_another_organizations_customer(): void
    {
        $theirs = Contact::factory()->create(['organization_id' => $this->other->id]);
        $mine = Contact::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiGet("/export/invoices?customer_id={$theirs->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);

        $this->apiGet("/export/invoices?customer_id={$mine->id}")
            ->assertNotFound()
            ->assertJsonPath('error.message', 'No invoices found for export.');
    }

    private function report(User $owner, array $attributes = []): SavedReport
    {
        return SavedReport::withoutGlobalScopes()->create([
            'organization_id' => $owner->organization_id,
            'user_id' => $owner->id,
            'name' => 'Trial balance',
            'report_type' => 'trial_balance',
            'export_format' => 'json',
            ...$attributes,
        ]);
    }

    private function execution(Organization $organization, User $user, array $attributes = []): ReportExecution
    {
        return ReportExecution::unguarded(fn () => ReportExecution::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'report_type' => 'trial_balance',
            'format' => 'json',
            'trigger' => ReportExecution::TRIGGER_MANUAL,
            'status' => ReportExecution::STATUS_COMPLETED,
            'file_path' => 'reports/trial_balance.json',
            'expires_at' => now()->addDay(),
            ...$attributes,
        ]));
    }
}
