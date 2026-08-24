<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\DashboardWidget;
use App\Models\Sales\Invoice;
use App\Services\Core\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers how DashboardService resolves a widget's data source to the provider
 * that supplies it, across every widget domain.
 */
class DashboardWidgetDataTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private DashboardService $dashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->dashboard = app(DashboardService::class)
            ->setContext($this->organization->id);
    }

    private function widget(string $dataSource): DashboardWidget
    {
        return new DashboardWidget(['data_source' => $dataSource]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function widgetSourceProvider(): array
    {
        return [
            'sales'         => ['DashboardService@getTodaySales', 'value'],
            'sales trend'   => ['DashboardService@getSalesTrend', 'labels'],
            'top customers' => ['DashboardService@getTopCustomers', 'rows'],
            'inventory'     => ['DashboardService@getLowStockCount', 'value'],
            'finance'       => ['DashboardService@getProfitLossSummary', 'revenue'],
            'hr'            => ['DashboardService@getEmployeeSummary', 'total'],
            'crm'           => ['DashboardService@getLeadsSummary', null],
            'manufacturing' => ['DashboardService@getWorkOrdersSummary', null],
            'activity'      => ['DashboardService@getActivityTimeline', 'items'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('widgetSourceProvider')]
    public function test_widget_data_source_resolves_to_a_provider(string $dataSource, ?string $expectedKey): void
    {
        $data = $this->dashboard->getWidgetData($this->widget($dataSource));

        $this->assertArrayNotHasKey('error', $data, "No provider supplies {$dataSource}");

        if ($expectedKey !== null) {
            $this->assertArrayHasKey($expectedKey, $data);
        }
    }

    public function test_unknown_data_source_reports_an_error(): void
    {
        $data = $this->dashboard->getWidgetData($this->widget('DashboardService@noSuchWidget'));

        $this->assertSame(['error' => 'Data source not found'], $data);
    }

    public function test_missing_data_source_reports_an_error(): void
    {
        $data = $this->dashboard->getWidgetData($this->widget(''));

        $this->assertSame(['error' => 'Data source not found'], $data);
    }

    public function test_every_default_widget_has_a_provider(): void
    {
        $unresolved = [];

        foreach (DashboardWidget::DEFAULT_WIDGETS as $definition) {
            $data = $this->dashboard->getWidgetData($this->widget($definition['data_source']));

            if (array_key_exists('error', $data)) {
                $unresolved[] = $definition['code'] . ' (' . $definition['data_source'] . ')';
            }
        }

        $this->assertSame([], $unresolved, "Widgets with no provider:\n  " . implode("\n  ", $unresolved));
    }

    public function test_sales_widget_reads_the_organizations_invoices(): void
    {
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => Invoice::STATUS_SENT,
            'invoice_date'    => now(),
            'total'           => 500,
        ]);

        $data = $this->dashboard->getWidgetData($this->widget('DashboardService@getTodaySales'));

        $this->assertSame(500.0, $data['value']);
    }

    public function test_quick_stats_covers_every_headline_figure(): void
    {
        $stats = $this->dashboard->getQuickStats();

        $this->assertSame(
            ['sales', 'pending_invoices', 'low_stock', 'employees', 'leads', 'work_orders'],
            array_keys($stats)
        );
    }
}
