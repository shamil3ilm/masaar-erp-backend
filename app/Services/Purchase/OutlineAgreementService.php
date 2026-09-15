<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\OutlineAgreement;
use App\Models\Purchase\OutlineAgreementItem;
use App\Models\Purchase\OutlineAgreementRelease;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class OutlineAgreementService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = OutlineAgreement::where('organization_id', $orgId)
            ->with(['vendor', 'items']);

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['agreement_type'])) {
            $query->where('agreement_type', $filters['agreement_type']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    /**
     * An outline agreement of the organization, with the given relations loaded.
     *
     * @param  list<string>  $with
     */
    public function find(int $orgId, int $id, array $with = []): OutlineAgreement
    {
        return OutlineAgreement::where('organization_id', $orgId)
            ->with($with)
            ->findOrFail($id);
    }

    public function create(int $orgId, array $data): OutlineAgreement
    {
        return OutlineAgreement::create(array_merge($data, [
            'organization_id' => $orgId,
            'created_by'      => Auth::id(),
        ]));
    }

    public function update(OutlineAgreement $agreement, array $data): OutlineAgreement
    {
        $agreement->update($data);

        return $agreement->fresh();
    }

    public function delete(OutlineAgreement $agreement): void
    {
        $agreement->delete();
    }

    public function addItem(OutlineAgreement $agreement, array $data): OutlineAgreementItem
    {
        return OutlineAgreementItem::create(array_merge($data, [
            'organization_id'      => $agreement->organization_id,
            'outline_agreement_id' => $agreement->id,
        ]));
    }

    /**
     * One of the agreement's items.
     */
    public function findItem(OutlineAgreement $agreement, int $itemId): OutlineAgreementItem
    {
        return $agreement->items()->findOrFail($itemId);
    }

    public function updateItem(OutlineAgreementItem $item, array $data): OutlineAgreementItem
    {
        $item->update($data);

        return $item->fresh();
    }

    /**
     * Record a release and add its quantity and value to the agreement and item totals.
     *
     * The agreement and item are locked while their totals are read and
     * written, so two releases at once both count, and the release row and
     * the totals are written together or not at all.
     */
    public function createRelease(OutlineAgreement $agreement, array $data): OutlineAgreementRelease
    {
        return $agreement->lockForTransition(function (OutlineAgreement $agreement) use ($data): OutlineAgreementRelease {
            $item = empty($data['outline_agreement_item_id'])
                ? null
                : $agreement->items()->lockForUpdate()->findOrFail($data['outline_agreement_item_id']);

            $release = OutlineAgreementRelease::create(array_merge($data, [
                'organization_id'      => $agreement->organization_id,
                'outline_agreement_id' => $agreement->id,
            ]));

            $quantity = (string) ($data['release_quantity'] ?? '0');
            $value = (string) ($data['release_value'] ?? '0');

            $agreement->update($this->addedToReleased($agreement, $quantity, $value));
            $item?->update($this->addedToReleased($item, $quantity, $value));

            return $release;
        });
    }

    /**
     * The agreement's releases with their item product and purchase order.
     *
     * @return Collection<int, OutlineAgreementRelease>
     */
    public function releasesOf(OutlineAgreement $agreement): Collection
    {
        return $agreement->releases()->with(['item.product', 'purchaseOrder'])->get();
    }

    /**
     * Activate a draft agreement, checked on the locked row.
     */
    public function activate(OutlineAgreement $agreement): OutlineAgreement
    {
        return $agreement->lockForTransition(function (OutlineAgreement $agreement): OutlineAgreement {
            if ($agreement->status !== OutlineAgreement::STATUS_DRAFT) {
                throw new \InvalidArgumentException('Only draft outline agreements can be activated.');
            }

            $agreement->update(['status' => OutlineAgreement::STATUS_ACTIVE]);

            return $agreement->fresh();
        });
    }

    public function expire(OutlineAgreement $agreement): OutlineAgreement
    {
        $agreement->update(['status' => OutlineAgreement::STATUS_EXPIRED]);

        return $agreement->fresh();
    }

    /**
     * Cancel a draft or active agreement, checked on the locked row.
     */
    public function cancel(OutlineAgreement $agreement): OutlineAgreement
    {
        return $agreement->lockForTransition(function (OutlineAgreement $agreement): OutlineAgreement {
            if (! in_array($agreement->status, [OutlineAgreement::STATUS_DRAFT, OutlineAgreement::STATUS_ACTIVE], true)) {
                throw new \InvalidArgumentException('Only draft or active outline agreements can be cancelled.');
            }

            $agreement->update(['status' => OutlineAgreement::STATUS_CANCELLED]);

            return $agreement->fresh();
        });
    }

    /**
     * Released quantity and value after adding a release to a row that tracks them.
     *
     * @return array{released_quantity: string, released_value: string}
     */
    private function addedToReleased(OutlineAgreement|OutlineAgreementItem $row, string $quantity, string $value): array
    {
        return [
            'released_quantity' => bcadd((string) ($row->released_quantity ?? '0'), $quantity, 4),
            'released_value'    => bcadd((string) ($row->released_value ?? '0'), $value, 4),
        ];
    }
}
