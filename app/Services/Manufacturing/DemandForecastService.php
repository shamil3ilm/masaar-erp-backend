<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\DemandForecast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demand forecasts that feed an MRP run, and how well past forecasts matched
 * the demand that actually materialised.
 */
class DemandForecastService
{
    /**
     * Create or update a demand forecast for a product.
     */
    public function setForecast(array $data, int $userId): DemandForecast
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['created_by'] = $userId;

            return DemandForecast::updateOrCreate(
                [
                    'organization_id' => $data['organization_id'],
                    'product_id'      => $data['product_id'],
                    'forecast_date'   => $data['forecast_date'],
                ],
                $data
            );
        });
    }

    /**
     * Get forecast accuracy statistics for an organization within a date range.
     */
    public function getForecastAccuracy(int $orgId, string $from, string $to): array
    {
        $forecasts = DemandForecast::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->forPeriod($from, $to)
            ->whereNotNull('actual_quantity')
            ->with('product:id,name,sku')
            ->get();

        if ($forecasts->isEmpty()) {
            return [
                'total_forecasts'  => 0,
                'average_accuracy' => null,
                'by_product'       => [],
            ];
        }

        $byProduct = $forecasts->groupBy('product_id')->map(function (Collection $items) {
            $accuracies = $items->map(fn ($f) => $f->getAccuracy())->filter(fn ($a) => $a !== null);

            return [
                'product_id'       => $items->first()->product_id,
                'product_name'     => $items->first()->product?->name,
                'sku'              => $items->first()->product?->sku,
                'total_forecasts'  => $items->count(),
                'average_accuracy' => $accuracies->isNotEmpty() ? round($accuracies->average(), 2) : null,
            ];
        })->values()->all();

        $allAccuracies = $forecasts->map(fn ($f) => $f->getAccuracy())->filter(fn ($a) => $a !== null);

        return [
            'total_forecasts'  => $forecasts->count(),
            'average_accuracy' => $allAccuracies->isNotEmpty() ? round($allAccuracies->average(), 2) : null,
            'by_product'       => $byProduct,
        ];
    }
}
