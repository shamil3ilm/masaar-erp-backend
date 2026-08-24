<?php

declare(strict_types=1);

namespace App\Services\Core\Widgets;

use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard widgets that report on leads and the opportunity pipeline.
 */
class CrmWidgetProvider extends WidgetProvider
{
    public function getLeadsSummary(array $config = []): array
    {
        $query = Lead::where('organization_id', $this->organizationId);

        $total = (clone $query)->count();
        $newThisMonth = (clone $query)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
        $qualified = (clone $query)->where('status', 'qualified')->count();
        $converted = (clone $query)->where('status', 'converted')->count();

        $conversionRate = $total > 0 ? round(($converted / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'new_this_month' => $newThisMonth,
            'qualified' => $qualified,
            'converted' => $converted,
            'conversion_rate' => $conversionRate,
            'label' => 'Leads Summary',
        ];
    }

    public function getOpportunitiesPipeline(array $config = []): array
    {
        $opportunities = Opportunity::where('organization_id', $this->organizationId)
            ->whereIn('status', ['open', 'negotiation', 'proposal'])
            ->select('stage', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_value'))
            ->with('pipelineStage:id,name,sequence')
            ->groupBy('stage')
            ->get();

        $totalValue = $opportunities->sum('total_value');
        $totalCount = $opportunities->sum('count');

        return [
            'total_value' => (float) $totalValue,
            'total_count' => $totalCount,
            'stages' => $opportunities->map(fn($o) => [
                'stage' => $o->pipelineStage->name ?? $o->stage,
                'count' => $o->count,
                'value' => (float) $o->total_value,
            ])->sortBy(fn($s) => $s['stage'])->values()->toArray(),
            'label' => 'Opportunities Pipeline',
        ];
    }

    public function getOpportunitiesClosingSoon(array $config = []): array
    {
        $days = $config['days'] ?? 30;

        $opportunities = Opportunity::where('organization_id', $this->organizationId)
            ->whereIn('status', ['open', 'negotiation', 'proposal'])
            ->where('expected_close_date', '<=', Carbon::now()->addDays($days))
            ->where('expected_close_date', '>=', Carbon::today())
            ->orderBy('expected_close_date')
            ->limit(10)
            ->get(['id', 'name', 'amount', 'expected_close_date', 'probability']);

        return [
            'items' => $opportunities->map(fn($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'amount' => (float) $o->amount,
                'close_date' => $o->expected_close_date->format('Y-m-d'),
                'days_left' => $o->expected_close_date->diffInDays(Carbon::today()),
                'probability' => $o->probability,
            ])->toArray(),
            'label' => 'Opportunities Closing Soon',
        ];
    }

    public function getLeadsBySource(array $config = []): array
    {
        $data = Lead::where('organization_id', $this->organizationId)
            ->whereNotNull('lead_source_id')
            ->select('lead_source_id', DB::raw('count(*) as count'))
            ->with('leadSource:id,name')
            ->groupBy('lead_source_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'labels' => $data->map(fn($l) => $l->leadSource->name ?? 'Unknown')->toArray(),
            'data' => $data->pluck('count')->toArray(),
        ];
    }
}
