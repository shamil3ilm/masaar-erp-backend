<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Maintenance\FleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly FleetService $fleetService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->fleetService->paginate($this->organizationId($request), [
            'vehicle_type' => $request->filled('vehicle_type') ? $request->input('vehicle_type') : null,
            'active_only' => $request->boolean('active_only'),
            'search' => $request->filled('search') ? $request->input('search') : null,
        ], $this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fleet_number' => 'required|string|max:20',
            'license_plate' => 'required|string|max:20',
            'make' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'vin' => 'nullable|string|max:50',
            'vehicle_type' => 'required|in:car,van,truck,motorcycle,bus,other',
            'fuel_type' => 'required|in:petrol,diesel,electric,hybrid,cng',
            'color' => 'nullable|string|max:30',
            'department_id' => ['nullable', 'integer', $this->ownedBy('departments')],
            'current_mileage_km' => 'integer|min:0',
            'last_service_km' => 'nullable|integer|min:0',
            'next_service_km' => 'nullable|integer|min:0',
            'insurance_expiry' => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        return $this->created($this->fleetService->create($this->organizationId($request), $validated));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->fleetService->findWithAssignments($this->organizationId($request), $id);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        return $this->success($vehicle);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->fleetService->find($this->organizationId($request), $id);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'fleet_number' => 'sometimes|string|max:20',
            'license_plate' => 'sometimes|string|max:20',
            'make' => 'sometimes|string|max:50',
            'model' => 'sometimes|string|max:50',
            'year' => 'sometimes|integer|min:1900|max:'.(date('Y') + 1),
            'vin' => 'nullable|string|max:50',
            'vehicle_type' => 'sometimes|in:car,van,truck,motorcycle,bus,other',
            'fuel_type' => 'sometimes|in:petrol,diesel,electric,hybrid,cng',
            'color' => 'nullable|string|max:30',
            'department_id' => ['nullable', 'integer', $this->ownedBy('departments')],
            'current_mileage_km' => 'sometimes|integer|min:0',
            'last_service_km' => 'nullable|integer|min:0',
            'next_service_km' => 'nullable|integer|min:0',
            'insurance_expiry' => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        return $this->success($this->fleetService->update($vehicle, $validated));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->fleetService->find($this->organizationId($request), $id);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $this->fleetService->delete($vehicle);

        return $this->noContent();
    }

    // -------------------------------------------------------------------------
    // Driver Assignment
    // -------------------------------------------------------------------------

    public function assign(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->find($this->organizationId($request), $vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', $this->ownedBy('employees')],
            'assigned_from' => 'nullable|date_format:Y-m-d H:i:s',
            'assigned_to' => 'nullable|date_format:Y-m-d H:i:s|after:assigned_from',
            'purpose' => 'nullable|string|max:100',
        ]);

        return $this->created($this->fleetService->assignDriver($vehicle, (int) $validated['driver_id'], $validated));
    }

    public function unassign(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->find($this->organizationId($request), $vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $this->fleetService->unassignDriver($vehicle);

        return $this->success(['message' => 'Driver unassigned successfully.']);
    }

    // -------------------------------------------------------------------------
    // Mileage Logs
    // -------------------------------------------------------------------------

    public function mileageLogs(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->findOrFail($this->organizationId($request), $vehicleId);

        return $this->paginated($this->fleetService->paginateMileageLogs($vehicle, $this->perPage($request)));
    }

    public function logMileage(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->find($this->organizationId($request), $vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'log_date' => 'nullable|date',
            'odometer_start' => 'required|integer|min:0',
            'odometer_end' => 'required|integer|gt:odometer_start',
            'trip_purpose' => 'nullable|string|max:100',
            'driver_id' => ['nullable', 'integer', $this->ownedBy('employees')],
            'route' => 'nullable|string|max:200',
        ]);

        return $this->created($this->fleetService->logMileage($vehicle, $validated));
    }

    // -------------------------------------------------------------------------
    // Fuel Logs
    // -------------------------------------------------------------------------

    public function fuelLogs(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->findOrFail($this->organizationId($request), $vehicleId);

        return $this->paginated($this->fleetService->paginateFuelLogs($vehicle, $this->perPage($request)));
    }

    public function logFuel(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->find($this->organizationId($request), $vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'log_date' => 'nullable|date',
            'odometer_reading' => 'required|integer|min:0',
            'fuel_quantity_liters' => 'required|numeric|min:0.001',
            'fuel_cost' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'fuel_type' => 'required|in:petrol,diesel,electric,hybrid,cng',
            'station' => 'nullable|string|max:100',
            'filled_by' => ['nullable', 'integer', $this->ownedBy('employees')],
        ]);

        return $this->created($this->fleetService->logFuel($vehicle, $validated));
    }

    // -------------------------------------------------------------------------
    // Vehicle Maintenance
    // -------------------------------------------------------------------------

    public function maintenanceRecords(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->findOrFail($this->organizationId($request), $vehicleId);

        return $this->paginated($this->fleetService->paginateMaintenanceRecords($vehicle, $this->perPage($request)));
    }

    public function recordMaintenance(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = $this->fleetService->find($this->organizationId($request), $vehicleId);

        if ($vehicle === null) {
            return $this->notFound('Vehicle not found.');
        }

        $validated = $request->validate([
            'maintenance_type' => 'required|in:scheduled,unscheduled,repair,inspection',
            'service_date' => 'required|date',
            'odometer_reading' => 'nullable|integer|min:0',
            'description' => 'required|string',
            'cost' => 'nullable|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'service_provider' => 'nullable|string|max:100',
            'next_service_date' => 'nullable|date|after:service_date',
            'next_service_km' => 'nullable|integer|min:0',
            'maintenance_order_id' => ['nullable', 'integer', $this->ownedBy('maintenance_orders')],
        ]);

        return $this->created($this->fleetService->recordMaintenance($vehicle, $validated));
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    public function requiringService(Request $request): JsonResponse
    {
        return $this->success($this->fleetService->getVehiclesRequiringService($this->organizationId($request)));
    }

    public function costSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        return $this->success($this->fleetService->getFleetCostSummary(
            $this->organizationId($request),
            $validated['date_from'],
            $validated['date_to'],
        ));
    }

    /** The page size asked for, capped at 100. */
    private function perPage(Request $request): int
    {
        return min((int) $request->input('per_page', 20), 100);
    }
}
