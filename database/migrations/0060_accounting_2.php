<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->enum('account_category', ['balance_sheet', 'profit_loss', 'statistical']);
            $table->string('number_range_from', 20)->nullable();
            $table->string('number_range_to', 20)->nullable();
            $table->boolean('reconciliation_account')->default(false);
            $table->enum('reconciliation_type', ['customer', 'vendor', 'asset', 'none'])->default('none');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('accounting_document_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->enum('account_type', ['asset', 'liability', 'revenue', 'expense', 'equity', 'all'])->default('all');
            $table->string('number_range_code', 20)->nullable();
            $table->boolean('reverse_document_type')->default(false);
            $table->string('reverse_document_type_code', 10)->nullable();
            $table->boolean('require_reference')->default(false);
            $table->boolean('check_duplicate_invoice')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
        });

        Schema::create('bank_guarantees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('guarantee_number', 50);
            $table->enum('guarantee_type', ['bid_bond', 'performance_bond', 'advance_payment', 'retention', 'financial'])
                ->default('performance_bond');
            $table->enum('direction', ['issued', 'received'])->default('issued');
            $table->foreignId('bank_id')->nullable()->constrained('contacts')->name('bg_bank_fk');
            $table->foreignId('beneficiary_id')->nullable()->constrained('contacts')->name('bg_beneficiary_fk');
            $table->foreignId('applicant_id')->nullable()->constrained('contacts')->name('bg_applicant_fk');
            $table->foreignId('related_purchase_order_id')->nullable()->constrained('purchase_orders')->name('bg_po_fk');
            $table->foreignId('related_sales_order_id')->nullable()->constrained('sales_orders')->name('bg_so_fk');
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('amount', 18, 4);
            $table->decimal('bank_charges', 18, 4)->default(0);
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->date('claim_deadline')->nullable();
            $table->enum('status', ['draft', 'active', 'expired', 'claimed', 'returned', 'cancelled'])->default('draft');
            $table->boolean('is_auto_renewed')->default(false);
            $table->unsignedSmallInteger('renewal_period_days')->nullable();
            $table->decimal('claim_amount', 18, 4)->nullable();
            $table->date('claim_date')->nullable();
            $table->text('claim_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'guarantee_number'], 'bg_org_number_unq');
            $table->index(['organization_id', 'status'], 'bg_org_status_idx');
            $table->index(['expiry_date'], 'bg_expiry_idx');
        });

        Schema::create('cash_flow_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_base_case')->default(false);
            $table->json('assumptions')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_base_case'], 'cash_flow_scenarios_org_base_idx');
        });

        Schema::create('cash_flow_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->tinyInteger('horizon_days')->unsigned()->default(90);
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('total_opening_balance', 15, 4)->default(0);
            $table->decimal('total_inflows', 15, 4)->default(0);
            $table->decimal('total_outflows', 15, 4)->default(0);
            $table->decimal('closing_balance', 15, 4)->default(0);
            $table->foreignId('scenario_id')->nullable()->constrained('cash_flow_scenarios')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'forecast_date'], 'cash_flow_forecasts_org_date_idx');
            $table->index(['organization_id', 'scenario_id'], 'cash_flow_forecasts_org_scen_idx');
        });

        Schema::create('cash_flow_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_flow_forecast_id')->constrained('cash_flow_forecasts')->cascadeOnDelete();
            $table->date('expected_date');
            $table->enum('line_type', ['inflow', 'outflow'])->default('inflow');
            $table->string('category', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cash_flow_forecast_id', 'expected_date'], 'cff_lines_forecast_date_idx');
            $table->index(['cash_flow_forecast_id', 'line_type'], 'cff_lines_forecast_type_idx');
            $table->index(['source_type', 'source_id'], 'cff_lines_source_idx');
        });

        Schema::create('cash_flow_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained('cash_flow_forecasts')->cascadeOnDelete();
            $table->date('expected_date');
            $table->enum('flow_type', ['inflow', 'outflow']);
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 200);
            $table->decimal('amount', 15, 4);
            $table->enum('confidence', ['certain', 'probable', 'possible'])->default('probable');
            $table->boolean('is_actual')->default(false);
            $table->timestamps();

            $table->index(['forecast_id', 'expected_date'], 'cash_flow_lines_forecast_date_idx');
            $table->index(['forecast_id', 'flow_type'], 'cash_flow_lines_forecast_type_idx');
            $table->index(['source_type', 'source_id'], 'cash_flow_lines_source_idx');
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Account classification
            $table->enum('account_type', [
                'asset',
                'liability',
                'equity',
                'income',
                'expense',
            ]);

            // More specific sub-types for system behavior
            $table->enum('sub_type', [
                // Assets
                'cash',
                'bank',
                'receivable',
                'inventory',
                'fixed_asset',
                'other_asset',
                // Liabilities
                'payable',
                'credit_card',
                'tax_payable',
                'other_liability',
                // Equity
                'capital',
                'retained_earnings',
                'drawings',
                // Income
                'sales',
                'other_income',
                // Expense
                'cost_of_goods',
                'operating_expense',
                'other_expense',
            ]);

            $table->string('currency_code', 3)->nullable(); // If account is in specific currency
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System accounts can't be deleted
            $table->boolean('is_header')->default(false); // Header accounts can't have transactions

            // For tree structure ordering
            $table->unsignedInteger('level')->default(1);
            $table->string('path', 255)->nullable(); // e.g., "1.2.3" for hierarchy

            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'account_type']);
            $table->index(['organization_id', 'sub_type']);
            $table->index(['organization_id', 'parent_id']);

            $table->foreign('currency_code')->references('code')->on('currencies')->nullOnDelete();
        });

        Schema::create('account_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->decimal('balance', 20, 4)->default(0);
            $table->decimal('debit_total', 20, 4)->default(0);
            $table->decimal('credit_total', 20, 4)->default(0);
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['organization_id', 'account_id']);
            $table->index(['organization_id', 'computed_at']);
        });

        Schema::create('accrual_deferrals', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('reference', 50);
            $table->enum('type', ['accrual', 'deferral']);
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts');
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts');
            $table->decimal('total_amount', 15, 4);
            $table->decimal('per_period_amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('periods_total');
            $table->integer('periods_posted')->default(0);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'ad_org_status_idx');
            $table->index(['organization_id', 'start_date', 'end_date'], 'ad_org_dates_idx');
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_useful_life_years')->default(5);
            $table->enum('default_depreciation_method', [
                'straight_line',
                'declining_balance',
                'units_of_production',
                'sum_of_years_digits',
            ])->default('straight_line');
            $table->decimal('default_salvage_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('gl_asset_account_id')->nullable();
            $table->unsignedBigInteger('gl_depreciation_account_id')->nullable();
            $table->unsignedBigInteger('gl_accumulated_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('gl_asset_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('gl_depreciation_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('gl_accumulated_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Bank details
            $table->string('bank_name', 100);
            $table->string('account_name', 100);
            $table->string('account_number', 50);
            $table->string('iban', 50)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->string('branch_code', 20)->nullable();

            // Currency and account type
            $table->string('currency_code', 3);
            $table->enum('account_type', ['current', 'savings', 'credit_card', 'cash'])->default('current');

            // Link to Chart of Accounts
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Current balance (updated via triggers or calculated)
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->date('last_reconciled_date')->nullable();
            $table->decimal('last_reconciled_balance', 18, 4)->nullable();

            $table->decimal('bank_balance', 15, 2)->default(0); // Last known bank balance
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Bank-specific settings

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'account_number', 'bank_name']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('bank_account_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->enum('request_type', ['open', 'close', 'modify', 'add_signatory', 'remove_signatory'])
                ->default('open');
            $table->enum('status', ['pending', 'approved', 'rejected', 'executed'])->default('pending');
            // Fields for 'open' requests
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_type', 30)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('swift_code', 11)->nullable();
            $table->string('branch_name')->nullable();
            // Generic change payload for modify/signatory requests
            $table->json('request_data')->nullable();
            $table->text('justification')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'request_type']);
        });

        Schema::create('bank_matching_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('match_field', 30); // description, reference, amount
            $table->string('match_type', 20); // contains, starts_with, equals, regex
            $table->string('match_value');
            $table->string('transaction_type', 10)->nullable(); // debit, credit
            $table->string('action', 30); // categorize, match_contact, match_account, exclude
            $table->json('action_data'); // Category, account_id, contact_id, etc.
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('bank_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('position_date');
            $table->decimal('book_balance', 15, 4)->default(0);
            $table->decimal('available_balance', 15, 4)->default(0);
            $table->decimal('projected_balance', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->unique(['organization_id', 'bank_account_id', 'position_date'], 'bp_org_acct_date_unique');
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('book_balance', 15, 2);
            $table->decimal('adjusted_book_balance', 15, 2)->nullable();
            $table->decimal('difference', 15, 2)->default(0);
            $table->string('status', 20)->default('in_progress'); // in_progress, completed, cancelled
            $table->json('summary')->nullable(); // Reconciliation summary
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'status']);
        });

        Schema::create('bank_signatories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->enum('authority_level', ['single', 'joint_any', 'joint_all'])->default('single');
            $table->decimal('signing_limit', 18, 4)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'bank_account_id']);
            $table->index(['bank_account_id', 'is_active']);
        });

        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('file_type', 10); // csv, ofx, qfx, mt940
            $table->date('statement_start_date')->nullable();
            $table->date('statement_end_date')->nullable();
            $table->unsignedInteger('total_transactions')->default(0);
            $table->unsignedInteger('imported_transactions')->default(0);
            $table->unsignedInteger('duplicate_transactions')->default(0);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'created_at']);


                $table->index(['organization_id', 'status'], 'bsi_org_status_idx');


                $table->index(['bank_account_id'], 'bsi_account_idx');
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('description');
            $table->string('transaction_type', 20); // debit, credit
            $table->decimal('amount', 15, 2);
            $table->decimal('balance', 15, 2)->nullable(); // Running balance from bank
            $table->string('status', 20)->default('unmatched'); // unmatched, matched, excluded, reconciled
            $table->string('category')->nullable(); // Auto-categorized
            $table->foreignId('matched_transaction_id')->nullable(); // Link to internal transaction
            $table->string('matched_transaction_type')->nullable(); // payment, receipt, journal, etc.
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->string('import_source', 50)->nullable(); // manual, csv, ofx, api
            $table->string('import_batch_id')->nullable();
            $table->json('raw_data')->nullable(); // Original import data
            $table->timestamps();

            $table->index(['bank_account_id', 'transaction_date']);
            $table->index(['bank_account_id', 'status']);
            $table->index(['organization_id', 'status']);

            $table->foreign('matched_transaction_id', 'bank_txn_matched_pair_fk')
                ->references('id')->on('bank_transactions')->nullOnDelete();
        });

        Schema::create('bank_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->string('item_type', 30); // bank_transaction, outstanding_check, outstanding_deposit, adjustment
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->boolean('is_cleared')->default(false);
            $table->timestamps();

            $table->index(['reconciliation_id', 'is_cleared']);
        });

        Schema::create('check_books', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->name('cb_bank_account_fk');
            $table->string('check_book_number', 50);
            $table->string('from_check_number', 20);
            $table->string('to_check_number', 20);
            $table->string('current_check_number', 20);
            $table->enum('status', ['active', 'exhausted', 'cancelled'])->default('active');
            $table->date('issued_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'check_book_number'], 'cb_org_number_unq');
        });

        Schema::create('distribution_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period_from')->unsigned()->default(1);
            $table->tinyInteger('period_to')->unsigned()->default(12);
            $table->enum('status', ['open', 'executed', 'reversed'])->default('open');
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users', 'id', 'co_dist_cyc_exec_by_fk')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fiscal_year', 'status'], 'co_dist_cyc_org_fy_status_idx');
        });

        Schema::create('collections_worklist', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id');
            $table->decimal('total_overdue', 15, 4)->default(0);
            $table->integer('overdue_days_max')->default(0);
            $table->enum('collections_status', ['new', 'contacted', 'promise_to_pay', 'payment_plan', 'legal', 'written_off'])->default('new');
            $table->date('promise_to_pay_date')->nullable();
            $table->decimal('promise_amount', 15, 4)->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contact_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'collections_status'], 'cw_org_status_idx');
            $table->index(['organization_id', 'contact_id'], 'cw_org_contact_idx');
        });

        Schema::create('consolidation_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('consolidation_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_group_id')->constrained('consolidation_groups')->cascadeOnDelete();
            $table->foreignId('entity_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('ownership_percent', 5, 2)->default(100.00);
            $table->enum('consolidation_method', ['full', 'proportional', 'equity'])->default('full');
            $table->string('local_currency', 3)->nullable();
            $table->timestamps();

            $table->unique(['consolidation_group_id', 'entity_organization_id'], 'consol_ent_group_entity_org_unique');
        });

        Schema::create('copa_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('dimension_type', 50); // product, customer, region, sales_channel, material_group
            $table->string('dimension_value', 100);
            $table->string('dimension_label', 200)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'dimension_type'], 'copa_dim_org_type_idx');
        });

        Schema::create('cost_elements', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->enum('element_type', ['primary', 'secondary']); // primary=GL mapped, secondary=internal
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->enum('cost_element_category', ['general', 'depreciation', 'imputed', 'revenue', 'internal_settlement'])->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code'], 'ce_org_code_unique');
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('unit_of_measure', 20)->default('HR'); // HR=hours, PC=pieces
            $table->foreignId('cost_element_id')->nullable()->constrained('cost_elements')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code'], 'at_org_code_unique');
        });

        Schema::create('cost_repostings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->string('reposting_number', 50);
            $table->unique(['organization_id', 'reposting_number']);

            $table->date('posting_date');
            $table->date('document_date');
            $table->unsignedTinyInteger('period');     // 1–12
            $table->unsignedSmallInteger('fiscal_year');

            // Polymorphic sender
            $table->enum('from_type', ['cost_center', 'internal_order', 'profit_center']);
            $table->unsignedBigInteger('from_id');

            // Polymorphic receiver
            $table->enum('to_type', ['cost_center', 'internal_order', 'profit_center']);
            $table->unsignedBigInteger('to_id');

            $table->foreignId('cost_element_id')->constrained('cost_elements');
            $table->decimal('amount', 15, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->text('narration')->nullable();

            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->foreignId('reversed_by_id')->nullable()->constrained('cost_repostings')->nullOnDelete();

            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cost_splitting_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers')
                ->name('csr_cc_fk');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('csr_ce_fk');
            $table->decimal('fixed_percentage', 5, 2);
            $table->decimal('variable_percentage', 5, 2);
            $table->enum('splitting_basis', ['activity_quantity', 'capacity_utilization', 'manual'])->default('manual');
            $table->boolean('is_active')->default(true);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'cost_center_id'], 'csr_org_cc_idx');
        });

        Schema::create('costing_sheets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('cost_component_structure_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'cs_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unique(['organization_id', 'code'], 'cs_org_code_uq');
            $table->index(['organization_id', 'is_active'], 'cs_org_active_idx');
        });

        Schema::create('costing_sheet_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('costing_sheet_id');
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->dateTime('run_date');
            $table->decimal('total_overhead', 18, 4)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'csrun_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('costing_sheet_id', 'csrun_cs_fk')
                ->references('id')->on('costing_sheets')->onDelete('cascade');
            $table->foreign('created_by', 'csrun_user_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('direct_debit_mandates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('mandate_reference', 50);
            $table->enum('mandate_type', ['core', 'b2b', 'standing_order'])->default('core');
            $table->enum('direction', ['collection', 'payment'])->default('collection');
            $table->foreignId('counterparty_id')->constrained('contacts')->name('ddm_counterparty_fk');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->name('ddm_bank_account_fk');
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('amount', 18, 4)->nullable();
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'quarterly', 'annually', 'one_time'])
                ->default('monthly');
            $table->date('first_collection_date')->nullable();
            $table->date('next_collection_date')->nullable();
            $table->date('last_collection_date')->nullable();
            $table->unsignedInteger('total_collections')->default(0);
            $table->unsignedInteger('max_collections')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'cancelled', 'expired'])->default('draft');
            $table->date('signed_date')->nullable();
            $table->date('cancellation_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'mandate_reference'], 'ddm_org_ref_unq');
            $table->index(['organization_id', 'status', 'next_collection_date'], 'ddm_org_status_next_idx');
        });

        Schema::create('dispute_cases', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('case_number', 30);
            $table->enum('document_type', ['invoice', 'payment_received', 'credit_note']);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('contact_id');
            $table->decimal('disputed_amount', 15, 4);
            $table->decimal('resolved_amount', 15, 4)->default(0);
            $table->enum('dispute_reason', ['pricing', 'quality', 'quantity', 'delivery', 'duplicate', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_review', 'escalated', 'resolved', 'closed'])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'case_number'], 'dc_org_case_unique');
            $table->index(['organization_id', 'status'], 'dc_org_status_idx');
            $table->index(['contact_id', 'status'], 'dc_contact_status_idx');
        });

        Schema::create('document_splitting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('split_method', ['profit_center', 'segment', 'cost_center', 'business_area'])
                ->default('profit_center');
            $table->string('base_item_category', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(10);
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'dsr_org_active_idx');
        });

        Schema::create('dunning_levels', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->tinyInteger('level_number')->unsigned();
            $table->string('name', 100);
            $table->smallInteger('days_overdue_from')->unsigned();
            $table->smallInteger('days_overdue_to')->unsigned()->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('dunning_fee', 15, 4)->default(0);
            $table->boolean('is_legal_action')->default(false);
            $table->foreignId('email_template_id')->nullable()->constrained('import_templates')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'level_number'], 'dunning_levels_org_level_unique');
            $table->index(['organization_id', 'is_active'], 'dunning_levels_org_active_idx');
        });

        Schema::create('dunning_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('run_date');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->unsignedInteger('total_customers')->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'run_date'], 'dunning_runs_org_date_idx');
            $table->index(['organization_id', 'status'], 'dunning_runs_org_status_idx');
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->timestamps();

            $table->unique(['organization_id', 'from_currency', 'to_currency', 'rate_date'], 'exchange_rate_unique');
            $table->index(['organization_id', 'rate_date']);

            $table->foreign('from_currency')->references('code')->on('currencies');
            $table->foreign('to_currency')->references('code')->on('currencies');
        });

        Schema::create('financial_close_templates', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('close_type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_fct_org')
                ->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('financial_close_periods', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('financial_close_template_id')->nullable();
            $table->smallInteger('fiscal_year');
            $table->tinyInteger('period');
            $table->string('close_type', 20);
            $table->string('status', 20)->default('open');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_fcp_org')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('financial_close_template_id', 'fk_fcp_template')
                ->references('id')->on('financial_close_templates')->onDelete('set null');
            $table->foreign('closed_by', 'fk_fcp_closed_by')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'fiscal_year', 'period'], 'idx_fcp_org_period');


                $table->unsignedBigInteger('signed_off_by')->nullable();
                $table->foreign('signed_off_by', 'fk_fcp_signoff_user')
                    ->references('id')->on('users')->nullOnDelete();


                $table->dateTime('signed_off_at')->nullable();


                $table->text('sign_off_notes')->nullable();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('financial_close_periods');
        Schema::dropIfExists('financial_close_templates');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('dunning_runs');
        Schema::dropIfExists('dunning_levels');
        Schema::dropIfExists('document_splitting_rules');
        Schema::dropIfExists('dispute_cases');
        Schema::dropIfExists('direct_debit_mandates');
        Schema::dropIfExists('costing_sheet_runs');
        Schema::dropIfExists('costing_sheets');
        Schema::dropIfExists('cost_splitting_rules');
        Schema::dropIfExists('cost_repostings');
        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('cost_elements');
        Schema::dropIfExists('copa_dimensions');
        Schema::dropIfExists('consolidation_entities');
        Schema::dropIfExists('consolidation_groups');
        Schema::dropIfExists('collections_worklist');
        Schema::dropIfExists('distribution_cycles');
        Schema::dropIfExists('check_books');
        Schema::dropIfExists('bank_reconciliation_items');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('bank_signatories');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_positions');
        Schema::dropIfExists('bank_matching_rules');
        Schema::dropIfExists('bank_account_requests');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('accrual_deferrals');
        Schema::dropIfExists('account_balance_snapshots');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('cash_flow_lines');
        Schema::dropIfExists('cash_flow_forecast_lines');
        Schema::dropIfExists('cash_flow_forecasts');
        Schema::dropIfExists('cash_flow_scenarios');
        Schema::dropIfExists('bank_guarantees');
        Schema::dropIfExists('accounting_document_types');
        Schema::dropIfExists('accounting_account_groups');
    }
};
