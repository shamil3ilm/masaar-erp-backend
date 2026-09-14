<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\VendorProductPricing;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Vendor pricing endpoints: records list with their vendor's id, name and
 * email and never its tax number, and ids a record names must be the
 * caller's organization's.
 */
class VendorPricingEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/vendor-pricing';
    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.pir.view', 'purchase.pir.create', 'purchase.pir.edit', 'purchase.pir.delete']);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_lists_only_this_organizations_records_with_their_vendor(): void
    {
        $this->pricing($this->organization, $this->supplier, $this->product);
        $other = Organization::factory()->create();
        $this->pricing(
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

    public function test_store_refuses_another_organizations_vendor_and_product(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost($this->baseUrl, [
            'vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
            'unit_price' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors(['vendor_id', 'product_id']);

        $this->assertSame(0, VendorProductPricing::withoutGlobalScopes()->count());
    }

    public function test_marking_a_record_preferred_unmarks_the_others_for_the_pair(): void
    {
        $first = $this->pricing($this->organization, $this->supplier, $this->product, true);
        $second = $this->pricing($this->organization, $this->supplier, $this->product);

        $this->apiPut("{$this->baseUrl}/{$second->id}", ['is_preferred_vendor' => true])
            ->assertOk()
            ->assertJsonPath('data.is_preferred_vendor', true)
            ->assertJsonPath('data.vendor.name', $this->supplier->getDisplayName());

        $this->assertFalse($first->fresh()->is_preferred_vendor);
    }

    public function test_show_of_another_organizations_record_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $record = $this->pricing(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
        );

        $this->apiGet("{$this->baseUrl}/{$record->id}")
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Vendor pricing record not found.');

        $this->apiDelete("{$this->baseUrl}/{$record->id}")->assertNotFound();
        $this->assertNotNull(VendorProductPricing::withoutGlobalScopes()->find($record->id));
    }

    public function test_for_product_lists_the_products_records_with_their_vendor(): void
    {
        $this->pricing($this->organization, $this->supplier, $this->product);

        $response = $this->apiGet("{$this->baseUrl}/for-product/{$this->product->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vendor.name', $this->supplier->getDisplayName());

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    private function pricing(Organization $organization, Contact $supplier, Product $product, bool $preferred = false): VendorProductPricing
    {
        return VendorProductPricing::create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'vendor_id' => $supplier->id,
            'unit_price' => 10,
            'currency_code' => 'SAR',
            'is_preferred_vendor' => $preferred,
        ]);
    }
}
