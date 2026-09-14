<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\SpecialLedger;
use App\Models\Accounting\SpecialLedgerEntry;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SpecialLedgerTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.special-ledgers.manage',
            'accounting.special-ledgers.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLedger(array $overrides = []): SpecialLedger
    {
        return SpecialLedger::create(array_merge([
            'organization_id'      => $this->organization->id,
            'code'                 => 'SL-' . fake()->unique()->numerify('##'),
            'name'                 => 'IFRS Special Ledger',
            'accounting_principle' => 'ifrs',
            'is_leading'           => false,
            'is_active'            => true,
            'currency_code'        => 'SAR',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_ledger_list(): void
    {
        $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_special_ledger(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/special-ledgers', [
                'code'                 => 'GAAP',
                'name'                 => 'GAAP Ledger',
                'accounting_principle' => 'gaap',
                'currency_code'        => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/special-ledgers', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_accounting_principle_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/special-ledgers', [
                'code'                 => 'XX',
                'name'                 => 'Test',
                'accounting_principle' => 'invalid',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_ledger_details(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $ledger->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_ledger(): void
    {
        $ledger = $this->makeLedger(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/special-ledgers/' . $ledger->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $ledger->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_non_leading_ledger(): void
    {
        $ledger = $this->makeLedger(['is_leading' => false]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/special-ledgers/' . $ledger->id);

        $response->assertStatus(200);
        $this->assertNull(SpecialLedger::find($ledger->id));
    }

    public function test_destroy_rejects_leading_ledger(): void
    {
        $ledger = $this->makeLedger(['is_leading' => true]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/special-ledgers/' . $ledger->id);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Trial Balance
    // -------------------------------------------------------------------------

    public function test_trial_balance_validates_fiscal_year_required(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/trial-balance');

        $response->assertStatus(422);
    }

    public function test_trial_balance_returns_data(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/trial-balance?fiscal_year=2025&period=3');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Entries
    // -------------------------------------------------------------------------

    public function test_entries_returns_paginated_list(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/entries');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_trial_balance_returns_404_for_another_organizations_ledger(): void
    {
        $otherOrg    = Organization::factory()->create();
        $otherLedger = $this->makeLedger(['organization_id' => $otherOrg->id]);
        $account     = Account::factory()->create(['organization_id' => $otherOrg->id]);

        SpecialLedgerEntry::create([
            'organization_id'   => $otherOrg->id,
            'special_ledger_id' => $otherLedger->id,
            'account_id'        => $account->id,
            'posting_date'      => '2025-03-01',
            'amount'            => 750,
            'currency_code'     => 'SAR',
            'exchange_rate'     => 1,
            'amount_local'      => 750,
            'debit_credit'      => 'D',
            'period'            => 3,
            'fiscal_year'       => 2025,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $otherLedger->id . '/trial-balance?fiscal_year=2025&period=3')
            ->assertStatus(404);
    }

    public function test_trial_balance_sums_the_ledgers_entries_by_account(): void
    {
        $ledger  = $this->makeLedger();
        $account = Account::factory()->create(['organization_id' => $this->organization->id]);
        $this->makeEntry($ledger, $account, 'D', 400, 2);
        $this->makeEntry($ledger, $account, 'C', 150, 3);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/trial-balance?fiscal_year=2025&period=3');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.account_id', $account->id)
            ->assertJsonPath('data.0.account.id', $account->id);
        $this->assertEquals(250, (float) $response->json('data.0.balance'));
    }

    public function test_index_filters_active_ledgers_and_orders_by_code(): void
    {
        $this->makeLedger(['code' => 'B-LEDGER']);
        $this->makeLedger(['code' => 'A-LEDGER']);
        $this->makeLedger(['code' => 'C-LEDGER', 'is_active' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'A-LEDGER');

        $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers?active_only=1')
            ->assertJsonCount(2, 'data');
    }

    public function test_show_includes_mapping_rules(): void
    {
        $ledger = $this->makeLedger();

        $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id)
            ->assertStatus(200)
            ->assertJsonPath('data.mapping_rules', []);
    }

    public function test_ledger_endpoints_return_404_for_another_organizations_ledger(): void
    {
        $otherOrg    = Organization::factory()->create();
        $otherLedger = $this->makeLedger(['organization_id' => $otherOrg->id]);
        $base        = '/api/v1/special-ledgers/' . $otherLedger->id;

        $this->withToken($this->token)->getJson($base)->assertStatus(404);
        $this->withToken($this->token)->putJson($base, ['name' => 'Taken'])->assertStatus(404);
        $this->withToken($this->token)->deleteJson($base)->assertStatus(404);
        $this->withToken($this->token)->getJson($base . '/entries')->assertStatus(404);

        $this->assertNotNull(SpecialLedger::withoutGlobalScopes()->find($otherLedger->id));
        $this->assertSame('IFRS Special Ledger', SpecialLedger::withoutGlobalScopes()->find($otherLedger->id)->name);
    }

    public function test_destroy_leading_ledger_returns_error_code(): void
    {
        $ledger = $this->makeLedger(['is_leading' => true]);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/special-ledgers/' . $ledger->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'LEADING_LEDGER')
            ->assertJsonPath('error.message', 'Cannot delete the leading ledger.');
    }

    public function test_entries_filters_by_period_and_paginates(): void
    {
        $ledger  = $this->makeLedger();
        $account = Account::factory()->create(['organization_id' => $this->organization->id]);
        $this->makeEntry($ledger, $account, 'D', 100, 2);
        $this->makeEntry($ledger, $account, 'D', 200, 3);

        $this->withToken($this->token)
            ->getJson('/api/v1/special-ledgers/' . $ledger->id . '/entries?period=3&per_page=5')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', 3)
            ->assertJsonPath('data.0.account.id', $account->id)
            ->assertJsonPath('meta.per_page', 5);
    }

    private function makeEntry(SpecialLedger $ledger, Account $account, string $side, float $amount, int $period): SpecialLedgerEntry
    {
        return SpecialLedgerEntry::create([
            'organization_id'   => $ledger->organization_id,
            'special_ledger_id' => $ledger->id,
            'account_id'        => $account->id,
            'posting_date'      => "2025-0{$period}-10",
            'amount'            => $amount,
            'currency_code'     => 'SAR',
            'exchange_rate'     => 1,
            'amount_local'      => $amount,
            'debit_credit'      => $side,
            'period'            => $period,
            'fiscal_year'       => 2025,
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/special-ledgers')->assertStatus(401);
    }
}
