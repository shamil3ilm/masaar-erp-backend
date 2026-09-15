<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\StabilityStudy;
use App\Models\Manufacturing\StabilityStudyTimePoint;
use App\Services\Manufacturing\StabilityStudyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Stability studies change status once, on the locked study, reach time points
 * only through their own study and accept only the organization's rows.
 */
class StabilityStudyFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->product = $this->stockedProduct();
    }

    public function test_a_stale_copy_cannot_reactivate_a_completed_study(): void
    {
        $study = $this->study();
        $stale = StabilityStudy::findOrFail($study->id);
        $service = app(StabilityStudyService::class);

        $service->activate(StabilityStudy::findOrFail($study->id));
        $service->complete(StabilityStudy::findOrFail($study->id));

        try {
            $service->activate($stale);
            $this->fail('A completed study was activated again.');
        } catch (RuntimeException) {
            // Refused on the locked study, as expected.
        }

        $this->assertSame(StabilityStudy::STATUS_COMPLETED, $study->fresh()->status);
    }

    public function test_a_stale_copy_cannot_modify_a_completed_study(): void
    {
        $study = $this->study(['status' => StabilityStudy::STATUS_ACTIVE]);
        $stale = StabilityStudy::findOrFail($study->id);
        $service = app(StabilityStudyService::class);

        $service->complete(StabilityStudy::findOrFail($study->id));

        try {
            $service->update($stale, ['notes' => 'Changed after completion']);
            $this->fail('A completed study was modified.');
        } catch (RuntimeException) {
            // Refused on the locked study, as expected.
        }

        $this->assertNull($study->fresh()->notes);
    }

    public function test_a_time_point_is_reached_only_through_its_own_study(): void
    {
        $study = $this->study();
        $other = $this->study();
        $point = $this->timePointOf($study);

        $this->apiPut("/manufacturing/stability-studies/{$other->id}/time-points/{$point->id}", ['status' => 'missed'])
            ->assertNotFound();
        $this->apiPost("/manufacturing/stability-studies/{$other->id}/time-points/{$point->id}/results", ['parameter_name' => 'pH'])
            ->assertNotFound();

        $this->apiPost("/manufacturing/stability-studies/{$study->id}/time-points/{$point->id}/results", [
            'parameter_name' => 'pH',
            'specification_min' => 6,
            'specification_max' => 8,
            'result_value' => 9,
        ])->assertCreated()->assertJsonPath('data.is_pass', false);

        $this->apiGet("/manufacturing/stability-studies/{$study->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.overall.total_results', 1)
            ->assertJsonPath('data.overall.failed', 1);
    }

    public function test_another_organizations_references_are_refused(): void
    {
        $this->apiPost('/manufacturing/stability-studies', [
            'study_number' => 'SS-X-1',
            'product_id' => $this->foreignProduct()->id,
            'inventory_batch_id' => $this->foreignBatch()->id,
            'study_type' => 'real_time',
            'start_date' => '2026-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'inventory_batch_id']);

        $study = $this->study();
        $point = $this->timePointOf($study);

        $this->apiPost("/manufacturing/stability-studies/{$study->id}/time-points/{$point->id}/results", [
            'parameter_name' => 'pH',
            'tested_by' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['tested_by']);
    }

    public function test_another_organizations_study_is_not_found(): void
    {
        $theirs = StabilityStudy::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'status' => StabilityStudy::STATUS_PLANNED,
        ]);

        $this->apiGet("/manufacturing/stability-studies/{$theirs->id}")->assertNotFound();
        $this->apiGet("/manufacturing/stability-studies/{$theirs->id}/summary")->assertNotFound();
        $this->apiPut("/manufacturing/stability-studies/{$theirs->id}", ['notes' => 'x'])->assertNotFound();
        $this->apiPost("/manufacturing/stability-studies/{$theirs->id}/activate")->assertNotFound();
        $this->apiPost("/manufacturing/stability-studies/{$theirs->id}/time-points", [
            'time_point' => 'T0',
            'scheduled_date' => '2026-01-01',
        ])->assertNotFound();

        $this->assertSame(StabilityStudy::STATUS_PLANNED, StabilityStudy::withoutGlobalScopes()->find($theirs->id)->status);
    }

    private function study(array $overrides = []): StabilityStudy
    {
        return StabilityStudy::create(array_merge([
            'organization_id' => $this->organization->id,
            'study_number' => 'SS-'.fake()->unique()->numerify('#####'),
            'product_id' => $this->product->id,
            'study_type' => StabilityStudy::TYPE_REAL_TIME,
            'start_date' => '2026-01-01',
            'status' => StabilityStudy::STATUS_PLANNED,
        ], $overrides));
    }

    private function timePointOf(StabilityStudy $study): StabilityStudyTimePoint
    {
        return StabilityStudyTimePoint::create([
            'organization_id' => $study->organization_id,
            'stability_study_id' => $study->id,
            'time_point' => 'T0',
            'scheduled_date' => '2026-01-01',
            'status' => StabilityStudyTimePoint::STATUS_SCHEDULED,
        ]);
    }
}
