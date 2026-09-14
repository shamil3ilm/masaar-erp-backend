<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\RebateMaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists, creates and edits rebate masters, the agreements under which a
 * customer earns a rebate on its purchases. Accruing and settling them belong
 * to RebateAccrualService and RebateSettlementService.
 */
class RebateMasterService
{
    /**
     * Rebate masters of the given organization with the reference columns of
     * their customer, latest start first, narrowed by the filters that are set.
     *
     * @param  array{status?: mixed, contact_id?: mixed}  $filters
     */
    public function list(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return RebateMaster::where('organization_id', $organizationId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['contact_id'] ?? null, fn ($q, $contactId) => $q->where('contact_id', (int) $contactId))
            ->with('customer:'.implode(',', Contact::REFERENCE_COLUMNS))
            ->orderByDesc('valid_from')
            ->paginate($perPage);
    }

    /**
     * A rebate master with the reference columns of its customer and its accruals.
     */
    public function loadDetails(RebateMaster $rebate): RebateMaster
    {
        return $rebate->load(['customer:'.implode(',', Contact::REFERENCE_COLUMNS), 'accruals']);
    }

    /**
     * Create an active rebate master for the given organization.
     *
     * @param  array<string, mixed>  $data  validated rebate master fields
     */
    public function create(int $organizationId, array $data): RebateMaster
    {
        return RebateMaster::create(array_merge($data, [
            'organization_id' => $organizationId,
            'status' => RebateMaster::STATUS_ACTIVE,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data  validated rebate master fields
     */
    public function update(RebateMaster $rebate, array $data): RebateMaster
    {
        $rebate->update($data);

        return $rebate;
    }
}
