<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\HR\Employee;
use App\Models\Maintenance\FuelLog;
use App\Models\Maintenance\MileageLog;
use App\Models\Maintenance\Vehicle;
use App\Models\Maintenance\VehicleAssignment;
use App\Models\Maintenance\VehicleMaintenanceRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fleet vehicles, their drivers, mileage and fuel logs and maintenance records.
 *
 * Vehicles are found in the caller's organization, and logs and assignments
 * are reached through their vehicle. Drivers and the employees who filled a
 * vehicle up are shown by reference only, without their personal details.
 */
class FleetService
{
    /**
     * Vehicles in fleet number order. The search matches the fleet number,
     * license plate, make or model.
     *
     * @param  array{vehicle_type?: mixed, active_only?: bool, search?: mixed}  $filters
     */
    public function paginate(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Vehicle::where('organization_id', $organizationId)
            ->with(['department'])
            ->when($filters['vehicle_type'] ?? null, fn ($query, $type) => $query->where('vehicle_type', $type))
            ->when($filters['active_only'] ?? false, fn ($query) => $query->where('is_active', true))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $term = '%'.$search.'%';
                $query->where(fn ($inner) => $inner->where('fleet_number', 'like', $term)
                    ->orWhere('license_plate', 'like', $term)
                    ->orWhere('make', 'like', $term)
                    ->orWhere('model', 'like', $term));
            })
            ->orderBy('fleet_number')
            ->paginate($perPage);
    }

    /**
     * The organization's vehicle, or null when it has none with this id.
     */
    public function find(int $organizationId, int $vehicleId): ?Vehicle
    {
        return Vehicle::where('organization_id', $organizationId)->find($vehicleId);
    }

    /**
     * The organization's vehicle with its department and driver assignments.
     */
    public function findWithAssignments(int $organizationId, int $vehicleId): ?Vehicle
    {
        return Vehicle::where('organization_id', $organizationId)
            ->with(['department', 'assignments.driver:'.$this->employeeColumns()])
            ->find($vehicleId);
    }

    /**
     * The organization's vehicle; another organization's id is not found.
     */
    public function findOrFail(int $organizationId, int $vehicleId): Vehicle
    {
        return Vehicle::where('organization_id', $organizationId)->findOrFail($vehicleId);
    }

    /**
     * Create a new vehicle record.
     */
    public function create(int $organizationId, array $data): Vehicle
    {
        return Vehicle::create([
            'organization_id' => $organizationId,
            'fleet_number' => $data['fleet_number'],
            'license_plate' => $data['license_plate'],
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'],
            'vin' => $data['vin'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? Vehicle::TYPE_CAR,
            'fuel_type' => $data['fuel_type'] ?? Vehicle::FUEL_PETROL,
            'color' => $data['color'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'current_mileage_km' => $data['current_mileage_km'] ?? 0,
            'last_service_km' => $data['last_service_km'] ?? null,
            'next_service_km' => $data['next_service_km'] ?? null,
            'insurance_expiry' => $data['insurance_expiry'] ?? null,
            'registration_expiry' => $data['registration_expiry'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->load('department');
    }

    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update($data);

        return $vehicle->fresh('department');
    }

    public function delete(Vehicle $vehicle): void
    {
        $vehicle->delete();
    }

    /**
     * Assign a driver to a vehicle, ending its current assignment first. The
     * vehicle is locked so two assignments cannot both become current.
     */
    public function assignDriver(Vehicle $vehicle, int $driverId, array $data): VehicleAssignment
    {
        return DB::transaction(function () use ($vehicle, $driverId, $data): VehicleAssignment {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);

            $this->endCurrentAssignment($vehicle);

            return VehicleAssignment::create([
                'organization_id' => $vehicle->organization_id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driverId,
                'assigned_from' => $data['assigned_from'] ?? now(),
                'assigned_to' => $data['assigned_to'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'is_current' => true,
            ])->load('driver:'.$this->employeeColumns());
        });
    }

    /**
     * End the current driver assignment for a vehicle.
     */
    public function unassignDriver(Vehicle $vehicle): void
    {
        $this->endCurrentAssignment($vehicle);
    }

    public function paginateMileageLogs(Vehicle $vehicle, int $perPage): LengthAwarePaginator
    {
        return MileageLog::where('vehicle_id', $vehicle->id)
            ->with('driver:'.$this->employeeColumns())
            ->orderByDesc('log_date')
            ->paginate($perPage);
    }

    /**
     * Log a trip and move the vehicle's odometer on when the trip ends past it.
     * The vehicle is locked so concurrent logs compare against its latest reading.
     */
    public function logMileage(Vehicle $vehicle, array $data): MileageLog
    {
        return DB::transaction(function () use ($vehicle, $data): MileageLog {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);

            $log = MileageLog::create([
                'organization_id' => $vehicle->organization_id,
                'vehicle_id' => $vehicle->id,
                'log_date' => $data['log_date'] ?? now()->toDateString(),
                'odometer_start' => $data['odometer_start'],
                'odometer_end' => $data['odometer_end'],
                'distance_km' => max(0, (int) $data['odometer_end'] - (int) $data['odometer_start']),
                'trip_purpose' => $data['trip_purpose'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'route' => $data['route'] ?? null,
            ]);

            if ((int) $data['odometer_end'] > $vehicle->current_mileage_km) {
                $vehicle->update(['current_mileage_km' => (int) $data['odometer_end']]);
            }

            return $log;
        });
    }

    public function paginateFuelLogs(Vehicle $vehicle, int $perPage): LengthAwarePaginator
    {
        return FuelLog::where('vehicle_id', $vehicle->id)
            ->with('filledBy:'.$this->employeeColumns())
            ->orderByDesc('log_date')
            ->paginate($perPage);
    }

    /**
     * Log a fuel fill-up for a vehicle.
     */
    public function logFuel(Vehicle $vehicle, array $data): FuelLog
    {
        return FuelLog::create([
            'organization_id' => $vehicle->organization_id,
            'vehicle_id' => $vehicle->id,
            'log_date' => $data['log_date'] ?? now()->toDateString(),
            'odometer_reading' => $data['odometer_reading'],
            'fuel_quantity_liters' => $data['fuel_quantity_liters'],
            'fuel_cost' => $data['fuel_cost'],
            'currency_code' => $data['currency_code'],
            'fuel_type' => $data['fuel_type'],
            'station' => $data['station'] ?? null,
            'filled_by' => $data['filled_by'] ?? null,
        ]);
    }

    public function paginateMaintenanceRecords(Vehicle $vehicle, int $perPage): LengthAwarePaginator
    {
        return VehicleMaintenanceRecord::where('vehicle_id', $vehicle->id)
            ->orderByDesc('service_date')
            ->paginate($perPage);
    }

    /**
     * Record a maintenance event and move the vehicle's service trackers to it.
     */
    public function recordMaintenance(Vehicle $vehicle, array $data): VehicleMaintenanceRecord
    {
        return DB::transaction(function () use ($vehicle, $data): VehicleMaintenanceRecord {
            $record = VehicleMaintenanceRecord::create([
                'organization_id' => $vehicle->organization_id,
                'vehicle_id' => $vehicle->id,
                'maintenance_type' => $data['maintenance_type'],
                'service_date' => $data['service_date'],
                'odometer_reading' => $data['odometer_reading'] ?? null,
                'description' => $data['description'],
                'cost' => $data['cost'] ?? null,
                'currency_code' => $data['currency_code'] ?? null,
                'service_provider' => $data['service_provider'] ?? null,
                'next_service_date' => $data['next_service_date'] ?? null,
                'next_service_km' => $data['next_service_km'] ?? null,
                'maintenance_order_id' => $data['maintenance_order_id'] ?? null,
            ]);

            $trackers = [];
            if (! empty($data['odometer_reading'])) {
                $trackers['last_service_km'] = (int) $data['odometer_reading'];
            }
            if (! empty($data['next_service_km'])) {
                $trackers['next_service_km'] = (int) $data['next_service_km'];
            }
            if (! empty($trackers)) {
                $vehicle->update($trackers);
            }

            return $record;
        });
    }

    /**
     * Get vehicles that have exceeded or are near their service mileage threshold.
     */
    public function getVehiclesRequiringService(int $organizationId): Collection
    {
        return Vehicle::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereNotNull('next_service_km')
            ->whereColumn('current_mileage_km', '>=', 'next_service_km')
            ->get();
    }

    /**
     * Summarise fleet fuel and maintenance costs over a date range.
     *
     * @return array{fuel_cost: float, maintenance_cost: float, total_cost: float, vehicle_count: int}
     */
    public function getFleetCostSummary(int $organizationId, string $dateFrom, string $dateTo): array
    {
        $fuelCost = FuelLog::where('organization_id', $organizationId)
            ->whereBetween('log_date', [$dateFrom, $dateTo])
            ->sum('fuel_cost');

        $maintenanceCost = VehicleMaintenanceRecord::where('organization_id', $organizationId)
            ->whereBetween('service_date', [$dateFrom, $dateTo])
            ->whereNotNull('cost')
            ->sum('cost');

        $vehicleCount = Vehicle::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->count();

        return [
            'fuel_cost' => round((float) $fuelCost, 4),
            'maintenance_cost' => round((float) $maintenanceCost, 4),
            'total_cost' => round((float) $fuelCost + (float) $maintenanceCost, 4),
            'vehicle_count' => $vehicleCount,
        ];
    }

    /**
     * Calculate average fuel efficiency (km/L) for a vehicle over a date range.
     */
    public function getFuelEfficiency(int $vehicleId, string $dateFrom, string $dateTo): float
    {
        $logs = FuelLog::where('vehicle_id', $vehicleId)
            ->whereBetween('log_date', [$dateFrom, $dateTo])
            ->orderBy('log_date')
            ->get();

        if ($logs->count() < 2) {
            return 0.0;
        }

        $totalLiters = (float) $logs->sum('fuel_quantity_liters');

        if ($totalLiters === 0.0) {
            return 0.0;
        }

        $totalKm = (int) $logs->last()->odometer_reading - (int) $logs->first()->odometer_reading;

        if ($totalKm <= 0) {
            return 0.0;
        }

        return round($totalKm / $totalLiters, 2);
    }

    private function endCurrentAssignment(Vehicle $vehicle): void
    {
        VehicleAssignment::where('vehicle_id', $vehicle->id)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'assigned_to' => now(),
            ]);
    }

    private function employeeColumns(): string
    {
        return implode(',', Employee::REFERENCE_COLUMNS);
    }
}
