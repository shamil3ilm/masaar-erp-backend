<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenancePermit;
use App\Models\Maintenance\PermitSafetyCheck;
use App\Models\User;
use App\Services\Maintenance\MaintenancePermitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the permit list and its lifecycle, keeps permit references inside the
 * caller's organization, and approves a permit and completes a safety check
 * once, on the locked row.
 */
class MaintenancePermitTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['maintenance.permits.view', 'maintenance.permits.manage']);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
    }

    public function test_a_new_permit_gets_the_default_checks_for_its_type(): void
    {
        $this->apiPost('/maintenance/permits', [
            'permit_number' => 'PTW-1',
            'permit_type' => MaintenancePermit::TYPE_HOT_WORK,
        ])->assertCreated()
            ->assertJsonPath('data.status', MaintenancePermit::STATUS_REQUESTED)
            ->assertJsonCount(5, 'data.safety_checks');
    }

    public function test_a_permit_for_another_organizations_order_or_requester_is_refused(): void
    {
        $theirOrder = MaintenanceOrder::factory()->create([
            'organization_id' => $this->other->id,
            'equipment_id' => Equipment::factory()->create(['organization_id' => $this->other->id])->id,
        ]);
        $theirUser = User::factory()->create(['organization_id' => $this->other->id]);

        $this->apiPost('/maintenance/permits', [
            'permit_number' => 'PTW-1',
            'permit_type' => MaintenancePermit::TYPE_GENERAL,
            'maintenance_order_id' => $theirOrder->id,
            'requested_by' => $theirUser->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['maintenance_order_id', 'requested_by']);

        $this->assertSame(0, MaintenancePermit::withoutGlobalScopes()->count());
    }

    public function test_the_list_filters_by_status_and_another_organizations_permit_is_not_found(): void
    {
        $requested = $this->permit();
        $this->permit(['status' => MaintenancePermit::STATUS_CLOSED]);
        $theirs = $this->permit(['organization_id' => $this->other->id]);

        $this->apiGet('/maintenance/permits?status=requested')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $requested->id);

        $this->apiGet("/maintenance/permits/{$theirs->id}")->assertNotFound();
        $this->apiPost("/maintenance/permits/{$theirs->id}/approve")->assertNotFound();
        $this->assertSame(MaintenancePermit::STATUS_REQUESTED, $theirs->fresh()->status);
    }

    public function test_a_second_approval_from_a_stale_permit_is_rejected(): void
    {
        $permit = $this->permit();
        $stale = MaintenancePermit::findOrFail($permit->id);
        $approver = User::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(MaintenancePermitService::class);

        $service->approve($permit, $this->user->id);

        $refusal = null;
        try {
            $service->approve($stale, $approver->id);
        } catch (RuntimeException $e) {
            $refusal = $e->getMessage();
        }

        $this->assertSame('Only requested permits can be approved.', $refusal);
        $this->assertSame($this->user->id, $permit->fresh()->approved_by);
    }

    public function test_closing_waits_for_the_mandatory_checks(): void
    {
        $id = $this->apiPost('/maintenance/permits', [
            'permit_number' => 'PTW-1',
            'permit_type' => MaintenancePermit::TYPE_GENERAL,
        ])->json('data.id');

        $this->apiPost("/maintenance/permits/{$id}/approve")->assertOk();
        $this->apiPost("/maintenance/permits/{$id}/activate")->assertOk();
        $this->apiPost("/maintenance/permits/{$id}/close")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OPERATION_FAILED');

        foreach (PermitSafetyCheck::where('maintenance_permit_id', $id)->pluck('id') as $checkId) {
            $this->apiPost("/maintenance/permits/{$id}/safety-checks/{$checkId}/complete")->assertOk();
        }

        $this->apiPost("/maintenance/permits/{$id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', MaintenancePermit::STATUS_CLOSED);
    }

    public function test_a_check_is_completed_once_and_only_under_its_own_permit(): void
    {
        $permit = $this->permit();
        $check = $this->check($permit);
        $otherPermit = $this->permit();

        $this->apiPost("/maintenance/permits/{$otherPermit->id}/safety-checks/{$check->id}/complete")->assertNotFound();

        $this->apiPost("/maintenance/permits/{$permit->id}/safety-checks/{$check->id}/complete", ['remarks' => 'Extinguisher checked'])
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->apiPost("/maintenance/permits/{$permit->id}/safety-checks/{$check->id}/complete", ['remarks' => 'Changed afterwards'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OPERATION_FAILED');

        $this->assertSame('Extinguisher checked', $check->fresh()->remarks);
    }

    private function permit(array $overrides = []): MaintenancePermit
    {
        return MaintenancePermit::create(array_merge([
            'organization_id' => $this->organization->id,
            'permit_number' => 'PTW-'.fake()->unique()->numerify('####'),
            'permit_type' => MaintenancePermit::TYPE_GENERAL,
            'status' => MaintenancePermit::STATUS_REQUESTED,
        ], $overrides));
    }

    private function check(MaintenancePermit $permit): PermitSafetyCheck
    {
        return PermitSafetyCheck::create([
            'organization_id' => $permit->organization_id,
            'maintenance_permit_id' => $permit->id,
            'check_description' => 'Fire extinguisher available',
            'is_mandatory' => true,
            'is_completed' => false,
            'sort_order' => 0,
        ]);
    }
}
