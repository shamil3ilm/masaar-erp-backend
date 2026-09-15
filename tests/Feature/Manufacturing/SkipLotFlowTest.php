<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\SkipLotDecision;
use App\Models\Manufacturing\SkipLotSamplingPlan;
use App\Models\Sales\Contact;
use App\Services\Manufacturing\SkipLotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Skip lot decisions count results on the locked decision, show vendors by
 * reference and accept only the organization's vendors, products, plans and
 * inspection lots.
 */
class SkipLotFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Contact $vendor;
    private Product $product;
    private SkipLotSamplingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->vendor = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = $this->stockedProduct();
        $this->plan = SkipLotSamplingPlan::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_stale_copy_does_not_lose_a_recorded_result(): void
    {
        $decision = $this->decision();
        $stale = SkipLotDecision::findOrFail($decision->id);
        $service = app(SkipLotService::class);
        $lot = $this->inspectionLot();

        $service->recordResult(SkipLotDecision::findOrFail($decision->id), true, $lot->id);
        $service->recordResult($stale, true, $lot->id);

        $this->assertSame(2, (int) $decision->fresh()->consecutive_accepted);
        $this->assertSame(2, (int) $decision->fresh()->lots_inspected_at_level);
    }

    public function test_should_inspect_creates_one_decision_and_decisions_show_the_vendor_by_reference(): void
    {
        $payload = ['vendor_id' => $this->vendor->id, 'product_id' => $this->product->id, 'plan_id' => $this->plan->id];

        $this->apiPost('/manufacturing/skip-lot-decisions/should-inspect', $payload)
            ->assertOk()
            ->assertJsonPath('data.should_inspect', true)
            ->assertJsonPath('data.current_level', SkipLotDecision::LEVEL_NORMAL);
        $this->apiPost('/manufacturing/skip-lot-decisions/should-inspect', $payload)->assertOk();

        $this->assertSame(1, SkipLotDecision::count());

        $vendor = $this->apiGet('/manufacturing/skip-lot-decisions')->assertOk()->json('data.data.0.vendor');

        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($vendor));
    }

    public function test_another_organizations_references_are_refused(): void
    {
        $theirVendor = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirPlan = SkipLotSamplingPlan::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiPost('/manufacturing/skip-lot-decisions/should-inspect', [
            'vendor_id' => $theirVendor->id,
            'product_id' => $this->foreignProduct()->id,
            'plan_id' => $theirPlan->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['vendor_id', 'product_id', 'plan_id']);

        $this->assertSame(0, SkipLotDecision::withoutGlobalScopes()->count());

        $theirLot = InspectionLot::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'quantity' => 5,
        ]);

        $this->apiPost("/manufacturing/skip-lot-decisions/{$this->decision()->id}/record-result", [
            'accepted' => true,
            'inspection_lot_id' => $theirLot->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['inspection_lot_id']);
    }

    public function test_another_organizations_plan_and_decision_are_not_found(): void
    {
        $theirPlan = SkipLotSamplingPlan::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirDecision = SkipLotDecision::create([
            'organization_id' => $this->otherOrganization()->id,
            'skip_lot_sampling_plan_id' => $theirPlan->id,
            'current_level' => SkipLotDecision::LEVEL_NORMAL,
        ]);

        $this->apiGet("/manufacturing/skip-lot-plans/{$theirPlan->id}")->assertNotFound();
        $this->apiPut("/manufacturing/skip-lot-plans/{$theirPlan->id}", ['plan_name' => 'x'])->assertNotFound();
        $this->apiDelete("/manufacturing/skip-lot-plans/{$theirPlan->id}")->assertNotFound();
        $this->apiPost("/manufacturing/skip-lot-decisions/{$theirDecision->id}/record-result", [
            'accepted' => true,
            'inspection_lot_id' => $this->inspectionLot()->id,
        ])->assertNotFound();

        $this->assertNotSoftDeleted('skip_lot_sampling_plans', ['id' => $theirPlan->id]);
    }

    private function decision(): SkipLotDecision
    {
        return SkipLotDecision::create([
            'organization_id' => $this->organization->id,
            'skip_lot_sampling_plan_id' => $this->plan->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'current_level' => SkipLotDecision::LEVEL_NORMAL,
            'lots_inspected_at_level' => 0,
            'consecutive_accepted' => 0,
            'consecutive_rejected' => 0,
        ]);
    }

    private function inspectionLot(): InspectionLot
    {
        return InspectionLot::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
    }
}
