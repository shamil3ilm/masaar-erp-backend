<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\DashboardLayout;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard layouts of an organization: each user's own and the shared ones
 * (no user). One layout is the default per owner and type; marking one default
 * clears the others in the same transaction.
 */
class DashboardLayoutService
{
    /**
     * The user's own layouts and the organization's shared layouts.
     *
     * @return array{user_layouts: Collection<int, DashboardLayout>, shared_layouts: Collection<int, DashboardLayout>}
     */
    public function listForUser(int $organizationId, int $userId): array
    {
        return [
            'user_layouts' => DashboardLayout::where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->get(),
            'shared_layouts' => DashboardLayout::where('organization_id', $organizationId)
                ->whereNull('user_id')
                ->where('is_shared', true)
                ->get(),
        ];
    }

    /**
     * A layout the user may view: their own or a shared one.
     */
    public function findViewable(int $organizationId, int $userId, int $id): DashboardLayout
    {
        return DashboardLayout::where('organization_id', $organizationId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('is_shared', true);
            })
            ->findOrFail($id);
    }

    /**
     * A layout the user may change: their own or an organization-wide shared one.
     */
    public function findChangeable(int $organizationId, int $userId, int $id): DashboardLayout
    {
        return DashboardLayout::where('organization_id', $organizationId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('user_id')
                            ->where('is_shared', true);
                    });
            })
            ->findOrFail($id);
    }

    /**
     * One of the user's own layouts; any other layout is not found.
     */
    public function findOwn(int $organizationId, int $userId, int $id): DashboardLayout
    {
        return DashboardLayout::where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->findOrFail($id);
    }

    /**
     * Creates a layout for the user, or a shared one, and makes it the only
     * default of its owner and type when asked.
     *
     * @param  array{name: string, type: string, widgets: array, layout: array, is_default: bool}  $data
     */
    public function create(int $organizationId, int $userId, array $data, bool $isShared): DashboardLayout
    {
        return DB::transaction(function () use ($organizationId, $userId, $data, $isShared): DashboardLayout {
            $layout = DashboardLayout::create([
                'organization_id' => $organizationId,
                'user_id' => $isShared ? null : $userId,
                'name' => $data['name'],
                'type' => $data['type'],
                'widgets' => $data['widgets'],
                'layout' => $data['layout'],
                'is_default' => $data['is_default'],
                'is_shared' => $isShared,
            ]);

            if ($layout->is_default) {
                $this->clearOtherDefaults($layout);
            }

            return $layout;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated name, widgets, layout and default flag
     */
    public function update(DashboardLayout $layout, array $data, bool $makeDefault): DashboardLayout
    {
        return DB::transaction(function () use ($layout, $data, $makeDefault): DashboardLayout {
            $layout->fill($data);
            $layout->save();

            if ($makeDefault) {
                $this->clearOtherDefaults($layout);
            }

            return $layout;
        });
    }

    public function delete(DashboardLayout $layout): void
    {
        $layout->delete();
    }

    /**
     * Replaces the user's layouts of a type with the default layout in one change.
     */
    public function reset(int $organizationId, int $userId, string $type): DashboardLayout
    {
        return DB::transaction(function () use ($organizationId, $userId, $type): DashboardLayout {
            DashboardLayout::where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('type', $type)
                ->delete();

            return DashboardLayout::createDefaultLayout($organizationId, $userId, $type);
        });
    }

    private function clearOtherDefaults(DashboardLayout $layout): void
    {
        DashboardLayout::where('organization_id', $layout->organization_id)
            ->where('user_id', $layout->user_id)
            ->where('type', $layout->type)
            ->where('id', '!=', $layout->id)
            ->update(['is_default' => false]);
    }
}
