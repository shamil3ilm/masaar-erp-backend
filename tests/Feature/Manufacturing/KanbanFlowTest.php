<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\KanbanCard;
use App\Models\Manufacturing\KanbanControlCycle;
use App\Models\Manufacturing\KanbanSupplyArea;
use App\Services\Manufacturing\KanbanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Kanban supply areas, control cycles and cards: cards have no organization
 * column and are reached through their control cycle; a card is signalled
 * empty once, on the locked row.
 */
class KanbanFlowTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.manage',
            'manufacturing.planning.view',
        ]);
    }

    public function test_another_organizations_card_cannot_be_signalled_empty(): void
    {
        $card = $this->card($this->otherOrganization()->id, $this->foreignProduct(), $this->foreignWarehouse());

        $this->apiPost("/manufacturing/kanban/cards/{$card->id}/empty")->assertNotFound();

        $this->assertSame(KanbanCard::STATUS_FULL, $card->fresh()->status);
    }

    public function test_a_stale_copy_cannot_signal_a_card_empty_twice(): void
    {
        $card = $this->ownCard();
        $stale = KanbanCard::findOrFail($card->id);
        $service = app(KanbanService::class);

        $service->signalEmpty(KanbanCard::findOrFail($card->id));

        $this->expectException(\InvalidArgumentException::class);
        $service->signalEmpty($stale);
    }

    public function test_a_card_signalled_in_the_wrong_status_names_the_error_code(): void
    {
        $card = $this->ownCard();

        $this->apiPost("/manufacturing/kanban/cards/{$card->id}/full", ['quantity' => 5])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_supply_area_is_shown_and_deleted_by_its_route_key(): void
    {
        $area = KanbanSupplyArea::factory()->create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->ownWarehouse()->id,
        ]);

        $this->apiGet("/manufacturing/kanban/supply-areas/{$area->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $area->id);

        $this->apiDelete("/manufacturing/kanban/supply-areas/{$area->id}")->assertOk();

        $this->assertNull(KanbanSupplyArea::find($area->id));
    }

    public function test_another_organizations_warehouse_is_refused_on_a_supply_area(): void
    {
        $this->apiPost('/manufacturing/kanban/supply-areas', [
            'code' => 'SA-1',
            'name' => 'Line side',
            'warehouse_id' => $this->foreignWarehouse()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['warehouse_id']);
    }

    private function ownCard(): KanbanCard
    {
        return $this->card(
            $this->organization->id,
            Product::factory()->create(['organization_id' => $this->organization->id]),
            $this->ownWarehouse(),
        );
    }

    private function ownWarehouse(): Warehouse
    {
        return Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * A full card on a stock-transfer cycle without a source warehouse, so
     * signalling it empty creates no replenishment document.
     */
    private function card(int $organizationId, Product $product, Warehouse $warehouse): KanbanCard
    {
        $area = KanbanSupplyArea::factory()->create([
            'organization_id' => $organizationId,
            'warehouse_id' => $warehouse->id,
        ]);
        $cycle = KanbanControlCycle::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'product_id' => $product->id,
            'supply_area_id' => $area->id,
            'replenishment_strategy' => KanbanControlCycle::STRATEGY_STOCK_TRANSFER,
            'number_of_cards' => 1,
            'replenishment_quantity' => 5,
        ]);

        return KanbanCard::create([
            'control_cycle_id' => $cycle->id,
            'card_number' => 'KC-'.$cycle->id.'-001',
            'status' => KanbanCard::STATUS_FULL,
            'current_quantity' => 5,
        ]);
    }
}
