<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\CycleCountLine;
use App\Models\Inventory\CycleCountPlan;
use App\Models\Inventory\CycleCountSession;
use App\Services\Inventory\CycleCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * A cycle count session is created with all its lines or not at all.
 */
class CycleCountSessionTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    public function test_a_session_whose_lines_cannot_be_created_is_not_kept(): void
    {
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $warehouse = $this->warehouse();
        $this->stockLevel($this->stockedProduct(), $warehouse, 10);
        $this->stockLevel($this->stockedProduct(), $warehouse, 5);

        $plan = CycleCountPlan::forceCreate([
            'organization_id' => $this->organization->id,
            'plan_name' => 'Weekly A items',
            'warehouse_id' => $warehouse->id,
            'count_frequency' => 'A',
            'status' => 'active',
        ]);

        $created = 0;
        CycleCountLine::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new \RuntimeException('The second line could not be written.');
            }
        });

        try {
            app(CycleCountService::class)->createSession($plan, $this->user->id, now());
            $this->fail('Creating the session was expected to fail.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, CycleCountSession::count());
        $this->assertSame(0, CycleCountLine::count());
    }
}
