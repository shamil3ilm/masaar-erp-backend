<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\CalibrationCertificate;
use App\Models\Manufacturing\CalibrationEquipment;
use App\Models\Manufacturing\CalibrationOrder;
use App\Models\Manufacturing\CalibrationPlan;
use App\Services\Manufacturing\CalibrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * A calibration order is completed once, on the locked order, issuing one
 * certificate and scheduling one next order; calibration rows are reached and
 * referenced only within the organization.
 */
class CalibrationFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private CalibrationEquipment $equipment;
    private CalibrationPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->equipment = CalibrationEquipment::factory()->create(['organization_id' => $this->organization->id]);
        $this->plan = $this->planFor($this->equipment);
    }

    public function test_completing_an_order_issues_one_certificate_and_schedules_one_next_order(): void
    {
        $order = $this->orderFor($this->equipment, $this->plan);

        $this->apiPost("/manufacturing/calibration/orders/{$order->id}/complete", $this->completion('CERT-1'))
            ->assertOk()
            ->assertJsonPath('data.status', CalibrationOrder::STATUS_COMPLETED)
            ->assertJsonCount(1, 'data.certificates');

        $this->apiPost("/manufacturing/calibration/orders/{$order->id}/complete", $this->completion('CERT-2'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(1, CalibrationCertificate::count());
        $this->assertSame(1, CalibrationOrder::where('calibration_plan_id', $this->plan->id)
            ->where('status', CalibrationOrder::STATUS_PLANNED)
            ->count());
    }

    public function test_a_stale_copy_cannot_complete_an_order_twice(): void
    {
        $order = $this->orderFor($this->equipment, $this->plan);
        $stale = CalibrationOrder::findOrFail($order->id);
        $service = app(CalibrationService::class);

        $service->completeCalibration(CalibrationOrder::findOrFail($order->id), $this->completion('CERT-1'));

        try {
            $service->completeCalibration($stale, $this->completion('CERT-2'));
            $this->fail('A completed calibration order was completed again.');
        } catch (InvalidArgumentException) {
            // Refused on the locked order, as expected.
        }

        $this->assertSame(1, CalibrationCertificate::count());
    }

    public function test_equipment_is_searched_shown_and_updated(): void
    {
        CalibrationEquipment::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Other gauge']);
        $this->orderFor($this->equipment, $this->plan);

        $this->apiGet('/manufacturing/calibration/equipment?search='.urlencode($this->equipment->equipment_code))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->equipment->id);

        $this->apiGet("/manufacturing/calibration/equipment/{$this->equipment->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.calibration_plans')
            ->assertJsonCount(1, 'data.calibration_orders');

        $this->apiPut("/manufacturing/calibration/equipment/{$this->equipment->id}", ['location' => 'Lab 2'])
            ->assertOk()
            ->assertJsonPath('data.location', 'Lab 2');

        $this->apiGet("/manufacturing/calibration/plans/{$this->plan->id}")
            ->assertOk()
            ->assertJsonPath('data.equipment.id', $this->equipment->id)
            ->assertJsonCount(1, 'data.calibration_orders');
    }

    public function test_an_orders_certificates_are_listed_newest_first(): void
    {
        $order = $this->orderFor($this->equipment, $this->plan);

        foreach (['2026-01-10' => 'CERT-OLD', '2026-03-10' => 'CERT-NEW'] as $date => $number) {
            CalibrationCertificate::create([
                'organization_id' => $this->organization->id,
                'calibration_order_id' => $order->id,
                'certificate_number' => $number,
                'issued_date' => $date,
                'valid_until' => '2027-01-01',
            ]);
        }

        $this->apiGet("/manufacturing/calibration/orders/{$order->id}/certificates")
            ->assertOk()
            ->assertJsonPath('data.0.certificate_number', 'CERT-NEW')
            ->assertJsonPath('data.1.certificate_number', 'CERT-OLD');
    }

    public function test_another_organizations_references_are_refused(): void
    {
        $theirEquipment = CalibrationEquipment::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirPlan = $this->planFor($theirEquipment);

        $this->apiPost('/manufacturing/calibration/equipment', [
            'equipment_code' => 'CAL-NEW',
            'name' => 'Gauge',
            'responsible_person_id' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['responsible_person_id']);

        $this->apiPost('/manufacturing/calibration/plans', [
            'calibration_equipment_id' => $theirEquipment->id,
            'plan_code' => 'PLAN-X',
            'calibration_interval_days' => 30,
        ])->assertStatus(422)->assertJsonValidationErrors(['calibration_equipment_id']);

        $this->apiPost('/manufacturing/calibration/orders', [
            'calibration_equipment_id' => $theirEquipment->id,
            'calibration_plan_id' => $theirPlan->id,
            'scheduled_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['calibration_equipment_id', 'calibration_plan_id']);

        $order = $this->orderFor($this->equipment, null);

        $this->apiPost("/manufacturing/calibration/orders/{$order->id}/complete", [
            'result' => CalibrationOrder::RESULT_PASS,
            'calibrated_by' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['calibrated_by']);
    }

    public function test_another_organizations_rows_are_not_found(): void
    {
        $theirEquipment = CalibrationEquipment::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirPlan = $this->planFor($theirEquipment);
        $theirOrder = $this->orderFor($theirEquipment, $theirPlan);

        $this->apiGet("/manufacturing/calibration/equipment/{$theirEquipment->id}")->assertNotFound();
        $this->apiPut("/manufacturing/calibration/equipment/{$theirEquipment->id}", ['location' => 'x'])->assertNotFound();
        $this->apiGet("/manufacturing/calibration/plans/{$theirPlan->id}")->assertNotFound();
        $this->apiGet("/manufacturing/calibration/orders/{$theirOrder->id}")->assertNotFound();
        $this->apiGet("/manufacturing/calibration/orders/{$theirOrder->id}/certificates")->assertNotFound();
        $this->apiPost("/manufacturing/calibration/orders/{$theirOrder->id}/complete", $this->completion('CERT-X'))
            ->assertNotFound();

        $this->assertSame(CalibrationOrder::STATUS_PLANNED, CalibrationOrder::withoutGlobalScopes()->find($theirOrder->id)->status);
    }

    private function planFor(CalibrationEquipment $equipment): CalibrationPlan
    {
        return CalibrationPlan::create([
            'organization_id' => $equipment->organization_id,
            'calibration_equipment_id' => $equipment->id,
            'plan_code' => 'PLAN-'.fake()->unique()->numerify('#####'),
            'calibration_interval_days' => 30,
            'is_active' => true,
        ]);
    }

    private function orderFor(CalibrationEquipment $equipment, ?CalibrationPlan $plan): CalibrationOrder
    {
        return CalibrationOrder::create([
            'organization_id' => $equipment->organization_id,
            'calibration_equipment_id' => $equipment->id,
            'calibration_plan_id' => $plan?->id,
            'order_number' => 'CAL-'.fake()->unique()->numerify('#####'),
            'scheduled_date' => now()->toDateString(),
            'status' => CalibrationOrder::STATUS_PLANNED,
        ]);
    }

    private function completion(string $certificateNumber): array
    {
        return [
            'result' => CalibrationOrder::RESULT_PASS,
            'actual_measurement' => 10.02,
            'certificate' => ['certificate_number' => $certificateNumber],
        ];
    }
}
