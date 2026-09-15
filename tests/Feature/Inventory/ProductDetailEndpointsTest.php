<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCertification;
use App\Models\Inventory\ProductImage;
use App\Models\Inventory\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Images, certifications and reviews are changed only under the product they
 * belong to, and relations link only the organization's products.
 */
class ProductDetailEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.products.view',
            'inventory.products.edit',
        ]);

        $this->product = $this->stockedProduct();
    }

    public function test_the_review_list_filters_by_status_newest_first(): void
    {
        $this->travelTo(now()->subHour());
        $older = $this->review('approved');
        $this->travelBack();
        $newer = $this->review('approved');
        $this->review('pending');

        $response = $this->apiGet("/inventory/products/{$this->product->id}/reviews?status=approved");

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_another_organizations_image_cannot_be_removed_through_our_product(): void
    {
        $theirImage = ProductImage::create(['product_id' => $this->foreignProduct()->id, 'image_path' => 'theirs.jpg']);

        $this->apiDelete("/inventory/products/{$this->product->id}/images/{$theirImage->id}")->assertNotFound();

        $this->assertNotNull(ProductImage::find($theirImage->id));
    }

    public function test_our_image_is_removed(): void
    {
        $image = ProductImage::create(['product_id' => $this->product->id, 'image_path' => 'ours.jpg']);

        $this->apiDelete("/inventory/products/{$this->product->id}/images/{$image->id}")->assertOk();

        $this->assertNull(ProductImage::find($image->id));
    }

    public function test_a_certification_is_not_moved_to_another_product_or_changed_under_one(): void
    {
        $ours = ProductCertification::create(['product_id' => $this->product->id, 'certification_name' => 'ISO 9001']);
        $theirs = ProductCertification::create(['product_id' => $this->foreignProduct()->id, 'certification_name' => 'HACCP']);

        $this->apiPut("/inventory/products/{$this->product->id}/certifications/{$ours->id}", [
            'certification_name' => 'ISO 14001',
            'product_id' => $theirs->product_id,
        ])->assertOk();

        $this->assertSame($this->product->id, $ours->fresh()->product_id);
        $this->assertSame('ISO 14001', $ours->fresh()->certification_name);

        $this->apiPut("/inventory/products/{$this->product->id}/certifications/{$theirs->id}", [
            'certification_name' => 'Changed',
        ])->assertNotFound();

        $this->assertSame('HACCP', $theirs->fresh()->certification_name);
    }

    public function test_a_review_of_another_product_is_not_approved_under_this_one(): void
    {
        $otherProduct = $this->stockedProduct();
        $review = ProductReview::create([
            'organization_id' => $this->organization->id,
            'product_id' => $otherProduct->id,
            'reviewer_name' => 'Reviewer',
            'rating' => 4,
            'status' => 'pending',
        ]);

        $this->apiPost("/inventory/products/{$this->product->id}/reviews/{$review->id}/approve")->assertNotFound();

        $this->assertSame('pending', $review->fresh()->status);
    }

    public function test_relations_refuse_another_organizations_product(): void
    {
        $response = $this->apiPut("/inventory/products/{$this->product->id}/relations", [
            'relation_type' => 'related',
            'related_ids' => [$this->foreignProduct()->id],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['related_ids.0']);
    }

    private function review(string $status): ProductReview
    {
        return ProductReview::create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'reviewer_name' => 'Reviewer',
            'rating' => 5,
            'status' => $status,
        ]);
    }
}
