<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PurchaseRequisitionLine;
use App\Models\Purchase\VendorProductPricing;
use App\Models\Purchase\VendorSourceList;
use App\Models\Sales\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SourceListService
{
    /**
     * Return the single preferred vendor for a product, or null when none is
     * configured.
     */
    public function getPreferredVendor(int $productId): ?Contact
    {
        $pricing = VendorProductPricing::forProduct($productId)
            ->preferredVendors()
            ->valid()
            ->with('vendor')
            ->first();

        if ($pricing) {
            return $pricing->vendor;
        }

        // Fall back to the highest-priority non-blocked source-list entry.
        $entry = VendorSourceList::forProduct($productId)
            ->active()
            ->byPriority()
            ->with('vendor')
            ->first();

        return $entry?->vendor;
    }

    /**
     * Return all vendors available for a product, ordered by source-list
     * priority (ascending = most preferred first).
     */
    public function getVendorsForProduct(int $productId): Collection
    {
        return VendorSourceList::forProduct($productId)
            ->active()
            ->byPriority()
            ->with(['vendor', 'pricingRecord'])
            ->get()
            ->map(fn (VendorSourceList $entry) => $entry->vendor)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Retrieve the pricing record for a specific product–vendor pair,
     * returning only currently valid records.
     */
    public function getPricingRecord(int $productId, int $vendorId): ?VendorProductPricing
    {
        return VendorProductPricing::forProduct($productId)
            ->forVendor($vendorId)
            ->valid()
            ->orderByDesc('is_preferred_vendor')
            ->first();
    }

    /**
     * Return true when the vendor appears in an active source-list entry for
     * the given product.
     */
    public function isApprovedVendor(int $productId, int $vendorId): bool
    {
        return VendorSourceList::forProduct($productId)
            ->active()
            ->where('vendor_id', $vendorId)
            ->exists();
    }

    /**
     * Auto-select the best vendor for each product in the supplied list.
     *
     * @param  int[]  $productIds
     * @return array<int, int>  [product_id => vendor_id]
     */
    public function autoSelectVendors(array $productIds): array
    {
        $result = [];

        foreach ($productIds as $productId) {
            $vendor = $this->getPreferredVendor($productId);

            if ($vendor !== null) {
                $result[$productId] = $vendor->id;
            }
        }

        return $result;
    }

    /**
     * Suggest the best vendor for a purchase-requisition line.
     *
     * Returns vendor, pricing record, unit price, and lead time — or null
     * when no vendor could be found.
     *
     * @return array{vendor: Contact, pricing: VendorProductPricing|null, unit_price: float|null, lead_time_days: int|null}|null
     */
    public function suggestVendorForRequisitionLine(PurchaseRequisitionLine $line): ?array
    {
        $productId = $line->product_id;

        if ($productId === null) {
            return null;
        }

        $vendor = $this->getPreferredVendor($productId);

        if ($vendor === null) {
            return null;
        }

        $pricing = $this->getPricingRecord($productId, $vendor->id);

        return [
            'vendor'         => $vendor,
            'pricing'        => $pricing,
            'unit_price'     => $pricing !== null ? (float) $pricing->unit_price : null,
            'lead_time_days' => $pricing !== null ? $pricing->lead_time_days : null,
        ];
    }

    // -------------------------------------------------------------------------
    // CRUD helpers
    // -------------------------------------------------------------------------

    /**
     * A page of pricing records, newest first, with vendor and product loaded.
     *
     * @param  array<string, mixed>  $filters  product_id, vendor_id, preferred_only, valid_only
     */
    public function listPricingRecords(array $filters, int $perPage): LengthAwarePaginator
    {
        return VendorProductPricing::with(['vendor', 'product:id,name,sku'])
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->forProduct((int) $id))
            ->when($filters['vendor_id'] ?? null, fn ($q, $id) => $q->forVendor((int) $id))
            ->when(($filters['preferred_only'] ?? null) === 'true', fn ($q) => $q->preferredVendors())
            ->when(($filters['valid_only'] ?? null) === 'true', fn ($q) => $q->valid())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A pricing record of the organization, or null when there is none with that id.
     *
     * @param  list<string>  $with
     */
    public function findPricingRecord(int $id, array $with = []): ?VendorProductPricing
    {
        return VendorProductPricing::with($with)->find($id);
    }

    /**
     * The product's valid pricing records, preferred vendors first, then cheapest.
     *
     * @return Collection<int, VendorProductPricing>
     */
    public function validPricingRecordsForProduct(int $productId): Collection
    {
        return VendorProductPricing::forProduct($productId)
            ->valid()
            ->with(['vendor'])
            ->orderByDesc('is_preferred_vendor')
            ->orderBy('unit_price')
            ->get();
    }

    public function deletePricingRecord(VendorProductPricing $record): void
    {
        $record->delete();
    }

    /**
     * A page of source list entries by priority, with vendor, product and pricing loaded.
     *
     * @param  array<string, mixed>  $filters  product_id, vendor_id, active_only
     */
    public function listSourceListEntries(array $filters, int $perPage): LengthAwarePaginator
    {
        return VendorSourceList::with([
            'vendor',
            'product:id,name,sku',
            'pricingRecord:id,uuid,unit_price,currency_code,lead_time_days',
        ])
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->forProduct((int) $id))
            ->when($filters['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', (int) $id))
            ->when(($filters['active_only'] ?? null) === 'true', fn ($q) => $q->active())
            ->byPriority()
            ->paginate($perPage);
    }

    /**
     * A source list entry of the organization, or null when there is none with that id.
     *
     * @param  list<string>  $with
     */
    public function findSourceListEntry(int $id, array $with = []): ?VendorSourceList
    {
        return VendorSourceList::with($with)->find($id);
    }

    public function deleteSourceListEntry(VendorSourceList $entry): void
    {
        $entry->delete();
    }

    public function createPricingRecord(array $data): VendorProductPricing
    {
        return DB::transaction(function () use ($data): VendorProductPricing {
            // Only one preferred pricing record per product–vendor pair.
            if (!empty($data['is_preferred_vendor'])) {
                VendorProductPricing::forProduct($data['product_id'])
                    ->forVendor($data['vendor_id'])
                    ->where('is_preferred_vendor', true)
                    ->update(['is_preferred_vendor' => false]);
            }

            return VendorProductPricing::create($data);
        });
    }

    public function updatePricingRecord(VendorProductPricing $record, array $data): VendorProductPricing
    {
        return DB::transaction(function () use ($record, $data): VendorProductPricing {
            if (!empty($data['is_preferred_vendor'])) {
                VendorProductPricing::forProduct($record->product_id)
                    ->forVendor($record->vendor_id)
                    ->where('id', '!=', $record->id)
                    ->where('is_preferred_vendor', true)
                    ->update(['is_preferred_vendor' => false]);
            }

            $record->update($data);

            return $record->fresh();
        });
    }

    public function createSourceListEntry(array $data): VendorSourceList
    {
        return VendorSourceList::create($data);
    }

    public function updateSourceListEntry(VendorSourceList $entry, array $data): VendorSourceList
    {
        $entry->update($data);

        return $entry->fresh();
    }
}
