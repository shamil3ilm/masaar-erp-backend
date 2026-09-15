<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CostingVersion;
use App\Models\Manufacturing\ProductStandardCost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Costing versions and the standard costs recorded under them.
 *
 * Standard costs carry no organization column; they are read only through a
 * costing version, which the caller has already resolved within its
 * organization.
 */
class CostingVersionService
{
    /** Columns a version list may be sorted by. */
    public const SORT_COLUMNS = ['version_code', 'valid_from', 'created_at'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return CostingVersion::query()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['costing_type'] ?? null, fn ($q, $v) => $q->where('costing_type', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(function ($inner) use ($s) {
                $inner->where('version_code', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            }))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Create a costing version; a new version starts as a draft.
     */
    public function create(array $data, ?int $userId): CostingVersion
    {
        return CostingVersion::create(array_merge($data, [
            'status'     => CostingVersion::STATUS_DRAFT,
            'created_by' => $userId,
        ]));
    }

    public function paginateStandardCosts(CostingVersion $version, ?int $productId, int $perPage): LengthAwarePaginator
    {
        return ProductStandardCost::where('costing_version_id', $version->id)
            ->with(['product', 'variant'])
            ->when($productId, fn ($q, $id) => $q->where('product_id', $id))
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * The version's standard cost for a product, with its cost components.
     */
    public function findStandardCostOrFail(CostingVersion $version, int $productId): ProductStandardCost
    {
        return ProductStandardCost::where('costing_version_id', $version->id)
            ->where('product_id', $productId)
            ->with(['product', 'components'])
            ->firstOrFail();
    }
}
