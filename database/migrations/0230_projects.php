<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_evm_baselines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->string('name');                                 // Original Baseline, Re-baseline 1, etc.
            $table->string('baseline_type')->default('original');   // original|revised|current
            $table->date('baseline_date');
            $table->decimal('planned_cost', 15, 2);                // BAC at baseline
            $table->decimal('planned_duration_days', 10, 1);
            $table->date('planned_start');
            $table->date('planned_finish');
            $table->boolean('is_active')->default(false);          // only one active per project
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'is_active']);
            $table->index(['organization_id', 'project_id']);
        });

        Schema::create('project_evm_baseline_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('baseline_id');
            $table->unsignedBigInteger('wbs_id');
            $table->decimal('planned_cost', 15, 2)->default(0);
            $table->date('planned_start');
            $table->date('planned_finish');
            $table->decimal('planned_duration_days', 10, 1)->default(0);
            $table->timestamps();

            $table->foreign('baseline_id')->references('id')->on('project_evm_baselines')->cascadeOnDelete();
            $table->index(['baseline_id', 'wbs_id']);
        });

        Schema::create('project_resource_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->string('wbs_element')->nullable();
            $table->enum('resource_type', ['labor', 'equipment', 'material', 'subcontractor']);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('resource_description');
            $table->decimal('planned_quantity', 10, 2);
            $table->string('uom', 20);
            $table->date('planned_start');
            $table->date('planned_end');
            $table->decimal('cost_rate', 18, 4)->nullable();
            $table->decimal('planned_cost', 18, 4)->nullable();
            $table->decimal('actual_quantity', 10, 2)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('project_revenue_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('version', 10)->default('0');
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->decimal('total_planned_revenue', 18, 4)->default(0);
            $table->decimal('total_planned_cost', 18, 4)->default(0);
            $table->char('currency', 3);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('approved_by', 'proj_rev_plan_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('project_revenue_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_revenue_plan_id');
            $table->tinyInteger('period_month');
            $table->decimal('planned_revenue', 18, 4)->default(0);
            $table->decimal('planned_cost', 18, 4)->default(0);
            $table->decimal('actual_revenue', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('project_revenue_plan_id', 'proj_rev_plan_line_fk')->references('id')->on('project_revenue_plans')->cascadeOnDelete();
        });

        Schema::create('project_revenue_recognitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedSmallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('recognized_revenue', 18, 4);
            $table->decimal('recognized_cost', 18, 4);
            $table->decimal('completion_percentage', 5, 2);
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('gl_account_id', 'proj_rev_rec_gl_fk')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::create('project_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('template_name');
            $table->text('description')->nullable();
            $table->enum('project_type', ['customer', 'internal', 'overhead', 'capital', 'maintenance']);
            $table->string('industry')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('created_by', 'proj_tmpl_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('project_template_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_template_id');
            $table->string('milestone_name');
            $table->unsignedSmallInteger('offset_days');
            $table->enum('milestone_type', ['start', 'gate', 'completion', 'billing']);
            $table->decimal('billing_percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign('project_template_id', 'proj_tmpl_ms_fk')->references('id')->on('project_templates')->cascadeOnDelete();
        });

        Schema::create('project_template_wbs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_template_id');
            $table->string('wbs_code');
            $table->string('description');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->decimal('planned_cost', 18, 4)->nullable();
            $table->unsignedBigInteger('responsible_dept_id')->nullable();
            $table->timestamps();

            $table->foreign('project_template_id', 'proj_tmpl_wbs_fk')->references('id')->on('project_templates')->cascadeOnDelete();
            $table->foreign('parent_id', 'proj_tmpl_wbs_par_fk')->references('id')->on('project_template_wbs')->nullOnDelete();
            $table->foreign('responsible_dept_id', 'proj_tmpl_wbs_dept_fk')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::create('project_time_sheets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('project_id');
            $table->string('wbs_element')->nullable();
            $table->date('work_date');
            $table->decimal('hours_worked', 5, 2);
            $table->text('activity_description')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('employee_id', 'proj_ts_emp_fk')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('approved_by', 'proj_ts_appr_fk')->references('id')->on('users')->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_sheets');
        Schema::dropIfExists('project_template_wbs');
        Schema::dropIfExists('project_template_milestones');
        Schema::dropIfExists('project_templates');
        Schema::dropIfExists('project_revenue_recognitions');
        Schema::dropIfExists('project_revenue_plan_lines');
        Schema::dropIfExists('project_revenue_plans');
        Schema::dropIfExists('project_resource_plans');
        Schema::dropIfExists('project_evm_baseline_lines');
        Schema::dropIfExists('project_evm_baselines');
    }
};
