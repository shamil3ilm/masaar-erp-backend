<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\OrganizationModule;
use App\Models\Inventory\StockLevel;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\EquipmentSparePart;
use App\Models\Maintenance\MaintenanceConditionRule;
use App\Models\Maintenance\MaintenanceMeasurement;
use App\Models\Maintenance\MaintenanceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the condition rule list and spare part availability, and keeps
 * measurements, rules and spare parts on the caller's own equipment, products
 * and stock.
 */
class ConditionMaintenanceTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'maintenance.measurements.manage',
            'maintenance.condition-rules.view',
            'maintenance.condition-rules.manage',
            'maintenance.spare-parts.manage',
        ]);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->equipment = Equipment::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_rules_filter_by_equipment_and_activity(): void
    {
        $active = $this->rule($this->equipment);
        $this->rule($this->equipment, ['is_active' => false]);
        $this->rule(Equipment::factory()->create(['organization_id' => $this->organization->id]));

        $response = $this->apiGet("/maintenance/condition-rules?equipment_id={$this->equipment->id}&is_active=1");

        $response->assertOk();
        $this->assertSame([$active->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_rule_is_not_found(): void
    {
        $theirs = $this->rule($this->foreignEquipment(), ['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/maintenance/condition-rules/{$theirs->id}")->assertNotFound();
        $this->apiDelete("/maintenance/condition-rules/{$theirs->id}")->assertNotFound();
    }

    public function test_a_rule_or_measurement_for_another_organizations_equipment_is_refused(): void
    {
        $theirs = $this->foreignEquipment();

        $this->apiPost('/maintenance/condition-rules', [
            'rule_name' => 'Vibration',
            'equipment_id' => $theirs->id,
            'measurement_point' => 'bearing',
            'condition_operator' => 'greater_than',
            'threshold_value' => 50,
            'trigger_action' => 'create_order',
            'maintenance_type' => 'inspection',
        ])->assertStatus(422)->assertJsonValidationErrors(['equipment_id']);

        $this->apiPost('/maintenance/measurements', [
            'equipment_id' => $theirs->id,
            'measurement_point' => 'bearing',
            'measurement_value' => 80,
        ])->assertStatus(422)->assertJsonValidationErrors(['equipment_id']);

        $this->assertSame(0, MaintenanceConditionRule::withoutGlobalScopes()->count());
        $this->assertSame(0, MaintenanceMeasurement::withoutGlobalScopes()->count());
    }

    public function test_a_measurement_is_evaluated_only_against_the_organizations_own_rules(): void
    {
        $this->rule($this->equipment, ['organization_id' => $this->otherOrganization()->id]);

        $this->apiPost('/maintenance/measurements', [
            'equipment_id' => $this->equipment->id,
            'measurement_point' => 'bearing',
            'measurement_value' => 80,
        ])->assertCreated()->assertJsonPath('data.threshold_breached', false);
        $this->assertSame(0, MaintenanceOrder::withoutGlobalScopes()->count());

        $ours = $this->rule($this->equipment);

        $this->apiPost('/maintenance/measurements', [
            'equipment_id' => $this->equipment->id,
            'measurement_point' => 'bearing',
            'measurement_value' => 80,
        ])->assertCreated()
            ->assertJsonPath('data.threshold_breached', true)
            ->assertJsonPath('data.triggered_rule_id', $ours->id);

        $order = MaintenanceOrder::sole();
        $this->assertSame($this->equipment->id, $order->equipment_id);
        $this->assertSame(MaintenanceOrder::TYPE_CORRECTIVE, $order->order_type);
    }

    public function test_spare_parts_of_another_organizations_equipment_are_not_found(): void
    {
        $theirs = $this->foreignEquipment();
        $product = $this->stockedProduct();

        $this->apiGet("/maintenance/equipment/{$theirs->id}/spare-parts")->assertNotFound();
        $this->apiGet("/maintenance/equipment/{$theirs->id}/spare-parts/availability")->assertNotFound();
        $this->apiPost("/maintenance/equipment/{$theirs->id}/spare-parts", [
            'product_id' => $product->id,
            'recommended_stock_qty' => 2,
        ])->assertNotFound();

        $this->assertSame(0, EquipmentSparePart::count());
    }

    public function test_a_spare_part_for_another_organizations_product_is_refused(): void
    {
        $this->apiPost("/maintenance/equipment/{$this->equipment->id}/spare-parts", [
            'product_id' => $this->foreignProduct()->id,
            'recommended_stock_qty' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id']);
    }

    public function test_availability_counts_only_the_organizations_stock(): void
    {
        $product = $this->stockedProduct();
        $this->stockLevel($product, $this->warehouse(), 5);
        StockLevel::create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->foreignWarehouse()->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'average_cost' => 1,
            'total_value' => 100,
        ]);

        $this->apiPost("/maintenance/equipment/{$this->equipment->id}/spare-parts", [
            'product_id' => $product->id,
            'recommended_stock_qty' => 10,
            'is_critical' => true,
        ])->assertCreated()
            ->assertJsonPath('data.current_stock_qty', '5.0000')
            ->assertJsonPath('data.product.id', $product->id);

        $this->apiGet("/maintenance/equipment/{$this->equipment->id}/spare-parts")
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $product->id);

        $this->apiGet("/maintenance/equipment/{$this->equipment->id}/spare-parts/availability")
            ->assertOk()
            ->assertJsonPath('data.equipment_id', $this->equipment->id)
            ->assertJsonPath('data.total_parts', 1)
            ->assertJsonPath('data.shortfall_count', 1)
            ->assertJsonPath('data.critical_shortfall', 1)
            ->assertJsonPath('data.parts.0.current_stock_qty', 5)
            ->assertJsonPath('data.parts.0.deficit', 5);
    }

    private function rule(Equipment $equipment, array $overrides = []): MaintenanceConditionRule
    {
        return MaintenanceConditionRule::create(array_merge([
            'organization_id' => $this->organization->id,
            'rule_name' => 'Bearing vibration',
            'equipment_id' => $equipment->id,
            'measurement_point' => 'bearing',
            'condition_operator' => MaintenanceConditionRule::OPERATOR_GREATER_THAN,
            'threshold_value' => 50,
            'trigger_action' => MaintenanceConditionRule::ACTION_CREATE_ORDER,
            'maintenance_type' => MaintenanceConditionRule::TYPE_INSPECTION,
            'is_active' => true,
        ], $overrides));
    }

    private function foreignEquipment(): Equipment
    {
        return Equipment::factory()->create(['organization_id' => $this->otherOrganization()->id]);
    }
}
