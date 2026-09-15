<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\VatReturnPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The VAT return periods and transactions an organization lists: only its
 * own, narrowed by the filters given, and another organization's period is
 * refused.
 */
class VatReturnListingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['tax.vat.view', 'tax.vat.manage']);
        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_periods_are_prepared_once_and_listed_by_country(): void
    {
        $payload = ['country_code' => 'sau', 'period_start' => '2026-01-01', 'period_end' => '2026-03-31'];

        $first = $this->apiPost('/vat-returns', $payload)
            ->assertCreated()
            ->assertJsonPath('message', 'VAT return period created')
            ->assertJsonPath('data.country_code', 'SAU')
            ->json('data.id');

        $this->assertSame($first, $this->apiPost('/vat-returns', $payload)->assertCreated()->json('data.id'));

        $this->period('ARE', $this->organization->id);
        $this->period('SAU', $this->otherOrganization->id);

        $this->apiGet('/vat-returns?country_code=SAU')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first);
    }

    public function test_another_organizations_period_is_refused(): void
    {
        $foreign = $this->period('SAU', $this->otherOrganization->id);

        $this->apiGet('/vat-returns/'.$foreign->uuid)->assertForbidden();
    }

    public function test_transactions_are_recorded_and_listed_by_type(): void
    {
        foreach (['sale' => 1000, 'purchase' => 400] as $type => $taxable) {
            $this->apiPost('/vat-returns/transactions', [
                'transaction_type' => $type,
                'tax_period' => '2026-02-01',
                'taxable_amount' => $taxable,
                'vat_amount' => $taxable * 0.15,
                'vat_rate' => 15,
                'country_code' => 'SAU',
            ])->assertCreated()->assertJsonPath('message', 'VAT transaction recorded');
        }

        $this->apiGet('/vat-returns/transactions?transaction_type=sale')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_type', 'sale');
    }

    private function period(string $countryCode, int $organizationId): VatReturnPeriod
    {
        return VatReturnPeriod::create([
            'organization_id' => $organizationId,
            'country_code' => $countryCode,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'status' => 'draft',
        ]);
    }
}
