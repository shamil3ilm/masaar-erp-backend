<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Reports\SavedReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\BuildsPostings;
use Tests\Traits\TestHelpers;

/**
 * The inventory turnover report run from a saved report, whose stored
 * parameters reach the service without the date checks the direct route
 * applies.
 */
class InventoryTurnoverReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['reports.exports.view', 'reports.exports.manage']);
    }

    public function test_a_period_shorter_than_a_day_is_refused_rather_than_divided_by(): void
    {
        // A saved report stores whatever parameters it was given, so an end
        // date before the start reaches the service. The period is a day
        // count, and annualising the ratio divides by it.
        $this->runSaved(['start_date' => '2026-03-02', 'end_date' => '2026-03-01'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_ARGUMENT');
    }

    public function test_a_single_day_period_counts_as_one_day(): void
    {
        $data = $this->runSaved(['start_date' => '2026-03-01', 'end_date' => '2026-03-01'])
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $data['period_days']);
        $this->assertSame('0.0000', $this->money($data['metrics']['turnover_ratio']));
        $this->assertSame('0.0000', $this->money($data['metrics']['annual_turnover']));
    }

    public function test_an_empty_period_reports_zero_metrics_rather_than_nulls(): void
    {
        $data = $this->runSaved(['start_date' => '2026-03-01', 'end_date' => '2026-03-31'])
            ->assertOk()
            ->json('data');

        $this->assertSame(31, $data['period_days']);
        $this->assertSame('0.0000', $this->money($data['metrics']['average_inventory_value']));
        $this->assertSame('0.0000', $this->money($data['metrics']['cost_of_goods_sold']));
        $this->assertSame('0.0000', $this->money($data['metrics']['turnover_ratio']));
        $this->assertSame('0.0000', $this->money($data['metrics']['days_in_inventory']));
        $this->assertSame([], $data['by_category']);
    }

    private function runSaved(array $parameters): TestResponse
    {
        $report = SavedReport::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'name' => 'Turnover',
            'report_type' => 'inventory_turnover',
            'export_format' => 'json',
            'parameters' => $parameters,
        ]);

        return $this->apiPost("/reports/saved/{$report->id}/run");
    }
}
