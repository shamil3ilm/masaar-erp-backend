<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\CycleCountLine;
use App\Models\Inventory\CycleCountPlan;
use App\Models\Inventory\CycleCountSession;
use App\Models\Inventory\StockLevel;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CycleCountService
{
    /**
     * Cycle count plans of the given organization with their warehouse.
     */
    public function paginatePlans(int $organizationId, int $perPage): LengthAwarePaginator
    {
        return CycleCountPlan::where('organization_id', $organizationId)
            ->with('warehouse')
            ->paginate($perPage);
    }

    /**
     * @param  array{plan_name: string, warehouse_id: int, count_frequency: string, products_per_day?: int|null, scheduled_date?: string|null}  $data
     */
    public function createPlan(int $organizationId, array $data): CycleCountPlan
    {
        return CycleCountPlan::create([
            ...$data,
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
        ]);
    }

    public function findPlanOrFail(int $organizationId, int $planId): CycleCountPlan
    {
        return CycleCountPlan::where('organization_id', $organizationId)->findOrFail($planId);
    }

    /**
     * @param  list<string>  $with
     */
    public function findSessionOrFail(int $organizationId, int $sessionId, array $with = []): CycleCountSession
    {
        return CycleCountSession::where('organization_id', $organizationId)
            ->with($with)
            ->findOrFail($sessionId);
    }

    /**
     * Creates a session with a line for each stock level in the plan's
     * warehouse, in one transaction, so a failure part way through leaves no
     * session holding only some of its lines.
     */
    public function createSession(CycleCountPlan $plan, int $counterId, Carbon $date): CycleCountSession
    {
        return DB::transaction(function () use ($plan, $counterId, $date): CycleCountSession {
            $session = CycleCountSession::create([
                'uuid' => Str::uuid(),
                'organization_id' => $plan->organization_id,
                'plan_id' => $plan->id,
                'warehouse_id' => $plan->warehouse_id,
                'session_date' => $date->toDateString(),
                'counted_by' => $counterId,
                'status' => CycleCountSession::STATUS_OPEN,
            ]);

            // Seed lines from current stock levels
            $stockLevels = StockLevel::where('warehouse_id', $plan->warehouse_id)->get();
            foreach ($stockLevels as $stock) {
                CycleCountLine::create([
                    'uuid' => Str::uuid(),
                    'cycle_count_session_id' => $session->id,
                    'product_id' => $stock->product_id,
                    'warehouse_location_id' => $stock->location_id ?? null,
                    'system_quantity' => $stock->quantity ?? 0,
                    'status' => 'pending',
                ]);
            }

            return $session->load('lines');
        });
    }

    /**
     * Records a count on a line of the session. The session is locked while
     * the line is written, so a count cannot land after the session is posted.
     * A line that belongs to another session is not found.
     */
    public function recordCountInSession(CycleCountSession $session, int $lineId, float $quantity): CycleCountLine
    {
        return $session->lockForTransition(function (CycleCountSession $session) use ($lineId, $quantity): CycleCountLine {
            $this->assertOpen($session);

            $line = CycleCountLine::where('cycle_count_session_id', $session->id)->findOrFail($lineId);
            $this->recordCount($line, $quantity);

            return $line->fresh();
        });
    }

    public function recordCount(CycleCountLine $line, float $quantity): void
    {
        $variance = $quantity - (float) $line->system_quantity;
        $variancePct = $line->system_quantity > 0
            ? abs($variance / (float) $line->system_quantity * 100)
            : ($quantity > 0 ? 100 : 0);

        $line->update([
            'counted_quantity' => $quantity,
            'variance_percentage' => $variancePct,
            'recount_required' => $variancePct > 5,
            'status' => 'counted',
        ]);
    }

    /**
     * Marks the session posted and returns the variances of its counted lines.
     * Runs on the locked session, so a second post waits and is then refused.
     *
     * @return array<int, array<string, mixed>>
     */
    public function postSession(CycleCountSession $session): array
    {
        return $session->lockForTransition(function (CycleCountSession $session): array {
            $this->assertOpen($session);

            $variances = $this->calculateVariances($session);
            $session->update(['status' => CycleCountSession::STATUS_POSTED, 'completed_at' => now()]);

            return $variances;
        });
    }

    public function calculateVariances(CycleCountSession $session): array
    {
        return $session->lines()
            ->whereNotNull('counted_quantity')
            ->get()
            ->map(fn ($line) => [
                'product_id' => $line->product_id,
                'system_qty' => $line->system_quantity,
                'counted_qty' => $line->counted_quantity,
                'variance' => (float) $line->counted_quantity - (float) $line->system_quantity,
                'variance_pct' => $line->variance_percentage,
                'recount_required' => $line->recount_required,
            ])
            ->toArray();
    }

    public function getAbcAnalysis(int $warehouseId): array
    {
        return StockLevel::where('warehouse_id', $warehouseId)
            ->with('product')
            ->orderByDesc('quantity')
            ->limit(200)
            ->get()
            ->map(fn ($s, $i) => [
                'product_id' => $s->product_id,
                'abc_class' => $i < 20 ? 'A' : ($i < 60 ? 'B' : 'C'),
                'quantity' => $s->quantity,
            ])
            ->toArray();
    }

    private function assertOpen(CycleCountSession $session): void
    {
        if ($session->status === CycleCountSession::STATUS_POSTED) {
            throw new \InvalidArgumentException('The cycle count session is already posted.');
        }
    }
}
