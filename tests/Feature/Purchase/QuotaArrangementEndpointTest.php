<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchase\PurchasingInfoRecord;
use App\Models\Purchase\QuotaArrangement;
use App\Models\Purchase\QuotaArrangementItem;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Quota arrangement endpoints: ids an arrangement or item names must be the
 * caller's organization's, and source determination allocates to one item.
 */
class QuotaArrangementEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/quota-arrangements';
    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.quota-arrangements.view', 'purchase.quota-arrangements.create',
            'purchase.quota-arrangements.edit', 'purchase.quota-arrangements.delete',
        ]);

        $this->supplier = Contact::factory()->supplier()->create(['organization_id' => $this->organization->id]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_store_refuses_another_organizations_product_warehouse_vendor_and_info_record(): void
    {
        $other = Organization::factory()->create();
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => $other->id]);
        $foreignRecord = PurchasingInfoRecord::create([
            'organization_id' => $other->id,
            'vendor_id' => $foreignSupplier->id,
            'info_category' => 'standard',
        ]);

        $this->apiPost($this->baseUrl, [
            'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $other->id])->id,
            'valid_from' => '2026-01-01',
            'items' => [[
                'vendor_id' => $foreignSupplier->id,
                'purchasing_info_record_id' => $foreignRecord->id,
                'quota_percentage' => 100,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'product_id', 'warehouse_id', 'items.0.vendor_id', 'items.0.purchasing_info_record_id',
        ]);

        $this->assertSame(0, QuotaArrangement::withoutGlobalScopes()->count());
    }

    public function test_adding_an_item_refuses_another_organizations_vendor(): void
    {
        $arrangement = $this->arrangement($this->organization, $this->product);
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("{$this->baseUrl}/{$arrangement->id}/items", [
            'vendor_id' => $foreignSupplier->id,
            'quota_percentage' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('vendor_id');

        $this->assertSame(0, QuotaArrangementItem::withoutGlobalScopes()->count());
    }

    public function test_show_of_another_organizations_arrangement_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $arrangement = $this->arrangement($other, Product::factory()->create(['organization_id' => $other->id]));

        $this->apiGet("{$this->baseUrl}/{$arrangement->id}")->assertNotFound();
        $this->apiDelete("{$this->baseUrl}/{$arrangement->id}")->assertNotFound();

        $this->assertNotNull(QuotaArrangement::withoutGlobalScopes()->find($arrangement->id));
    }

    public function test_determining_a_source_allocates_the_quantity_to_one_item(): void
    {
        $arrangement = $this->arrangement($this->organization, $this->product);
        $item = $this->item($arrangement, $this->supplier, 60);
        $this->item($arrangement, Contact::factory()->supplier()->create(['organization_id' => $this->organization->id]), 40);

        $this->apiPost("{$this->baseUrl}/determine-source", ['product_id' => $this->product->id, 'quantity' => 10])
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.allocated_quantity', 10);

        $this->assertSame('10.0000', $item->fresh()->allocated_quantity);
    }

    public function test_resetting_allocations_zeroes_every_item(): void
    {
        $arrangement = $this->arrangement($this->organization, $this->product);
        $item = $this->item($arrangement, $this->supplier, 100, 7);

        $this->apiPost("{$this->baseUrl}/{$arrangement->id}/reset-allocations")->assertOk();

        $this->assertSame('0.0000', $item->fresh()->allocated_quantity);
    }

    private function arrangement(Organization $organization, Product $product): QuotaArrangement
    {
        return QuotaArrangement::create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'valid_from' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function item(QuotaArrangement $arrangement, Contact $supplier, float $percentage, float $allocated = 0): QuotaArrangementItem
    {
        return QuotaArrangementItem::create([
            'organization_id' => $arrangement->organization_id,
            'quota_arrangement_id' => $arrangement->id,
            'vendor_id' => $supplier->id,
            'quota_percentage' => $percentage,
            'allocated_quantity' => $allocated,
            'is_blocked' => false,
        ]);
    }
}
