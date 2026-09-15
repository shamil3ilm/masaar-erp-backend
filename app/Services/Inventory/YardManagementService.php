<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\DockDoor;
use App\Models\Sales\Contact;
use App\Models\Inventory\TruckAppointment;
use App\Models\Inventory\YardMovement;
use App\Models\Inventory\YardZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class YardManagementService
{
    /**
     * Create a truck appointment.
     *
     * Expected keys in $data:
     *   organization_id, warehouse_id, appointment_number, scheduled_arrival,
     *   appointment_type (delivery/pickup/both), vendor_id?, scheduled_departure?,
     *   vehicle_plate?, driver_name?, driver_phone?, reference_type?,
     *   reference_id?, notes?, created_by?
     */
    public function createAppointment(array $data): TruckAppointment
    {
        $data['status'] = TruckAppointment::STATUS_SCHEDULED;

        return TruckAppointment::create($data);
    }

    /**
     * Check in a truck: set actual_arrival, update status to checked_in,
     * assign to a yard zone if provided, and create an arrival movement.
     *
     * The status is checked on the locked appointment, so a second check-in
     * is refused instead of recording another arrival.
     */
    public function checkIn(TruckAppointment $appointment, array $data): YardMovement
    {
        return $appointment->lockForTransition(function (TruckAppointment $appointment) use ($data): YardMovement {
            if (!$appointment->canCheckIn()) {
                throw new RuntimeException(
                    "Appointment cannot be checked in. Current status: {$appointment->status}."
                );
            }

            $yardZoneId = $data['yard_zone_id'] ?? null;

            $appointment->update([
                'status'         => TruckAppointment::STATUS_CHECKED_IN,
                'actual_arrival' => $data['actual_arrival'] ?? now(),
                'yard_zone_id'   => $yardZoneId,
                'vehicle_plate'  => $data['vehicle_plate'] ?? $appointment->vehicle_plate,
                'driver_name'    => $data['driver_name'] ?? $appointment->driver_name,
                'driver_phone'   => $data['driver_phone'] ?? $appointment->driver_phone,
            ]);

            return YardMovement::create([
                'organization_id'      => $appointment->organization_id,
                'truck_appointment_id' => $appointment->id,
                'to_zone_id'           => $yardZoneId,
                'movement_type'        => YardMovement::TYPE_ARRIVAL,
                'moved_at'             => $data['actual_arrival'] ?? now(),
                'moved_by'             => $data['moved_by'] ?? null,
                'notes'                => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Assign a checked-in truck to a dock door.
     *
     * The appointment and the door are both locked while the checks run, so
     * two trucks cannot both take a door that was free when they asked.
     */
    public function assignToDock(TruckAppointment $appointment, int $dockDoorId): YardMovement
    {
        return $appointment->lockForTransition(function (TruckAppointment $appointment) use ($dockDoorId): YardMovement {
            if (!$appointment->canAssignDock()) {
                throw new RuntimeException(
                    "Appointment cannot be assigned to a dock. Current status: {$appointment->status}."
                );
            }

            $dockDoor = DockDoor::query()->lockForUpdate()->findOrFail($dockDoorId);

            if (!$dockDoor->isAvailable()) {
                throw new RuntimeException("Dock door #{$dockDoorId} is not available.");
            }

            $previousZoneId = $appointment->yard_zone_id;

            $appointment->update([
                'status'       => TruckAppointment::STATUS_DOCKED,
                'dock_door_id' => $dockDoor->id,
            ]);

            $dockDoor->update(['status' => DockDoor::STATUS_OCCUPIED]);

            return YardMovement::create([
                'organization_id'      => $appointment->organization_id,
                'truck_appointment_id' => $appointment->id,
                'from_zone_id'         => $previousZoneId,
                'to_dock_id'           => $dockDoor->id,
                'movement_type'        => YardMovement::TYPE_MOVE_TO_DOCK,
                'moved_at'             => now(),
                'moved_by'             => auth()->id(),
            ]);
        });
    }

    /**
     * Mark a truck as departed and free the dock door.
     *
     * The status is checked on the locked appointment, so a second departure
     * is refused instead of recording another movement.
     */
    public function depart(TruckAppointment $appointment): YardMovement
    {
        return $appointment->lockForTransition(function (TruckAppointment $appointment): YardMovement {
            if (!$appointment->canDepart()) {
                throw new RuntimeException(
                    "Appointment cannot be departed. Current status: {$appointment->status}."
                );
            }

            $dockDoorId = $appointment->dock_door_id;

            $appointment->update([
                'status'          => TruckAppointment::STATUS_DEPARTED,
                'actual_departure' => now(),
            ]);

            if ($dockDoorId !== null) {
                DockDoor::where('id', $dockDoorId)
                    ->update(['status' => DockDoor::STATUS_AVAILABLE]);
            }

            return YardMovement::create([
                'organization_id'      => $appointment->organization_id,
                'truck_appointment_id' => $appointment->id,
                'from_dock_id'         => $dockDoorId,
                'from_zone_id'         => $appointment->yard_zone_id,
                'movement_type'        => YardMovement::TYPE_DEPARTURE,
                'moved_at'             => now(),
                'moved_by'             => auth()->id(),
            ]);
        });
    }

    /**
     * Get available dock doors for a warehouse at a given datetime.
     * A door is available if it has no overlapping active appointment.
     */
    public function getAvailableDocks(int $warehouseId, string $datetime): Collection
    {
        $occupiedDoorIds = TruckAppointment::where('warehouse_id', $warehouseId)
            ->whereNotIn('status', [TruckAppointment::STATUS_DEPARTED, TruckAppointment::STATUS_CANCELLED])
            ->whereNotNull('dock_door_id')
            ->pluck('dock_door_id')
            ->all();

        return DockDoor::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->where('status', DockDoor::STATUS_AVAILABLE)
            ->whereNotIn('id', $occupiedDoorIds)
            ->with('yardZone')
            ->get();
    }

    /**
     * Get the daily schedule for a warehouse on a given date.
     */
    public function getDailySchedule(int $warehouseId, string $date): Collection
    {
        return TruckAppointment::where('warehouse_id', $warehouseId)
            ->whereDate('scheduled_arrival', $date)
            ->whereNot('status', TruckAppointment::STATUS_CANCELLED)
            ->with([$this->vendorReference(), 'dockDoor', 'yardZone'])
            ->orderBy('scheduled_arrival')
            ->get();
    }

    /**
     * Get current yard status: zone occupancy and dock door statuses.
     */
    public function getYardStatus(int $warehouseId): array
    {
        $zones = YardZone::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->withCount([
                'movements as current_vehicles' => fn ($q) => $q
                    ->whereHas('truckAppointment', fn ($q2) => $q2
                        ->whereNotIn('status', [
                            TruckAppointment::STATUS_DEPARTED,
                            TruckAppointment::STATUS_CANCELLED,
                        ])
                        ->where('yard_zone_id', DB::raw('to_zone_id'))
                    ),
            ])
            ->get();

        $dockDoors = DockDoor::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->with([
                'appointments' => fn ($q) => $q
                    ->whereNotIn('status', [
                        TruckAppointment::STATUS_DEPARTED,
                        TruckAppointment::STATUS_CANCELLED,
                    ])
                    ->latest('actual_arrival')
                    ->limit(1),
            ])
            ->get();

        $activeAppointments = TruckAppointment::where('warehouse_id', $warehouseId)
            ->whereNotIn('status', [TruckAppointment::STATUS_DEPARTED, TruckAppointment::STATUS_CANCELLED])
            ->with([$this->vendorReference(), 'dockDoor', 'yardZone'])
            ->orderBy('scheduled_arrival')
            ->get();

        return [
            'warehouse_id'        => $warehouseId,
            'zones'               => $zones,
            'dock_doors'          => $dockDoors,
            'active_appointments' => $activeAppointments,
            'summary'             => [
                'total_zones'            => $zones->count(),
                'total_docks'            => $dockDoors->count(),
                'available_docks'        => $dockDoors->where('status', DockDoor::STATUS_AVAILABLE)->count(),
                'occupied_docks'         => $dockDoors->where('status', DockDoor::STATUS_OCCUPIED)->count(),
                'maintenance_docks'      => $dockDoors->where('status', DockDoor::STATUS_MAINTENANCE)->count(),
                'active_trucks'          => $activeAppointments->count(),
            ],
        ];
    }

    // ── Lookups and master data ──────────────────────────────────────────────

    /**
     * Yard zones of the organization with their warehouse, by zone code.
     * warehouse_id applies when given; active_only when true.
     *
     * @param  array{warehouse_id?: mixed, active_only?: bool}  $filters
     */
    public function listZones(int $organizationId, array $filters): Collection
    {
        return YardZone::where('organization_id', $organizationId)
            ->when($filters['warehouse_id'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->where('is_active', true))
            ->with('warehouse')
            ->orderBy('zone_code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createZone(array $data): YardZone
    {
        return YardZone::create($data);
    }

    /**
     * Dock doors of the organization with their warehouse and zone, by door
     * code. warehouse_id and status apply when given; active_only when true.
     *
     * @param  array{warehouse_id?: mixed, status?: mixed, active_only?: bool}  $filters
     */
    public function listDockDoors(int $organizationId, array $filters): Collection
    {
        return DockDoor::where('organization_id', $organizationId)
            ->when($filters['warehouse_id'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->where('is_active', true))
            ->with(['warehouse', 'yardZone'])
            ->orderBy('door_code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDockDoor(array $data): DockDoor
    {
        return DockDoor::create($data)->load('yardZone');
    }

    public function findDockDoorOrFail(int $organizationId, int $id): DockDoor
    {
        return DockDoor::where('organization_id', $organizationId)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDockDoor(DockDoor $door, array $data): DockDoor
    {
        $door->update($data);

        return $door->fresh()->load('yardZone');
    }

    /**
     * Appointments of the organization with their vendor, door and zone, by
     * scheduled arrival. warehouse_id, status and date apply when given.
     *
     * @param  array{warehouse_id?: mixed, status?: mixed, date?: mixed}  $filters
     */
    public function paginateAppointments(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return TruckAppointment::where('organization_id', $organizationId)
            ->when($filters['warehouse_id'] ?? null, fn ($q, $w) => $q->where('warehouse_id', $w))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['date'] ?? null, fn ($q, $d) => $q->whereDate('scheduled_arrival', $d))
            ->with([$this->vendorReference(), 'dockDoor', 'yardZone'])
            ->orderBy('scheduled_arrival')
            ->paginate($perPage);
    }

    /**
     * An appointment of the organization. A vendor among $with is loaded with
     * its reference columns only.
     *
     * @param  list<string>  $with
     */
    public function findAppointmentOrFail(int $organizationId, int $id, array $with = []): TruckAppointment
    {
        $with = array_map(fn (string $relation) => $relation === 'vendor' ? $this->vendorReference() : $relation, $with);

        return TruckAppointment::where('organization_id', $organizationId)
            ->with($with)
            ->findOrFail($id);
    }

    /**
     * Update an appointment that has neither departed nor been cancelled,
     * checked on the locked row.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAppointment(TruckAppointment $appointment, array $data): TruckAppointment
    {
        return $appointment->lockForTransition(function (TruckAppointment $appointment) use ($data): TruckAppointment {
            if ($appointment->isDeparted() || $appointment->isCancelled()) {
                throw new RuntimeException('Departed or cancelled appointments cannot be updated.');
            }

            $appointment->update($data);

            return $appointment->fresh();
        });
    }

    /**
     * Cancel an appointment that has not departed, checked on the locked row.
     * An appointment already cancelled is returned as it is.
     */
    public function cancelAppointment(TruckAppointment $appointment): TruckAppointment
    {
        return $appointment->lockForTransition(function (TruckAppointment $appointment): TruckAppointment {
            if ($appointment->isDeparted()) {
                throw new RuntimeException('Departed appointments cannot be cancelled.');
            }

            if (! $appointment->isCancelled()) {
                $appointment->update(['status' => TruckAppointment::STATUS_CANCELLED]);
            }

            return $appointment->fresh();
        });
    }

    /**
     * The vendor is a contact; only its reference columns are embedded, never
     * its tax number or other private fields.
     */
    private function vendorReference(): string
    {
        return 'vendor:'.implode(',', Contact::REFERENCE_COLUMNS);
    }
}
