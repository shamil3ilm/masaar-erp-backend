<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\HazmatClassification;
use App\Models\Inventory\HazmatStorageClass;
use App\Models\Inventory\HazmatStorageCompatibilityRule;
use App\Models\Inventory\HazmatTransportRegulation;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductHazmatClassification;
use App\Models\Inventory\SafetyDataSheet;
use App\Models\Inventory\SafetyDataSheetSection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HazmatService
{
    /**
     * Assign hazmat classifications to a product.
     * Replaces any existing classifications for the product.
     */
    public function classifyProduct(int $productId, array $classificationData): void
    {
        DB::transaction(function () use ($productId, $classificationData): void {
            // Classifications carry no organization column, so they are
            // written only for a product the caller's organization owns.
            Product::query()->lockForUpdate()->findOrFail($productId);

            // Remove existing classifications for this product.
            ProductHazmatClassification::where('product_id', $productId)->delete();

            foreach ($classificationData as $item) {
                ProductHazmatClassification::create([
                    'product_id'               => $productId,
                    'hazmat_classification_id' => $item['hazmat_classification_id'],
                    'storage_class_id'         => $item['storage_class_id'] ?? null,
                    'is_primary'               => $item['is_primary'] ?? false,
                ]);
            }
        });
    }

    /**
     * Return the current (latest revision) Safety Data Sheet for a product in a given language.
     */
    public function getCurrentSds(int $productId, string $language = 'en'): ?SafetyDataSheet
    {
        return SafetyDataSheet::with('sections')
            ->forProduct($productId)
            ->current()
            ->forLanguage($language)
            ->latest('revision_date')
            ->first();
    }

    /**
     * Create a new Safety Data Sheet and optionally mark it as the current version.
     */
    public function createSds(array $data): SafetyDataSheet
    {
        return DB::transaction(function () use ($data): SafetyDataSheet {
            $sections = $data['sections'] ?? [];
            unset($data['sections']);

            $markCurrent = (bool) ($data['is_current'] ?? true);
            $data['is_current'] = false; // will be set below after creation

            $sds = SafetyDataSheet::create($data);

            foreach ($sections as $sectionData) {
                SafetyDataSheetSection::create(array_merge($sectionData, [
                    'safety_data_sheet_id' => $sds->id,
                ]));
            }

            if ($markCurrent) {
                $sds->markAsCurrentVersion();
            }

            return $sds->load('sections');
        });
    }

    /**
     * Check whether two storage classes are compatible for co-storage.
     * Returns true when compatible, false when incompatible, and true (permissive) when no rule exists.
     */
    public function checkStorageCompatibility(int $storageClassAId, int $storageClassBId): bool
    {
        $organizationId = auth()->user()->organization_id;

        $rule = HazmatStorageCompatibilityRule::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where(function ($query) use ($storageClassAId, $storageClassBId): void {
                $query->where(function ($q) use ($storageClassAId, $storageClassBId): void {
                    $q->where('storage_class_a_id', $storageClassAId)
                      ->where('storage_class_b_id', $storageClassBId);
                })->orWhere(function ($q) use ($storageClassAId, $storageClassBId): void {
                    $q->where('storage_class_a_id', $storageClassBId)
                      ->where('storage_class_b_id', $storageClassAId);
                });
            })
            ->first();

        // If no explicit rule, treat as compatible (permissive default).
        return $rule === null || $rule->is_compatible;
    }

    /**
     * Return transport regulations for a product filtered by transport mode.
     */
    public function getTransportRestrictions(int $productId, string $transportMode): Collection
    {
        return HazmatTransportRegulation::forProduct($productId)
            ->forMode($transportMode)
            ->get();
    }

    /**
     * Return all hazardous products for an organization (products with at least one hazmat classification).
     */
    public function getHazardousProducts(int $organizationId): Collection
    {
        return Product::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->whereHas('hazmatClassifications')
            ->with('hazmatClassifications')
            ->get();
    }

    /**
     * Determine whether a product has any hazmat classifications assigned.
     */
    public function isProductHazardous(int $productId): bool
    {
        return ProductHazmatClassification::where('product_id', $productId)->exists();
    }

    /**
     * Classifications of the current organization by system and code. system
     * and active_only apply when truthy.
     *
     * @param  array{system?: mixed, active_only?: mixed}  $filters
     */
    public function paginateClassifications(array $filters, int $perPage): LengthAwarePaginator
    {
        return HazmatClassification::query()
            ->when($filters['system'] ?? null, fn ($q, $s) => $q->bySystem($s))
            ->when($filters['active_only'] ?? null, fn ($q) => $q->active())
            ->orderBy('classification_system')
            ->orderBy('code')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createClassification(array $data): HazmatClassification
    {
        return HazmatClassification::create($data);
    }

    public function paginateStorageClasses(int $perPage): LengthAwarePaginator
    {
        return HazmatStorageClass::query()
            ->orderBy('code')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createStorageClass(array $data): HazmatStorageClass
    {
        return HazmatStorageClass::create($data);
    }

    /**
     * Safety data sheets of the current organization with their product,
     * latest revision first. product_id, language and current_only apply when
     * truthy.
     *
     * @param  array{product_id?: mixed, language?: mixed, current_only?: mixed}  $filters
     */
    public function paginateSafetyDataSheets(array $filters, int $perPage): LengthAwarePaginator
    {
        return SafetyDataSheet::with(['product'])
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->forProduct((int) $id))
            ->when($filters['language'] ?? null, fn ($q, $lang) => $q->forLanguage($lang))
            ->when($filters['current_only'] ?? null, fn ($q) => $q->current())
            ->orderByDesc('revision_date')
            ->paginate($perPage);
    }

    public function findSafetyDataSheetOrFail(int $id): SafetyDataSheet
    {
        return SafetyDataSheet::with('sections')->findOrFail($id);
    }

    /**
     * Transport regulations of a product, for one transport mode when given.
     */
    public function listTransportRegulations(int $productId, ?string $mode): Collection
    {
        return HazmatTransportRegulation::forProduct($productId)
            ->when($mode, fn ($q, $m) => $q->forMode($m))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTransportRegulation(array $data): HazmatTransportRegulation
    {
        return HazmatTransportRegulation::create($data);
    }
}
