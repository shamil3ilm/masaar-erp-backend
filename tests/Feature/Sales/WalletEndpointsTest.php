<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Wallet;
use App\Services\Sales\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the wallet list, lookups, credits and debits, shows the contact without
 * its tax number, and checks the active flag on the locked wallet.
 */
class WalletEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.wallets.view',
            'sales.wallets.credit',
            'sales.wallets.debit',
            'sales.wallets.adjust',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'tax_number' => '300000000000003',
        ]);
    }

    public function test_the_list_and_a_wallet_show_the_contact_without_its_tax_number(): void
    {
        $wallet = $this->wallet();

        $listed = $this->apiGet('/sales/wallets')->assertOk();
        $this->assertSame($this->customer->contact_name, $listed->json('data.0.contact.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.contact'));

        $shown = $this->apiGet("/sales/wallets/{$wallet->id}")->assertOk();
        $this->assertSame($this->customer->company_name, $shown->json('data.contact.company_name'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.contact'));
    }

    public function test_the_list_filters_by_type_and_active(): void
    {
        $match = $this->wallet(['wallet_type' => Wallet::TYPE_CUSTOMER]);
        $this->wallet(['wallet_type' => Wallet::TYPE_SUPPLIER, 'currency_code' => 'AED']);
        $this->wallet(['wallet_type' => Wallet::TYPE_CUSTOMER, 'currency_code' => 'USD', 'is_active' => false]);

        $response = $this->apiGet('/sales/wallets?wallet_type=customer&active_only=1');

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_wallet_of_another_organization_is_not_found(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreign = Wallet::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_id' => Contact::factory()->create(['organization_id' => $otherOrg->id])->id,
            'balance' => 100,
        ]);

        $this->apiGet("/sales/wallets/{$foreign->id}")->assertNotFound();
        $this->apiGet("/sales/wallets/{$foreign->id}/statement")->assertNotFound();
        $this->apiPost("/sales/wallets/{$foreign->id}/credit", ['amount' => 5, 'description' => 'x'])->assertNotFound();
        $this->apiPost("/sales/wallets/{$foreign->id}/debit", ['amount' => 5, 'description' => 'x'])->assertNotFound();
        $this->apiPost("/sales/wallets/{$foreign->id}/adjust", ['amount' => 5, 'description' => 'x'])->assertNotFound();

        $this->assertEquals(100, Wallet::withoutGlobalScopes()->find($foreign->id)->balance);
    }

    public function test_the_balance_of_a_contact_of_another_organization_is_not_found(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiGet("/sales/wallets/contact/{$foreign->id}/balance")->assertNotFound();
    }

    public function test_an_inactive_wallet_is_neither_credited_nor_debited(): void
    {
        $wallet = $this->wallet(['is_active' => false]);

        $this->apiPost("/sales/wallets/{$wallet->id}/credit", ['amount' => 5, 'description' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WALLET_INACTIVE')
            ->assertJsonPath('error.message', 'Cannot credit an inactive wallet.');

        $this->apiPost("/sales/wallets/{$wallet->id}/debit", ['amount' => 5, 'description' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'WALLET_INACTIVE')
            ->assertJsonPath('error.message', 'Cannot debit an inactive wallet.');

        $this->assertEquals(100, $wallet->fresh()->balance);
    }

    public function test_crediting_and_debiting_move_the_balance(): void
    {
        $wallet = $this->wallet();

        $this->apiPost("/sales/wallets/{$wallet->id}/credit", ['amount' => 50, 'description' => 'top up'])
            ->assertOk()
            ->assertJsonPath('message', 'Wallet credited successfully.');

        $this->apiPost("/sales/wallets/{$wallet->id}/debit", ['amount' => 30, 'description' => 'spend'])
            ->assertOk()
            ->assertJsonPath('message', 'Wallet debited successfully.');

        $this->apiGet("/sales/wallets/contact/{$this->customer->id}/balance")
            ->assertOk()
            ->assertJsonPath('data.0.id', $wallet->id);

        $this->assertEquals(120, $wallet->fresh()->balance);
    }

    public function test_a_wallet_deactivated_meanwhile_is_not_credited_or_debited_through_a_stale_copy(): void
    {
        $wallet = $this->wallet();
        $stale = Wallet::findOrFail($wallet->id);
        $service = app(WalletService::class);

        Wallet::whereKey($wallet->id)->update(['is_active' => false]);

        foreach ([
            'credited' => fn () => $service->creditActive($stale, 10, 'late credit'),
            'debited' => fn () => $service->debitActive($stale, 10, 'late debit'),
        ] as $action => $call) {
            try {
                $call();
                $this->fail("A deactivated wallet must not be {$action}.");
            } catch (\InvalidArgumentException) {
            }
        }

        $this->assertEquals(100, $wallet->fresh()->balance);
    }

    /** @param  array<string, mixed>  $attributes */
    private function wallet(array $attributes = []): Wallet
    {
        return Wallet::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'wallet_type' => Wallet::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'balance' => 100,
            'credit_limit' => 0,
            'is_active' => true,
        ], $attributes));
    }
}
