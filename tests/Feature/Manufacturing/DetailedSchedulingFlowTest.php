<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\SchedulingBoard;
use App\Models\Manufacturing\SchedulingOperation;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Scheduling operations link only the organization's own boards, work orders
 * and process orders, and are found only within the organization.
 */
class DetailedSchedulingFlowTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private WorkCenter $workCenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.manage',
            'manufacturing.planning.view',
        ]);

        $this->workCenter = WorkCenter::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_another_organizations_work_order_is_refused_on_an_operation(): void
    {
        $theirWorkOrder = WorkOrder::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiPost('/manufacturing/detailed-scheduling/operations', $this->operationPayload([
            'work_order_id' => $theirWorkOrder->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['work_order_id']);
    }

    public function test_another_organizations_board_is_refused_on_an_operation(): void
    {
        $theirBoard = SchedulingBoard::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'name' => 'Their board',
        ]);

        $this->apiPost('/manufacturing/detailed-scheduling/operations', $this->operationPayload([
            'scheduling_board_id' => $theirBoard->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['scheduling_board_id']);
    }

    public function test_another_organizations_operation_is_not_found(): void
    {
        $theirs = SchedulingOperation::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'work_center_id' => WorkCenter::factory()->create(['organization_id' => $this->otherOrganization()->id])->id,
            'operation_number' => 10,
            'description' => 'Their operation',
            'planned_start' => now(),
            'planned_finish' => now()->addHour(),
            'duration_minutes' => 60,
        ]);

        $this->apiPut("/manufacturing/detailed-scheduling/operations/{$theirs->id}", ['description' => 'x'])->assertNotFound();
        $this->apiPost("/manufacturing/detailed-scheduling/operations/{$theirs->id}/reschedule", [
            'new_start' => now()->addDay()->toDateTimeString(),
        ])->assertNotFound();

        $this->assertSame('Their operation', $theirs->fresh()->description);
    }

    private function operationPayload(array $overrides = []): array
    {
        return array_merge([
            'work_center_id' => $this->workCenter->id,
            'operation_number' => 10,
            'description' => 'Cut',
            'planned_start' => now()->toDateTimeString(),
            'planned_finish' => now()->addHours(2)->toDateTimeString(),
            'duration_minutes' => 120,
        ], $overrides);
    }
}
