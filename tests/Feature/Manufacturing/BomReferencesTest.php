<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * BOMs, BOM alternatives and capacity reports accept only the organization's
 * own products, variants, units and work centers. Variants have no
 * organization column and belong to whoever owns their product.
 */
class BomReferencesTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.bom.view',
            'manufacturing.bom.create',
            'manufacturing.bom.edit',
            'manufacturing.planning.view',
            'manufacturing.planning.manage',
            'manufacturing.capacity.view',
        ]);

        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_line_variant_of_the_organizations_product_is_accepted(): void
    {
        $variant = $this->variantOf($this->product);

        $response = $this->apiPost('/manufacturing/bom-templates', $this->bomPayload([
            'lines' => [['product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 2]],
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('bom_lines', ['variant_id' => $variant->id]);
    }

    public function test_a_line_variant_of_another_organizations_product_is_refused(): void
    {
        $theirVariant = $this->variantOf($this->foreignProduct());

        $response = $this->apiPost('/manufacturing/bom-templates', $this->bomPayload([
            'lines' => [['product_id' => $this->product->id, 'variant_id' => $theirVariant->id, 'quantity' => 2]],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['lines.0.variant_id']);
        $this->assertSame(0, BomTemplate::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_unit_is_refused_on_a_bom(): void
    {
        $response = $this->apiPost('/manufacturing/bom-templates', $this->bomPayload([
            'output_unit_id' => $this->foreignUnit()->id,
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['output_unit_id']);
    }

    public function test_an_alternative_is_not_determined_for_another_organizations_product(): void
    {
        $response = $this->apiPost('/manufacturing/bom-alternatives/determine', [
            'product_id' => $this->foreignProduct()->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['product_id']);
    }

    public function test_capacity_load_refuses_another_organizations_work_center(): void
    {
        $theirs = WorkCenter::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $from = now()->toDateString();
        $to = now()->addWeek()->toDateString();

        $this->apiGet("/manufacturing/capacity/load?from={$from}&to={$to}&work_center_id={$theirs->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['work_center_id']);
    }

    private function bomPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Assembly',
            'product_id' => $this->product->id,
            'output_quantity' => 10,
            'lines' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], $overrides);
    }
}
