<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceFaultCode;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenanceRca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the fault code and root cause analysis lists, and keeps an analysis on
 * the caller's own order, equipment, fault code and assignee.
 */
class FaultAnalysisTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    private Equipment $equipment;

    private MaintenanceOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'maintenance.fault-codes.view',
            'maintenance.fault-codes.manage',
            'maintenance.rca.manage',
        ]);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
        $this->equipment = Equipment::factory()->create(['organization_id' => $this->organization->id]);
        $this->order = MaintenanceOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'equipment_id' => $this->equipment->id,
        ]);
    }

    public function test_fault_codes_list_only_the_organizations_active_codes(): void
    {
        $this->faultCode($this->organization, 'FC-1');
        $this->faultCode($this->organization, 'FC-2', false);
        $this->faultCode($this->other, 'FC-3');

        $response = $this->apiGet('/maintenance/fault-codes');

        $response->assertOk();
        $this->assertSame(['FC-1'], array_column($response->json('data'), 'code'));
    }

    public function test_an_analysis_for_another_organizations_order_equipment_fault_code_or_assignee_is_refused(): void
    {
        $theirEquipment = Equipment::factory()->create(['organization_id' => $this->other->id]);
        $theirOrder = MaintenanceOrder::factory()->create([
            'organization_id' => $this->other->id,
            'equipment_id' => $theirEquipment->id,
        ]);

        $this->apiPost('/maintenance/rca', [
            'maintenance_order_id' => $theirOrder->id,
            'equipment_id' => $theirEquipment->id,
            'fault_code_id' => $this->faultCode($this->other, 'FC-9')->id,
            'assigned_to' => User::factory()->create(['organization_id' => $this->other->id])->id,
            'rca_method' => '5_why',
        ])->assertStatus(422)->assertJsonValidationErrors(['maintenance_order_id', 'equipment_id', 'fault_code_id', 'assigned_to']);

        $this->assertSame(0, MaintenanceRca::withoutGlobalScopes()->count());
    }

    public function test_analyses_filter_by_status_and_equipment_newest_first(): void
    {
        $older = $this->analysis(['created_at' => now()->subDays(2)]);
        $newer = $this->analysis(['created_at' => now()->subDay()]);
        $this->analysis(['status' => MaintenanceRca::STATUS_CLOSED]);
        $this->analysis(['equipment_id' => Equipment::factory()->create(['organization_id' => $this->organization->id])->id]);

        $response = $this->apiGet("/maintenance/rca?status=open&equipment_id={$this->equipment->id}");

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_closing_an_analysis_stamps_todays_date_and_another_organizations_is_not_found(): void
    {
        $analysis = $this->analysis();
        $theirs = $this->analysis(['organization_id' => $this->other->id]);

        $this->apiPut("/maintenance/rca/{$analysis->id}", ['status' => MaintenanceRca::STATUS_CLOSED])->assertOk();
        $this->assertSame(now()->toDateString(), $analysis->fresh()->closed_date->toDateString());

        $this->apiPut("/maintenance/rca/{$theirs->id}", ['status' => MaintenanceRca::STATUS_CLOSED])->assertNotFound();
        $this->assertSame(MaintenanceRca::STATUS_OPEN, $theirs->fresh()->status);
    }

    private function faultCode(Organization $organization, string $code, bool $active = true): MaintenanceFaultCode
    {
        return MaintenanceFaultCode::create([
            'organization_id' => $organization->id,
            'code' => $code,
            'description' => 'Bearing wear',
            'fault_type' => 'wear',
            'is_active' => $active,
        ]);
    }

    private function analysis(array $overrides = []): MaintenanceRca
    {
        return tap((new MaintenanceRca)->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'maintenance_order_id' => $this->order->id,
            'equipment_id' => $this->equipment->id,
            'rca_method' => '5_why',
            'status' => MaintenanceRca::STATUS_OPEN,
        ], $overrides)))->save();
    }
}
