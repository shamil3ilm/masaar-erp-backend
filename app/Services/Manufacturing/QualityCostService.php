<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\QualityCostEntry;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QualityCostService
{
    private const CATEGORIES = [
        QualityCostEntry::CATEGORY_PREVENTION,
        QualityCostEntry::CATEGORY_APPRAISAL,
        QualityCostEntry::CATEGORY_INTERNAL_FAILURE,
        QualityCostEntry::CATEGORY_EXTERNAL_FAILURE,
    ];

    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = QualityCostEntry::with(['product', 'recorder'])
            ->where('organization_id', $orgId);

        if (isset($filters['cost_category'])) {
            $query->where('cost_category', $filters['cost_category']);
        }

        if (isset($filters['period']) && isset($filters['fiscal_year'])) {
            $query->where('period', $filters['period'])->where('fiscal_year', $filters['fiscal_year']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    /**
     * One of the organization's entries; a missing id is a 404.
     *
     * @param  array<int, string>  $with
     */
    public function find(int $orgId, int $id, array $with = []): QualityCostEntry
    {
        return QualityCostEntry::where('organization_id', $orgId)
            ->with($with)
            ->findOrFail($id);
    }

    public function create(int $orgId, array $data): QualityCostEntry
    {
        return QualityCostEntry::create(array_merge($data, ['organization_id' => $orgId]));
    }

    public function update(QualityCostEntry $entry, array $data): QualityCostEntry
    {
        $entry->update($data);
        return $entry->fresh();
    }

    public function delete(QualityCostEntry $entry): void
    {
        $entry->delete();
    }

    public function getSummary(int $period, int $year, int $orgId): array
    {
        $rows = QualityCostEntry::where('organization_id', $orgId)
            ->where('period', $period)
            ->where('fiscal_year', $year)
            ->select('cost_category', DB::raw('SUM(amount) as total'))
            ->groupBy('cost_category')
            ->get()
            ->keyBy('cost_category');

        $summary = [];
        $grandTotal = '0.0000';

        foreach (self::CATEGORIES as $category) {
            $total = isset($rows[$category]) ? (string) $rows[$category]->total : '0.0000';
            $summary[$category] = $total;
            $grandTotal = bcadd($grandTotal, $total, 4);
        }

        return [
            'period'      => $period,
            'fiscal_year' => $year,
            'by_category' => $summary,
            'total'       => $grandTotal,
        ];
    }

    /**
     * Each of the last $months months, oldest first, with its total per category.
     *
     * The totals for the whole range are read in one grouped query: a period
     * is numbered fiscal_year * 12 + period, so the range is a single BETWEEN.
     */
    public function getTrend(int $months, int $orgId): array
    {
        $now = Carbon::now();
        $dates = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $dates[] = $now->copy()->subMonths($i);
        }

        $monthNumber = fn (Carbon $date): int => (int) $date->format('Y') * 12 + (int) $date->format('n');

        $totals = QualityCostEntry::where('organization_id', $orgId)
            ->whereRaw('(fiscal_year * 12 + period) between ? and ?', [$monthNumber($dates[0]), $monthNumber(end($dates))])
            ->select('fiscal_year', 'period', 'cost_category', DB::raw('SUM(amount) as total'))
            ->groupBy('fiscal_year', 'period', 'cost_category')
            ->get()
            ->keyBy(fn (QualityCostEntry $row): string => "{$row->fiscal_year}-{$row->period}-{$row->cost_category}");

        $result = [];

        foreach ($dates as $date) {
            $period = (int) $date->format('n');
            $year   = (int) $date->format('Y');

            $entry = [
                'period'      => $period,
                'fiscal_year' => $year,
                'label'       => $date->format('M Y'),
            ];

            foreach (self::CATEGORIES as $cat) {
                $row = $totals->get("{$year}-{$period}-{$cat}");
                $entry[$cat] = $row !== null ? (string) $row->total : '0.0000';
            }

            $result[] = $entry;
        }

        return $result;
    }
}
