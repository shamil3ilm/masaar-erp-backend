<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\PlatformSetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide key/value settings.
 */
class PlatformSettingsService
{
    public function all(): Collection
    {
        return PlatformSetting::all();
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(string $key): PlatformSetting
    {
        return PlatformSetting::where('key', $key)->firstOrFail();
    }

    /**
     * Create or overwrite one setting.
     */
    public function put(string $key, mixed $value, ?string $group): PlatformSetting
    {
        return PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    /**
     * Create or overwrite several settings together: either all are saved or none.
     *
     * @param  array<string, mixed>  $settings
     */
    public function putMany(array $settings): void
    {
        DB::transaction(function () use ($settings) {
            foreach ($settings as $key => $value) {
                PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });
    }
}
