<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\VendorContract;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VendorContractService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * A page of the organization's vendor contracts, newest first.
     *
     * @param  array<string, mixed>  $filters  status, contact_id
     */
    public function list(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return VendorContract::where('organization_id', $organizationId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['contact_id'] ?? null, fn ($q, $contactId) => $q->where('contact_id', $contactId))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A vendor contract of the organization, with the given relations loaded.
     *
     * @param  list<string>  $with
     */
    public function find(int $organizationId, int $id, array $with = []): VendorContract
    {
        return VendorContract::where('organization_id', $organizationId)
            ->with($with)
            ->findOrFail($id);
    }

    /**
     * Create a new vendor contract with optional line items.
     *
     * Expected keys in $data:
     *   organization_id, contact_id, title, description?,
     *   contract_type?, currency_code?, total_value?, start_date,
     *   end_date?, auto_renew?, renewal_notice_days?, payment_terms?,
     *   signed_at?, notes?, created_by?,
     *   items[] => [product_id?, description, unit_price, quantity?, unit_of_measure?]
     */
    public function create(array $data): VendorContract
    {
        return DB::transaction(function () use ($data) {
            $data['contract_number'] = $data['contract_number']
                ?? $this->numberGenerator->generate('VCON');

            $data['status'] = VendorContract::STATUS_DRAFT;

            $items = $data['items'] ?? [];
            unset($data['items']);

            $contract = VendorContract::create($data);

            foreach ($items as $item) {
                $contract->items()->create($item);
            }

            return $contract->load('items');
        });
    }

    /**
     * Activate a draft contract, checked on the locked row.
     */
    public function activate(VendorContract $contract): VendorContract
    {
        return $contract->lockForTransition(function (VendorContract $contract): VendorContract {
            if ($contract->status !== VendorContract::STATUS_DRAFT) {
                throw new RuntimeException(
                    "Only draft contracts can be activated. Current status: {$contract->status}."
                );
            }

            $contract->update(['status' => VendorContract::STATUS_ACTIVE]);

            return $contract->refresh();
        });
    }

    /**
     * Terminate an active contract with a reason, checked on the locked row.
     *
     * Vendor contracts have no column for the reason, so it is appended to the notes.
     */
    public function terminate(VendorContract $contract, string $reason): VendorContract
    {
        return $contract->lockForTransition(function (VendorContract $contract) use ($reason): VendorContract {
            if ($contract->status !== VendorContract::STATUS_ACTIVE) {
                throw new RuntimeException(
                    "Only active contracts can be terminated. Current status: {$contract->status}."
                );
            }

            $contract->update([
                'status'        => VendorContract::STATUS_TERMINATED,
                'terminated_at' => now()->toDateString(),
                'notes'         => trim(($contract->notes ?? '')."\n\nTerminated: {$reason}"),
            ]);

            return $contract->refresh();
        });
    }

    /**
     * Return contracts for an organization that expire within $days days.
     */
    public function getExpiringContracts(int $organizationId, int $days = 30): Collection
    {
        return VendorContract::where('organization_id', $organizationId)
            ->where('status', VendorContract::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '>=', now()->toDateString())
            ->where('end_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('end_date')
            ->get();
    }
}
