<?php

declare(strict_types=1);

namespace Tests\Feature\RealEstate;

use App\Models\Core\OrganizationModule;
use App\Models\RealEstate\Building;
use App\Models\RealEstate\ContractCondition;
use App\Models\RealEstate\Portfolio;
use App\Models\RealEstate\Property;
use App\Models\RealEstate\RentalContract;
use App\Models\RealEstate\RentalUnit;
use App\Models\Tax\TaxCategory;
use App\Models\Tax\TaxRate;
use App\Services\RealEstate\LeasePostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A rent posting run charges the tax the organization's tax scheme charges:
 * VAT at its country's rate, GST for India, and nothing on a condition that is
 * not taxable. A taxable condition whose rate cannot be determined is refused.
 */
class LeasePostingTaxTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_a_saudi_run_charges_saudi_vat(): void
    {
        $this->setUpLease('SA');

        $run = $this->service()->executePostingRun($this->organization->id, 'all', 2026, 3);

        $item = $run->items->sole();
        $this->assertSame(0, bccomp((string) $item->tax_amount, '150', 4));
        $this->assertSame(0, bccomp((string) $item->total_amount, '1150', 4));
        $this->assertSame(0, bccomp((string) $run->total_amount, '1150', 4));
    }

    public function test_an_emirati_run_charges_emirati_vat(): void
    {
        $this->setUpLease('AE');

        $run = $this->service()->executePostingRun($this->organization->id, 'all', 2026, 3);

        $item = $run->items->sole();
        $this->assertSame(0, bccomp((string) $item->tax_amount, '50', 4));
        $this->assertSame(0, bccomp((string) $item->total_amount, '1050', 4));
    }

    public function test_an_indian_run_charges_the_configured_gst_rate(): void
    {
        $this->setUpLease('IN');
        $this->configureStandardRate('IN', '18');

        $run = $this->service()->executePostingRun($this->organization->id, 'all', 2026, 3);

        $item = $run->items->sole();
        $this->assertSame(0, bccomp((string) $item->tax_amount, '180', 4));
        $this->assertSame(0, bccomp((string) $item->total_amount, '1180', 4));
    }

    public function test_a_configured_rate_is_charged_over_the_schemes_standard_rate(): void
    {
        $this->setUpLease('SA');
        $this->configureStandardRate('SA', '5');

        $run = $this->service()->executePostingRun($this->organization->id, 'all', 2026, 3);

        $this->assertSame(0, bccomp((string) $run->items->sole()->tax_amount, '50', 4));
    }

    public function test_a_taxable_lease_with_no_determinable_rate_is_refused(): void
    {
        $this->setUpLease('IN');

        $this->expectException(RuntimeException::class);

        $this->service()->executePostingRun($this->organization->id, 'all', 2026, 3);
    }

    public function test_a_simulation_of_an_undeterminable_lease_is_refused_too(): void
    {
        $this->setUpLease('IN');

        $this->expectException(RuntimeException::class);

        $this->service()->simulatePostingRun($this->organization->id, 'all', 2026, 3);
    }

    public function test_a_condition_that_is_not_taxable_is_posted_without_tax(): void
    {
        $this->setUpLease('SA', ['is_taxable' => false]);

        $run = $this->service()->executePostingRun($this->organization->id, 'all', 2026, 3);

        $item = $run->items->sole();
        $this->assertSame(0, bccomp((string) $item->tax_amount, '0', 4));
        $this->assertSame(0, bccomp((string) $item->total_amount, '1000', 4));
    }

    private function service(): LeasePostingService
    {
        return app(LeasePostingService::class);
    }

    /** @param  array<string, mixed>  $conditionOverrides */
    private function setUpLease(string $countryCode, array $conditionOverrides = []): void
    {
        $this->setUpOrganization($countryCode);
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'real_estate',
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $this->setUpAuthenticatedUser(['real_estate.posting.manage']);
        $this->actingAs($this->user);

        $portfolio = Portfolio::create([
            'organization_id' => $this->organization->id,
            'code' => 'PF-1',
            'name' => 'Portfolio',
        ]);
        $property = Property::create([
            'organization_id' => $this->organization->id,
            'portfolio_id' => $portfolio->id,
            'code' => 'PR-1',
            'name' => 'Property',
        ]);
        $building = Building::create([
            'organization_id' => $this->organization->id,
            'property_id' => $property->id,
            'code' => 'B-1',
            'name' => 'Building',
        ]);
        $unit = RentalUnit::create([
            'organization_id' => $this->organization->id,
            'building_id' => $building->id,
            'code' => 'U-1',
            'status' => 'occupied',
            'area_sqm' => 100,
        ]);

        $contract = RentalContract::create([
            'organization_id' => $this->organization->id,
            'contract_number' => 'RC-1',
            'rental_unit_id' => $unit->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'currency_code' => $this->organization->base_currency,
        ]);

        ContractCondition::create(array_merge([
            'contract_id' => $contract->id,
            'condition_type' => 'base_rent',
            'amount' => '1000',
            'basis' => 'flat',
            'valid_from' => '2026-01-01',
            'is_taxable' => true,
            'is_active' => true,
        ], $conditionOverrides));
    }

    private function configureStandardRate(string $countryCode, string $rate): void
    {
        $category = TaxCategory::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'code' => TaxCategory::CODE_STANDARD,
            'is_active' => true,
        ]);

        TaxRate::create([
            'tax_category_id' => $category->id,
            'name' => "Standard {$countryCode}",
            'rate' => $rate,
            'country_code' => $countryCode,
            'effective_from' => '2020-01-01',
            'is_active' => true,
        ]);
    }
}
