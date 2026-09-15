<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\HazmatClassification;
use App\Models\Inventory\HazmatStorageClass;
use App\Models\Inventory\ProductHazmatClassification;
use App\Models\Inventory\SafetyDataSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the classification list and keeps product classification, safety data
 * sheets and compatibility checks inside the caller's organization.
 */
class HazmatEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.hazmat.view',
            'inventory.hazmat.manage',
        ]);
    }

    public function test_classifications_filter_by_system_in_code_order(): void
    {
        $second = $this->classification($this->organization->id, 'ghs', 'H300');
        $first = $this->classification($this->organization->id, 'ghs', 'H200');
        $this->classification($this->organization->id, 'un', 'UN1203');

        $response = $this->apiGet('/inventory/hazmat/classifications?system=ghs');

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_product_cannot_be_classified(): void
    {
        $classification = $this->classification($this->organization->id, 'ghs', 'H200');
        $theirs = $this->foreignProduct();

        $response = $this->apiPost("/inventory/hazmat/products/{$theirs->id}/classify", [
            'classifications' => [['hazmat_classification_id' => $classification->id]],
        ]);

        $response->assertNotFound();
        $this->assertSame(0, ProductHazmatClassification::count());
    }

    public function test_our_product_is_classified(): void
    {
        $classification = $this->classification($this->organization->id, 'ghs', 'H200');
        $product = $this->stockedProduct();

        $this->apiPost("/inventory/hazmat/products/{$product->id}/classify", [
            'classifications' => [['hazmat_classification_id' => $classification->id, 'is_primary' => true]],
        ])->assertOk();

        $this->assertSame(1, ProductHazmatClassification::where('product_id', $product->id)->count());
    }

    public function test_compatibility_refuses_another_organizations_storage_class(): void
    {
        $ours = HazmatStorageClass::create(['organization_id' => $this->organization->id, 'code' => '3', 'name' => 'Flammable']);
        $theirs = HazmatStorageClass::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'code' => '4',
            'name' => 'Oxidizing',
        ]);

        $response = $this->apiPost('/inventory/hazmat/compatibility-check', [
            'storage_class_a_id' => $ours->id,
            'storage_class_b_id' => $theirs->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['storage_class_b_id']);
    }

    public function test_another_organizations_safety_data_sheet_is_not_found(): void
    {
        $theirs = SafetyDataSheet::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
            'sds_number' => 'SDS-1',
            'version' => '1.0',
            'revision_date' => now()->toDateString(),
        ]);

        $this->apiGet("/inventory/hazmat/sds/{$theirs->id}")->assertNotFound();
    }

    private function classification(int $organizationId, string $system, string $code): HazmatClassification
    {
        return HazmatClassification::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'classification_system' => $system,
            'code' => $code,
            'name' => $code,
            'hazard_class' => '3',
            'is_active' => true,
        ]);
    }
}
