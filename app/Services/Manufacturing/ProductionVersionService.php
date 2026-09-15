<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Production versions: which BOM and routing produce a product for a lot size
 * and validity period, with one default version per product.
 */
class ProductionVersionService
{
    /**
     * @param  array{product_id?: mixed, active_only?: bool}  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return ProductionVersion::with(['product', 'bom', 'routing'])
            ->when($filters['product_id'] ?? null, fn($q, $id) => $q->forProduct((int) $id))
            ->when($filters['active_only'] ?? false, fn($q) => $q->active())
            ->orderByDesc('is_default')
            ->orderBy('product_id')
            ->orderBy('version_code')
            ->paginate($perPage);
    }

    /**
     * One of the organization's production versions, or null.
     *
     * @param  list<string>  $with
     */
    public function find(int $id, array $with = []): ?ProductionVersion
    {
        return ProductionVersion::with($with)->find($id);
    }

    /**
     * Retrieve the default production version for a product.
     */
    public function getDefaultVersion(int $productId): ?ProductionVersion
    {
        return ProductionVersion::active()
            ->defaultForProduct($productId)
            ->first();
    }

    /**
     * Find the first active version whose lot size range covers the given quantity.
     */
    public function getVersionForLotSize(int $productId, float $quantity): ?ProductionVersion
    {
        $versions = ProductionVersion::active()
            ->forProduct($productId)
            ->orderByDesc('is_default')
            ->get();

        foreach ($versions as $version) {
            if ($version->isValidForLotSize($quantity)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Create a new production version.
     */
    public function create(array $data): ProductionVersion
    {
        return DB::transaction(function () use ($data): ProductionVersion {
            if (!empty($data['is_default']) && $data['is_default']) {
                $this->clearDefaultFlag((int) $data['product_id']);
            }

            return ProductionVersion::create($data);
        });
    }

    /**
     * Update an existing production version.
     */
    public function update(ProductionVersion $version, array $data): ProductionVersion
    {
        return DB::transaction(function () use ($version, $data): ProductionVersion {
            if (!empty($data['is_default']) && $data['is_default']) {
                $this->clearDefaultFlag((int) ($data['product_id'] ?? $version->product_id), $version->id);
            }

            $version->update($data);

            return $version->fresh();
        });
    }

    /**
     * Soft-delete a production version.
     */
    public function delete(ProductionVersion $version): void
    {
        $version->delete();
    }

    /**
     * Set a version as the default for its product, unsetting all others.
     */
    public function setDefault(ProductionVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $this->clearDefaultFlag($version->product_id, $version->id);

            $version->update(['is_default' => true]);
        });
    }

    /**
     * Retrieve all versions for a product, ordered so the default comes first.
     */
    public function getVersionsForProduct(int $productId): Collection
    {
        return ProductionVersion::forProduct($productId)
            ->with(['bom', 'routing'])
            ->orderByDesc('is_default')
            ->orderBy('version_code')
            ->get();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Clear the is_default flag for all versions of a product except the given one.
     */
    private function clearDefaultFlag(int $productId, ?int $exceptId = null): void
    {
        ProductionVersion::forProduct($productId)
            ->where('is_default', true)
            ->when($exceptId !== null, fn($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }
}
