<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ChecksOwnedReferences;
use Tests\Traits\TestHelpers;

/**
 * Ledger, closing, reporting and tax requests accept only the caller's
 * organization's accounts, fiscal years, branches, users and other rows.
 */
class LedgerOwnedReferencesTest extends TestCase
{
    use ChecksOwnedReferences;
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.accounts.create',
            'accounting.accruals.manage',
            'accounting.carry-forward.manage',
            'accounting.journals.create',
            'accounting.fsv.manage',
            'accounting.reports.view',
            'accounting.multi-currency.manage',
            'accounting.wht.manage',
            'accounting.financial-close.manage',
            'accounting.xbrl.create',
            'accounting.consolidation.edit',
        ]);
    }

    public function test_account_parent(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/accounts', ['parent_id' => 'chart_of_accounts']);
    }

    public function test_accrual_deferral_accounts(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/accruals-deferrals', [
            'debit_account_id' => 'chart_of_accounts',
            'credit_account_id' => 'chart_of_accounts',
        ]);
    }

    public function test_carry_forward_fiscal_years(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/carry-forward/execute', [
            'from_fiscal_year_id' => 'fiscal_years',
            'to_fiscal_year_id' => 'fiscal_years',
        ]);
    }

    public function test_journal_entry_branch(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/journal-entries', ['branch_id' => 'branches']);
    }

    public function test_financial_statement_version_node_parent(): void
    {
        $version = $this->ownRow('financial_statement_versions');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/financial-statement-versions/{$version}/nodes", [
            'parent_id' => 'financial_statement_version_nodes',
        ]);
    }

    public function test_report_fiscal_year(): void
    {
        foreach (['trial-balance', 'balance-sheet', 'income-statement'] as $report) {
            $this->assertOnlyOwnRowsAccepted('GET', "/api/v1/reports/{$report}", ['fiscal_year_id' => 'fiscal_years']);
        }
    }

    public function test_multi_currency_accounts(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/multi-currency/currencies', [
            'exchange_gain_account_id' => 'chart_of_accounts',
            'exchange_loss_account_id' => 'chart_of_accounts',
            'rounding_account_id' => 'chart_of_accounts',
        ]);
    }

    public function test_currency_revaluation_accounts_and_contacts(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/multi-currency/revaluations', [
            'gain_loss_account_id' => 'chart_of_accounts',
            'accounts.*.account_id' => 'chart_of_accounts',
            'accounts.*.contact_id' => 'contacts',
        ]);

        $revaluation = $this->ownRow('currency_revaluations');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/multi-currency/revaluations/{$revaluation}/post", [
            'gain_loss_account_id' => 'chart_of_accounts',
        ]);
    }

    public function test_withholding_tax_code_accounts(): void
    {
        $references = [
            'payable_account_id' => 'chart_of_accounts',
            'receivable_account_id' => 'chart_of_accounts',
        ];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/withholding-tax/codes', $references);

        $code = $this->ownRow('withholding_tax_codes');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/withholding-tax/codes/{$code}", $references);

        $payable = $this->ownRow('chart_of_accounts');
        $receivable = $this->ownRow('chart_of_accounts');

        $this->withToken($this->token)
            ->postJson('/api/v1/withholding-tax/codes', [
                'code' => 'WHT15',
                'name' => '15% WHT Supplier',
                'applicable_to' => 'supplier',
                'rate' => 15.00,
                'payable_account_id' => $payable,
                'receivable_account_id' => $receivable,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('withholding_tax_codes', [
            'organization_id' => $this->organization->id,
            'code' => 'WHT15',
            'payable_account_id' => $payable,
            'receivable_account_id' => $receivable,
        ]);
    }

    public function test_financial_close_template_and_assignee(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/financial-close/periods', [
            'financial_close_template_id' => 'financial_close_templates',
        ]);

        $task = $this->ownRow('financial_close_tasks');

        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/financial-close/tasks/{$task}/assign", ['assigned_to' => 'users']);
    }

    public function test_xbrl_filing_fiscal_year_and_taxonomy(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/xbrl/filings', [
            'fiscal_year_id' => 'fiscal_years',
            'taxonomy_id' => 'xbrl_taxonomies',
        ]);
    }

    public function test_consolidation_elimination_accounts(): void
    {
        $period = $this->ownRow('consolidation_periods');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/consolidation/periods/{$period}/eliminations", [
            'debit_account_id' => 'chart_of_accounts',
            'credit_account_id' => 'chart_of_accounts',
        ]);
    }
}
