<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductionSchedulingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.view',
        ]);
    }

    // ─── gantt ────────────────────────────────────────────────────────────────

    public function test_gantt_returns_data(): void
    {
        $from = now()->format('Y-m-d');
        $to   = now()->addDays(30)->format('Y-m-d');

        $response = $this->getJson(
            "/api/v1/manufacturing/schedule/gantt?from={$from}&to={$to}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_gantt_reports_an_operation_duration_in_hours(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
        ]);
        WorkOrderOperation::factory()->create([
            'work_order_id'   => $workOrder->id,
            'scheduled_start' => now()->addDay()->setTime(8, 0),
            'scheduled_end'   => now()->addDay()->setTime(10, 30),
        ]);

        $from = now()->format('Y-m-d');
        $to   = now()->addDays(30)->format('Y-m-d');

        $this->getJson("/api/v1/manufacturing/schedule/gantt?from={$from}&to={$to}", $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.work_orders.0.operations.0.duration', 2.5);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/schedule/gantt')->assertUnauthorized();
    }
}
