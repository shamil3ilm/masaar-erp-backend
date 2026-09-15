<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ApprovedVendorList;
use App\Models\Manufacturing\SupplierNcrRecord;
use App\Models\Manufacturing\SupplierQualityRating;
use App\Models\Sales\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Supplier quality ratings, the approved vendor list and supplier
 * nonconformance reports. The supplier is always shown by reference, so its
 * contact and tax details are never embedded.
 */
class SupplierQualityService
{
    public function listRatings(int $organizationId): LengthAwarePaginator
    {
        return SupplierQualityRating::where('organization_id', $organizationId)
            ->with($this->supplierReference())
            ->paginate(20);
    }

    public function createRating(int $organizationId, int $evaluatorId, array $data): SupplierQualityRating
    {
        $rating = SupplierQualityRating::create([
            ...$data,
            'uuid'            => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'evaluated_by_id' => $evaluatorId,
        ]);

        return $rating->load($this->supplierReference());
    }

    public function listApprovedVendors(int $organizationId): LengthAwarePaginator
    {
        return ApprovedVendorList::where('organization_id', $organizationId)
            ->with([$this->supplierReference(), 'product'])
            ->paginate(20);
    }

    public function approveVendor(int $organizationId, array $data): ApprovedVendorList
    {
        $entry = ApprovedVendorList::create([
            ...$data,
            'uuid'            => (string) Str::uuid(),
            'organization_id' => $organizationId,
        ]);

        return $entry->load([$this->supplierReference(), 'product']);
    }

    public function listNcrs(int $organizationId): LengthAwarePaginator
    {
        return SupplierNcrRecord::where('organization_id', $organizationId)
            ->with([$this->supplierReference(), 'product'])
            ->paginate(20);
    }

    public function createNcr(int $organizationId, array $data): SupplierNcrRecord
    {
        $ncr = SupplierNcrRecord::create([
            ...$data,
            'uuid'            => (string) Str::uuid(),
            'organization_id' => $organizationId,
        ]);

        return $ncr->load([$this->supplierReference(), 'product']);
    }

    /**
     * One of the organization's NCRs; a missing id is a 404.
     */
    public function findNcr(int $organizationId, int $id): SupplierNcrRecord
    {
        return SupplierNcrRecord::where('organization_id', $organizationId)->findOrFail($id);
    }

    public function closeNcr(SupplierNcrRecord $ncr, string $disposition): SupplierNcrRecord
    {
        $ncr->update([
            'disposition' => $disposition,
            'status'      => 'closed',
            'closed_date' => now()->toDateString(),
        ]);

        return $ncr;
    }

    private function supplierReference(): string
    {
        return 'supplier:'.implode(',', Contact::REFERENCE_COLUMNS);
    }
}
