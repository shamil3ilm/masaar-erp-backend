<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->boolean('is_statistical')->default(false);
            $table->softDeletes();
            $table->timestamps();

            // Unique code per organization
            $table->unique(['organization_id', 'code']);

            // Foreign keys
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')->on('cost_centers')
                ->onDelete('set null');

            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->onDelete('set null');

            $table->foreign('department_id')
                ->references('id')->on('departments')
                ->onDelete('set null');

            $table->foreign('gl_account_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('set null');

            // Indexes
            $table->index(['organization_id', 'status']);
            $table->index('organization_id');
        });

        Schema::create('activity_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained('activity_types')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->integer('period')->default(1); // 1-12
            $table->decimal('planned_rate', 15, 4)->default(0);
            $table->decimal('actual_rate', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->unique(['activity_type_id', 'cost_center_id', 'fiscal_year_id', 'period'], 'ar_unique_rate');

            $table->boolean('is_confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')
                ->nullable()

                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('assessment_postings', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('assessment_cycle_id')
                ->constrained('assessment_cycles', 'id', 'co_asmt_post_cycle_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period')->unsigned();
            $table->foreignId('sender_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_post_snd_cc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_post_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements', 'id', 'co_asmt_post_ce_fk')
                ->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            // Self-referential FK for reversals
            $table->unsignedBigInteger('reversal_id')->nullable();
            $table->timestamps();

            $table->foreign('reversal_id', 'co_asmt_post_reversal_fk')
                ->references('id')
                ->on('assessment_postings')
                ->nullOnDelete();

            $table->index(['organization_id', 'fiscal_year', 'period'], 'co_asmt_post_org_fy_period_idx');
            $table->index(['assessment_cycle_id'], 'co_asmt_post_cycle_idx');
        });

        Schema::create('distribution_postings', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('distribution_cycle_id')
                ->constrained('distribution_cycles', 'id', 'co_dist_post_cycle_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period')->unsigned();
            $table->foreignId('sender_cost_center_id')
                ->constrained('cost_centers', 'id', 'co_dist_post_snd_cc_fk')
                ->restrictOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_dist_post_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('cost_element_id')
                ->constrained('cost_elements', 'id', 'co_dist_post_ce_fk')
                ->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->timestamps();

            $table->index(['organization_id', 'fiscal_year', 'period'], 'co_dist_post_org_fy_period_idx');
            $table->index(['distribution_cycle_id'], 'co_dist_post_cycle_idx');
        });

        Schema::create('distribution_segments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('distribution_cycle_id')
                ->constrained('distribution_cycles', 'id', 'co_dist_seg_cycle_fk')
                ->cascadeOnDelete();
            $table->foreignId('sender_cost_center_id')
                ->constrained('cost_centers', 'id', 'co_dist_seg_snd_cc_fk')
                ->restrictOnDelete();
            // JSON array of cost_element IDs covered by this segment
            $table->json('cost_element_ids');
            $table->enum('tracing_factor', ['fixed_percentages', 'statistical_key_figure', 'posted_amounts'])
                ->default('fixed_percentages');
            $table->foreignId('skf_id')
                ->nullable()
                ->constrained('statistical_key_figures', 'id', 'co_dist_seg_skf_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['distribution_cycle_id'], 'co_dist_seg_cycle_idx');
        });

        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('from_cost_center_id');
            $table->unsignedBigInteger('to_cost_center_id');
            $table->enum('allocation_method', ['fixed', 'percentage', 'activity'])->default('percentage');
            $table->decimal('allocation_percent', 5, 2)->nullable();
            $table->decimal('allocation_amount', 15, 4)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('fiscal_year_id')
                ->references('id')->on('fiscal_years')
                ->onDelete('set null');

            $table->foreign('from_cost_center_id')
                ->references('id')->on('cost_centers')
                ->onDelete('restrict');

            $table->foreign('to_cost_center_id')
                ->references('id')->on('cost_centers')
                ->onDelete('restrict');

            $table->foreign('journal_entry_id')
                ->references('id')->on('journal_entries')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'period_start', 'period_end']);
        });

        Schema::create('cost_center_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers', 'id', 'cc_budget_cc_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('budget_version', 20)->default('0');
            $table->decimal('total_budget', 18, 4)->default(0);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['draft', 'approved', 'active'])->default('draft');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users', 'id', 'cc_budget_approved_by_fk')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'cost_center_id', 'fiscal_year', 'budget_version'],
                'cc_budget_unique'
            );
            $table->index(['organization_id', 'fiscal_year', 'status'], 'cc_budget_org_fy_status_idx');
        });

        Schema::create('cost_center_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_center_budget_id')
                ->constrained('cost_center_budgets', 'id', 'cc_bdgt_line_budget_fk')
                ->cascadeOnDelete();
            $table->tinyInteger('period')->unsigned(); // 1-12
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements', 'id', 'cc_bdgt_line_ce_fk')
                ->nullOnDelete();
            $table->decimal('budgeted_amount', 18, 4)->default(0);
            $table->decimal('committed_amount', 18, 4)->default(0);
            $table->decimal('actual_amount', 18, 4)->default(0);
            // Stored computed column: available = budgeted - committed - actual
            $table->decimal('available_amount', 18, 4)
                ->storedAs('budgeted_amount - committed_amount - actual_amount');
            $table->timestamps();

            $table->index(['cost_center_budget_id', 'period'], 'cc_bdgt_line_budget_period_idx');

            // period column may not exist yet — check at runtime for safety
        });

        Schema::create('cost_center_budget_supplements', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cost_center_budget_id')
                ->constrained('cost_center_budgets', 'id', 'cc_bdgt_supp_budget_fk')
                ->cascadeOnDelete();
            $table->string('supplement_number', 50)->unique();
            $table->decimal('requested_amount', 18, 4);
            $table->decimal('approved_amount', 18, 4)->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')
                ->constrained('users', 'id', 'cc_bdgt_supp_req_by_fk')
                ->restrictOnDelete();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users', 'id', 'cc_bdgt_supp_rev_by_fk')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'cc_bdgt_supp_org_status_idx');
            $table->index(['cost_center_budget_id'], 'cc_bdgt_supp_budget_idx');
        });

        Schema::create('costing_sheet_rows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('costing_sheet_id');
            $table->string('row_type', 20);
            $table->string('description');
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('base_cost_element_id')->nullable();
            $table->unsignedBigInteger('overhead_key_id')->nullable();
            $table->unsignedBigInteger('credit_cost_center_id')->nullable();
            $table->unsignedBigInteger('credit_cost_element_id')->nullable();
            $table->integer('from_row')->nullable();
            $table->integer('to_row')->nullable();
            $table->timestamps();

            $table->foreign('costing_sheet_id', 'csr_cs_fk')
                ->references('id')->on('costing_sheets')->onDelete('cascade');
            // overhead_key_id FK added after overhead_keys table is created
            $table->foreign('base_cost_element_id', 'csr_base_ce_fk')
                ->references('id')->on('cost_elements')->onDelete('set null');
            $table->foreign('credit_cost_center_id', 'csr_credit_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('credit_cost_element_id', 'csr_credit_ce_fk')
                ->references('id')->on('cost_elements')->onDelete('set null');

            $table->foreign('overhead_key_id', 'csr_ok_fk')
                ->references('id')->on('overhead_keys')->onDelete('set null');
        });

        Schema::create('costing_sheet_run_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('costing_sheet_run_id');
            $table->unsignedBigInteger('costing_sheet_row_id');
            $table->decimal('base_amount', 18, 4)->default(0);
            $table->decimal('overhead_rate', 10, 6)->default(0);
            $table->decimal('overhead_amount', 18, 4)->default(0);
            $table->boolean('credit_posted')->default(false);
            $table->timestamps();

            $table->foreign('costing_sheet_run_id', 'csrr_run_fk')
                ->references('id')->on('costing_sheet_runs')->onDelete('cascade');
            $table->foreign('costing_sheet_row_id', 'csrr_row_fk')
                ->references('id')->on('costing_sheet_rows')->onDelete('cascade');
        });

        Schema::create('internal_orders', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('order_number', 30);
            $table->string('description', 255);
            $table->enum('order_type', ['overhead', 'investment', 'accrual', 'statistical'])->default('overhead');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget_amount', 15, 4)->default(0);
            $table->decimal('committed_amount', 15, 4)->default(0);
            $table->decimal('actual_amount', 15, 4)->default(0);
            $table->enum('status', ['created', 'released', 'technically_completed', 'closed'])->default('created');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'order_number'], 'io_org_number_unique');
        });

        Schema::create('internal_order_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_order_id')->constrained('internal_orders')->cascadeOnDelete();
            $table->enum('receiver_type', ['cost_center', 'gl_account', 'project_wbs', 'profit_center']);
            $table->unsignedBigInteger('receiver_id');
            $table->decimal('settlement_percentage', 5, 2);
            $table->timestamps();
            $table->index(['internal_order_id'], 'ios_order_idx');
        });

        Schema::create('overhead_key_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('overhead_key_id');
            $table->date('validity_from');
            $table->date('validity_to')->nullable();
            $table->decimal('overhead_rate', 10, 6);
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('activity_type_id')->nullable();
            $table->timestamps();

            $table->foreign('overhead_key_id', 'okr_ok_fk')
                ->references('id')->on('overhead_keys')->onDelete('cascade');
            $table->foreign('cost_center_id', 'okr_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('activity_type_id', 'okr_at_fk')
                ->references('id')->on('activity_types')->onDelete('set null');

            $table->index(['overhead_key_id', 'validity_from'], 'okr_key_validity_idx');
        });

        Schema::create('profit_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('parent_id')
                ->references('id')->on('profit_centers')
                ->onDelete('set null');

            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->onDelete('set null');

            $table->foreign('gl_account_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index('organization_id');
        });

        Schema::create('assessment_cycle_segments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('assessment_cycle_id')
                ->constrained('assessment_cycles', 'id', 'co_asmt_seg_cycle_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('segment_number')->default(1);
            $table->foreignId('sender_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_seg_snd_cc_fk')
                ->nullOnDelete();
            $table->foreignId('sender_profit_center_id')
                ->nullable()
                ->constrained('profit_centers', 'id', 'co_asmt_seg_snd_pc_fk')
                ->nullOnDelete();
            $table->foreignId('sender_cost_element_id')
                ->nullable()
                ->constrained('cost_elements', 'id', 'co_asmt_seg_snd_ce_fk')
                ->nullOnDelete();
            $table->enum('tracing_factor', ['fixed_percentages', 'statistical_key_figure', 'posted_amounts'])
                ->default('fixed_percentages');
            $table->foreignId('skf_id')
                ->nullable()
                ->constrained('statistical_key_figures', 'id', 'co_asmt_seg_skf_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['assessment_cycle_id'], 'co_asmt_seg_cycle_idx');
        });

        Schema::create('assessment_cycle_receivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_cycle_segment_id')
                ->constrained('assessment_cycle_segments', 'id', 'co_asmt_rcv_seg_fk')
                ->cascadeOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_profit_center_id')
                ->nullable()
                ->constrained('profit_centers', 'id', 'co_asmt_rcv_pc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_order_id')
                ->nullable()
                ->constrained('internal_orders', 'id', 'co_asmt_rcv_io_fk')
                ->nullOnDelete();
            $table->decimal('fixed_percentage', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['assessment_cycle_segment_id'], 'co_asmt_rcv_seg_idx');
        });

        Schema::create('distribution_segment_receivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_segment_id')
                ->constrained('distribution_segments', 'id', 'co_dist_rcv_seg_fk')
                ->cascadeOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_dist_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_profit_center_id')
                ->nullable()
                ->constrained('profit_centers', 'id', 'co_dist_rcv_pc_fk')
                ->nullOnDelete();
            $table->decimal('fixed_percentage', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['distribution_segment_id'], 'co_dist_rcv_seg_idx');
        });

        Schema::create('copa_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->integer('period');
            $table->date('posting_date');
            $table->string('source_document_type', 30); // invoice, credit_note, settlement
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->foreignId('profit_center_id')->nullable()->constrained('profit_centers')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('revenue', 15, 4)->default(0);
            $table->decimal('cogs', 15, 4)->default(0);
            $table->decimal('gross_profit', 15, 4)->default(0);
            $table->decimal('overhead_allocated', 15, 4)->default(0);
            $table->decimal('net_profit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->index(['organization_id', 'fiscal_year_id', 'period'], 'copa_li_org_fy_period_idx');
            $table->index(['organization_id', 'posting_date'], 'copa_li_org_date_idx');
        });

        Schema::create('copa_planned_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_version_id')->constrained('copa_plan_versions')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->integer('period');
            $table->foreignId('profit_center_id')->nullable()->constrained('profit_centers')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('planned_revenue', 15, 4)->default(0);
            $table->decimal('planned_cogs', 15, 4)->default(0);
            $table->decimal('planned_gross_profit', 15, 4)->default(0);
            $table->decimal('planned_overhead', 15, 4)->default(0);
            $table->decimal('planned_net_profit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->index(['plan_version_id', 'period'], 'cpli_version_period_idx');
            $table->index(['organization_id', 'profit_center_id'], 'cpli_org_pc_idx');
        });

        Schema::create('cost_center_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('assignable_type', 191);
            $table->unsignedBigInteger('assignable_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->unsignedBigInteger('profit_center_id')->nullable();
            $table->decimal('split_percent', 5, 2)->default(100.00);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->onDelete('cascade');

            $table->foreign('cost_center_id')
                ->references('id')->on('cost_centers')
                ->onDelete('cascade');

            $table->foreign('profit_center_id')
                ->references('id')->on('profit_centers')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->index(['assignable_type', 'assignable_id']);
            $table->index('cost_center_id');
            $table->index('organization_id');
        });

        Schema::create('journal_entry_split_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedBigInteger('original_line_id')->nullable();
            $table->foreignId('profit_center_id')->nullable()->constrained('profit_centers')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->decimal('debit_amount', 15, 4)->default(0);
            $table->decimal('credit_amount', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->index(['journal_entry_id'], 'jesi_je_idx');
            $table->index(['profit_center_id'], 'jesi_pc_idx');

            $table->string('segment_id', 50)->nullable();
            $table->string('split_method', 30)->default('profit_center');
        });

        Schema::create('profit_center_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('profit_center_id')->constrained('profit_centers')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('period');    // 1–12
            $table->decimal('plan_revenue', 15, 4)->default(0);
            $table->decimal('plan_cost', 15, 4)->default(0);
            $table->decimal('plan_profit', 15, 4)->default(0);
            $table->char('currency_code', 3)->default('SAR');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'profit_center_id', 'fiscal_year', 'period'],
                'pcp_org_pc_year_period_unique'
            );
        });

        Schema::create('recurring_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'annually']);
            $table->unsignedTinyInteger('interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date');
            $table->date('last_run_date')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('max_runs')->nullable();
            $table->unsignedBigInteger('debit_account_id');
            $table->unsignedBigInteger('credit_account_id');
            $table->decimal('amount', 15, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->text('narration')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('profit_center_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('debit_account_id')->references('id')->on('chart_of_accounts');
            $table->foreign('credit_account_id')->references('id')->on('chart_of_accounts');
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('profit_center_id')->references('id')->on('profit_centers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'is_active', 'next_run_date'], 'recurring_journal_templates_org_active_next_run_idx');
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->decimal('q1_amount', 15, 2)->default(0);
            $table->decimal('q2_amount', 15, 2)->default(0);
            $table->decimal('q3_amount', 15, 2)->default(0);
            $table->decimal('q4_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('budget_id');
        });

        Schema::create('budget_commitments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->cascadeOnDelete();
            $table->enum('source_type', ['purchase_order', 'expense_report', 'journal_entry'])->default('purchase_order');
            $table->unsignedBigInteger('source_id');
            $table->decimal('committed_amount', 15, 2);
            $table->enum('status', ['open', 'partially_used', 'used', 'cancelled'])->default('open');
            $table->timestamp('committed_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('budget_line_id');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('budget_revision_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_revision_id')->constrained('budget_revisions')->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->cascadeOnDelete();
            $table->string('field_changed');
            $table->decimal('old_value', 15, 2);
            $table->decimal('new_value', 15, 2);
            $table->timestamps();
        });

        Schema::create('territory_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('role', ['owner', 'backup', 'viewer'])->default('owner');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('territory_id');
            $table->index('employee_id');
        });

        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable(); // NULL for annual budget
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0); // Pending approvals
            $table->boolean('alert_at_80')->default(true);
            $table->boolean('alert_at_100')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'category_id', 'department_id', 'year', 'month'], 'expense_budgets_org_cat_dept_yr_mo_unique');
        });

        Schema::create('expense_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('report_number', 30);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('reimbursed_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, rejected, paid
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->enum('unit_type', ['company', 'division', 'department', 'team', 'cost_center_group']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id', 'org_unit_parent_fk')->references('id')->on('org_units')->onDelete('set null');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->foreign('manager_id', 'org_unit_mgr_fk')->references('id')->on('employees')->onDelete('set null');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'org_unit_cc_fk')->references('id')->on('cost_centers')->onDelete('set null');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();


                $table->string('org_unit_code', 20)->nullable();



                $table->string('org_unit_type', 50)->nullable();



                $table->boolean('is_active')->default(true);



                $table->unsignedSmallInteger('head_count_plan')->default(0);



                $table->unsignedBigInteger('manager_position_id')->nullable();



                $table->unsignedBigInteger('created_by')->nullable();
        });

        Schema::create('time_sheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_sheet_id')->constrained('time_sheets')->cascadeOnDelete();
            $table->date('entry_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('hours', 5, 2);
            $table->enum('entry_type', [
                'regular',
                'overtime',
                'absence',
                'holiday',
                'training',
            ])->default('regular');
            $table->foreignId('wage_type_id')->nullable()->constrained('time_wage_types')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('activity_code', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['time_sheet_id', 'entry_date'], 'tse_sheet_date_idx');
            $table->index(['cost_center_id', 'entry_date'], 'tse_cc_date_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('time_sheet_entries');
        Schema::dropIfExists('org_units');
        Schema::dropIfExists('expense_reports');
        Schema::dropIfExists('expense_budgets');
        Schema::dropIfExists('territory_assignments');
        Schema::dropIfExists('budget_revision_lines');
        Schema::dropIfExists('budget_commitments');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('recurring_journal_templates');
        Schema::dropIfExists('profit_center_plans');
        Schema::dropIfExists('journal_entry_split_items');
        Schema::dropIfExists('cost_center_assignments');
        Schema::dropIfExists('copa_planned_line_items');
        Schema::dropIfExists('copa_line_items');
        Schema::dropIfExists('distribution_segment_receivers');
        Schema::dropIfExists('assessment_cycle_receivers');
        Schema::dropIfExists('assessment_cycle_segments');
        Schema::dropIfExists('profit_centers');
        Schema::dropIfExists('overhead_key_rates');
        Schema::dropIfExists('internal_order_settlements');
        Schema::dropIfExists('internal_orders');
        Schema::dropIfExists('costing_sheet_run_results');
        Schema::dropIfExists('costing_sheet_rows');
        Schema::dropIfExists('cost_center_budget_supplements');
        Schema::dropIfExists('cost_center_budget_lines');
        Schema::dropIfExists('cost_center_budgets');
        Schema::dropIfExists('cost_allocations');
        Schema::dropIfExists('distribution_segments');
        Schema::dropIfExists('distribution_postings');
        Schema::dropIfExists('assessment_postings');
        Schema::dropIfExists('activity_rates');
        Schema::dropIfExists('cost_centers');
    }
};
