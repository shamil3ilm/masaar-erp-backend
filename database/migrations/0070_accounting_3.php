<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_close_template_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('financial_close_template_id');
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->string('task_type', 50);
            $table->integer('sort_order')->default(0);
            $table->decimal('estimated_duration_hours', 4, 1)->nullable();
            $table->string('required_role', 100)->nullable();
            $table->timestamps();

            $table->foreign('financial_close_template_id', 'fk_fctt_template')
                ->references('id')->on('financial_close_templates')->onDelete('cascade');
        });

        Schema::create('financial_close_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('financial_close_period_id');
            $table->unsignedBigInteger('template_task_id')->nullable();
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->string('task_type', 50);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status', 20)->default('pending');
            $table->date('due_date')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('financial_close_period_id', 'fk_fctask_period')
                ->references('id')->on('financial_close_periods')->onDelete('cascade');
            $table->foreign('template_task_id', 'fk_fctask_tmpl_task')
                ->references('id')->on('financial_close_template_tasks')->onDelete('set null');
            $table->foreign('assigned_to', 'fk_fctask_assigned')
                ->references('id')->on('users')->onDelete('set null');
            $table->foreign('completed_by', 'fk_fctask_completed_by')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['financial_close_period_id', 'status'], 'idx_fctask_period_status');
            $table->index(['assigned_to', 'status'], 'idx_fctask_assignee_status');
        });

        Schema::create('financial_statement_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['balance_sheet', 'income_statement', 'cash_flow']);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'type', 'is_active'], 'financial_statement_versions_org_type_active_idx');
        });

        Schema::create('financial_statement_version_nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fsv_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->enum('node_type', ['header', 'account', 'total']);
            $table->string('label', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->tinyInteger('sign')->default(1);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('fsv_id')->references('id')->on('financial_statement_versions')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('financial_statement_version_nodes')->nullOnDelete();
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->index(['fsv_id', 'parent_id', 'sort_order'], 'fin_stmt_version_nodes_fsv_parent_sort_idx');
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50); // e.g., "FY 2024-25"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'is_current']);
            $table->index(['organization_id', 'start_date', 'end_date']);
        });

        Schema::create('account_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'fiscal_year_id']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number'); // 1-12 for months, 1-4 for quarters
            $table->string('period_type', 10)->default('month'); // month, quarter
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'period_number', 'period_type'], 'acct_periods_fy_num_type_unique');
        });

        Schema::create('carry_forward_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('from_fiscal_year_id')->constrained('fiscal_years');
            $table->foreignId('to_fiscal_year_id')->constrained('fiscal_years');
            $table->enum('run_type', ['balance_sheet', 'profit_loss', 'both'])->default('both');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->integer('accounts_processed')->default(0);
            $table->decimal('total_amount_carried', 15, 4)->default(0);
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'from_fiscal_year_id'], 'cfr_org_fy_idx');
        });

        Schema::create('consolidation_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('consolidation_group_id')->constrained('consolidation_groups')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['open', 'in_progress', 'completed'])->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('consolidation_group_id');
        });

        Schema::create('consolidated_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_period_id')
                ->constrained('consolidation_periods')
                ->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->foreignId('entity_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->decimal('local_amount', 15, 4);
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->decimal('consolidated_amount', 15, 4);
            $table->timestamps();

            $table->unique(
                ['consolidation_period_id', 'account_id', 'entity_organization_id'],
                'consolidated_balances_unique'
            );
        });

        Schema::create('copa_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('version_name', 100);
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'fiscal_year_id', 'version_name'], 'cpv_org_fy_name_unique');
        });

        Schema::create('depreciation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fiscal_year_id');
            $table->date('run_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', [
                'pending',
                'processing',
                'posted',
                'reversed',
            ])->default('pending');
            $table->unsignedInteger('total_assets')->default(0);
            $table->decimal('total_depreciation', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->restrictOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('asset_category_id');
            $table->string('asset_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', [
                'active',
                'disposed',
                'written_off',
                'under_maintenance',
            ])->default('active');
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 15, 4);
            $table->decimal('salvage_value', 15, 4)->default(0);
            $table->decimal('useful_life_years', 5, 2);
            $table->enum('depreciation_method', [
                'straight_line',
                'declining_balance',
                'units_of_production',
                'sum_of_years_digits',
            ])->default('straight_line');
            $table->decimal('accumulated_depreciation', 15, 4)->default(0);
            $table->decimal('book_value', 15, 4);
            $table->date('last_depreciation_date')->nullable();
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 15, 4)->nullable();
            $table->string('disposal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('asset_category_id')->references('id')->on('asset_categories')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['organization_id', 'asset_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'asset_category_id']);

            $table->boolean('is_auc')->default(false)
                ->comment('Asset Under Construction flag (SAP AuC)');
            $table->decimal('auc_settled_amount', 15, 4)->default(0)
                ->comment('Cumulative amount already settled to final assets');
            $table->timestamp('auc_settled_at')->nullable()
                ->comment('Timestamp of final full settlement');
        });

        Schema::create('house_banks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 20);                        // e.g. "RIYAD", "SABB"
            $table->string('name', 200);
            $table->string('bank_name', 200)->nullable();
            $table->string('bank_country', 3)->nullable();     // ISO alpha-2/3
            $table->string('swift_code', 11)->nullable();
            $table->string('routing_number', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('house_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('house_bank_id');
            $table->unsignedBigInteger('bank_account_id')->nullable(); // optional FK to bank_accounts
            $table->string('account_id_code', 20);              // SAP: account ID within house bank
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('account_purpose', ['payments', 'collections', 'both'])->default('both');
            $table->decimal('daily_payment_limit', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['house_bank_id', 'account_id_code']);
            $table->foreign('house_bank_id')->references('id')->on('house_banks')->cascadeOnDelete();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();
        });

        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            // Source document
            $table->string('document_type', 30);           // invoice | bill | sales_order
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            // Plan totals
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('total_amount', 18, 4);
            $table->decimal('total_paid', 18, 4)->default(0);
            $table->decimal('outstanding', 18, 4);         // total_amount - total_paid
            $table->integer('installment_count');
            // Status
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type', 'document_id'], 'inst_plans_doc_idx');
            $table->index(['organization_id', 'contact_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();

            // Entry identification
            $table->string('entry_number', 50);
            $table->date('entry_date');
            $table->string('reference', 100)->nullable(); // External reference
            $table->text('description')->nullable();

            // Source document (invoice, bill, payment, etc.)
            $table->string('source_type', 50)->nullable(); // invoice, bill, payment, manual
            $table->unsignedBigInteger('source_id')->nullable();

            // Currency handling
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 18, 8)->default(1);

            // Totals (must be equal for balanced entry)
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);

            // Status workflow
            $table->enum('status', ['draft', 'posted', 'voided'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            // Reversal tracking
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'entry_number']);
            $table->index(['organization_id', 'entry_date']);
            $table->index(['organization_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['organization_id', 'fiscal_year_id']);

            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        Schema::create('asset_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('component_number', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 15, 4);
            $table->decimal('salvage_value', 15, 4)->default(0);
            $table->decimal('useful_life_years', 8, 2)->default(0);
            $table->decimal('accumulated_depreciation', 15, 4)->default(0);
            $table->decimal('book_value', 15, 4)->default(0);
            $table->string('depreciation_method', 50)->nullable();
            $table->enum('status', ['active', 'retired', 'transferred'])->default('active');
            $table->date('retirement_date')->nullable();
            $table->decimal('retirement_amount', 15, 4)->nullable();
            $table->string('retirement_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fixed_asset_id', 'component_number']);
            $table->index(['organization_id', 'fixed_asset_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('asset_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fixed_asset_id');
            $table->enum('transaction_type', [
                'acquisition',
                'depreciation',
                'impairment',
                'revaluation',
                'partial_disposal',
                'full_disposal',
                'write_off',
                'transfer',
            ]);
            $table->date('transaction_date');
            $table->decimal('amount', 15, 4);
            $table->string('description');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'fixed_asset_id']);
            $table->index(['organization_id', 'transaction_type']);
        });

        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('transfer_number', 50);

            // Sending side
            $table->unsignedBigInteger('sending_organization_id');
            $table->foreign('sending_organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->cascadeOnDelete();

            // Receiving side
            $table->unsignedBigInteger('receiving_organization_id');
            $table->foreign('receiving_organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('receiving_asset_id')->nullable();
            $table->foreign('receiving_asset_id')->references('id')->on('fixed_assets')->nullOnDelete();

            // Transfer details
            $table->date('transfer_date');
            $table->enum('transfer_type', ['book_value', 'gross_value', 'negotiated_price'])->default('book_value');

            // Asset values at transfer date
            $table->decimal('gross_value', 18, 4)->comment('Original acquisition cost');
            $table->decimal('accumulated_depreciation', 18, 4)->default(0);
            $table->decimal('net_book_value', 18, 4)->comment('book value = gross - accum_dep');
            $table->decimal('transfer_price', 18, 4)->nullable()->comment('For negotiated_price type');
            $table->decimal('gain_loss_amount', 18, 4)->default(0);

            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->string('cancellation_reason', 500)->nullable();

            // GL journal references
            $table->unsignedBigInteger('sending_journal_id')->nullable();
            $table->foreign('sending_journal_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->unsignedBigInteger('receiving_journal_id')->nullable();
            $table->foreign('receiving_journal_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sending_organization_id', 'transfer_number']);
            $table->index(['sending_organization_id', 'status']);
            $table->index(['receiving_organization_id', 'status']);
        });

        Schema::create('currency_revaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('revaluation_number', 30);
            $table->date('revaluation_date');
            $table->string('currency_code', 3); // The foreign currency being revalued
            $table->decimal('old_rate', 15, 8); // Previous exchange rate
            $table->decimal('new_rate', 15, 8); // New exchange rate
            $table->string('base_currency', 3); // Org's base currency

            // Impact
            $table->decimal('total_unrealized_gain', 18, 4)->default(0);
            $table->decimal('total_unrealized_loss', 18, 4)->default(0);
            $table->decimal('net_gain_loss', 18, 4)->default(0);

            // Account for gain/loss
            $table->foreignId('gain_loss_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->string('status', 20)->default('draft'); // draft, posted, reversed
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'revaluation_number']);
            $table->index(['organization_id', 'revaluation_date']);
            $table->index(['organization_id', 'currency_code']);
        });

        Schema::create('depreciation_run_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('depreciation_run_id');
            $table->unsignedBigInteger('fixed_asset_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_book_value', 15, 4);
            $table->decimal('depreciation_amount', 15, 4);
            $table->decimal('closing_book_value', 15, 4);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('depreciation_run_id')->references('id')->on('depreciation_runs')->cascadeOnDelete();
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->index(['depreciation_run_id', 'fixed_asset_id']);
        });

        Schema::create('elimination_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('consolidation_period_id')->constrained('consolidation_periods')->cascadeOnDelete();
            $table->enum('entry_type', [
                'intercompany_receivable',
                'intercompany_payable',
                'dividend',
                'investment',
                'other',
            ])->default('intercompany_receivable');
            $table->string('description');
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts');
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'consolidation_period_id'], 'elim_entries_org_period_idx');
        });

        Schema::create('forex_gain_loss_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 20); // realized, unrealized
            $table->string('transaction_type', 20); // payment, receipt, transfer, revaluation

            // Source
            $table->string('source_type', 100); // PaymentReceived, PaymentMade, BankTransfer
            $table->unsignedBigInteger('source_id');

            // Currencies
            $table->string('foreign_currency', 3);
            $table->string('base_currency', 3);
            $table->decimal('foreign_amount', 18, 4);
            $table->decimal('original_rate', 15, 8); // Rate when invoice/bill was created
            $table->decimal('settlement_rate', 15, 8); // Rate at payment time
            $table->decimal('gain_loss_amount', 18, 4); // In base currency (positive = gain)

            // Accounting
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['organization_id', 'transaction_date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['entry_type']);
        });

        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('installment_plan_id');
            $table->integer('installment_number');         // 1, 2, 3 …
            $table->decimal('amount', 18, 4);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived'])->default('pending');
            $table->unsignedBigInteger('payment_id')->nullable();   // FK to payments_received / payments_made
            $table->string('payment_type', 50)->nullable();         // payment_received | payment_made
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['installment_plan_id', 'installment_number'], 'inst_sched_plan_num_uq');
            $table->index('due_date');
            $table->foreign('installment_plan_id')->references('id')->on('installment_plans')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('lease_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->string('lease_number', 50);
            $table->unique(['organization_id', 'lease_number']);

            // Lease parties
            $table->enum('party_role', ['lessee', 'lessor'])->default('lessee');
            $table->string('asset_description');
            $table->string('lessor_name')->nullable();
            $table->string('lessor_contact', 200)->nullable();

            // Dates & terms
            $table->date('commencement_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('lease_term_months');

            // Payment terms
            $table->decimal('payment_amount', 18, 4);
            $table->enum('payment_frequency', ['monthly', 'quarterly', 'semi_annual', 'annual'])->default('monthly');
            $table->string('currency_code', 3)->default('SAR');

            // Discount rate (IBR or implicit rate)
            $table->decimal('discount_rate', 10, 6)->comment('Annual discount rate, e.g. 0.05 for 5%');

            // IFRS 16 classification
            $table->enum('classification', ['finance', 'operating', 'short_term', 'low_value'])->default('finance');

            // Calculated present values (set on creation)
            $table->decimal('initial_rou_asset', 18, 4)->default(0);
            $table->decimal('initial_lease_liability', 18, 4)->default(0);
            $table->decimal('current_lease_liability', 18, 4)->default(0);

            // GL account linkage
            $table->unsignedBigInteger('rou_asset_account_id')->nullable();
            $table->foreign('rou_asset_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('accum_depreciation_account_id')->nullable();
            $table->foreign('accum_depreciation_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('lease_liability_account_id')->nullable();
            $table->foreign('lease_liability_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('interest_expense_account_id')->nullable();
            $table->foreign('interest_expense_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
            $table->foreign('depreciation_expense_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->enum('status', ['active', 'terminated', 'expired', 'modified'])->default('active');
            $table->date('termination_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'commencement_date']);
        });

        Schema::create('lease_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lease_contract_id');
            $table->foreign('lease_contract_id')->references('id')->on('lease_contracts')->cascadeOnDelete();

            $table->unsignedSmallInteger('period_number');
            $table->date('payment_date');

            $table->decimal('opening_balance', 18, 4);
            $table->decimal('payment_amount', 18, 4);
            $table->decimal('interest_portion', 18, 4);
            $table->decimal('principal_portion', 18, 4);
            $table->decimal('closing_balance', 18, 4);

            // Depreciation of ROU asset for finance leases
            $table->decimal('rou_depreciation', 18, 4)->default(0);

            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->timestamps();

            $table->unique(['lease_contract_id', 'period_number']);
            $table->index(['lease_contract_id', 'is_posted']);
        });

        Schema::create('liquidity_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('plan_name', 100);
            $table->date('plan_from');
            $table->date('plan_to');
            $table->enum('granularity', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->timestamps();
            $table->index(['organization_id'], 'lp_org_idx');
        });

        Schema::create('liquidity_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidity_plan_id')->constrained('liquidity_plans')->cascadeOnDelete();
            $table->date('period_date');
            $table->string('category', 100);
            $table->enum('flow_type', ['inflow', 'outflow'])->default('inflow');
            $table->decimal('planned_amount', 15, 4)->default(0);
            $table->decimal('actual_amount', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->timestamps();
            $table->index(['liquidity_plan_id', 'period_date'], 'lpl_plan_date_idx');
        });

        Schema::create('material_ledger_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('product_id')->constrained('products')->name('mlr_product_fk');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->name('mlr_warehouse_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('opening_stock_qty', 18, 4)->default(0);
            $table->decimal('opening_stock_value', 18, 4)->default(0);
            $table->decimal('closing_stock_qty', 18, 4)->default(0);
            $table->decimal('closing_stock_value', 18, 4)->default(0);
            $table->decimal('cumulative_receipts_qty', 18, 4)->default(0);
            $table->decimal('cumulative_receipts_value', 18, 4)->default(0);
            $table->decimal('cumulative_issues_qty', 18, 4)->default(0);
            $table->decimal('cumulative_issues_value', 18, 4)->default(0);
            $table->decimal('standard_price', 18, 4)->default(0);
            $table->decimal('actual_price', 18, 4)->default(0);
            $table->decimal('price_difference', 18, 4)->default(0);
            $table->unsignedInteger('price_unit')->default(1);
            $table->char('currency_code', 3)->default('SAR');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'product_id', 'warehouse_id', 'period', 'fiscal_year'],
                'mlr_org_prod_wh_per_fy_unq'
            );
            $table->index(['organization_id', 'period', 'fiscal_year'], 'mlr_org_period_fy_idx');
        });

        Schema::create('organization_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->boolean('is_base_currency')->default(false);
            $table->boolean('is_active')->default(true);

            // Default accounts for this currency
            $table->foreignId('exchange_gain_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('exchange_loss_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('rounding_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Rounding
            $table->decimal('rounding_precision', 8, 4)->default(0.01);
            $table->string('rounding_method', 10)->default('round'); // round, ceil, floor

            $table->timestamps();

            $table->unique(['organization_id', 'currency_code']);
        });

        Schema::create('overhead_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 30);
            $table->string('name');
            $table->string('overhead_type', 20)->default('percentage');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'ok_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unique(['organization_id', 'code'], 'ok_org_code_uq');
        });

        Schema::create('parked_documents', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('reference', 50)->nullable();
            $table->date('document_date');
            $table->date('posting_date');
            $table->json('document_data');
            $table->decimal('total_debit', 15, 4)->default(0);
            $table->decimal('total_credit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->text('parking_reason')->nullable();
            $table->foreignId('parked_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['parked', 'pending_approval', 'posted', 'rejected'])->default('parked');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'pd_org_status_idx');
            $table->index(['organization_id', 'document_date'], 'pd_org_date_idx');
        });

        Schema::create('payment_advices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('advice_number', 100)->nullable();   // auto-generated
            $table->enum('direction', ['outgoing', 'incoming'])->default('outgoing');
            // Linked payment
            $table->string('payment_type', 50)->nullable();     // payment_received | payment_made
            $table->unsignedBigInteger('payment_id')->nullable();
            // Bank details
            $table->unsignedBigInteger('house_bank_id')->nullable();
            $table->unsignedBigInteger('house_bank_account_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            // Amounts
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('amount', 18, 4);
            $table->date('payment_date');
            $table->string('reference', 200)->nullable();       // bank reference / UTR
            $table->string('narration', 500)->nullable();
            // Status
            $table->enum('status', ['draft', 'sent', 'acknowledged', 'cancelled'])->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'payment_date']);
            $table->index(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'payment_type', 'payment_id'], 'pay_adv_org_pmt_idx');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('house_bank_id')->references('id')->on('house_banks')->nullOnDelete();
            $table->foreign('house_bank_account_id')->references('id')->on('house_bank_accounts')->nullOnDelete();
        });

        Schema::create('payment_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('run_reference', 50);
            $table->enum('payment_direction', ['outgoing', 'incoming'])->default('outgoing');
            $table->date('payment_date');
            $table->date('due_date_from')->nullable();
            $table->date('due_date_to')->nullable();
            $table->json('vendor_filter')->nullable();
            $table->json('payment_methods')->nullable();
            $table->decimal('minimum_payment', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'proposed', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->integer('total_items')->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'run_reference'], 'pr_org_ref_unique');
            $table->index(['organization_id', 'status'], 'payment_runs_org_status_idx');
        });

        Schema::create('payment_files', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('payment_run_id')->nullable();
            $table->string('file_format', 20);
            $table->string('file_name');
            $table->longText('file_content');
            $table->string('message_id', 50);
            $table->dateTime('creation_datetime');
            $table->integer('number_of_transactions')->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('generated');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_pf_org')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('payment_run_id', 'fk_pf_payment_run')
                ->references('id')->on('payment_runs')->onDelete('set null');
            $table->foreign('created_by', 'fk_pf_created_by')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'status'], 'idx_pf_org_status');
            $table->index('payment_run_id', 'idx_pf_run');
        });

        Schema::create('payment_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_run_id')->constrained('payment_runs')->cascadeOnDelete();
            $table->enum('document_type', ['bill', 'purchase_order', 'invoice']);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->decimal('open_amount', 15, 4);
            $table->decimal('payment_amount', 15, 4);
            $table->decimal('discount_taken', 15, 4)->default(0);
            $table->date('due_date')->nullable();
            $table->enum('status', ['proposed', 'included', 'excluded', 'paid'])->default('proposed');
            $table->string('exclusion_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['payment_run_id', 'status'], 'pri_run_status_idx');
            $table->index(['document_type', 'document_id'], 'pri_doc_idx');
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedTinyInteger('net_days')
                ->default(30)
                ->comment('Payment due in N days');
            $table->unsignedTinyInteger('discount_days')
                ->default(0)
                ->comment('Days within which discount applies');
            $table->decimal('discount_pct', 5, 2)
                ->default(0)
                ->comment('Cash discount percentage');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('payment_tolerance_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 20);                        // e.g. "DEFAULT", "KEY-ACC"
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->enum('applies_to', ['customer', 'supplier', 'both'])->default('both');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('payment_difference_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('tolerance_group_id');
            // Source (the payment being cleared)
            $table->string('payment_type', 50);               // payment_received | payment_made
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            // Cleared document (invoice / bill)
            $table->string('document_type', 50)->nullable();  // invoice | bill | credit_note
            $table->unsignedBigInteger('document_id')->nullable();
            // Amounts
            $table->string('currency_code', 3);
            $table->decimal('invoice_amount', 18, 4);
            $table->decimal('payment_amount', 18, 4);
            $table->decimal('difference_amount', 18, 4);      // signed: negative = underpay
            $table->enum('difference_type', ['underpayment', 'overpayment']);
            // Resolution
            $table->enum('resolution', ['written_off', 'credited', 'auto_cleared'])->default('written_off');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->date('posting_date');
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'payment_type', 'payment_id'], 'pay_diff_posts_org_type_id_idx');
            $table->index(['organization_id', 'posting_date']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('tolerance_group_id')->references('id')->on('payment_tolerance_groups');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('payment_difference_posts');
        Schema::dropIfExists('payment_tolerance_groups');
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('payment_run_items');
        Schema::dropIfExists('payment_files');
        Schema::dropIfExists('payment_runs');
        Schema::dropIfExists('payment_advices');
        Schema::dropIfExists('parked_documents');
        Schema::dropIfExists('overhead_keys');
        Schema::dropIfExists('organization_currencies');
        Schema::dropIfExists('material_ledger_records');
        Schema::dropIfExists('liquidity_plan_lines');
        Schema::dropIfExists('liquidity_plans');
        Schema::dropIfExists('lease_schedules');
        Schema::dropIfExists('lease_contracts');
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('forex_gain_loss_entries');
        Schema::dropIfExists('elimination_entries');
        Schema::dropIfExists('depreciation_run_lines');
        Schema::dropIfExists('currency_revaluations');
        Schema::dropIfExists('asset_transfers');
        Schema::dropIfExists('asset_transactions');
        Schema::dropIfExists('asset_components');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('installment_plans');
        Schema::dropIfExists('house_bank_accounts');
        Schema::dropIfExists('house_banks');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('depreciation_runs');
        Schema::dropIfExists('copa_plan_versions');
        Schema::dropIfExists('consolidated_balances');
        Schema::dropIfExists('consolidation_periods');
        Schema::dropIfExists('carry_forward_runs');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('account_opening_balances');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('financial_statement_version_nodes');
        Schema::dropIfExists('financial_statement_versions');
        Schema::dropIfExists('financial_close_tasks');
        Schema::dropIfExists('financial_close_template_tasks');
    }
};
