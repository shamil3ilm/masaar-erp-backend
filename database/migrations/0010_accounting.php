<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_register_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('cre_org_fk');
            $table->foreignId('check_book_id')->nullable()->constrained('check_books')->name('cre_checkbook_fk');
            $table->string('check_number', 20);
            $table->enum('check_type', ['payment', 'payroll', 'refund', 'other'])->default('payment');
            $table->enum('direction', ['issued', 'received'])->default('issued');
            $table->foreignId('payee_id')->nullable()->constrained('contacts')->name('cre_payee_fk');
            $table->foreignId('payment_made_id')->nullable()->constrained('payments_made')->name('cre_payment_made_fk');
            $table->foreignId('payment_received_id')->nullable()->constrained('payments_received')->name('cre_payment_rcvd_fk');
            $table->date('check_date');
            $table->decimal('amount', 18, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->text('memo')->nullable();
            $table->enum('status', ['draft', 'printed', 'issued', 'presented', 'cleared', 'bounced', 'cancelled', 'stale'])
                ->default('draft');
            $table->dateTime('printed_at')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('cleared_at')->nullable();
            $table->dateTime('bounced_at')->nullable();
            $table->text('bounce_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'check_number', 'direction'], 'cre_org_num_dir_unq');
            $table->index(['organization_id', 'status'], 'cre_org_status_idx');
            $table->index(['check_date'], 'cre_check_date_idx');
        });

        Schema::create('cost_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('run_number')->unique();              // KALC-2026-001
            $table->string('source_type');                      // assessment|distribution|activity_confirmation
            $table->unsignedBigInteger('source_id');            // FK to the CO posting (polymorphic by source_type)
            $table->string('fiscal_year', 4);
            $table->string('period', 2);
            $table->string('status')->default('pending');       // pending|posted|reversed
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('cost_reconciliation_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('reconciliation_run_id');
            $table->string('entry_type');                       // debit|credit
            $table->unsignedBigInteger('sender_company_id');
            $table->unsignedBigInteger('receiver_company_id');
            $table->unsignedBigInteger('sender_cost_center_id');
            $table->unsignedBigInteger('receiver_cost_center_id');
            $table->unsignedBigInteger('cost_element_id');
            $table->unsignedBigInteger('journal_entry_id')->nullable();  // generated FI document
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('SAR');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('reconciliation_run_id')->references('id')->on('cost_reconciliation_runs')->cascadeOnDelete();
            $table->index(['organization_id', 'reconciliation_run_id'], 'co_recon_entries_org_run_idx');
        });

        Schema::create('cost_splitting_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('csres_org_fk');
            $table->foreignId('cost_splitting_rule_id')
                ->constrained('cost_splitting_rules')
                ->name('csres_rule_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('total_cost', 18, 4);
            $table->decimal('fixed_cost', 18, 4);
            $table->decimal('variable_cost', 18, 4);
            $table->dateTime('run_at');
            $table->timestamps();

            $table->index(['organization_id', 'period', 'fiscal_year'], 'csres_org_period_fy_idx');
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // ISO 4217 code
            $table->string('name', 50);
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('direct_debit_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('ddc_org_fk');
            $table->foreignId('direct_debit_mandate_id')->constrained('direct_debit_mandates')->name('ddc_mandate_fk');
            $table->date('collection_date');
            $table->decimal('amount', 18, 4);
            $table->enum('status', ['scheduled', 'submitted', 'collected', 'failed', 'returned'])->default('scheduled');
            $table->foreignId('payment_run_id')->nullable()->constrained('payment_runs')->name('ddc_payment_run_fk');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['direct_debit_mandate_id'], 'ddc_mandate_idx');
            $table->index(['collection_date', 'status'], 'ddc_date_status_idx');
        });

        Schema::create('fx_forwards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');

            $table->string('contract_number', 50)->unique();
            $table->string('counterparty_bank')->nullable();
            $table->string('buy_currency', 3);
            $table->string('sell_currency', 3);
            $table->decimal('notional_amount', 18, 4);      // amount in buy_currency
            $table->decimal('forward_rate', 18, 8);         // agreed rate
            $table->date('trade_date');
            $table->date('maturity_date');
            $table->enum('purpose', ['speculative', 'hedge'])->default('hedge');
            $table->enum('status', ['active', 'matured', 'cancelled', 'exercised'])->default('active');

            // Settlement
            $table->decimal('settlement_rate', 18, 8)->nullable();
            $table->decimal('settlement_gain_loss', 18, 4)->nullable();
            $table->date('settled_at')->nullable();

            // GL accounts
            $table->unsignedBigInteger('derivative_asset_account_id')->nullable();
            $table->unsignedBigInteger('unrealised_gain_loss_account_id')->nullable();
            $table->unsignedBigInteger('realised_gain_loss_account_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'maturity_date']);
        });

        Schema::create('fx_hedge_relations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fx_forward_id');

            $table->string('hedge_type', 20);                // fair_value | cash_flow | net_investment
            $table->string('hedged_item_type', 50);          // sales_order | purchase_order | forecast
            $table->unsignedBigInteger('hedged_item_id')->nullable();
            $table->string('hedged_item_description')->nullable();
            $table->decimal('hedge_ratio', 5, 4)->default(1.0000);  // 0-1

            $table->date('designation_date');
            $table->date('dedesignation_date')->nullable();
            $table->enum('status', ['designated', 'dedesignated', 'expired'])->default('designated');

            $table->text('effectiveness_notes')->nullable();
            $table->timestamps();

            $table->foreign('fx_forward_id')->references('id')->on('fx_forwards')->cascadeOnDelete();
            $table->index(['organization_id', 'fx_forward_id']);
        });

        Schema::create('fx_valuations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fx_forward_id');
            $table->date('valuation_date');
            $table->decimal('spot_rate', 18, 8);
            $table->decimal('fair_value', 18, 4);           // mark-to-market
            $table->decimal('fair_value_change', 18, 4);    // vs previous period
            $table->decimal('effective_portion', 18, 4)->default(0);   // cash flow hedge
            $table->decimal('ineffective_portion', 18, 4)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->unique(['fx_forward_id', 'valuation_date']);
            $table->foreign('fx_forward_id')->references('id')->on('fx_forwards')->cascadeOnDelete();
        });

        Schema::create('ic_reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('receivable_item_id');  // -> ic_reconciliation_items
            $table->unsignedBigInteger('payable_item_id');     // -> ic_reconciliation_items
            $table->decimal('receivable_amount', 18, 4);
            $table->decimal('payable_amount', 18, 4);
            $table->decimal('difference', 18, 4)->default(0);  // payable - receivable
            $table->string('currency', 3)->default('SAR');
            $table->enum('match_type', ['auto', 'manual'])->default('auto');
            $table->enum('status', ['proposed', 'confirmed', 'disputed'])->default('proposed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
        });

        Schema::create('ic_reconciliation_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');   // initiating org
            $table->string('session_number', 30)->unique();  // ICR-2026-0001
            $table->string('fiscal_year', 4);
            $table->unsignedTinyInteger('period');           // 1-12
            $table->enum('status', ['draft', 'running', 'completed', 'closed'])->default('draft');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->decimal('matched_amount', 18, 4)->default(0);
            $table->decimal('difference_amount', 18, 4)->default(0);
            $table->unsignedBigInteger('run_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'fiscal_year', 'period'], 'ic_recon_sessions_org_fy_period_idx');
        });

        Schema::create('ic_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('organization_id');

            // Source transaction
            $table->string('source_type', 50);                // invoice | purchase_order | journal_entry
            $table->unsignedBigInteger('source_id');
            $table->string('reference_number', 100);          // IC key used for matching
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('SAR');
            $table->date('transaction_date');

            // Counterparty
            $table->unsignedBigInteger('counterparty_organization_id')->nullable();
            $table->string('counterparty_reference', 100)->nullable();

            $table->enum('item_type', ['payable', 'receivable']);
            $table->enum('match_status', ['unmatched', 'matched', 'disputed', 'excluded'])->default('unmatched');
            $table->unsignedBigInteger('match_id')->nullable();  // -> ic_reconciliation_matches

            $table->timestamps();

            $table->index(['session_id', 'match_status']);
            $table->index(['organization_id', 'reference_number']);
            $table->foreign('session_id')->references('id')->on('ic_reconciliation_sessions')->cascadeOnDelete();
        });

        Schema::create('material_ledger_closing_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('mlce_org_fk');
            $table->foreignId('material_ledger_record_id')
                ->constrained('material_ledger_records')
                ->name('mlce_mlr_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('total_price_difference', 18, 4);
            $table->decimal('revaluation_amount', 18, 4);
            $table->decimal('actual_price_calculated', 18, 4);
            $table->foreignId('run_by')->nullable()->constrained('users')->name('mlce_run_by_fk');
            $table->dateTime('run_at');
            $table->timestamps();

            $table->index(['organization_id', 'period', 'fiscal_year'], 'mlce_org_period_fy_idx');
        });

        Schema::create('material_ledger_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('mld_org_fk');
            $table->foreignId('material_ledger_record_id')
                ->constrained('material_ledger_records')
                ->name('mld_mlr_fk');
            $table->enum('document_type', [
                'goods_receipt',
                'goods_issue',
                'invoice',
                'transfer',
                'adjustment',
                'closing',
            ])->default('goods_receipt');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('standard_value', 18, 4);
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('price_difference', 18, 4)->default(0);
            $table->date('posting_date');
            $table->timestamps();

            $table->index(['material_ledger_record_id'], 'mld_mlr_idx');
        });

        Schema::create('material_ledger_price_differences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->name('mlpd_org_fk');
            $table->foreignId('ml_closing_entry_id')
                ->constrained('material_ledger_closing_entries')
                ->name('mlpd_ce_fk');
            $table->foreignId('product_id')->constrained('products')->name('mlpd_product_fk');
            $table->enum('category', [
                'purchase_price_variance',
                'exchange_rate_difference',
                'invoice_difference',
                'production_variance',
            ])->default('purchase_price_variance');
            $table->decimal('amount', 18, 4);
            $table->decimal('quantity_affected', 18, 4);
            $table->timestamps();

            $table->index(['ml_closing_entry_id'], 'mlpd_ce_idx');
        });

        Schema::create('profitability_segment_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('psv_org_fk');
            $table->foreignId('profitability_segment_id')
                ->constrained('profitability_segments')
                ->name('psv_seg_fk');
            $table->foreignId('copa_dimension_id')
                ->nullable()
                ->constrained('copa_dimensions')
                ->name('psv_copa_dim_fk');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('revenue', 18, 4)->default(0);
            $table->decimal('cost_of_sales', 18, 4)->default(0);
            $table->decimal('gross_margin', 18, 4)->default(0);
            $table->decimal('overhead_costs', 18, 4)->default(0);
            $table->decimal('net_margin', 18, 4)->default(0);
            $table->decimal('quantity_sold', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['profitability_segment_id', 'period', 'fiscal_year'], 'psv_seg_period_fy_idx');
        });

        Schema::create('statistical_key_figure_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('skfv_org_fk');
            $table->foreignId('statistical_key_figure_id')
                ->constrained('statistical_key_figures')
                ->name('skfv_skf_fk');
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained('cost_centers')
                ->name('skfv_cc_fk');
            $table->foreignId('profit_center_id')
                ->nullable()
                ->constrained('profit_centers')
                ->name('skfv_pc_fk');
            $table->unsignedTinyInteger('period'); // 1-12
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('value', 18, 4);
            $table->foreignId('posted_by')
                ->nullable()
                ->constrained('users')
                ->name('skfv_posted_by_fk');
            $table->timestamps();

            $table->unique(
                ['organization_id', 'statistical_key_figure_id', 'cost_center_id', 'profit_center_id', 'period', 'fiscal_year'],
                'skfv_unique_posting'
            );
        });

        Schema::create('variance_analysis_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('vai_org_fk');
            $table->foreignId('variance_analysis_run_id')
                ->constrained('variance_analysis_runs')
                ->name('vai_run_fk');
            $table->string('reference_type', 50); // 'work_order', 'process_order', 'cost_center'
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('vai_ce_fk');
            $table->enum('variance_category', [
                'price_variance',
                'quantity_variance',
                'efficiency_variance',
                'spending_variance',
                'resource_usage_variance',
                'remaining_input_variance',
                'output_price_variance',
                'mixed_price_variance',
            ]);
            $table->decimal('standard_cost', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->decimal('variance_amount', 18, 4)->default(0);
            $table->decimal('variance_percent', 8, 4)->default(0);
            $table->timestamps();

            $table->index(['variance_analysis_run_id'], 'vai_run_idx');
            $table->index(['reference_type', 'reference_id'], 'vai_ref_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('variance_analysis_items');
        Schema::dropIfExists('statistical_key_figure_values');
        Schema::dropIfExists('profitability_segment_values');
        Schema::dropIfExists('material_ledger_price_differences');
        Schema::dropIfExists('material_ledger_documents');
        Schema::dropIfExists('material_ledger_closing_entries');
        Schema::dropIfExists('ic_reconciliation_items');
        Schema::dropIfExists('ic_reconciliation_sessions');
        Schema::dropIfExists('ic_reconciliation_matches');
        Schema::dropIfExists('fx_valuations');
        Schema::dropIfExists('fx_hedge_relations');
        Schema::dropIfExists('fx_forwards');
        Schema::dropIfExists('direct_debit_collections');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('cost_splitting_results');
        Schema::dropIfExists('cost_reconciliation_entries');
        Schema::dropIfExists('cost_reconciliation_runs');
        Schema::dropIfExists('check_register_entries');
    }
};
