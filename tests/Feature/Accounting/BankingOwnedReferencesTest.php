<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ChecksOwnedReferences;
use Tests\Traits\TestHelpers;

/**
 * Banking, payment, treasury and loan requests accept only the caller's
 * organization's bank accounts, contacts, accounts, users and other rows.
 */
class BankingOwnedReferencesTest extends TestCase
{
    use ChecksOwnedReferences;
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.bank-reconciliation.create',
            'accounting.bank-reconciliation.update',
            'accounting.bank-reconciliation.import',
            'accounting.check-books.manage',
            'accounting.checks.manage',
            'accounting.direct-debit.manage',
            'accounting.bank-accounts.manage',
            'accounting.transfers.create',
            'accounting.payment-files.manage',
            'accounting.payment-runs.manage',
            'accounting.tolerance.manage',
            'accounting.petty-cash.manage',
            'accounting.petty-cash.create',
            'accounting.treasury.manage',
            'accounting.loans.create',
            'accounting.loans.payment',
            'accounting.cash-flow.generate',
            'accounting.bank-guarantees.manage',
        ]);
    }

    public function test_bank_reconciliation_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/bank-reconciliation/bank-reconciliations', [
            'bank_account_id' => 'bank_accounts',
        ]);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/bank-reconciliation/bank-statement-imports', [
            'bank_account_id' => 'bank_accounts',
        ]);

        $reconciliation = $this->ownRow('bank_reconciliations');
        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/bank-reconciliation/bank-reconciliations/{$reconciliation}/manual-match", [
            'bank_transaction_id' => 'bank_transactions',
        ]);
    }

    public function test_check_book_and_check_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/check-books', ['bank_account_id' => 'bank_accounts']);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/checks', [
            'check_book_id' => 'check_books',
            'payee_id' => 'contacts',
            'payment_made_id' => 'payments_made',
            'payment_received_id' => 'payments_received',
        ]);
    }

    public function test_direct_debit_mandate_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/direct-debit/mandates', [
            'counterparty_id' => 'contacts',
            'bank_account_id' => 'bank_accounts',
        ]);
    }

    public function test_bank_account_management_references(): void
    {
        $bankAccount = $this->ownRow('bank_accounts');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/bank-accounts/{$bankAccount}/signatories", ['user_id' => 'users']);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/bank-account-requests', ['bank_account_id' => 'bank_accounts']);
    }

    public function test_inter_company_transfer_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/loans/inter-company-transfers', [
            'from_branch_id' => 'branches',
            'from_bank_account_id' => 'bank_accounts',
            'to_branch_id' => 'branches',
            'to_bank_account_id' => 'bank_accounts',
            'loan_id' => 'loans',
        ]);
    }

    public function test_payment_run_and_file_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/payment-runs', ['bank_account_id' => 'bank_accounts']);

        $run = $this->ownRow('payment_runs', ['status' => 'draft']);
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/payment-runs/{$run}", ['bank_account_id' => 'bank_accounts']);

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/payment-files/generate', ['payment_run_id' => 'payment_runs']);
    }

    public function test_payment_tolerance_accounts(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/payment-tolerance/groups', [
            'items.*.underpay_gl_account_id' => 'chart_of_accounts',
            'items.*.overpay_gl_account_id' => 'chart_of_accounts',
        ]);

        $group = $this->ownRow('payment_tolerance_groups');
        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/payment-tolerance/groups/{$group}/items", [
            'underpay_gl_account_id' => 'chart_of_accounts',
            'overpay_gl_account_id' => 'chart_of_accounts',
        ]);
    }

    public function test_petty_cash_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/petty-cash/funds', [
            'branch_id' => 'branches',
            'custodian_id' => 'users',
            'account_id' => 'chart_of_accounts',
        ]);

        $fund = $this->ownRow('petty_cash_funds');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/petty-cash/funds/{$fund}", ['custodian_id' => 'users']);
        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/petty-cash/funds/{$fund}/vouchers", ['account_id' => 'chart_of_accounts']);
    }

    public function test_treasury_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/treasury/investments', [
            'bank_account_id' => 'bank_accounts',
            'gl_account_id' => 'chart_of_accounts',
        ]);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/treasury/liquidity-plans', [
            'lines.*.bank_account_id' => 'bank_accounts',
        ]);
    }

    public function test_loan_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/loans/loans', [
            'branch_id' => 'branches',
            'employee_id' => 'employees',
            'contact_id' => 'contacts',
            'lender_contact_id' => 'contacts',
            'loan_account_id' => 'chart_of_accounts',
            'interest_account_id' => 'chart_of_accounts',
            'bank_account_id' => 'bank_accounts',
        ]);

        // A schedule has no organization column; it belongs to its loan's organization.
        $loan = $this->ownRow('loans');
        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/loans/loans/{$loan}/payments", ['schedule_id' => 'loan_schedules']);
    }

    public function test_cash_flow_forecast_scenario(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/cash-flow/forecasts/generate', ['scenario_id' => 'cash_flow_scenarios']);
    }

    public function test_bank_guarantee_references(): void
    {
        $references = [
            'bank_id' => 'contacts',
            'beneficiary_id' => 'contacts',
            'applicant_id' => 'contacts',
            'related_purchase_order_id' => 'purchase_orders',
            'related_sales_order_id' => 'sales_orders',
        ];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/bank-guarantees', $references);

        $guarantee = $this->ownRow('bank_guarantees');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/bank-guarantees/{$guarantee}", $references);

        $bank = $this->ownRow('contacts');
        $salesOrder = $this->ownRow('sales_orders');

        $this->withToken($this->token)
            ->postJson('/api/v1/bank-guarantees', [
                'guarantee_number' => 'BG-TEST-001',
                'guarantee_type' => 'bid_bond',
                'amount' => 10000.00,
                'issue_date' => '2026-01-01',
                'expiry_date' => '2026-06-30',
                'bank_id' => $bank,
                'related_sales_order_id' => $salesOrder,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('bank_guarantees', [
            'organization_id' => $this->organization->id,
            'guarantee_number' => 'BG-TEST-001',
            'bank_id' => $bank,
            'related_sales_order_id' => $salesOrder,
        ]);
    }
}
