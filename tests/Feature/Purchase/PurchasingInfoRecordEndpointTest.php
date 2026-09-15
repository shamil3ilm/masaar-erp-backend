<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchase\PurchasingInfoRecord;
use App\Models\Purchase\PurchasingInfoRecordCondition;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Purchasing info record endpoints: ids a record names must be the caller's
 * organization's, and a condition is changed only through its own record.
 */
class PurchasingInfoRecordEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/info-records';
    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.info-records.view', 'purchase.info-records.create',
            'purchase.info-records.edit', 'purchase.info-records.delete',
        ]);

        $this->supplier = Contact::factory()->supplier()->create(['organization_id' => $this->organization->id]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_store_refuses_another_organizations_vendor_product_and_warehouse(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost($this->baseUrl, [
            'vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $other->id])->id,
            'net_price' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors(['vendor_id', 'product_id', 'warehouse_id']);

        $this->assertSame(0, PurchasingInfoRecord::withoutGlobalScopes()->count());
    }

    public function test_update_refuses_another_organizations_vendor(): void
    {
        $record = $this->record($this->organization, $this->supplier, $this->product);
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPut("{$this->baseUrl}/{$record->id}", ['vendor_id' => $foreignSupplier->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vendor_id');

        $this->assertSame($this->supplier->id, $record->fresh()->vendor_id);
    }

    public function test_show_returns_the_record_and_refuses_another_organizations(): void
    {
        $record = $this->record($this->organization, $this->supplier, $this->product);
        $other = Organization::factory()->create();
        $foreign = $this->record(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
        );

        $this->apiGet("{$this->baseUrl}/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.vendor.id', $this->supplier->id)
            ->assertJsonPath('data.product.id', $this->product->id);

        $this->apiGet("{$this->baseUrl}/{$foreign->id}")->assertNotFound();
    }

    public function test_updating_a_condition_through_another_record_is_not_found(): void
    {
        $record = $this->record($this->organization, $this->supplier, $this->product);
        $otherRecord = $this->record(
            $this->organization,
            $this->supplier,
            Product::factory()->create(['organization_id' => $this->organization->id]),
        );
        $condition = PurchasingInfoRecordCondition::create([
            'organization_id' => $this->organization->id,
            'purchasing_info_record_id' => $otherRecord->id,
            'valid_from' => '2026-01-01',
            'net_price' => 10,
        ]);

        $this->apiPut("{$this->baseUrl}/{$record->id}/conditions/{$condition->id}", ['net_price' => 5])
            ->assertNotFound();

        $this->assertSame('10.0000', $condition->fresh()->net_price);
    }

    public function test_destroy_deletes_the_record(): void
    {
        $record = $this->record($this->organization, $this->supplier, $this->product);

        $this->apiDelete("{$this->baseUrl}/{$record->id}")->assertNoContent();

        $this->assertNull(PurchasingInfoRecord::find($record->id));
    }

    private function record(Organization $organization, Contact $supplier, Product $product): PurchasingInfoRecord
    {
        return PurchasingInfoRecord::create([
            'organization_id' => $organization->id,
            'vendor_id' => $supplier->id,
            'product_id' => $product->id,
            'info_category' => 'standard',
            'is_active' => true,
            'net_price' => 10,
        ]);
    }
}
