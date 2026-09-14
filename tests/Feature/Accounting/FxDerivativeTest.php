<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FxForward;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\FxDerivativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class FxDerivativeTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.fx.manage',
            'accounting.fx.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeForward(array $overrides = []): FxForward
    {
        return FxForward::create(array_merge([
            'organization_id' => $this->organization->id,
            'contract_number' => 'FWD-' . fake()->unique()->numerify('######'),
            'buy_currency'    => 'USD',
            'sell_currency'   => 'SAR',
            'notional_amount' => 100000.00,
            'forward_rate'    => 3.75,
            'trade_date'      => '2025-01-01',
            'maturity_date'   => '2025-06-30',
            'purpose'         => 'hedge',
            'status'          => 'active',
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeForward();
        $this->makeForward();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_books_fx_forward(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards', [
                'buy_currency'    => 'USD',
                'sell_currency'   => 'SAR',
                'notional_amount' => 50000,
                'forward_rate'    => 3.75,
                'trade_date'      => '2025-01-01',
                'maturity_date'   => '2025-06-30',
                'purpose'         => 'hedge',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_forward_details(): void
    {
        $forward = $this->makeForward();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards/' . $forward->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $forward->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Designate Hedge
    // -------------------------------------------------------------------------

    public function test_designate_hedge_creates_hedge_relation(): void
    {
        $forward = $this->makeForward();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/designate-hedge', [
                'hedge_type'              => 'cash_flow',
                'hedged_item_type'        => 'forecast_sale',
                'hedged_item_description' => 'Q2 2025 expected sales',
                'designation_date'        => '2025-01-01',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Valuate
    // -------------------------------------------------------------------------

    public function test_valuate_records_mtm_valuation(): void
    {
        $forward = $this->makeForward();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/valuate', [
                'valuation_date' => '2025-03-31',
                'spot_rate'      => 3.76,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_a_valuation_with_gl_accounts_records_a_balanced_journal_entry(): void
    {
        $asset = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_OTHER_ASSET,
            'is_header'       => false,
            'is_active'       => true,
        ]);
        $unrealised = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_INCOME,
            'sub_type'        => Account::SUBTYPE_OTHER_INCOME,
            'is_header'       => false,
            'is_active'       => true,
        ]);
        $forward = $this->makeForward([
            'derivative_asset_account_id'     => $asset->id,
            'unrealised_gain_loss_account_id' => $unrealised->id,
        ]);

        // (3.76 - 3.75) x 100,000 notional is a gain of 1,000.
        app(FxDerivativeService::class)->recordValuation($forward, Carbon::parse('2025-03-31'), 3.76);

        $lines = JournalEntry::withoutGlobalScopes()->with('lines')->sole()->lines;
        $this->assertEquals(1000, (float) $lines->firstWhere('account_id', $asset->id)->debit);
        $this->assertEquals(1000, (float) $lines->firstWhere('account_id', $unrealised->id)->credit);
    }

    // -------------------------------------------------------------------------
    // Settle
    // -------------------------------------------------------------------------

    public function test_settle_marks_forward_as_exercised(): void
    {
        $forward = $this->makeForward(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/settle', [
                'settlement_rate' => 3.78,
                'settlement_date' => '2025-06-30',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals('exercised', $forward->fresh()->status);
    }

    public function test_index_filters_by_status_and_buy_currency(): void
    {
        $this->makeForward(['contract_number' => 'FWD-MATCH']);
        $this->makeForward(['contract_number' => 'FWD-EUR', 'buy_currency' => 'EUR']);
        $this->makeForward(['contract_number' => 'FWD-DONE', 'status' => 'exercised']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/fx-forwards?status=active&buy_currency=USD');

        $response->assertStatus(200);
        $this->assertSame(['FWD-MATCH'], array_column($response->json('data'), 'contract_number'));
    }

    public function test_dedesignate_hedge_ends_the_designated_relation(): void
    {
        $forward = $this->makeForward();
        $relation = $this->designate($forward);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/dedesignate-hedge', [
                'dedesignation_date' => '2025-03-01',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $relation->id)
            ->assertJsonPath('data.status', 'dedesignated');
    }

    public function test_dedesignate_hedge_returns_404_without_a_designated_relation(): void
    {
        $forward = $this->makeForward();
        $this->designate($forward, ['status' => 'dedesignated']);

        $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/dedesignate-hedge', [
                'dedesignation_date' => '2025-03-01',
            ])
            ->assertStatus(404);
    }

    private function designate(FxForward $forward, array $overrides = []): \App\Models\Accounting\FxHedgeRelation
    {
        return \App\Models\Accounting\FxHedgeRelation::create(array_merge([
            'organization_id'  => $this->organization->id,
            'fx_forward_id'    => $forward->id,
            'hedge_type'       => 'cash_flow',
            'hedged_item_type' => 'forecast_sale',
            'hedge_ratio'      => 1,
            'designation_date' => '2025-01-01',
            'status'           => 'designated',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/fx-forwards')->assertStatus(401);
    }
}
