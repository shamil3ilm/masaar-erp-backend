<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FxForward;
use App\Models\Accounting\FxValuation;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A forward carried in the general ledger is journalled or nothing happens,
 * and the service says which account mapping it is missing. The endpoints
 * report that as a refused business rule the caller can put right, the way
 * the rest of the module reports one, rather than as a server fault.
 */
class FxDerivativeRefusalTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.fx.manage', 'accounting.fx.view']);
    }

    public function test_valuating_without_a_mapped_result_account_is_refused_not_a_fault(): void
    {
        $forward = $this->forward();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/valuate', [
                'valuation_date' => '2025-03-31',
                'spot_rate'      => 3.76,
            ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsString('fx_unrealised_gain_account_id', (string) $response->json('error.message'));

        $this->assertSame(0, FxValuation::where('fx_forward_id', $forward->id)->count());
        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());
        $this->assertSame('active', $forward->fresh()->status);
    }

    public function test_settling_without_a_mapped_result_account_is_refused_not_a_fault(): void
    {
        $forward = $this->forward();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/fx-forwards/' . $forward->uuid . '/settle', [
                'settlement_rate' => 3.78,
                'settlement_date' => '2025-06-30',
            ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsString('fx_gain_account_id', (string) $response->json('error.message'));

        $unchanged = $forward->fresh();
        $this->assertSame('active', $unchanged->status);
        $this->assertNull($unchanged->settlement_rate);
        $this->assertNull($unchanged->settlement_gain_loss);
        $this->assertNull($unchanged->settled_at);
        $this->assertSame(0, JournalEntry::withoutGlobalScopes()->count());
    }

    /** A forward carried in the ledger, so its valuation and settlement are journalled. */
    private function forward(): FxForward
    {
        $derivative = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_OTHER_ASSET,
            'is_header'       => false,
            'is_active'       => true,
        ]);

        return FxForward::create([
            'organization_id'             => $this->organization->id,
            'contract_number'             => 'FWD-2025-0003',
            'buy_currency'                => 'USD',
            'sell_currency'               => 'SAR',
            'notional_amount'             => '100000.0000',
            'forward_rate'                => '3.75000000',
            'trade_date'                  => '2025-01-01',
            'maturity_date'               => '2025-06-30',
            'purpose'                     => 'hedge',
            'status'                      => 'active',
            'derivative_asset_account_id' => $derivative->id,
            'created_by'                  => $this->user->id,
        ]);
    }
}
