<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\SaDeliverySchedule;
use App\Models\Purchase\SchedulingAgreement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SchedulingAgreementService
{
    public function list(int $orgId, array $filters = []): LengthAwarePaginator
    {
        $query = SchedulingAgreement::where('organization_id', $orgId)
            ->with(['vendor', 'product']);

        if (!empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    /**
     * A scheduling agreement of the organization, with the given relations loaded.
     *
     * @param  list<string>  $with
     */
    public function find(int $orgId, int $id, array $with = []): SchedulingAgreement
    {
        return SchedulingAgreement::where('organization_id', $orgId)
            ->with($with)
            ->findOrFail($id);
    }

    public function create(int $orgId, array $data): SchedulingAgreement
    {
        return SchedulingAgreement::create(array_merge($data, [
            'organization_id' => $orgId,
        ]));
    }

    public function update(SchedulingAgreement $agreement, array $data): SchedulingAgreement
    {
        $agreement->update($data);

        return $agreement->fresh();
    }

    public function delete(SchedulingAgreement $agreement): void
    {
        $agreement->delete();
    }

    public function addScheduleLine(SchedulingAgreement $agreement, array $data): SaDeliverySchedule
    {
        return SaDeliverySchedule::create(array_merge($data, [
            'organization_id'        => $agreement->organization_id,
            'scheduling_agreement_id' => $agreement->id,
        ]));
    }

    /**
     * One of the agreement's schedule lines.
     */
    public function findScheduleLine(SchedulingAgreement $agreement, int $lineId): SaDeliverySchedule
    {
        return $agreement->schedules()->findOrFail($lineId);
    }

    /**
     * The agreement's schedule lines, earliest date first.
     *
     * @return Collection<int, SaDeliverySchedule>
     */
    public function schedulesOf(SchedulingAgreement $agreement): Collection
    {
        return $agreement->schedules()->orderBy('schedule_date')->get();
    }

    public function updateScheduleLine(SaDeliverySchedule $line, array $data): SaDeliverySchedule
    {
        $line->update($data);

        return $line->fresh();
    }

    /**
     * Count a delivery against a schedule line and its agreement.
     *
     * The line and the agreement are locked while their quantities are read
     * and written: two deliveries counted from the same stale received
     * quantity would otherwise lose one of them.
     */
    public function receiveDelivery(SaDeliverySchedule $line, float $quantity): SaDeliverySchedule
    {
        return $line->lockForTransition(function (SaDeliverySchedule $line) use ($quantity): SaDeliverySchedule {
            $received = bcadd((string) ($line->received_quantity ?? '0'), (string) $quantity, 4);

            $line->update([
                'received_quantity' => $received,
                'status' => bccomp($received, (string) $line->scheduled_quantity, 4) >= 0
                    ? SaDeliverySchedule::STATUS_COMPLETE
                    : SaDeliverySchedule::STATUS_PARTIAL,
            ]);

            $agreement = SchedulingAgreement::query()->lockForUpdate()->findOrFail($line->scheduling_agreement_id);

            $agreement->update([
                'released_quantity' => bcadd((string) ($agreement->released_quantity ?? '0'), (string) $quantity, 4),
            ]);

            return $line->fresh();
        });
    }

    /**
     * Match MRP requirements to active scheduling agreements and generate schedule lines.
     *
     * @param  int    $productId
     * @param  array  $requirements  Each entry: ['date' => 'Y-m-d', 'quantity' => float, 'organization_id' => int]
     * @return array  Created SaDeliverySchedule records
     */
    public function generateFromMrp(int $productId, array $requirements): array
    {
        $created = [];

        foreach ($requirements as $req) {
            $orgId = (int) ($req['organization_id'] ?? 0);

            $agreement = SchedulingAgreement::where('organization_id', $orgId)
                ->where('product_id', $productId)
                ->where('status', SchedulingAgreement::STATUS_ACTIVE)
                ->where('valid_from', '<=', $req['date'])
                ->where(function ($q) use ($req) {
                    $q->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $req['date']);
                })
                ->first();

            if ($agreement === null) {
                continue;
            }

            $created[] = $this->addScheduleLine($agreement, [
                'schedule_date'      => $req['date'],
                'scheduled_quantity' => $req['quantity'],
            ]);
        }

        return $created;
    }
}
