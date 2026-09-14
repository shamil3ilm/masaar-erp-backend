<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Jobs\ExecuteScheduledReportJob;
use App\Models\Reports\ReportExecution;
use App\Models\Reports\SavedReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Reports are saved, run, exported and scheduled by report type.
 */
class SavedReportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['reports.exports.view', 'reports.exports.manage']);
    }

    public function test_a_saved_report_is_kept_and_run_by_its_type(): void
    {
        $this->apiPost('/reports/saved', [
            'name' => 'Month end trial balance',
            'report_type' => 'trial_balance',
            'parameters' => ['as_of_date' => '2026-08-31'],
            'export_format' => 'json',
            'schedule_frequency' => 'monthly',
            'schedule_day' => '1',
        ])->assertStatus(201);

        $report = SavedReport::sole();
        $this->assertSame('trial_balance', $report->report_type);
        $this->assertSame(['as_of_date' => '2026-08-31'], $report->parameters);
        $this->assertTrue($report->is_scheduled);
        $this->assertNotNull($report->next_run_at);

        $this->apiPost("/reports/saved/{$report->id}/run")
            ->assertOk()
            ->assertJsonPath('data.as_of_date', '2026-08-31')
            ->assertJsonPath('data.is_balanced', true);

        $this->assertNotNull($report->fresh()->last_run_at);
    }

    public function test_a_report_type_nothing_produces_is_refused(): void
    {
        $this->apiPost('/reports/saved', [
            'name' => 'Ledger',
            'report_type' => SavedReport::TYPE_GENERAL_LEDGER,
        ])->assertStatus(422);

        $this->assertSame(0, SavedReport::count());
    }

    public function test_an_export_is_recorded_with_its_format(): void
    {
        $this->apiPost('/reports/export', [
            'report_type' => 'trial_balance',
            'format' => 'json',
            'parameters' => ['as_of_date' => '2026-08-31'],
        ])->assertOk();

        $execution = ReportExecution::sole();
        $this->assertSame('trial_balance', $execution->report_type);
        $this->assertSame('json', $execution->format);
        $this->assertSame(ReportExecution::TRIGGER_MANUAL, $execution->trigger);
        $this->assertSame(ReportExecution::STATUS_COMPLETED, $execution->status);
        $this->assertGreaterThanOrEqual(0, $execution->execution_time_ms);
        Storage::assertExists($execution->file_path);
    }

    public function test_a_scheduled_report_runs_as_its_owner(): void
    {
        $report = SavedReport::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'name' => 'Daily trial balance',
            'report_type' => 'trial_balance',
            'export_format' => 'json',
            'is_scheduled' => true,
            'schedule_frequency' => SavedReport::SCHEDULE_DAILY,
        ]);

        auth()->forgetUser();
        app()->call([new ExecuteScheduledReportJob($report), 'handle']);

        $execution = ReportExecution::withoutGlobalScopes()->sole();
        $this->assertSame(ReportExecution::TRIGGER_SCHEDULED, $execution->trigger);
        $this->assertSame(ReportExecution::STATUS_COMPLETED, $execution->status);
        $this->assertNotNull($report->fresh()->next_run_at);
        $this->assertNull(auth()->user());
    }
}
