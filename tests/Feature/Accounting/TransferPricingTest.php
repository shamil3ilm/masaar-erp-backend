<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\TransferPrice;
use App\Models\Accounting\TransferPriceHistory;
use App\Models\Accounting\TransferPriceVersion;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TransferPricingTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.transfer-pricing.manage',
            'accounting.transfer-pricing.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePrice(array $overrides = []): TransferPrice
    {
        return TransferPrice::create(array_merge([
            'organization_id'        => $this->organization->id,
            'transfer_price_method'  => TransferPrice::METHOD_STANDARD_COST,
            'base_price'             => 100.00,
            'effective_from'         => '2025-01-01',
            'currency_code'          => 'SAR',
            'is_active'              => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_list(): void
    {
        $this->makePrice();
        $this->makePrice();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_transfer_price(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing', [
                'transfer_price_method' => TransferPrice::METHOD_COST_PLUS,
                'base_price'            => 200.00,
                'markup_percentage'     => 15.0,
                'effective_from'        => '2025-01-01',
                'currency_code'         => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_method_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing', [
                'transfer_price_method' => 'invalid_method',
                'base_price'            => 100.00,
                'effective_from'        => '2025-01-01',
                'currency_code'         => 'SAR',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $price = $this->makePrice();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/' . $price->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_transfer_price(): void
    {
        $price = $this->makePrice(['base_price' => 100.00]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/transfer-pricing/' . $price->id, [
                'base_price' => 250.00,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(250.00, (float) $price->fresh()->base_price);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_transfer_price(): void
    {
        $price = $this->makePrice();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/transfer-pricing/' . $price->id);

        $response->assertStatus(200);
        $this->assertNull(TransferPrice::find($price->id));
    }

    // -------------------------------------------------------------------------
    // Versions
    // -------------------------------------------------------------------------

    public function test_versions_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/versions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_store_version_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions', []);

        $response->assertStatus(422);
    }

    public function test_store_version_creates_version(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions', [
                'version_name' => 'FY2025 Version 1',
                'fiscal_year'  => 2025,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_activate_version_returns_success(): void
    {
        $version = \App\Models\Accounting\TransferPriceVersion::create([
            'organization_id' => $this->organization->id,
            'version_name'    => 'FY2025 v1',
            'fiscal_year'     => 2025,
            'status'          => \App\Models\Accounting\TransferPriceVersion::STATUS_DRAFT,
            'created_by'      => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions/' . $version->id . '/activate');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Calculate
    // -------------------------------------------------------------------------

    public function test_calculate_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/calculate', []);

        $response->assertStatus(422);
    }

    public function test_calculate_also_validates_missing_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/calculate', [
                'quantity' => 10,
                // missing product_id, from/to_profit_center_id, date
            ]);

        $response->assertStatus(422);
    }

    public function test_index_filters_by_method_and_active_flag(): void
    {
        $this->makePrice(['transfer_price_method' => TransferPrice::METHOD_COST_PLUS, 'is_active' => true, 'effective_from' => '2025-02-01']);
        $this->makePrice(['transfer_price_method' => TransferPrice::METHOD_COST_PLUS, 'is_active' => false]);
        $this->makePrice(['transfer_price_method' => TransferPrice::METHOD_STANDARD_COST, 'is_active' => true]);

        $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing?method=' . TransferPrice::METHOD_COST_PLUS)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('data.0.effective_from', fn ($date) => str_starts_with((string) $date, '2025-02-01'));

        $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing?active_only=1&method=' . TransferPrice::METHOD_COST_PLUS . '&per_page=5')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_show_includes_conditions_and_history(): void
    {
        $created = $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing', [
                'transfer_price_method' => TransferPrice::METHOD_STANDARD_COST,
                'base_price'            => 80,
                'effective_from'        => '2025-01-01',
                'currency_code'         => 'SAR',
            ])
            ->json('data.id');

        $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/' . $created)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $created)
            ->assertJsonPath('data.conditions', [])
            ->assertJsonCount(1, 'data.history');
    }

    public function test_price_endpoints_return_404_for_another_organizations_price(): void
    {
        $otherOrg   = Organization::factory()->create();
        $otherPrice = $this->makePrice(['organization_id' => $otherOrg->id]);
        $url        = '/api/v1/transfer-pricing/' . $otherPrice->id;

        $this->withToken($this->token)->getJson($url)->assertStatus(404);
        $this->withToken($this->token)->putJson($url, ['base_price' => 1])->assertStatus(404);
        $this->withToken($this->token)->deleteJson($url)->assertStatus(404);

        $this->assertEquals(100.0, (float) TransferPrice::withoutGlobalScopes()->find($otherPrice->id)->base_price);
    }

    public function test_update_with_change_reason_records_it_in_price_history(): void
    {
        $price = $this->makePrice();

        $this->withToken($this->token)
            ->putJson('/api/v1/transfer-pricing/' . $price->id, ['base_price' => 120, 'change_reason' => 'Repriced'])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $price->id)
            ->assertJsonPath('data.from_profit_center', null)
            ->assertJsonPath('data.to_profit_center', null);

        $history = TransferPriceHistory::where('transfer_price_id', $price->id)->sole();
        $this->assertSame('Repriced', $history->change_reason);
        $this->assertEquals(120.0, (float) $history->new_price);
    }

    public function test_versions_filters_by_status_and_fiscal_year(): void
    {
        $this->makeVersion(['status' => TransferPriceVersion::STATUS_DRAFT, 'fiscal_year' => 2025]);
        $this->makeVersion(['status' => TransferPriceVersion::STATUS_ACTIVE, 'fiscal_year' => 2025]);
        $this->makeVersion(['status' => TransferPriceVersion::STATUS_ACTIVE, 'fiscal_year' => 2024]);

        $this->withToken($this->token)
            ->getJson('/api/v1/transfer-pricing/versions?status=active&fiscal_year=2025')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.created_by.id', $this->user->id);
    }

    public function test_activate_version_returns_the_active_version(): void
    {
        $version = $this->makeVersion();

        $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions/' . $version->id . '/activate')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $version->id)
            ->assertJsonPath('data.status', TransferPriceVersion::STATUS_ACTIVE);
    }

    public function test_activate_version_returns_404_for_another_organizations_version(): void
    {
        $otherOrg = Organization::factory()->create();
        $version  = $this->makeVersion(['organization_id' => $otherOrg->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transfer-pricing/versions/' . $version->id . '/activate')
            ->assertStatus(404);

        $this->assertSame(TransferPriceVersion::STATUS_DRAFT, TransferPriceVersion::withoutGlobalScopes()->find($version->id)->status);
    }

    private function makeVersion(array $overrides = []): TransferPriceVersion
    {
        return TransferPriceVersion::create(array_merge([
            'organization_id' => $this->organization->id,
            'version_name'    => 'FY2025 v' . fake()->unique()->numerify('##'),
            'fiscal_year'     => 2025,
            'status'          => TransferPriceVersion::STATUS_DRAFT,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/transfer-pricing')->assertStatus(401);
    }
}
