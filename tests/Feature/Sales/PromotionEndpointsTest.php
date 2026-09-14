<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\CouponCode;
use App\Models\Sales\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the promotion list, create, update, code validation and coupons, and
 * keeps a promotion and its codes inside the caller's organization.
 */
class PromotionEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.promotions.view',
            'sales.promotions.create',
            'sales.promotions.edit',
            'sales.promotions.delete',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_the_list_shows_the_organizations_promotions_newest_first_and_filters(): void
    {
        $older = $this->promotion(['type' => 'percentage', 'created_at' => now()->subDay()]);
        $newer = $this->promotion(['type' => 'fixed_amount', 'created_at' => now()]);
        $inactive = $this->promotion(['type' => 'percentage', 'is_active' => false, 'created_at' => now()->subDays(2)]);
        Promotion::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->assertSame(
            [$newer->id, $older->id, $inactive->id],
            array_column($this->apiGet('/sales/promotions')->assertOk()->json('data'), 'id')
        );
        $this->assertSame(
            [$older->id, $inactive->id],
            array_column($this->apiGet('/sales/promotions?type=percentage')->json('data'), 'id')
        );
        $this->assertSame(
            [$newer->id, $older->id],
            array_column($this->apiGet('/sales/promotions?active_only=1')->json('data'), 'id')
        );
    }

    public function test_a_promotion_is_created_for_the_organization_and_its_code_is_unique_within_it(): void
    {
        Promotion::factory()->create(['organization_id' => $this->otherOrg->id, 'code' => 'SAVE10']);

        $this->apiPost('/sales/promotions', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('message', 'Promotion created successfully.')
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.created_by', $this->user->id);

        $this->apiPost('/sales/promotions', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_an_update_changes_the_promotion_but_never_its_organization(): void
    {
        $promotion = $this->promotion();

        $this->apiPut("/sales/promotions/{$promotion->id}", [
            'name' => 'Summer',
            'organization_id' => $this->otherOrg->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Promotion updated.')
            ->assertJsonPath('data.name', 'Summer');

        $this->assertSame($this->organization->id, $promotion->fresh()->organization_id);
    }

    public function test_a_code_is_validated_against_the_minimum_order_amount(): void
    {
        $this->promotion(['code' => 'SAVE10', 'min_order_amount' => 50, 'max_uses_per_customer' => null, 'max_uses' => null]);

        $this->apiPost('/sales/promotions/validate-code', ['code' => 'SAVE10', 'order_amount' => 100])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.promotion.code', 'SAVE10');

        $this->apiPost('/sales/promotions/validate-code', ['code' => 'SAVE10', 'order_amount' => 10])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MIN_ORDER_NOT_MET');

        $this->apiPost('/sales/promotions/validate-code', ['code' => 'NOPE'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PROMO_CODE');
    }

    public function test_code_validation_refuses_another_organizations_contact(): void
    {
        $this->promotion(['code' => 'SAVE10']);
        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/sales/promotions/validate-code', ['code' => 'SAVE10', 'contact_id' => $foreign->id]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('contact_id', $response->json('errors') ?? []);
    }

    public function test_generated_coupons_are_eight_characters_and_carry_the_use_limit(): void
    {
        $promotion = $this->promotion();

        $response = $this->apiPost("/sales/promotions/{$promotion->id}/generate-coupons", [
            'quantity' => 3,
            'prefix' => 'VIP',
            'max_uses_each' => 5,
        ]);

        $response->assertOk()->assertJsonPath('message', 'Coupon codes generated.');
        $codes = $response->json('data');
        $this->assertCount(3, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^VIP[A-Z0-9]{8}$/', $code);
        }
        $this->assertSame([5, 5, 5], CouponCode::where('promotion_id', $promotion->id)->pluck('max_uses')->all());
    }

    public function test_the_coupon_list_shows_the_promotions_codes_and_filters_active_ones(): void
    {
        $promotion = $this->promotion();
        $active = CouponCode::factory()->create(['promotion_id' => $promotion->id, 'expires_at' => null, 'created_at' => now()]);
        $inactive = CouponCode::factory()->create(['promotion_id' => $promotion->id, 'is_active' => false, 'created_at' => now()->subDay()]);
        CouponCode::factory()->create(['promotion_id' => $this->promotion()->id]);

        $this->assertSame(
            [$active->id, $inactive->id],
            array_column($this->apiGet("/sales/promotions/{$promotion->id}/coupons")->assertOk()->json('data'), 'id')
        );
        $this->assertSame(
            [$active->id],
            array_column($this->apiGet("/sales/promotions/{$promotion->id}/coupons?active_only=1")->json('data'), 'id')
        );
    }

    public function test_show_analytics_and_delete_stay_inside_the_organization(): void
    {
        $promotion = $this->promotion();
        $foreign = Promotion::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiGet("/sales/promotions/{$promotion->id}")->assertOk()->assertJsonPath('data.id', $promotion->id);
        $this->apiGet("/sales/promotions/{$foreign->id}")->assertNotFound();
        $this->apiGet("/sales/promotions/{$promotion->id}/analytics")->assertOk()->assertJsonPath('data.total_uses', 0);

        $this->apiDelete("/sales/promotions/{$promotion->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Promotion deleted.');
        $this->assertNull(Promotion::find($promotion->id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function promotion(array $attributes = []): Promotion
    {
        return Promotion::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Ten off',
            'code' => 'SAVE10',
            'type' => 'percentage',
            'apply_to' => 'order',
            'discount_value' => 10,
            'start_date' => now()->toDateString(),
        ];
    }
}
