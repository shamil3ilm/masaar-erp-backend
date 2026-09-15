<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\ProcurementInspection;
use App\Models\Manufacturing\ProcurementInspectionConfig;
use App\Models\Manufacturing\ProcurementInspectionResult;
use App\Models\Sales\Contact;
use App\Services\Manufacturing\ProcurementInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Procurement inspections record results and are approved or rejected once, on
 * the locked inspection, show vendors by reference and accept only the
 * organization's own rows.
 */
class ProcurementInspectionFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;
    private Contact $vendor;
    private ProcurementInspectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->product = $this->stockedProduct();
        $this->vendor = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->service = app(ProcurementInspectionService::class);
    }

    public function test_a_stale_copy_cannot_record_results_twice(): void
    {
        $inspection = $this->inspection();
        $stale = ProcurementInspection::findOrFail($inspection->id);

        $this->service->recordResults(ProcurementInspection::findOrFail($inspection->id), $this->results(8, 2));

        try {
            $this->service->recordResults($stale, $this->results(10, 0));
            $this->fail('A completed inspection recorded its results again.');
        } catch (InvalidArgumentException) {
            // Refused on the locked inspection, as expected.
        }

        $this->assertEqualsWithDelta(8.0, (float) $inspection->fresh()->quantity_accepted, 0.0001);
        $this->assertSame(1, ProcurementInspectionResult::where('procurement_inspection_id', $inspection->id)->count());
    }

    public function test_a_stale_copy_cannot_approve_a_rejected_inspection(): void
    {
        $inspection = $this->inspection(['status' => ProcurementInspection::STATUS_COMPLETED]);
        $stale = ProcurementInspection::findOrFail($inspection->id);

        $this->service->rejectInspection(ProcurementInspection::findOrFail($inspection->id), 'Out of spec');

        try {
            $this->service->approveInspection($stale);
            $this->fail('A rejected inspection was approved.');
        } catch (InvalidArgumentException) {
            // Refused on the locked inspection, as expected.
        }

        $this->assertSame(ProcurementInspection::STATUS_REJECTED, $inspection->fresh()->status);
    }

    public function test_results_are_recorded_and_the_inspection_approved(): void
    {
        $inspection = $this->inspection();

        $this->apiPost("/manufacturing/procurement-inspection/inspections/{$inspection->id}/results", $this->results(9, 1))
            ->assertOk()
            ->assertJsonPath('data.status', ProcurementInspection::STATUS_COMPLETED)
            ->assertJsonCount(1, 'data.results');

        $this->apiPost("/manufacturing/procurement-inspection/inspections/{$inspection->id}/results", $this->results(9, 1))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->apiPost("/manufacturing/procurement-inspection/inspections/{$inspection->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', ProcurementInspection::STATUS_APPROVED);

        $this->apiPost("/manufacturing/procurement-inspection/inspections/{$inspection->id}/reject", ['reason' => 'Late'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_configs_are_listed_filtered_and_updated(): void
    {
        $config = ProcurementInspectionConfig::create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'vendor_id' => $this->vendor->id,
            'inspection_required' => true,
            'sampling_percentage' => 10,
            'is_active' => true,
        ]);
        ProcurementInspectionConfig::create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->stockedProduct()->id,
            'inspection_required' => true,
            'sampling_percentage' => 5,
            'is_active' => false,
        ]);

        $this->apiGet("/manufacturing/procurement-inspection/configs?vendor_id={$this->vendor->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $config->id);

        $this->apiGet('/manufacturing/procurement-inspection/configs?active_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->apiPut("/manufacturing/procurement-inspection/configs/{$config->id}", ['sampling_percentage' => 25])
            ->assertOk()
            ->assertJsonPath('data.sampling_percentage', '25.00');
    }

    public function test_vendors_are_shown_by_reference(): void
    {
        $this->inspection();

        $this->apiPost('/manufacturing/procurement-inspection/configs', [
            'product_id' => $this->product->id,
            'vendor_id' => $this->vendor->id,
            'sampling_percentage' => 10,
        ])->assertCreated();

        foreach ([
            $this->apiGet('/manufacturing/procurement-inspection/configs')->json('data.0.vendor'),
            $this->apiGet('/manufacturing/procurement-inspection/inspections')->json('data.0.vendor'),
            $this->apiGet('/manufacturing/procurement-inspection/inspections/'.ProcurementInspection::first()->id)->json('data.vendor'),
        ] as $vendor) {
            $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($vendor));
        }
    }

    public function test_another_organizations_references_are_refused(): void
    {
        $theirVendor = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirLot = InspectionLot::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'quantity' => 5,
        ]);

        $this->apiPost('/manufacturing/procurement-inspection/configs', [
            'product_id' => $this->foreignProduct()->id,
            'vendor_id' => $theirVendor->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'vendor_id']);

        $this->apiPost('/manufacturing/procurement-inspection/inspections', [
            'product_id' => $this->foreignProduct()->id,
            'vendor_id' => $theirVendor->id,
            'inspection_lot_id' => $theirLot->id,
            'quantity_received' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'vendor_id', 'inspection_lot_id']);

        $inspection = $this->inspection();

        $this->apiPost(
            "/manufacturing/procurement-inspection/inspections/{$inspection->id}/results",
            [...$this->results(1, 0), 'inspected_by' => $this->foreignUser()->id],
        )->assertStatus(422)->assertJsonValidationErrors(['inspected_by']);
    }

    public function test_another_organizations_inspection_and_config_are_not_found(): void
    {
        $theirs = ProcurementInspection::create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'quantity_received' => 5,
            'quantity_to_inspect' => 5,
            'status' => ProcurementInspection::STATUS_COMPLETED,
        ]);
        $theirConfig = ProcurementInspectionConfig::create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'sampling_percentage' => 10,
        ]);

        $this->apiGet("/manufacturing/procurement-inspection/inspections/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/procurement-inspection/inspections/{$theirs->id}/approve")->assertNotFound();
        $this->apiPut("/manufacturing/procurement-inspection/configs/{$theirConfig->id}", ['sampling_percentage' => 50])
            ->assertNotFound();

        $this->assertSame(ProcurementInspection::STATUS_COMPLETED, $theirs->fresh()->status);
    }

    private function inspection(array $overrides = []): ProcurementInspection
    {
        return ProcurementInspection::create(array_merge([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'vendor_id' => $this->vendor->id,
            'quantity_received' => 10,
            'quantity_to_inspect' => 10,
            'status' => ProcurementInspection::STATUS_PENDING,
        ], $overrides));
    }

    private function results(float $accepted, float $rejected): array
    {
        return [
            'quantity_accepted' => $accepted,
            'quantity_rejected' => $rejected,
            'characteristics' => [
                ['characteristic_name' => 'Diameter', 'actual_value' => '10.1', 'is_within_spec' => true],
            ],
        ];
    }
}
