<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\PriceCheckLog;
use App\Models\Inventory\PriceCheckStation;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PriceCheckService
{
    public function __construct(
        private BarcodeService $barcodeService
    ) {}

    /**
     * Check price by scanning a barcode/QR/SKU.
     */
    public function checkPrice(string $scanValue, string $scanType, int $branchId, ?int $stationId = null, ?int $contactId = null): array
    {
        $result = $this->barcodeService->lookup($scanValue);

        // If not found via barcode, try SKU lookup
        if (! $result && in_array($scanType, [PriceCheckLog::SCAN_MANUAL, PriceCheckLog::SCAN_SKU])) {
            $product = Product::where('sku', $scanValue)
                ->where('organization_id', auth()->user()->organization_id)
                ->first();
            if ($product) {
                $result = [
                    'product' => $product,
                    'variant' => null,
                    'barcode' => null,
                    'source' => 'sku_lookup',
                ];
            }
        }

        // Build log data
        $logData = [
            'organization_id' => auth()->user()->organization_id,
            'station_id' => $stationId,
            'branch_id' => $branchId,
            'scan_type' => $scanType,
            'scan_value' => $scanValue,
            'contact_id' => $contactId,
            'scanned_at' => now(),
        ];

        if (! $result) {
            // Product not found
            $logData['scan_successful'] = false;
            $logData['error_type'] = PriceCheckLog::ERROR_NOT_FOUND;
            $logData['error_message'] = 'Product not found for scanned value.';

            $this->logScan($logData);

            return [
                'found' => false,
                'error' => 'Product not found.',
                'error_type' => PriceCheckLog::ERROR_NOT_FOUND,
            ];
        }

        $product = $result['product'];

        if (! $product->is_active) {
            $logData['scan_successful'] = false;
            $logData['product_id'] = $product->id;
            $logData['product_name'] = $product->name;
            $logData['product_sku'] = $product->sku;
            $logData['error_type'] = PriceCheckLog::ERROR_INACTIVE;
            $logData['error_message'] = 'Product is inactive.';

            $this->logScan($logData);

            return [
                'found' => false,
                'error' => 'Product is inactive.',
                'error_type' => PriceCheckLog::ERROR_INACTIVE,
            ];
        }

        // Get pricing info
        $displayedPrice = (float) $product->selling_price;
        $originalPrice = $displayedPrice;
        $hasPromotion = false;
        $promotionName = null;
        $promotionDiscount = null;

        // Get stock info
        $stockAvailable = $product->getTotalStock();
        $stockStatus = match (true) {
            $stockAvailable <= 0 => PriceCheckLog::STOCK_OUT_OF_STOCK,
            $product->reorder_level && $stockAvailable <= $product->reorder_level => PriceCheckLog::STOCK_LOW_STOCK,
            default => PriceCheckLog::STOCK_IN_STOCK,
        };

        // Log successful scan
        $logData['scan_successful'] = true;
        $logData['product_id'] = $product->id;
        $logData['variant_id'] = $result['variant']?->id;
        $logData['product_name'] = $product->name;
        $logData['product_sku'] = $product->sku;
        $logData['displayed_price'] = $displayedPrice;
        $logData['original_price'] = $originalPrice;
        $logData['currency_code'] = $product->organization->base_currency ?? 'SAR';
        $logData['has_promotion'] = $hasPromotion;
        $logData['promotion_name'] = $promotionName;
        $logData['promotion_discount'] = $promotionDiscount;
        $logData['stock_available'] = $stockAvailable;
        $logData['stock_status'] = $stockStatus;

        $this->logScan($logData);

        return [
            'found' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'image_url' => $product->image_url,
            ],
            'variant' => $result['variant'],
            'pricing' => [
                'price' => $displayedPrice,
                'original_price' => $originalPrice,
                'has_promotion' => $hasPromotion,
                'promotion_name' => $promotionName,
                'promotion_discount' => $promotionDiscount,
                'currency_code' => $logData['currency_code'],
            ],
            'stock' => [
                'available' => $stockAvailable,
                'status' => $stockStatus,
            ],
        ];
    }

    /**
     * Log a price check scan.
     */
    public function logScan(array $data): PriceCheckLog
    {
        return PriceCheckLog::create($data);
    }

    /**
     * Get station statistics.
     */
    public function getStationStats(PriceCheckStation $station): array
    {
        $today = now()->startOfDay();
        $logsQuery = PriceCheckLog::where('station_id', $station->id);

        return [
            'station' => [
                'id' => $station->uuid,
                'name' => $station->name,
                'status' => $station->status,
                'is_online' => $station->isOnline(),
                'last_heartbeat' => $station->last_heartbeat_at?->toISOString(),
            ],
            'today' => [
                'total_scans' => (clone $logsQuery)->where('scanned_at', '>=', $today)->count(),
                'successful_scans' => (clone $logsQuery)->where('scanned_at', '>=', $today)->successful()->count(),
                'failed_scans' => (clone $logsQuery)->where('scanned_at', '>=', $today)->failed()->count(),
            ],
            'last_7_days' => [
                'total_scans' => (clone $logsQuery)->where('scanned_at', '>=', now()->subDays(7))->count(),
                'successful_scans' => (clone $logsQuery)->where('scanned_at', '>=', now()->subDays(7))->successful()->count(),
                'failed_scans' => (clone $logsQuery)->where('scanned_at', '>=', now()->subDays(7))->failed()->count(),
            ],
            'error_breakdown' => PriceCheckLog::where('station_id', $station->id)
                ->where('scanned_at', '>=', now()->subDays(7))
                ->whereNotNull('error_type')
                ->selectRaw('error_type, COUNT(*) as count')
                ->groupBy('error_type')
                ->get()
                ->keyBy('error_type'),
        ];
    }

    /**
     * Get scan analytics for a branch or organization.
     */
    public function getScanAnalytics(?int $branchId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = PriceCheckLog::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($fromDate) {
            $query->where('scanned_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('scanned_at', '<=', $toDate);
        }

        $totalScans = (clone $query)->count();
        $successfulScans = (clone $query)->where('scan_successful', true)->count();
        $failedScans = (clone $query)->where('scan_successful', false)->count();

        return [
            'total_scans' => $totalScans,
            'successful_scans' => $successfulScans,
            'failed_scans' => $failedScans,
            'success_rate' => $totalScans > 0 ? round(($successfulScans / $totalScans) * 100, 2) : 0,
            'top_scanned_products' => (clone $query)
                ->where('scan_successful', true)
                ->whereNotNull('product_id')
                ->selectRaw('product_id, product_name, COUNT(*) as scan_count')
                ->groupBy('product_id', 'product_name')
                ->orderByDesc('scan_count')
                ->limit(10)
                ->get(),
            'scans_by_type' => (clone $query)
                ->selectRaw('scan_type, COUNT(*) as count')
                ->groupBy('scan_type')
                ->get()
                ->keyBy('scan_type'),
            'errors_by_type' => (clone $query)
                ->whereNotNull('error_type')
                ->selectRaw('error_type, COUNT(*) as count')
                ->groupBy('error_type')
                ->get()
                ->keyBy('error_type'),
            // Grouped here rather than in SQL: HOUR() is MySQL only.
            'hourly_distribution' => (clone $query)
                ->get(['scanned_at'])
                ->groupBy(fn ($scan) => (int) $scan->scanned_at->format('G'))
                ->map(fn ($scans, $hour) => ['hour' => $hour, 'count' => $scans->count()])
                ->sortKeys()
                ->values(),
        ];
    }

    /**
     * Price check stations of the current organization with their branch,
     * newest first. branch_id and status apply when present; online_only when
     * true.
     *
     * @param  array{branch_id?: int, status?: string, online_only?: bool}  $filters
     * @return Collection<int, PriceCheckStation>
     */
    public function listStations(array $filters): Collection
    {
        return PriceCheckStation::with(['branch'])
            ->latest()
            ->when(array_key_exists('branch_id', $filters), fn ($q) => $q->byBranch($filters['branch_id']))
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['online_only'] ?? false, fn ($q) => $q->online())
            ->get();
    }

    /**
     * Create an active station for the organization with a new device token.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStation(int $organizationId, array $data): PriceCheckStation
    {
        return PriceCheckStation::create([
            ...$data,
            'organization_id' => $organizationId,
            'api_token' => Str::random(64),
            'status' => PriceCheckStation::STATUS_ACTIVE,
        ]);
    }

    /**
     * Scan logs of the current organization with their station and product,
     * latest scan first. Each filter applies when its key is present.
     *
     * @param  array{station_id?: int, branch_id?: int, product_id?: int, scan_successful?: bool, error_type?: string, from_date?: string, to_date?: string}  $filters
     */
    public function listLogs(array $filters, int $perPage): LengthAwarePaginator
    {
        return PriceCheckLog::with(['station', 'product'])
            ->latest('scanned_at')
            ->when(array_key_exists('station_id', $filters), fn ($q) => $q->byStation($filters['station_id']))
            ->when(array_key_exists('branch_id', $filters), fn ($q) => $q->byBranch($filters['branch_id']))
            ->when(array_key_exists('product_id', $filters), fn ($q) => $q->byProduct($filters['product_id']))
            ->when(array_key_exists('scan_successful', $filters), fn ($q) => $filters['scan_successful'] ? $q->successful() : $q->failed())
            ->when(array_key_exists('error_type', $filters), fn ($q) => $q->byErrorType($filters['error_type']))
            ->when(array_key_exists('from_date', $filters), fn ($q) => $q->where('scanned_at', '>=', $filters['from_date']))
            ->when(array_key_exists('to_date', $filters), fn ($q) => $q->where('scanned_at', '<=', $filters['to_date']))
            ->paginate($perPage);
    }
}
