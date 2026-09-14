<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Finance\PettyCashFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PettyCashTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.petty-cash.view',
            'accounting.petty-cash.manage',
            'accounting.petty-cash.create',
            'accounting.petty-cash.approve',
            'accounting.petty-cash.post',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAccount(): Account
    {
        return Account::create([
            'organization_id' => $this->organization->id,
            'code'            => 'PCH-' . fake()->unique()->numerify('####'),
            'name'            => 'Petty Cash Account',
            'account_type'    => 'asset',
            'sub_type'        => 'cash',
        ]);
    }

    private function makeFund(array $overrides = []): PettyCashFund
    {
        $account = $this->makeAccount();

        return PettyCashFund::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Main Petty Cash',
            'custodian_id'    => $this->user->id,
            'account_id'      => $account->id,
            'opening_balance' => 1000.00,
            'current_balance' => 1000.00,
            'currency_code'   => 'SAR',
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index Funds
    // -------------------------------------------------------------------------

    public function test_index_funds_returns_paginated_list(): void
    {
        $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_index_funds_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store Fund
    // -------------------------------------------------------------------------

    public function test_store_fund_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show Fund
    // -------------------------------------------------------------------------

    public function test_show_fund_returns_details(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . $fund->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $fund->id);
    }

    public function test_show_fund_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update Fund
    // -------------------------------------------------------------------------

    public function test_update_fund_modifies_fund(): void
    {
        $fund = $this->makeFund(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/petty-cash/funds/' . $fund->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $fund->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Vouchers
    // -------------------------------------------------------------------------

    public function test_index_vouchers_returns_empty_list(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_store_voucher_validates_required_fields(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers', []);

        $response->assertStatus(422);
    }

    public function test_store_voucher_validates_transaction_type_enum(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers', [
                'transaction_type' => 'invalid',
                'amount'           => 100.00,
                'description'      => 'Test',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Replenishments
    // -------------------------------------------------------------------------

    public function test_index_replenishments_returns_empty_list(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/replenishments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_request_replenishment_validates_required_fields(): void
    {
        $fund = $this->makeFund();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/replenishments', []);

        $response->assertStatus(422);
    }

    public function test_store_fund_opens_with_its_opening_balance(): void
    {
        $account = $this->makeAccount();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/petty-cash/funds', [
                'name'            => 'Branch Float',
                'custodian_id'    => $this->user->id,
                'account_id'      => $account->id,
                'opening_balance' => 500,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Branch Float')
            ->assertJsonPath('data.custodian.id', $this->user->id)
            ->assertJsonPath('data.account.id', $account->id);

        $fund = PettyCashFund::sole();
        $this->assertSame($this->organization->id, $fund->organization_id);
        $this->assertEquals(500, (float) $fund->current_balance);
    }

    public function test_index_funds_filters_active_only_within_the_organization(): void
    {
        $this->makeFund(['name' => 'B Active']);
        $this->makeFund(['name' => 'A Inactive', 'is_active' => false]);
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        PettyCashFund::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Foreign',
            'custodian_id'    => $this->user->id,
            'account_id'      => $this->makeAccount()->id,
            'opening_balance' => 1,
            'current_balance' => 1,
        ]);

        $all = $this->withToken($this->token)->getJson('/api/v1/petty-cash/funds');
        $all->assertStatus(200);
        $this->assertSame(['A Inactive', 'B Active'], array_column($all->json('data'), 'name'));

        $active = $this->withToken($this->token)->getJson('/api/v1/petty-cash/funds?active_only=1');
        $this->assertSame(['B Active'], array_column($active->json('data'), 'name'));
    }

    public function test_index_vouchers_filters_by_status_type_and_date(): void
    {
        $fund = $this->makeFund();
        $this->makeVoucher($fund, ['description' => 'match', 'status' => 'approved', 'transaction_type' => 'payment', 'voucher_date' => '2025-02-10']);
        $this->makeVoucher($fund, ['description' => 'wrong status', 'status' => 'draft', 'transaction_type' => 'payment', 'voucher_date' => '2025-02-10']);
        $this->makeVoucher($fund, ['description' => 'wrong type', 'status' => 'approved', 'transaction_type' => 'receipt', 'voucher_date' => '2025-02-10']);
        $this->makeVoucher($fund, ['description' => 'too early', 'status' => 'approved', 'transaction_type' => 'payment', 'voucher_date' => '2025-01-10']);
        $this->makeVoucher($this->makeFund(), ['description' => 'other fund', 'status' => 'approved', 'transaction_type' => 'payment', 'voucher_date' => '2025-02-10']);

        $response = $this->withToken($this->token)->getJson(
            '/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers?status=approved&type=payment&from_date=2025-02-01&to_date=2025-02-28'
        );

        $response->assertStatus(200);
        $this->assertSame(['match'], array_column($response->json('data'), 'description'));
        $this->assertCount(4, $this->withToken($this->token)->getJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/vouchers')->json('data'));
    }

    public function test_index_replenishments_filters_by_status(): void
    {
        $fund = $this->makeFund();
        $this->makeReplenishment($fund, ['notes' => 'requested']);
        $this->makeReplenishment($fund, ['notes' => 'approved', 'status' => 'approved']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/petty-cash/funds/' . $fund->uuid . '/replenishments?status=approved');

        $response->assertStatus(200);
        $this->assertSame(['approved'], array_column($response->json('data'), 'notes'));
    }

    public function test_another_organizations_vouchers_and_replenishments_cannot_be_acted_on(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $fund = PettyCashFund::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Foreign',
            'custodian_id'    => $this->user->id,
            'account_id'      => Account::create([
                'organization_id' => $otherOrg->id,
                'code'            => 'PCH-FOREIGN',
                'name'            => 'Foreign Petty Cash',
                'account_type'    => 'asset',
                'sub_type'        => 'cash',
            ])->id,
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active'       => true,
        ]);
        $draft = $this->makeVoucher($fund, ['status' => 'draft']);
        $approved = $this->makeVoucher($fund, ['status' => 'approved']);
        $requested = $this->makeReplenishment($fund);
        $approvedReplenishment = $this->makeReplenishment($fund, ['status' => 'approved']);

        $this->withToken($this->token)->postJson('/api/v1/petty-cash/vouchers/' . $draft->uuid . '/approve')->assertStatus(404);
        $this->withToken($this->token)->postJson('/api/v1/petty-cash/vouchers/' . $approved->uuid . '/post')->assertStatus(404);
        $this->withToken($this->token)->postJson('/api/v1/petty-cash/replenishments/' . $requested->uuid . '/approve')->assertStatus(404);
        $this->withToken($this->token)->postJson('/api/v1/petty-cash/replenishments/' . $approvedReplenishment->uuid . '/disburse')->assertStatus(404);

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertSame('requested', $requested->fresh()->status);
        $this->assertSame('approved', $approvedReplenishment->fresh()->status);
        $this->assertEquals(1000, (float) PettyCashFund::withoutGlobalScopes()->find($fund->id)->current_balance);
    }

    public function test_approve_voucher_and_replenishment_within_the_organization(): void
    {
        $fund = $this->makeFund();
        $voucher = $this->makeVoucher($fund, ['status' => 'draft']);
        $replenishment = $this->makeReplenishment($fund);

        $this->withToken($this->token)->postJson('/api/v1/petty-cash/vouchers/' . $voucher->uuid . '/approve')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.id', $this->user->id);
        $this->withToken($this->token)->postJson('/api/v1/petty-cash/replenishments/' . $replenishment->uuid . '/approve')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by.id', $this->user->id);
    }

    private function makeVoucher(PettyCashFund $fund, array $overrides = []): \App\Models\Finance\PettyCashVoucher
    {
        return \App\Models\Finance\PettyCashVoucher::create(array_merge([
            'fund_id'          => $fund->id,
            'voucher_number'   => 'PCV-' . fake()->unique()->numerify('#####'),
            'voucher_date'     => now()->toDateString(),
            'transaction_type' => 'payment',
            'amount'           => 10,
            'description'      => 'Stationery',
            'status'           => 'draft',
            'created_by'       => $this->user->id,
        ], $overrides));
    }

    private function makeReplenishment(PettyCashFund $fund, array $overrides = []): \App\Models\Finance\PettyCashReplenishment
    {
        return \App\Models\Finance\PettyCashReplenishment::create(array_merge([
            'fund_id'            => $fund->id,
            'replenishment_date' => now()->toDateString(),
            'amount'             => 50,
            'requested_by'       => $this->user->id,
            'status'             => 'requested',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/petty-cash/funds')->assertStatus(401);
    }
}
