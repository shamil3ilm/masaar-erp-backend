<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\SupplierNcrRecord;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Supplier ratings, approved vendors and NCRs show the supplier by reference,
 * accept only the organization's suppliers and products, and are closed only
 * within the organization.
 */
class SupplierQualityFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->supplier = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = $this->stockedProduct();
    }

    public function test_suppliers_are_shown_by_reference(): void
    {
        $created = [
            $this->apiPost('/manufacturing/supplier-quality/ratings', $this->rating($this->supplier->id))
                ->assertCreated()->json('data.supplier'),
            $this->apiPost('/manufacturing/supplier-quality/avl', $this->avl($this->supplier->id, $this->product->id))
                ->assertCreated()->json('data.supplier'),
            $this->apiPost('/manufacturing/supplier-quality/ncrs', $this->ncr($this->supplier->id, $this->product->id))
                ->assertCreated()->json('data.supplier'),
        ];

        $listed = [
            $this->apiGet('/manufacturing/supplier-quality/ratings')->assertOk()->json('data.0.supplier'),
            $this->apiGet('/manufacturing/supplier-quality/avl')->assertOk()->json('data.0.supplier'),
            $this->apiGet('/manufacturing/supplier-quality/ncrs')->assertOk()->json('data.0.supplier'),
        ];

        foreach ([...$created, ...$listed] as $supplier) {
            $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($supplier));
        }
    }

    public function test_another_organizations_suppliers_and_products_are_refused(): void
    {
        $theirSupplier = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirProduct = $this->foreignProduct();

        $this->apiPost('/manufacturing/supplier-quality/ratings', $this->rating($theirSupplier->id))
            ->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);

        $this->apiPost('/manufacturing/supplier-quality/avl', $this->avl($theirSupplier->id, $theirProduct->id))
            ->assertStatus(422)->assertJsonValidationErrors(['supplier_id', 'product_id']);

        $this->apiPost('/manufacturing/supplier-quality/ncrs', $this->ncr($theirSupplier->id, $theirProduct->id))
            ->assertStatus(422)->assertJsonValidationErrors(['supplier_id', 'product_id']);
    }

    public function test_an_ncr_is_closed_only_within_the_organization(): void
    {
        $ours = $this->ncrRecord($this->organization->id, $this->supplier->id);
        $theirs = $this->ncrRecord(
            $this->otherOrganization()->id,
            Contact::factory()->create(['organization_id' => $this->otherOrganization()->id])->id,
        );

        $this->apiPost("/manufacturing/supplier-quality/ncrs/{$theirs->id}/close", ['disposition' => 'scrap'])
            ->assertNotFound();
        $this->assertSame('open', SupplierNcrRecord::withoutGlobalScopes()->find($theirs->id)->status);

        $this->apiPost("/manufacturing/supplier-quality/ncrs/{$ours->id}/close", ['disposition' => 'rework'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.disposition', 'rework');
    }

    private function rating(int $supplierId): array
    {
        return [
            'supplier_id' => $supplierId,
            'rating_period_start' => '2026-01-01',
            'rating_period_end' => '2026-03-31',
            'quality_score' => 92,
            'classification' => 'approved',
        ];
    }

    private function avl(int $supplierId, int $productId): array
    {
        return [
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'approved_date' => '2026-01-01',
        ];
    }

    private function ncr(int $supplierId, int $productId): array
    {
        return [
            'ncr_number' => 'NCR-'.fake()->unique()->numerify('#####'),
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'nonconformance_description' => 'Wrong dimensions',
            'severity' => 'major',
            'detected_date' => '2026-02-01',
        ];
    }

    private function ncrRecord(int $organizationId, int $supplierId): SupplierNcrRecord
    {
        return SupplierNcrRecord::create([
            'organization_id' => $organizationId,
            'supplier_id' => $supplierId,
            'ncr_number' => 'NCR-'.fake()->unique()->numerify('#####'),
            'nonconformance_description' => 'Wrong dimensions',
            'severity' => 'major',
            'detected_date' => '2026-02-01',
            'status' => 'open',
        ]);
    }
}
