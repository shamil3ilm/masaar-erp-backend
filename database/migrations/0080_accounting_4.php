<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_tolerance_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tolerance_group_id');
            $table->string('currency_code', 3);               // ISO 4217
            // Underpayment tolerance (customer pays less than invoice)
            $table->decimal('underpay_abs', 18, 4)->default(0);    // absolute max underpayment
            $table->decimal('underpay_pct', 7, 4)->default(0);     // % of invoice amount
            // Overpayment tolerance (customer pays more)
            $table->decimal('overpay_abs', 18, 4)->default(0);
            $table->decimal('overpay_pct', 7, 4)->default(0);
            // GL accounts for automatic write-off postings
            $table->unsignedBigInteger('underpay_gl_account_id')->nullable();  // expense
            $table->unsignedBigInteger('overpay_gl_account_id')->nullable();   // income
            $table->timestamps();

            $table->unique(['tolerance_group_id', 'currency_code']);
            $table->foreign('tolerance_group_id')->references('id')->on('payment_tolerance_groups')->cascadeOnDelete();
            $table->foreign('underpay_gl_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('overpay_gl_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::create('period_lock_overrides', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'period_id']);
        });

        Schema::create('posting_validation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_name', 100);
            $table->enum('rule_type', ['validation', 'substitution'])->default('validation');
            $table->string('trigger_event', 50)->default('on_save');
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(10);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'is_active', 'rule_type'], 'pvr_org_active_type_idx');
        });

        Schema::create('profitability_segments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('segment_name', 100);
            $table->foreignId('customer_group_id')
                ->nullable()
                ->constrained('customer_groups')
                ->name('ps_cg_fk');
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->name('ps_prod_fk');
            $table->string('region', 100)->nullable();
            $table->string('sales_channel', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id'], 'ps_org_idx');
        });

        Schema::create('assessment_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('cycle_type', ['assessment', 'distribution'])->default('assessment');
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period_from')->unsigned()->default(1);  // 1-12
            $table->tinyInteger('period_to')->unsigned()->default(12);
            $table->enum('status', ['open', 'executed', 'reversed'])->default('open');
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users', 'id', 'co_asmt_cyc_exec_by_fk')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fiscal_year', 'status'], 'co_asmt_cyc_org_fy_status_idx');

            $table->boolean('copa_enabled')->default(false);
            $table->foreignId('copa_segment_id')
                ->nullable()

                ->constrained('profitability_segments')
                ->nullOnDelete();
        });

        Schema::create('special_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('accounting_principle', 50)->default('ifrs');
            $table->boolean('is_leading')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'fk_sl_org')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unique(['organization_id', 'code'], 'uq_sl_org_code');
            $table->index(['organization_id', 'is_active'], 'idx_sl_org_active');
        });

        Schema::create('special_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('special_ledger_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->date('posting_date');
            $table->decimal('amount', 18, 4);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('amount_local', 18, 4);
            $table->char('debit_credit', 1);
            $table->tinyInteger('period');
            $table->smallInteger('fiscal_year');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('profit_center_id')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->foreign('special_ledger_id', 'fk_sle_ledger')
                ->references('id')->on('special_ledgers')->onDelete('cascade');
            $table->foreign('journal_entry_id', 'fk_sle_je')
                ->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('account_id', 'fk_sle_account')
                ->references('id')->on('chart_of_accounts')->onDelete('restrict');

            $table->index(['special_ledger_id', 'fiscal_year', 'period'], 'idx_sle_ledger_period');
            $table->index(['account_id', 'posting_date'], 'idx_sle_account_date');
            $table->index('organization_id', 'idx_sle_org');
        });

        Schema::create('special_ledger_mapping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('special_ledger_id');
            $table->unsignedBigInteger('source_account_id')->nullable();
            $table->string('account_type', 50)->nullable();
            $table->unsignedBigInteger('target_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('special_ledger_id', 'fk_slmr_ledger')
                ->references('id')->on('special_ledgers')->onDelete('cascade');
            $table->foreign('source_account_id', 'fk_slmr_src_acct')
                ->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('target_account_id', 'fk_slmr_tgt_acct')
                ->references('id')->on('chart_of_accounts')->onDelete('set null');

            $table->index(['organization_id', 'is_active'], 'idx_slmr_org_active');
        });

        Schema::create('statistical_key_figures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('unit_of_measure', 20);
            $table->enum('skf_type', ['fixed', 'total'])->default('total');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'skf_org_code_unq');
        });

        Schema::create('transfer_price_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('version_name');
            $table->smallInteger('fiscal_year');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'tpv_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by', 'tpv_user_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('treasury_investments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('instrument_number', 30);
            $table->enum('instrument_type', ['fixed_deposit', 'money_market', 'bond', 'treasury_bill', 'mutual_fund'])->default('fixed_deposit');
            $table->string('counterparty', 150);
            $table->decimal('principal_amount', 15, 4);
            $table->decimal('interest_rate', 8, 4);
            $table->date('investment_date');
            $table->date('maturity_date');
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('accrued_interest', 15, 4)->default(0);
            $table->decimal('maturity_value', 15, 4)->nullable();
            $table->enum('status', ['active', 'matured', 'pre_liquidated', 'rolled_over'])->default('active');
            $table->foreignId('gl_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'instrument_number'], 'ti_org_number_unique');
            $table->index(['organization_id', 'status', 'maturity_date'], 'ti_org_status_maturity_idx');
        });

        Schema::create('variance_analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('run_type', ['production_order', 'cost_center', 'project'])->default('production_order');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->foreignId('run_by')
                ->nullable()
                ->constrained('users')
                ->name('var_run_by_fk');
            $table->dateTime('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'period', 'fiscal_year'], 'var_run_period_idx');
        });

        Schema::create('withholding_tax_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 20);                       // e.g. WHT001, W1
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->enum('applicable_to', ['supplier', 'customer', 'both'])->default('supplier');
            $table->decimal('rate', 7, 4);                    // 5.0000 = 5%
            $table->string('country_code', 3)->nullable();    // ISO-3166 alpha-3 or alpha-2
            $table->string('tax_type', 50)->nullable();       // e.g. WHT, TCS, royalty
            $table->decimal('threshold_amount', 18, 4)->nullable(); // minimum cumulative before WHT kicks in
            $table->decimal('ceiling_amount', 18, 4)->nullable();   // max cumulative WHT per period
            // GL accounts
            $table->unsignedBigInteger('payable_account_id')->nullable();   // Dr Expense / Cr WHT Payable
            $table->unsignedBigInteger('receivable_account_id')->nullable(); // for customer-side (TCS)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('payable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('receivable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::create('withholding_tax_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('wht_code_id');
            // Polymorphic link to the parent payment (payments_received or payments_made)
            $table->string('payment_type', 50);               // 'payment_received' | 'payment_made'
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('gross_amount', 18, 4);           // taxable payment amount
            $table->decimal('wht_rate', 7, 4);                // rate applied (snapshot)
            $table->decimal('wht_amount', 18, 4);             // computed WHT
            $table->decimal('net_amount', 18, 4);             // gross - wht
            $table->string('currency_code', 3)->default('SAR');
            $table->date('transaction_date');
            $table->string('certificate_number', 100)->nullable(); // WHT certificate issued
            $table->date('certificate_date')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'payment_type', 'payment_id'], 'wht_lines_org_payment_idx');
            $table->index(['organization_id', 'contact_id'], 'wht_lines_org_contact_idx');
            $table->index(['organization_id', 'transaction_date'], 'wht_lines_org_date_idx');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('wht_code_id')->references('id')->on('withholding_tax_codes')->cascadeOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::create('xbrl_taxonomies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('version', 20);
            $table->string('namespace')->unique();
            $table->string('schema_location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('xbrl_filings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('taxonomy_id')->constrained('xbrl_taxonomies');
            $table->enum('report_type', ['annual', 'semi_annual', 'quarterly', 'interim'])->default('annual');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'validated', 'submitted', 'accepted', 'rejected'])->default('draft');
            $table->longText('xml_content')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'fiscal_year_id']);
        });

        Schema::create('xbrl_filing_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('xbrl_filing_id')->constrained('xbrl_filings')->cascadeOnDelete();
            $table->string('concept');          // e.g. ifrs-full:Equity
            $table->string('context_ref');      // e.g. duration_2023_2024
            $table->string('unit_ref')->nullable(); // e.g. SAR, ISO4217:USD
            $table->string('value', 1000);      // numeric or string value
            $table->integer('decimals')->nullable();
            $table->enum('period_type', ['instant', 'duration'])->default('instant');
            $table->enum('balance_type', ['debit', 'credit'])->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->index('xbrl_filing_id');
            $table->index(['xbrl_filing_id', 'concept']);
        });

        Schema::create('zakat_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();

            // Assessment period (Gregorian dates, Hijri reference stored as string)
            $table->year('assessment_year');
            $table->string('hijri_year', 10)->nullable(); // e.g. "1447"

            // Zakat base components (SAR)
            $table->decimal('total_assets', 18, 4)->default(0);
            $table->decimal('total_liabilities', 18, 4)->default(0);
            $table->decimal('non_zakatable_assets', 18, 4)->default(0);  // fixed assets, investments
            $table->decimal('zakat_base', 18, 4)->default(0);             // total_assets - total_liabilities - non_zakatable_assets
            $table->decimal('zakat_rate', 7, 4)->default(2.5000);         // 2.5%
            $table->decimal('zakat_due', 18, 4)->default(0);              // zakat_base × rate / 100

            // Saudi-national shareholder proportion (for mixed ownership companies)
            $table->decimal('saudi_ownership_pct', 7, 4)->default(100.0000); // 100% for fully Saudi-owned

            // Adjustments
            $table->decimal('zakat_paid', 18, 4)->default(0);
            $table->decimal('zakat_remaining', 18, 4)->default(0);

            // Status workflow
            $table->enum('status', ['draft', 'submitted', 'assessed', 'paid'])->default('draft');
            $table->string('gazt_reference', 100)->nullable();   // GAZT / ZATCA filing reference
            $table->date('filing_due_date')->nullable();
            $table->date('filed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'assessment_year'], 'zakat_org_year_unique');
            $table->index(['organization_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_assessments');
        Schema::dropIfExists('xbrl_filing_elements');
        Schema::dropIfExists('xbrl_filings');
        Schema::dropIfExists('xbrl_taxonomies');
        Schema::dropIfExists('withholding_tax_lines');
        Schema::dropIfExists('withholding_tax_codes');
        Schema::dropIfExists('variance_analysis_runs');
        Schema::dropIfExists('treasury_investments');
        Schema::dropIfExists('transfer_price_versions');
        Schema::dropIfExists('statistical_key_figures');
        Schema::dropIfExists('special_ledger_mapping_rules');
        Schema::dropIfExists('special_ledger_entries');
        Schema::dropIfExists('special_ledgers');
        Schema::dropIfExists('assessment_cycles');
        Schema::dropIfExists('profitability_segments');
        Schema::dropIfExists('posting_validation_rules');
        Schema::dropIfExists('period_lock_overrides');
        Schema::dropIfExists('payment_tolerance_items');
    }
};
