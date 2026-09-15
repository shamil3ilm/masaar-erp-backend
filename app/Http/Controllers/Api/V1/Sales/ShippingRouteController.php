<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Sales\ShippingRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingRouteController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ShippingRouteService $shippingRouteService,
    ) {}

    // -------------------------------------------------------------------------
    // Shipping Zones
    // -------------------------------------------------------------------------

    public function zoneIndex(Request $request): JsonResponse
    {
        $zones = $this->shippingRouteService->listZones(
            $request->only(['is_active']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($zones);
    }

    public function zoneStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_code' => 'required|string|max:20',
            'zone_name' => 'required|string|max:100',
            'country_codes' => 'nullable|array',
            'country_codes.*' => 'string|size:2',
            'postal_code_pattern' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $zone = $this->shippingRouteService->createZone(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($zone);
    }

    public function zoneShow(int $id): JsonResponse
    {
        return $this->success($this->shippingRouteService->zoneOf($id));
    }

    public function zoneUpdate(Request $request, int $id): JsonResponse
    {
        $zone = $this->shippingRouteService->zoneOf($id);

        $validator = Validator::make($request->all(), [
            'zone_code' => 'nullable|string|max:20',
            'zone_name' => 'nullable|string|max:100',
            'country_codes' => 'nullable|array',
            'country_codes.*' => 'string|size:2',
            'postal_code_pattern' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->shippingRouteService->updateZone($zone, $validator->validated());

        return $this->success($updated);
    }

    public function zoneDestroy(int $id): JsonResponse
    {
        $this->shippingRouteService->deleteZone($this->shippingRouteService->zoneOf($id));

        return $this->noContent();
    }

    // -------------------------------------------------------------------------
    // Shipping Routes
    // -------------------------------------------------------------------------

    public function routeIndex(Request $request): JsonResponse
    {
        $routes = $this->shippingRouteService->listRoutes(
            $request->only(['is_active', 'transportation_mode', 'departure_zone_id', 'destination_zone_id']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($routes);
    }

    public function routeStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_code' => 'required|string|max:30',
            'route_name' => 'required|string|max:100',
            'departure_zone_id' => ['required', $this->ownedBy('shipping_zones')],
            'destination_zone_id' => ['required', $this->ownedBy('shipping_zones')],
            'transportation_mode' => 'required|in:road,air,sea,rail,courier',
            'transit_days' => 'nullable|integer|min:1',
            'carrier' => 'nullable|string|max:100',
            'freight_cost' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $route = $this->shippingRouteService->createRoute(array_merge(
            $validator->validated(),
            ['organization_id' => $request->user()->organization_id]
        ));

        return $this->created($route);
    }

    public function routeShow(int $id): JsonResponse
    {
        return $this->success($this->shippingRouteService->routeDetails($id));
    }

    public function routeUpdate(Request $request, int $id): JsonResponse
    {
        $route = $this->shippingRouteService->routeOf($id);

        $validator = Validator::make($request->all(), [
            'route_code' => 'nullable|string|max:30',
            'route_name' => 'nullable|string|max:100',
            'departure_zone_id' => ['nullable', $this->ownedBy('shipping_zones')],
            'destination_zone_id' => ['nullable', $this->ownedBy('shipping_zones')],
            'transportation_mode' => 'nullable|in:road,air,sea,rail,courier',
            'transit_days' => 'nullable|integer|min:1',
            'carrier' => 'nullable|string|max:100',
            'freight_cost' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $updated = $this->shippingRouteService->updateRoute($route, $validator->validated());

        return $this->success($updated);
    }

    public function routeDestroy(int $id): JsonResponse
    {
        $this->shippingRouteService->deleteRoute($this->shippingRouteService->routeOf($id));

        return $this->noContent();
    }

    // -------------------------------------------------------------------------
    // Route Determination
    // -------------------------------------------------------------------------

    public function determineRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'destination_country' => 'required|string|size:2',
            'postal_code' => 'nullable|string|max:20',
            'warehouse_country' => 'nullable|string|size:2',
            'preference' => 'nullable|in:cheapest,fastest',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', 422, $validator->errors()->toArray());
        }

        $route = $this->shippingRouteService->determineRoute(
            $request->input('destination_country'),
            $request->input('postal_code'),
            $request->input('warehouse_country', 'SA'),
            $request->input('preference', 'cheapest')
        );

        if ($route === null) {
            return $this->error('No matching shipping route found.', 'NOT_FOUND', 404);
        }

        return $this->success($route);
    }

    public function determineForOrder(int $orderId): JsonResponse
    {
        return $this->success(
            $this->shippingRouteService->determineForOrder($orderId),
            'Route determined successfully.'
        );
    }
}
