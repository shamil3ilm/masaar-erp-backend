<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\CustomerMaterialInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer material info: the number and description a customer uses for one
 * of the organization's products. The contact is embedded by its reference
 * columns only, never with its tax number or email.
 */
class CustomerMaterialInfoService
{
    /**
     * Records of the current organization, latest first, narrowed by the
     * filters that are set; search matches the customer's material number or
     * description.
     *
     * @param  array{contact_id?: ?int, product_id?: ?int, active_only?: bool, search?: ?string}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return CustomerMaterialInfo::with([$this->contactReference(), 'product'])
            ->latest()
            ->when(isset($filters['contact_id']), fn ($q) => $q->forContact($filters['contact_id']))
            ->when(isset($filters['product_id']), fn ($q) => $q->forProduct($filters['product_id']))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $term = $filters['search'];
                $q->where(function ($q) use ($term) {
                    $q->where('customer_material_number', 'like', "%{$term}%")
                        ->orWhere('customer_material_description', 'like', "%{$term}%");
                });
            })
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  validated fields; the contact and product belong to the organization
     */
    public function create(int $organizationId, array $data): CustomerMaterialInfo
    {
        return CustomerMaterialInfo::create(array_merge($data, ['organization_id' => $organizationId]))
            ->load([$this->contactReference(), 'product']);
    }

    public function details(CustomerMaterialInfo $info): CustomerMaterialInfo
    {
        return $info->load([$this->contactReference(), 'product']);
    }

    /**
     * @param  array<string, mixed>  $data  validated fields
     */
    public function update(CustomerMaterialInfo $info, array $data): CustomerMaterialInfo
    {
        $info->update($data);

        return $info->fresh([$this->contactReference(), 'product']);
    }

    public function delete(CustomerMaterialInfo $info): void
    {
        $info->delete();
    }

    /**
     * The active record of a contact for a product or a customer material
     * number, with its product, or null when there is none.
     */
    public function lookup(int $contactId, ?int $productId, ?string $customerMaterialNumber): ?CustomerMaterialInfo
    {
        return CustomerMaterialInfo::with(['product'])
            ->forContact($contactId)
            ->active()
            ->when($productId !== null, fn ($q) => $q->forProduct($productId))
            ->when($customerMaterialNumber !== null, fn ($q) => $q->where('customer_material_number', $customerMaterialNumber))
            ->first();
    }

    private function contactReference(): string
    {
        return 'contact:'.implode(',', Contact::REFERENCE_COLUMNS);
    }
}
