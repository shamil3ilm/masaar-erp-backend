<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Warehouse master data of the current organization. An organization has at
 * most one default warehouse, so every change that sets a default clears the
 * previous one in the same transaction.
 */
class WarehouseService
{
    /**
     * Warehouses with their branch and manager; only active ones when
     * active_only is true, one branch when branch_id is present.
     *
     * @param  array{active_only?: bool, branch_id?: mixed}  $filters
     * @return Collection<int, Warehouse>
     */
    public function list(array $filters): Collection
    {
        return Warehouse::with(['branch', 'manager'])
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->when(array_key_exists('branch_id', $filters), fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Warehouse
    {
        return DB::transaction(function () use ($data): Warehouse {
            if ($data['is_default'] ?? false) {
                $this->clearDefault();
            }

            return Warehouse::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            if (($data['is_default'] ?? false) && ! $warehouse->is_default) {
                $this->clearDefault();
            }

            $warehouse->update($data);

            return $warehouse->fresh();
        });
    }

    /**
     * Deletes a warehouse that holds no stock. The stock check and the delete
     * run in one transaction on the locked warehouse row.
     */
    public function delete(Warehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse): void {
            $locked = Warehouse::query()->lockForUpdate()->findOrFail($warehouse->id);

            if ($locked->stockLevels()->where('quantity', '>', 0)->exists()) {
                throw new \InvalidArgumentException('Cannot delete warehouse with existing stock.');
            }

            $locked->delete();
        });
    }

    public function setDefault(Warehouse $warehouse): Warehouse
    {
        return DB::transaction(function () use ($warehouse): Warehouse {
            $this->clearDefault();
            $warehouse->update(['is_default' => true]);

            return $warehouse->fresh();
        });
    }

    /**
     * Unsets the current default. Each warehouse is saved as a model so its
     * audit trail records the change.
     */
    private function clearDefault(): void
    {
        Warehouse::where('is_default', true)
            ->lockForUpdate()
            ->get()
            ->each(fn (Warehouse $w) => $w->update(['is_default' => false]));
    }
}
