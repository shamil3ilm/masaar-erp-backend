<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\QInfoRecord;
use App\Models\Manufacturing\SkipLotSamplingPlan;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Q-Info records show vendors by reference, accept only the organization's
 * vendors, products and plans, and are reached only within the organization.
 */
class QInfoRecordFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Contact $vendor;
    private Product $product;

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
    }

    public function test_vendors_are_shown_by_reference(): void
    {
        $record = $this->record();

        foreach ([
            $this->apiGet('/manufacturing/q-info-records')->assertOk()->json('data.data.0.vendor'),
            $this->apiGet("/manufacturing/q-info-records/{$record->id}")->assertOk()->json('data.vendor'),
            $this->apiGet('/manufacturing/q-info-records/due-for-inspection')->assertOk()->json('data.0.vendor'),
        ] as $vendor) {
            $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($vendor));
        }
    }

    public function test_another_organizations_references_are_refused(): void
    {
        $theirVendor = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirPlan = SkipLotSamplingPlan::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $foreign = [
            'vendor_id' => $theirVendor->id,
            'product_id' => $this->foreignProduct()->id,
            'skip_lot_plan_id' => $theirPlan->id,
        ];

        $this->apiPost('/manufacturing/q-info-records', [...$foreign, 'inspection_type' => 'goods_receipt'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vendor_id', 'product_id', 'skip_lot_plan_id']);

        $this->apiPut("/manufacturing/q-info-records/{$this->record()->id}", $foreign)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vendor_id', 'product_id', 'skip_lot_plan_id']);
    }

    public function test_another_organizations_record_is_not_found(): void
    {
        $theirs = QInfoRecord::create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'inspection_type' => QInfoRecord::INSPECTION_GOODS_RECEIPT,
            'is_active' => true,
        ]);

        $this->apiGet("/manufacturing/q-info-records/{$theirs->id}")->assertNotFound();
        $this->apiPut("/manufacturing/q-info-records/{$theirs->id}", ['notes' => 'x'])->assertNotFound();
        $this->apiDelete("/manufacturing/q-info-records/{$theirs->id}")->assertNotFound();

        $this->assertNotSoftDeleted('q_info_records', ['id' => $theirs->id]);
    }

    private function record(): QInfoRecord
    {
        return QInfoRecord::create([
            'organization_id' => $this->organization->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'inspection_type' => QInfoRecord::INSPECTION_GOODS_RECEIPT,
            'is_active' => true,
        ]);
    }
}
