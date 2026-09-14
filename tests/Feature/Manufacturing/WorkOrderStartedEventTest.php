<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Events\Manufacturing\WorkOrderStarted;
use App\Models\Core\Notification;
use App\Models\Manufacturing\WorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class WorkOrderStartedEventTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['manufacturing.workorders.start']);
        $this->setUpOpenFiscalPeriod();

        $this->operator = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_starting_a_work_order_dispatches_work_order_started(): void
    {
        Event::fake([WorkOrderStarted::class]);

        $workOrder = $this->releasedWorkOrder();

        $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/start")->assertOk();

        Event::assertDispatched(WorkOrderStarted::class, fn (WorkOrderStarted $event) => $event->workOrder->id === $workOrder->id
            && $event->workOrder->status === WorkOrder::STATUS_IN_PROGRESS);
    }

    public function test_work_order_start_notifies_the_operator_and_creator_with_the_planned_quantity(): void
    {
        $workOrder = $this->releasedWorkOrder();

        $this->apiPost("/manufacturing/work-orders/{$workOrder->uuid}/start")->assertOk();

        foreach ([$this->operator->id, $this->user->id] as $userId) {
            $notification = Notification::where('user_id', $userId)
                ->where('type', 'work_order_started')
                ->firstOrFail();

            $this->assertEquals(40.0, (float) $notification->data['quantity']);
        }
    }

    private function releasedWorkOrder(): WorkOrder
    {
        return WorkOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'status' => WorkOrder::STATUS_RELEASED,
            'planned_quantity' => 40,
            'assigned_to' => $this->operator->id,
            'created_by' => $this->user->id,
        ]);
    }
}
