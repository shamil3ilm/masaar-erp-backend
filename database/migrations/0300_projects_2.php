<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_billing_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->enum('billing_type', ['milestone', 'time_material', 'fixed_price', 'percentage_completion']);
            $table->char('currency', 3);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('total_contract_value', 18, 4)->nullable();
            $table->decimal('retention_percentage', 5, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('customer_id', 'proj_bill_cust_fk')->references('id')->on('contacts')->nullOnDelete();
        });

        Schema::create('project_billing_milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_billing_rule_id');
            $table->string('milestone_name');
            $table->decimal('billing_amount', 18, 4);
            $table->decimal('billing_percentage', 5, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->enum('status', ['pending', 'invoiced', 'paid'])->default('pending');
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();

            $table->foreign('project_billing_rule_id', 'proj_bill_ms_rule_fk')->references('id')->on('project_billing_rules')->cascadeOnDelete();
            $table->foreign('invoice_id', 'proj_bill_ms_inv_fk')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('project_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('project_type', ['internal', 'customer', 'rd', 'capital'])->default('internal');
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->enum('status', ['draft', 'planning', 'active', 'on_hold', 'completed', 'cancelled'])->default('draft');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'project_number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('earned_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('budget_at_completion', 15, 4)->default(0); // BAC
            $table->decimal('planned_value', 15, 4)->default(0);        // PV (BCWS)
            $table->decimal('earned_value', 15, 4)->default(0);         // EV (BCWP)
            $table->decimal('actual_cost', 15, 4)->default(0);          // AC (ACWP)
            $table->decimal('schedule_variance', 15, 4)->default(0);    // SV = EV - PV
            $table->decimal('cost_variance', 15, 4)->default(0);        // CV = EV - AC
            $table->decimal('schedule_performance_index', 8, 4)->default(1); // SPI = EV/PV
            $table->decimal('cost_performance_index', 8, 4)->default(1);     // CPI = EV/AC
            $table->decimal('estimate_at_completion', 15, 4)->default(0);    // EAC = BAC/CPI
            $table->decimal('estimate_to_complete', 15, 4)->default(0);      // ETC = EAC - AC
            $table->decimal('variance_at_completion', 15, 4)->default(0);    // VAC = BAC - EAC
            $table->timestamps();
            $table->index(['project_id', 'snapshot_date'], 'evs_project_date_idx');
        });

        Schema::create('project_budget_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id', 'pbv_project_fk')->references('id')->on('projects')->cascadeOnDelete();

            $table->string('version_code', 20);
            $table->string('version_name', 100);
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('status', ['draft', 'active', 'frozen', 'archived'])->default('draft');
            $table->boolean('is_current')->default(false);
            $table->decimal('total_budget', 18, 4)->default(0);

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'pbv_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'project_id', 'version_code', 'fiscal_year'],
                'pbv_org_proj_ver_fy_unq'
            );
            $table->index(['project_id', 'is_current'], 'pbv_project_current_idx');
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('role', ['manager', 'member', 'reviewer', 'sponsor'])->default('member');
            $table->date('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'employee_id']);
        });

        Schema::create('wbs_elements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->string('wbs_code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['created', 'released', 'technically_complete', 'closed'])->default('created');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('planned_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->decimal('planned_revenue', 15, 2)->default(0);
            $table->decimal('actual_revenue', 15, 2)->default(0);
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->tinyInteger('progress_percent')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'wbs_code']);
            $table->index(['project_id', 'parent_id']);
        });

        Schema::create('network_activities', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->string('activity_number', 10); // 0010, 0020
            $table->string('description', 255);
            $table->enum('activity_type', ['internal', 'external', 'general_cost'])->default('internal');
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->decimal('planned_work', 10, 2)->default(0); // hours
            $table->decimal('actual_work', 10, 2)->default(0);
            $table->date('earliest_start')->nullable();
            $table->date('latest_start')->nullable();
            $table->date('earliest_finish')->nullable();
            $table->date('latest_finish')->nullable();
            $table->decimal('float_days', 10, 2)->default(0); // scheduling float
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'cancelled'])->default('not_started');
            $table->timestamps();
            $table->index(['project_id', 'status'], 'na_project_status_idx');
        });

        Schema::create('network_activity_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('predecessor_activity_id')->constrained('network_activities')->cascadeOnDelete();
            $table->foreignId('successor_activity_id')->constrained('network_activities')->cascadeOnDelete();
            $table->enum('relationship_type', ['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'])->default('finish_to_start');
            $table->integer('lag_days')->default(0);
            $table->timestamps();
            $table->unique(['predecessor_activity_id', 'successor_activity_id'], 'nar_pred_succ_unique');
        });

        Schema::create('project_budget_line_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pbli_org_fk')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_budget_version_id');
            $table->foreign('project_budget_version_id', 'pbli_version_fk')
                ->references('id')->on('project_budget_versions')->cascadeOnDelete();

            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->foreign('wbs_element_id', 'pbli_wbs_fk')->references('id')->on('wbs_elements')->nullOnDelete();

            $table->unsignedBigInteger('cost_element_id')->nullable();
            $table->foreign('cost_element_id', 'pbli_cost_element_fk')->references('id')->on('cost_elements')->nullOnDelete();

            $table->decimal('budgeted_amount', 18, 4)->default(0);
            $table->decimal('committed_amount', 18, 4)->default(0);
            $table->decimal('actual_amount', 18, 4)->default(0);
            $table->decimal('available_amount', 18, 4)->default(0);
            $table->enum('avac_action', ['warning', 'error', 'none'])->default('warning');
            $table->decimal('tolerance_percent', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['project_budget_version_id', 'wbs_element_id', 'cost_element_id'],
                'pbli_ver_wbs_ce_unq'
            );
            $table->index(['project_budget_version_id'], 'pbli_version_idx');
            $table->index(['wbs_element_id'], 'pbli_wbs_idx');
        });

        Schema::create('project_budget_supplements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pbs_org_fk')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_budget_version_id');
            $table->foreign('project_budget_version_id', 'pbs_version_fk')
                ->references('id')->on('project_budget_versions')->cascadeOnDelete();

            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->foreign('wbs_element_id', 'pbs_wbs_fk')->references('id')->on('wbs_elements')->nullOnDelete();

            $table->enum('supplement_type', ['supplement', 'return', 'transfer_in', 'transfer_out'])->default('supplement');
            $table->decimal('amount', 18, 4);
            $table->text('reason')->nullable();
            $table->string('reference_number', 50)->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'pbs_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();

            $table->index(['project_budget_version_id'], 'pbs_version_idx');
        });

        Schema::create('project_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->enum('cost_type', ['labor', 'material', 'equipment', 'subcontract', 'overhead', 'other'])->default('other');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('cost_date');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'cost_type']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->enum('status', ['pending', 'achieved', 'missed'])->default('pending');
            $table->date('achieved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('project_settlement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->enum('receiver_type', ['cost_center', 'gl_account', 'internal_order', 'profit_center'])->default('gl_account');
            $table->unsignedBigInteger('receiver_id');
            $table->decimal('settlement_percentage', 5, 2)->default(100);
            $table->timestamps();
            $table->index(['project_id'], 'psr_project_idx');
        });

        Schema::create('project_time_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('wbs_element_id')->nullable()->constrained('wbs_elements')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->string('description')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'employee_id']);
            $table->index(['project_id', 'work_date']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_entries');
        Schema::dropIfExists('project_settlement_rules');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_cost_entries');
        Schema::dropIfExists('project_budget_supplements');
        Schema::dropIfExists('project_budget_line_items');
        Schema::dropIfExists('network_activity_relationships');
        Schema::dropIfExists('network_activities');
        Schema::dropIfExists('wbs_elements');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_budget_versions');
        Schema::dropIfExists('earned_value_snapshots');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_billing_milestones');
        Schema::dropIfExists('project_billing_rules');
    }
};
