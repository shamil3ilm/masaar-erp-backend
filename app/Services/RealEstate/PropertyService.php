<?php

declare(strict_types=1);

namespace App\Services\RealEstate;

use App\Models\RealEstate\Portfolio;
use App\Models\RealEstate\Property;
use App\Models\RealEstate\RentalUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The property hierarchy: portfolios, properties, rental units, and the
 * occupancy figures derived from them.
 */
class PropertyService
{
    public function listPortfolios(int $organizationId): Collection
    {
        return Portfolio::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with('properties')
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    public function createPortfolio(int $organizationId, array $data): Portfolio
    {
        return Portfolio::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    /**
     * Occupancy summary per portfolio.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPortfolioOverview(int $organizationId): array
    {
        $portfolios = Portfolio::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->with(['properties.buildings.rentalUnits'])
            ->limit(50)
            ->get();

        return $portfolios->map(function (Portfolio $portfolio) {
            $units = $portfolio->properties
                ->flatMap(fn ($p) => $p->buildings)
                ->flatMap(fn ($b) => $b->rentalUnits);

            $totalUnits    = $units->count();
            $vacantUnits   = $units->where('status', 'vacant')->count();
            $occupiedUnits = $units->where('status', 'occupied')->count();
            $totalArea     = $units->sum('area_sqm');
            $vacantArea    = $units->where('status', 'vacant')->sum('area_sqm');

            return [
                'portfolio_id'   => $portfolio->id,
                'portfolio_code' => $portfolio->code,
                'portfolio_name' => $portfolio->name,
                'type'           => $portfolio->type,
                'total_units'    => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units'   => $vacantUnits,
                'occupancy_rate_pct' => $totalUnits > 0
                    ? round($occupiedUnits / $totalUnits * 100, 2)
                    : 0,
                'total_area_sqm'  => $totalArea,
                'vacant_area_sqm' => $vacantArea,
                'vacancy_rate_pct' => $totalArea > 0
                    ? round((float) $vacantArea / (float) $totalArea * 100, 2)
                    : 0,
            ];
        })->all();
    }

    public function listProperties(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = Property::where('organization_id', $organizationId);

        if (! empty($filters['portfolio_id'])) {
            $query->where('portfolio_id', $filters['portfolio_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with('portfolio')->orderBy('name')->paginate(20);
    }

    public function createProperty(int $organizationId, array $data): Property
    {
        return Property::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    public function listRentalUnits(int $organizationId, array $filters = []): LengthAwarePaginator
    {
        $query = RentalUnit::where('organization_id', $organizationId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['building_id'])) {
            $query->where('building_id', $filters['building_id']);
        }
        if (! empty($filters['unit_type'])) {
            $query->where('unit_type', $filters['unit_type']);
        }

        return $query->with(['building.property', 'activeContract'])->orderBy('code')->paginate(20);
    }

    /**
     * Vacancy totals and a per-unit-type breakdown, aggregated in the database
     * so the unit rows are never loaded into memory.
     *
     * @return array<string, mixed>
     */
    public function getVacancyReport(int $organizationId, ?int $portfolioId = null): array
    {
        $baseQuery = RentalUnit::where('organization_id', $organizationId);

        if ($portfolioId) {
            $baseQuery->whereHas('building.property', fn ($q) => $q->where('portfolio_id', $portfolioId));
        }

        $totals = (clone $baseQuery)
            ->selectRaw(
                'COUNT(*) as total,
                 COALESCE(SUM(area_sqm), 0) as total_area,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as vacant_count,
                 COALESCE(SUM(CASE WHEN status = ? THEN area_sqm ELSE 0 END), 0) as vacant_area',
                ['vacant', 'vacant']
            )
            ->first();

        $byType = (clone $baseQuery)
            ->selectRaw(
                'unit_type,
                 COUNT(*) as total,
                 COALESCE(SUM(area_sqm), 0) as total_area,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as vacant_count,
                 COALESCE(SUM(CASE WHEN status = ? THEN area_sqm ELSE 0 END), 0) as vacant_area',
                ['vacant', 'vacant']
            )
            ->groupBy('unit_type')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->unit_type => [
                    'total'            => (int) $row->total,
                    'occupied'         => (int) $row->total - (int) $row->vacant_count,
                    'vacant'           => (int) $row->vacant_count,
                    'vacancy_rate_pct' => $row->total > 0
                        ? round($row->vacant_count / $row->total * 100, 2)
                        : 0,
                    'total_area_sqm'  => (float) $row->total_area,
                    'vacant_area_sqm' => (float) $row->vacant_area,
                ],
            ]);

        $total       = (int) ($totals->total ?? 0);
        $vacantCount = (int) ($totals->vacant_count ?? 0);
        $totalArea   = (float) ($totals->total_area ?? 0);
        $vacantArea  = (float) ($totals->vacant_area ?? 0);

        return [
            'total_units'     => $total,
            'occupied_units'  => $total - $vacantCount,
            'vacant_units'    => $vacantCount,
            'total_area_sqm'  => $totalArea,
            'vacant_area_sqm' => $vacantArea,
            'overall_vacancy_rate_pct' => $total > 0
                ? round($vacantCount / $total * 100, 2)
                : 0,
            'by_unit_type' => $byType,
        ];
    }
}
