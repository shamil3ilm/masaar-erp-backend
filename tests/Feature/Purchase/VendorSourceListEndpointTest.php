<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\VendorProductPricing;
use App\Models\Purchase\VendorSourceList;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Vendor source list endpoints: entries list with their vendor and product,
 * vendors leave with their tax number masked, and ids an entry names must be
 * the caller's organization's.
 */
class VendorSourceListEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/vendor-source-lists';
    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.source-lists.view', 'purchase.source-lists.create',
            'purchase.source-lists.edit', 'purchase.source-lists.delete',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_lists_only_this_organizations_entries_with_masked_vendors(): void
    {
        $this->entry($this->organization, $this->supplier, $this->product);
        $other = Organization::factory()->create();
        $this->entry(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
        );

        $response = $this->apiGet($this->baseUrl)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.vendor', [
                'id' => $this->supplier->id,
                'name' => $this->supplier->getDisplayName(),
                'email' => $this->supplier->email,
            ])
            ->assertJsonPath('data.0.product.name', $this->product->name);

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_store_refuses_another_organizations_pricing_record(): void
    {
        $other = Organization::factory()->create();
        $foreignPricing = VendorProductPricing::create([
            'organization_id' => $other->id,
            'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
            'vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'unit_price' => 10,
        ]);

        $this->apiPost($this->baseUrl, [
            'product_id' => $this->product->id,
            'vendor_id' => $this->supplier->id,
            'vendor_product_pricing_id' => $foreignPricing->id,
        ])->assertStatus(422)->assertJsonValidationErrors('vendor_product_pricing_id');

        $this->assertSame(0, VendorSourceList::withoutGlobalScopes()->count());
    }

    public function test_store_creates_an_entry_with_its_vendor(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'product_id' => $this->product->id,
            'vendor_id' => $this->supplier->id,
            'priority' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.vendor.name', $this->supplier->getDisplayName());

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_vendors_for_product_leave_with_masked_tax_numbers(): void
    {
        $this->entry($this->organization, $this->supplier, $this->product);

        $response = $this->apiGet("{$this->baseUrl}/vendors-for-product/{$this->product->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->supplier->id)
            ->assertJsonPath('data.0.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_show_of_another_organizations_entry_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $entry = $this->entry(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
        );

        $this->apiGet("{$this->baseUrl}/{$entry->id}")
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Vendor source list entry not found.');
    }

    private function entry(Organization $organization, Contact $supplier, Product $product): VendorSourceList
    {
        return VendorSourceList::create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'vendor_id' => $supplier->id,
            'priority' => 1,
            'is_blocked' => false,
        ]);
    }
}
