<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\FeatureFlag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform feature flags managed by super admins.
 */
class FeatureFlagService
{
    public function all(): Collection
    {
        return FeatureFlag::orderBy('code')->get();
    }

    public function create(array $data): FeatureFlag
    {
        return FeatureFlag::create($data);
    }

    public function update(FeatureFlag $flag, array $data): FeatureFlag
    {
        $flag->update($data);

        return $flag->fresh();
    }

    /**
     * Flip the flag on its locked row, so two toggles at once flip it twice
     * instead of both writing the same value.
     */
    public function toggle(FeatureFlag $flag): FeatureFlag
    {
        return DB::transaction(function () use ($flag) {
            $locked = FeatureFlag::query()->lockForUpdate()->findOrFail($flag->id);
            $locked->update(['is_enabled' => ! $locked->is_enabled]);

            return $locked->fresh();
        });
    }

    public function findByCode(string $code): ?FeatureFlag
    {
        return FeatureFlag::where('code', $code)->first();
    }
}
