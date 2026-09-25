<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Accounting\FiscalYear;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The balance sheet refuses a fiscal year of another organization.
 *
 * The request is sent twice: once with a second organization's fiscal year,
 * which the field must reject, and once with the caller's own, which the
 * field must accept.
 */
class OwnedReferenceValidationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_a_balance_sheet_is_not_drawn_for_another_organizations_fiscal_year(): void
    {
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['accounting.reports.view']);

        $url = fn (FiscalYear $year) => '/reports/financial/balance-sheet-v2'
            ."?as_of_date=2026-03-31&fiscal_year_id={$year->id}";

        $this->apiGet($url($this->fiscalYear(Organization::factory()->create())))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fiscal_year_id');

        $this->apiGet($url($this->fiscalYear($this->organization)))
            ->assertJsonMissingValidationErrors('fiscal_year_id');
    }

    private function fiscalYear(Organization $organization): FiscalYear
    {
        return FiscalYear::factory()->create(['organization_id' => $organization->id]);
    }
}
