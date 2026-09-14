<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\Currency;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class FiscalYearTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private string $baseUrl = '/fiscal-years';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->ensureBaseCurrency();
    }

    private function ensureBaseCurrency(): void
    {
        Currency::firstOrCreate(
            ['code' => 'SAR'],
            ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_active' => true]
        );
    }

    /**
     * Create a fiscal year scoped to the current organization.
     */
    private function createFiscalYear(array $overrides = []): FiscalYear
    {
        return FiscalYear::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
            'is_closed' => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/fiscal-years - List Fiscal Years
    // -------------------------------------------------------------------------

    public function test_can_list_fiscal_years_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $this->createFiscalYear(['name' => 'FY 2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31']);
        $this->createFiscalYear(['name' => 'FY 2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
    }

    public function test_list_fiscal_years_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/fiscal-years', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_list_fiscal_years_requires_permission(): void
    {
        $this->setUpAuthenticatedUser([]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertForbidden($response);
    }

    public function test_list_fiscal_years_respects_tenant_isolation(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $this->createFiscalYear(['name' => 'Own FY 2025']);

        // Create fiscal year in another organization
        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
        FiscalYear::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
            'is_closed' => false,
        ]);

        $response = $this->apiGet($this->baseUrl);

        $this->assertSuccessResponse($response);
        $data = $response->json('data');
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Own FY 2025', $names);
        $this->assertNotContains('Other FY 2025', $names);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/fiscal-years/current - Current Fiscal Year
    // -------------------------------------------------------------------------

    public function test_can_get_current_fiscal_year(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $this->createFiscalYear(['name' => 'FY 2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => false]);
        $currentFy = $this->createFiscalYear(['name' => 'FY 2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_current' => true]);

        $response = $this->apiGet("{$this->baseUrl}/current");

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['name' => 'FY 2025']);
    }

    public function test_current_fiscal_year_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/fiscal-years/current', [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/fiscal-years - Create Fiscal Year
    // -------------------------------------------------------------------------

    public function test_can_create_fiscal_year_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);

        $payload = [
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];

        $response = $this->apiPost($this->baseUrl, $payload);

        $this->assertCreatedResponse($response);
        $response->assertJsonFragment(['name' => 'FY 2026']);

        $this->assertDatabaseHas('fiscal_years', [
            'organization_id' => $this->organization->id,
            'name' => 'FY 2026',
        ]);
    }

    public function test_create_fiscal_year_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $response = $this->apiPost($this->baseUrl, [
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertForbidden($response);
    }

    public function test_create_fiscal_year_validates_required_fields(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);

        $response = $this->apiPost($this->baseUrl, []);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_fiscal_year_validates_end_date_after_start_date(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);

        $response = $this->apiPost($this->baseUrl, [
            'name' => 'Invalid FY',
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_fiscal_year_rejects_overlapping_dates(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);

        $this->createFiscalYear([
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        // Attempt to create an overlapping fiscal year
        $response = $this->apiPost($this->baseUrl, [
            'name' => 'Overlapping FY',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_create_fiscal_year_allows_non_overlapping_dates(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);

        $this->createFiscalYear([
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $response = $this->apiPost($this->baseUrl, [
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertCreatedResponse($response);
    }

    public function test_create_fiscal_year_with_periods_returns_its_monthly_periods(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);

        $response = $this->apiPost($this->baseUrl, [
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'create_periods' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Fiscal year created successfully')
            ->assertJsonPath('data.is_current', true)
            ->assertJsonCount(12, 'data.periods')
            ->assertJsonPath('data.periods.11.end_date', '2026-12-31T00:00:00.000000Z');
    }

    public function test_create_fiscal_year_overlap_returns_overlap_error(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);
        $this->createFiscalYear();

        $this->apiPost($this->baseUrl, [
            'name' => 'Overlapping FY',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'OVERLAP')
            ->assertJsonPath('error.message', 'Fiscal year dates overlap with an existing fiscal year');
    }

    public function test_create_fiscal_year_saves_nothing_when_its_periods_fail(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.create']);

        AccountingPeriod::creating(function (): void {
            throw new RuntimeException('Period could not be stored.');
        });

        $this->apiPost($this->baseUrl, [
            'name' => 'FY Half Built',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'create_periods' => true,
        ])->assertStatus(500);

        $this->assertDatabaseMissing('fiscal_years', ['name' => 'FY Half Built']);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/fiscal-years/initialize-coa - Default Chart of Accounts
    // -------------------------------------------------------------------------

    public function test_initialize_chart_of_accounts_creates_the_default_accounts(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $this->apiPost("{$this->baseUrl}/initialize-coa")
            ->assertStatus(200)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Chart of accounts initialized successfully');

        $this->assertGreaterThan(0, Account::withoutGlobalScopes()->where('organization_id', $this->organization->id)->count());
    }

    public function test_initialize_chart_of_accounts_refuses_an_existing_chart(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);
        $this->apiPost("{$this->baseUrl}/initialize-coa")->assertStatus(200);

        $this->apiPost("{$this->baseUrl}/initialize-coa")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ALREADY_EXISTS')
            ->assertJsonPath('error.message', 'Chart of accounts already exists');
    }

    public function test_initialize_chart_of_accounts_saves_no_partial_chart(): void
    {
        $this->setUpAuthenticatedUser(['accounting.accounts.create']);

        $created = 0;
        Account::creating(function () use (&$created): void {
            if (++$created === 3) {
                throw new RuntimeException('Account could not be stored.');
            }
        });

        $this->apiPost("{$this->baseUrl}/initialize-coa")->assertStatus(500);

        $this->assertSame(0, Account::withoutGlobalScopes()->where('organization_id', $this->organization->id)->count());
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/fiscal-years/{fiscalYear} - Show Fiscal Year
    // -------------------------------------------------------------------------

    public function test_can_show_fiscal_year(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $fy = $this->createFiscalYear(['name' => 'FY 2025']);

        $response = $this->apiGet("{$this->baseUrl}/{$fy->id}");

        $this->assertSuccessResponse($response);
        $response->assertJsonFragment(['name' => 'FY 2025']);
    }

    public function test_show_fiscal_year_returns_404_for_other_org(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $otherOrg = Organization::factory()->create(['country_code' => 'AE', 'base_currency' => 'AED']);
        $otherFy = FiscalYear::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other FY',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
            'is_closed' => false,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$otherFy->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/fiscal-years/{fy}/set-current - Set as Current
    // -------------------------------------------------------------------------

    public function test_can_set_fiscal_year_as_current(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.update']);

        $fy2024 = $this->createFiscalYear([
            'name' => 'FY 2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_current' => true,
        ]);

        $fy2025 = $this->createFiscalYear([
            'name' => 'FY 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => false,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$fy2025->id}/set-current");

        $this->assertSuccessResponse($response);

        // Verify the fiscal years have been updated
        $this->assertDatabaseHas('fiscal_years', ['id' => $fy2025->id, 'is_current' => true]);
        $this->assertDatabaseHas('fiscal_years', ['id' => $fy2024->id, 'is_current' => false]);
    }

    public function test_set_current_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $fy = $this->createFiscalYear();

        $response = $this->apiPost("{$this->baseUrl}/{$fy->id}/set-current");

        $this->assertForbidden($response);
    }

    public function test_cannot_set_closed_fiscal_year_as_current(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.update']);

        $closedFy = $this->createFiscalYear([
            'name' => 'Closed FY',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$closedFy->id}/set-current");

        $this->assertErrorResponse($response);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/fiscal-years/{fy}/close - Close Fiscal Year
    // -------------------------------------------------------------------------

    public function test_can_close_fiscal_year_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.close']);

        $fy = $this->createFiscalYear([
            'name' => 'FY 2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_current' => false,
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$fy->id}/close");

        $this->assertSuccessResponse($response);
        $this->assertDatabaseHas('fiscal_years', [
            'id' => $fy->id,
            'is_closed' => true,
        ]);
    }

    public function test_close_fiscal_year_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $fy = $this->createFiscalYear();

        $response = $this->apiPost("{$this->baseUrl}/{$fy->id}/close");

        $this->assertForbidden($response);
    }

    public function test_cannot_close_already_closed_fiscal_year(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.close']);

        $closedFy = $this->createFiscalYear([
            'name' => 'Already Closed',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $response = $this->apiPost("{$this->baseUrl}/{$closedFy->id}/close");

        $this->assertErrorResponse($response);
    }

    public function test_set_current_keeps_the_previous_current_year_when_the_change_fails(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.update']);

        $fy2024 = $this->createFiscalYear([
            'name' => 'FY 2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_current' => true,
        ]);
        $fy2025 = $this->createFiscalYear();

        FiscalYear::updating(function (FiscalYear $year): void {
            if ($year->is_current) {
                throw new RuntimeException('Fiscal year could not be updated.');
            }
        });

        $this->apiPost("{$this->baseUrl}/{$fy2025->id}/set-current")->assertStatus(500);

        $this->assertDatabaseHas('fiscal_years', ['id' => $fy2024->id, 'is_current' => true]);
    }

    public function test_cannot_close_fiscal_year_with_draft_journal_entries(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.close']);

        $fy = $this->createFiscalYear();
        JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'fiscal_year_id' => $fy->id,
            'entry_date' => '2025-06-15',
            'description' => 'Draft entry',
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'total_debit' => 0,
            'total_credit' => 0,
            'status' => JournalEntry::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $this->apiPost("{$this->baseUrl}/{$fy->id}/close")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'DRAFT_ENTRIES')
            ->assertJsonPath('error.message', 'There are still draft journal entries in this fiscal year');

        $this->assertFalse($fy->fresh()->is_closed);
    }

    public function test_cannot_close_fiscal_year_with_open_periods(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.close']);

        $fy = $this->createFiscalYear();
        AccountingPeriod::factory()->create(['fiscal_year_id' => $fy->id, 'is_closed' => false]);

        $this->apiPost("{$this->baseUrl}/{$fy->id}/close")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'OPEN_PERIODS')
            ->assertJsonPath('error.message', 'All accounting periods must be closed first');

        $this->assertFalse($fy->fresh()->is_closed);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/fiscal-years/{fiscalYear} - Delete Fiscal Year
    // -------------------------------------------------------------------------

    public function test_delete_fiscal_year_keeps_its_periods_when_the_year_cannot_be_deleted(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.delete']);

        $fy = $this->createFiscalYear();
        $period = AccountingPeriod::factory()->create(['fiscal_year_id' => $fy->id]);

        FiscalYear::deleting(function (): void {
            throw new RuntimeException('Fiscal year could not be deleted.');
        });

        $this->apiDelete("{$this->baseUrl}/{$fy->id}")->assertStatus(500);

        $this->assertDatabaseHas('accounting_periods', ['id' => $period->id]);
        $this->assertDatabaseHas('fiscal_years', ['id' => $fy->id]);
    }

    public function test_can_delete_open_fiscal_year_with_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.delete']);

        $fy = $this->createFiscalYear([
            'name' => 'Deletable FY',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$fy->id}");

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_delete_closed_fiscal_year(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.delete']);

        $closedFy = $this->createFiscalYear([
            'name' => 'Closed FY',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$closedFy->id}");

        $this->assertErrorResponse($response);

        $this->assertDatabaseHas('fiscal_years', ['id' => $closedFy->id]);
    }

    public function test_cannot_delete_fiscal_year_with_journal_entries(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.delete']);

        $fy = $this->createFiscalYear();

        // Create a journal entry in this fiscal year
        JournalEntry::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'fiscal_year_id' => $fy->id,
            'entry_date' => '2025-06-15',
            'description' => 'Test entry',
            'currency_code' => 'SAR',
            'exchange_rate' => 1.0,
            'total_debit' => 0,
            'total_credit' => 0,
            'status' => JournalEntry::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiDelete("{$this->baseUrl}/{$fy->id}");

        $this->assertErrorResponse($response);
    }

    public function test_delete_fiscal_year_requires_permission(): void
    {
        $this->setUpAuthenticatedUser(['accounting.fiscal-years.view']);

        $fy = $this->createFiscalYear();

        $response = $this->apiDelete("{$this->baseUrl}/{$fy->id}");

        $this->assertForbidden($response);
    }
}
