<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * A serial number may only point at products, variants, batches, warehouses,
 * locations and customers of the caller's organization.
 */
class SerialNumberEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.serial-numbers.view',
            'inventory.serial-numbers.manage',
        ]);

        $this->store = $this->warehouse();
        $this->product = $this->stockedProduct();
    }

    public function test_a_serial_number_with_the_organizations_references_is_created(): void
    {
        $response = $this->apiPost('/inventory/serial-numbers', [
            'serial_number' => 'SN-1',
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variantOf($this->product)->id,
            'batch_id' => $this->batch($this->product, $this->store, 1)->id,
            'warehouse_id' => $this->store->id,
            'location_id' => $this->locationIn($this->store)->id,
        ]);

        $response->assertCreated();
    }

    public function test_references_of_another_organization_are_refused(): void
    {
        $foreignWarehouse = $this->foreignWarehouse();

        $response = $this->apiPost('/inventory/serial-numbers', [
            'serial_number' => 'SN-1',
            'product_id' => $this->foreignProduct()->id,
            'product_variant_id' => $this->variantOf($this->foreignProduct())->id,
            'batch_id' => $this->foreignBatch()->id,
            'warehouse_id' => $foreignWarehouse->id,
            'location_id' => $this->locationIn($foreignWarehouse)->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'product_id', 'product_variant_id', 'batch_id', 'warehouse_id', 'location_id',
        ]);
        $this->assertSame(0, SerialNumber::withoutGlobalScopes()->count());
    }

    public function test_a_serial_number_cannot_be_issued_to_another_organizations_customer(): void
    {
        $serial = SerialNumber::create([
            'organization_id' => $this->organization->id,
            'serial_number' => 'SN-2',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->store->id,
            'status' => SerialNumber::STATUS_IN_STOCK,
        ]);
        $theirCustomer = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $response = $this->apiPost("/inventory/serial-numbers/{$serial->id}/issue", [
            'warehouse_id' => $this->store->id,
            'contact_id' => $theirCustomer->id,
            'document_type' => 'invoice',
            'document_id' => 1,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['contact_id']);
        $this->assertSame(SerialNumber::STATUS_IN_STOCK, $serial->fresh()->status);
    }
}
