<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\PriceOverride;
use App\Models\Sales\PriceOverridePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the price override list, recording against a policy, approval and the
 * report, and refuses another organization's policy, product or customer.
 */
class PriceOverrideEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Product $product;
    private PriceOverridePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.price-overrides.view', 'sales.price-overrides.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->policy = PriceOverridePolicy::factory()->create([
            'organization_id' => $this->organization->id,
            'allow_discount' => true,
            'allow_markup' => false,
            'max_discount_percent' => 20,
            'requires_approval' => false,
        ]);
    }

    public function test_the_list_shows_the_organizations_overrides_newest_first_with_product_and_creator(): void
    {
        $older = $this->override(['created_at' => now()->subDay()]);
        $newer = $this->override(['created_at' => now()]);
        PriceOverride::factory()->create(['organization_id' => $this->otherOrg->id, 'created_by' => $this->user->id]);

        $response = $this->apiGet('/sales/price-overrides');

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertSame($this->product->id, $response->json('data.0.product.id'));
        $this->assertSame($this->user->id, $response->json('data.0.creator.id'));
        $this->assertSame(20, $response->json('meta.per_page'));
    }

    public function test_an_override_within_the_policy_is_recorded_with_its_derived_amounts(): void
    {
        $response = $this->apiPost('/sales/price-overrides', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.price_difference', '10.0000')
            ->assertJsonPath('data.discount_percent', '10.00')
            ->assertJsonPath('data.total_impact', '20.00')
            ->assertJsonPath('data.approval_status', 'auto_approved')
            ->assertJsonPath('data.document_id', 0)
            ->assertJsonPath('data.line_item_id', 0)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.created_by', $this->user->id);
    }

    public function test_an_override_under_a_policy_that_requires_approval_is_pending(): void
    {
        $this->policy->update(['requires_approval' => true]);

        $this->apiPost('/sales/price-overrides', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.approval_status', 'pending');
    }

    public function test_the_policy_refuses_a_markup_and_a_discount_beyond_its_limit(): void
    {
        $this->apiPost('/sales/price-overrides', $this->payload(['override_price' => 110]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'POLICY_VIOLATION')
            ->assertJsonPath('error.message', 'Price markups are not allowed by this policy.');

        $this->apiPost('/sales/price-overrides', $this->payload(['override_price' => 70]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EXCEEDS_LIMIT');

        $this->assertSame(0, PriceOverride::count());
    }

    public function test_another_organizations_policy_product_and_customer_are_refused(): void
    {
        $foreignPolicy = PriceOverridePolicy::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/sales/price-overrides', $this->payload([
            'policy_id' => $foreignPolicy->id,
            'product_id' => Product::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]));

        $response->assertStatus(422);
        foreach (['policy_id', 'product_id', 'customer_id'] as $field) {
            $this->assertArrayHasKey($field, $response->json('errors') ?? []);
        }
        $this->assertSame(0, PriceOverride::count());
    }

    public function test_a_pending_override_is_approved_once(): void
    {
        $override = $this->override(['approval_status' => 'pending']);

        $this->apiPost("/sales/price-overrides/{$override->id}/approve", ['approval_notes' => 'Fine'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.approved_by', $this->user->id)
            ->assertJsonPath('data.approval_notes', 'Fine');

        $this->apiPost("/sales/price-overrides/{$override->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Only pending overrides can be approved.');
    }

    public function test_a_rejection_needs_notes_and_a_pending_override(): void
    {
        $override = $this->override(['approval_status' => 'pending']);

        $this->apiPost("/sales/price-overrides/{$override->id}/reject")->assertStatus(422);

        $this->apiPost("/sales/price-overrides/{$override->id}/reject", ['approval_notes' => 'Too low'])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'rejected');

        $this->apiPost("/sales/price-overrides/{$override->id}/reject", ['approval_notes' => 'Again'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only pending overrides can be rejected.');
    }

    public function test_an_override_shows_its_relations_and_another_organizations_is_not_found(): void
    {
        $override = $this->override(['policy_id' => $this->policy->id]);

        $this->apiGet("/sales/price-overrides/{$override->id}")
            ->assertOk()
            ->assertJsonPath('data.policy.id', $this->policy->id)
            ->assertJsonPath('data.product.id', $this->product->id);

        $foreign = PriceOverride::factory()->create(['organization_id' => $this->otherOrg->id, 'created_by' => $this->user->id]);
        $this->apiGet("/sales/price-overrides/{$foreign->id}")->assertNotFound();
    }

    public function test_the_report_totals_the_organizations_overrides_by_type(): void
    {
        $this->override(['override_type' => 'discount', 'total_impact' => 20]);
        $this->override(['override_type' => 'discount', 'total_impact' => 5]);
        PriceOverride::factory()->create(['organization_id' => $this->otherOrg->id, 'created_by' => $this->user->id, 'total_impact' => 99]);

        $response = $this->apiGet('/sales/price-overrides/report');

        $response->assertOk()
            ->assertJsonPath('data.total_overrides', 2)
            ->assertJsonPath('data.by_type.0.override_type', 'discount')
            ->assertJsonPath('data.by_type.0.count', 2);
        $this->assertEquals(25, $response->json('data.total_impact'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function override(array $attributes = []): PriceOverride
    {
        return PriceOverride::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'product_id' => $this->product->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'policy_id' => $this->policy->id,
            'document_type' => 'invoice',
            'product_id' => $this->product->id,
            'original_price' => 100,
            'override_price' => 90,
            'quantity' => 2,
            'override_type' => 'discount',
        ], $overrides);
    }
}
