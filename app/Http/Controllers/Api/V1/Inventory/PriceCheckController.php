<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\Inventory\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Inventory\PriceCheckStation;
use App\Services\Inventory\PriceCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceCheckController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private PriceCheckService $priceCheckService
    ) {}

    /**
     * Check price by scanning.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scan_value' => 'required|string|max:255',
            'scan_type' => 'required|string|in:barcode,qr,rfid,nfc,manual,sku',
            'branch_id' => ['required', 'integer', $this->ownedBy('branches')],
            'station_id' => ['nullable', 'integer', $this->ownedBy('price_check_stations')],
            'contact_id' => ['nullable', 'integer', $this->ownedBy('contacts')],
        ]);

        $result = $this->priceCheckService->checkPrice(
            $validated['scan_value'],
            $validated['scan_type'],
            $validated['branch_id'],
            $validated['station_id'] ?? null,
            $validated['contact_id'] ?? null
        );

        if (!$result['found']) {
            return $this->error($result['error'], 'NOT_FOUND', 404);
        }

        return $this->success($result);
    }

    /**
     * Quick price check using barcode_value (convenience endpoint).
     * Automatically resolves the branch from the user's default branch.
     */
    public function quickCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode_value' => 'required|string|max:255',
            'scan_type' => 'nullable|string|in:barcode,qr,rfid,nfc,manual,sku',
            'branch_id' => ['nullable', 'integer', $this->ownedBy('branches')],
            'station_id' => ['nullable', 'integer', $this->ownedBy('price_check_stations')],
            'contact_id' => ['nullable', 'integer', $this->ownedBy('contacts')],
        ]);

        $branchId = $validated['branch_id']
            ?? (int) $request->header('X-Branch-Id')
            ?: auth()->user()->branches()->wherePivot('is_default', true)->first()?->id;

        if (!$branchId) {
            return $this->error('No branch specified or default branch found.', 'NO_BRANCH', 422);
        }

        $result = $this->priceCheckService->checkPrice(
            $validated['barcode_value'],
            $validated['scan_type'] ?? 'barcode',
            $branchId,
            $validated['station_id'] ?? null,
            $validated['contact_id'] ?? null
        );

        if (!$result['found']) {
            return $this->error($result['error'], 'NOT_FOUND', 404);
        }

        return $this->success($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Price Check Stations CRUD
    |--------------------------------------------------------------------------
    */

    /**
     * List price check stations.
     */
    public function stationIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);
        $filters['online_only'] = $request->boolean('online_only');

        if ($request->has('branch_id')) {
            $filters['branch_id'] = $request->integer('branch_id');
        }

        return $this->success($this->priceCheckService->listStations($filters));
    }

    /**
     * Create a price check station.
     */
    public function stationStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', $this->ownedBy('branches')],
            'name' => 'required|string|max:255',
            'station_code' => 'required|string|max:30',
            'location_description' => 'nullable|string|max:255',
            'device_type' => 'nullable|string|in:kiosk,handheld,mobile,tablet,pos',
            'device_id' => 'nullable|string|max:255',
            'scanner_type' => 'nullable|string|in:laser,camera,rfid,nfc',
            'scan_barcode' => 'boolean',
            'scan_qr' => 'boolean',
            'scan_rfid' => 'boolean',
            'scan_nfc' => 'boolean',
            'manual_entry' => 'boolean',
            'show_price' => 'boolean',
            'show_stock' => 'boolean',
            'show_promotions' => 'boolean',
            'show_alternatives' => 'boolean',
            'show_loyalty_points' => 'boolean',
            'show_product_image' => 'boolean',
            'show_description' => 'boolean',
            'show_location' => 'boolean',
            'price_list_id' => ['nullable', 'integer', $this->ownedBy('price_lists')],
            'use_customer_price' => 'boolean',
        ]);

        $station = $this->priceCheckService->createStation(auth()->user()->organization_id, $validated);

        return $this->created($station, 'Price check station created successfully.');
    }

    /**
     * Show a station.
     */
    public function stationShow(PriceCheckStation $station): JsonResponse
    {
        $station->load(['branch']);

        return $this->success($station);
    }

    /**
     * Update a station.
     */
    public function stationUpdate(Request $request, PriceCheckStation $station): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location_description' => 'nullable|string|max:255',
            'device_type' => 'nullable|string|in:kiosk,handheld,mobile,tablet,pos',
            'device_id' => 'nullable|string|max:255',
            'scanner_type' => 'nullable|string|in:laser,camera,rfid,nfc',
            'scan_barcode' => 'boolean',
            'scan_qr' => 'boolean',
            'scan_rfid' => 'boolean',
            'scan_nfc' => 'boolean',
            'manual_entry' => 'boolean',
            'show_price' => 'boolean',
            'show_stock' => 'boolean',
            'show_promotions' => 'boolean',
            'show_alternatives' => 'boolean',
            'show_loyalty_points' => 'boolean',
            'show_product_image' => 'boolean',
            'show_description' => 'boolean',
            'show_location' => 'boolean',
            'price_list_id' => ['nullable', 'integer', $this->ownedBy('price_lists')],
            'use_customer_price' => 'boolean',
            'status' => 'nullable|string|in:active,inactive,maintenance',
        ]);

        $station->update($validated);

        return $this->success($station->fresh(), 'Station updated successfully.');
    }

    /**
     * Delete a station.
     */
    public function stationDestroy(PriceCheckStation $station): JsonResponse
    {
        $station->delete();

        return $this->success(null, 'Station deleted successfully.');
    }

    /**
     * Get station statistics.
     */
    public function stationStats(PriceCheckStation $station): JsonResponse
    {
        $stats = $this->priceCheckService->getStationStats($station);

        return $this->success($stats);
    }

    /*
    |--------------------------------------------------------------------------
    | Logs & Analytics
    |--------------------------------------------------------------------------
    */

    /**
     * List price check logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $filters = $request->only(['error_type', 'from_date', 'to_date']);

        foreach (['station_id', 'branch_id', 'product_id'] as $id) {
            if ($request->has($id)) {
                $filters[$id] = $request->integer($id);
            }
        }

        if ($request->has('scan_successful')) {
            $filters['scan_successful'] = $request->boolean('scan_successful');
        }

        return $this->paginated($this->priceCheckService->listLogs($filters, $request->integer('per_page', 15)));
    }

    /**
     * Get scan analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $analytics = $this->priceCheckService->getScanAnalytics(
            $request->has('branch_id') ? $request->integer('branch_id') : null,
            $request->input('from_date'),
            $request->input('to_date')
        );

        return $this->success($analytics);
    }
}
