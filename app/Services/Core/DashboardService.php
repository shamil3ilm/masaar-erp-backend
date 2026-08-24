<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\Activity;
use App\Models\Core\DashboardLayout;
use App\Models\Core\DashboardWidget;
use App\Services\Core\Widgets\CrmWidgetProvider;
use App\Services\Core\Widgets\FinanceWidgetProvider;
use App\Services\Core\Widgets\HrWidgetProvider;
use App\Services\Core\Widgets\InventoryWidgetProvider;
use App\Services\Core\Widgets\ManufacturingWidgetProvider;
use App\Services\Core\Widgets\SalesWidgetProvider;
use App\Services\Core\Widgets\WidgetProvider;

/**
 * Assembles dashboards from widgets.
 *
 * The data behind each widget comes from a per-domain provider under
 * Services\Core\Widgets. A widget names its source as `Something@methodName`;
 * this class finds the provider declaring that method and calls it.
 */
class DashboardService
{
    protected int $organizationId;

    protected ?int $branchId = null;

    public function __construct(
        private readonly SalesWidgetProvider $sales,
        private readonly InventoryWidgetProvider $inventory,
        private readonly FinanceWidgetProvider $finance,
        private readonly HrWidgetProvider $hr,
        private readonly CrmWidgetProvider $crm,
        private readonly ManufacturingWidgetProvider $manufacturing,
    ) {}

    public function setContext(int $organizationId, ?int $branchId = null): self
    {
        $this->organizationId = $organizationId;
        $this->branchId       = $branchId;

        return $this;
    }

    /**
     * Build every widget in a layout.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(DashboardLayout $layout): array
    {
        $widgetData = [];

        foreach ($layout->widgets as $widgetConfig) {
            $widget = DashboardWidget::getByCode($widgetConfig['code']);

            if (! $widget || ! $widget->is_active) {
                continue;
            }

            $config = array_merge($widget->default_config ?? [], $widgetConfig['config'] ?? []);

            $widgetData[$widgetConfig['code']] = [
                'widget'   => $widget->toArray(),
                'config'   => $config,
                'data'     => $this->getWidgetData($widget, $config),
                'position' => $widgetConfig['position'],
                'size'     => $widgetConfig['size'],
            ];
        }

        return [
            'layout'       => $layout->toArray(),
            'widgets'      => $widgetData,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Resolve one widget's data source and run it.
     *
     * @return array<string, mixed>
     */
    public function getWidgetData(DashboardWidget $widget, array $config = []): array
    {
        $method = $this->parseDataSource($widget->data_source);

        if ($method === null) {
            return ['error' => 'Data source not found'];
        }

        foreach ($this->providers() as $provider) {
            if (method_exists($provider, $method)) {
                return $provider->setContext($this->organizationId, $this->branchId)->$method($config);
            }
        }

        // Widgets that span domains live on this class.
        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        return ['error' => 'Data source not found'];
    }

    /**
     * A widget's data source is either `methodName` or `Something@methodName`.
     */
    protected function parseDataSource(?string $dataSource): ?string
    {
        if (! $dataSource) {
            return null;
        }

        if (str_contains($dataSource, '@')) {
            return explode('@', $dataSource)[1] ?? null;
        }

        return $dataSource;
    }

    /**
     * @return list<WidgetProvider>
     */
    private function providers(): array
    {
        return [
            $this->sales,
            $this->inventory,
            $this->finance,
            $this->hr,
            $this->crm,
            $this->manufacturing,
        ];
    }

    /**
     * Recent activity across the whole organization.
     *
     * @return array<string, mixed>
     */
    public function getActivityTimeline(array $config = []): array
    {
        $limit = $config['limit'] ?? 20;

        $activities = Activity::where('organization_id', $this->organizationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->with('user:id,name')
            ->get();

        return [
            'items' => $activities->map(fn ($a) => [
                'id'          => $a->id,
                'event'       => $a->event,
                'description' => $a->getFormattedDescription(),
                'user'        => $a->user?->name ?? 'System',
                'icon'        => $a->getIcon(),
                'color'       => $a->getColor(),
                'time'        => $a->created_at->diffForHumans(),
            ])->toArray(),
        ];
    }

    /**
     * The headline figures shown above the widget grid.
     *
     * @return array<string, mixed>
     */
    public function getQuickStats(array $config = []): array
    {
        $withContext = fn (WidgetProvider $p) => $p->setContext($this->organizationId, $this->branchId);

        return [
            'sales'            => $withContext($this->sales)->getTodaySales(),
            'pending_invoices' => $withContext($this->sales)->getPendingInvoices(),
            'low_stock'        => $withContext($this->inventory)->getLowStockCount(),
            'employees'        => $withContext($this->hr)->getEmployeeSummary(),
            'leads'            => $withContext($this->crm)->getLeadsSummary(),
            'work_orders'      => $withContext($this->manufacturing)->getWorkOrdersSummary(),
        ];
    }
}
