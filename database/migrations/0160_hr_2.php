<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('leave_date');
            $table->string('day_type', 20); // full, first_half, second_half
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_date', 'day_type']);
            $table->index(['organization_id', 'leave_date']);
            $table->index(['leave_request_id']);
        });

        Schema::create('leave_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // Eligibility criteria
            $table->unsignedSmallInteger('min_service_months')->default(0); // 0 = from joining
            $table->unsignedSmallInteger('max_service_months')->nullable(); // NULL = no upper limit
            $table->string('employee_grade')->nullable(); // Specific grade requirement
            $table->string('department_id')->nullable(); // Specific department requirement

            // Entitlement
            $table->decimal('entitled_days', 5, 2); // 21, 28, 30, etc. (decimal for partial days)
            $table->string('entitlement_period', 20)->default('yearly'); // yearly, monthly

            // Accrual rate (if different from base)
            $table->decimal('monthly_accrual_rate', 5, 2)->nullable(); // Override default accrual

            // Carryforward limits for this tier
            $table->unsignedSmallInteger('max_carryforward_days')->nullable();
            $table->unsignedSmallInteger('carryforward_expiry_months')->nullable(); // How long carryforward is valid

            // Encashment limits
            $table->unsignedSmallInteger('max_encashable_days')->nullable();
            $table->decimal('encashment_rate', 5, 2)->nullable(); // Percentage of daily salary

            $table->unsignedSmallInteger('priority')->default(0); // Higher = checked first
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['leave_type_id', 'min_service_months']);
        });

        Schema::create('leave_tier_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_tier_id')->constrained('leave_tiers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->string('designation')->nullable(); // Alternative to specific user
            $table->unsignedTinyInteger('approval_level')->default(1); // For multi-level approval
            $table->boolean('can_approve')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->boolean('is_final_approver')->default(false);
            $table->timestamps();

            $table->index(['leave_tier_id', 'approval_level']);
        });

        Schema::create('manager_delegations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete()->name('md_manager_fk');
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete()->name('md_delegate_fk');
            $table->enum('delegation_type', ['full', 'leave_approval', 'attendance_approval', 'expense_approval'])->default('full');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['manager_id', 'is_active'], 'md_manager_active_idx');
            $table->index(['delegate_id', 'is_active'], 'md_delegate_active_idx');
        });

        Schema::create('manager_team_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('mtv_org_fk');
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete()->name('mtv_manager_fk');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('mtv_employee_fk');
            $table->enum('relationship_type', ['direct_report', 'indirect_report'])->default('direct_report');
            $table->timestamps();

            $table->unique(['manager_id', 'employee_id'], 'mtv_manager_employee_unq');
        });

        Schema::create('off_cycle_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('ocpi_org_fk');
            $table->foreignId('off_cycle_payroll_run_id')->constrained('off_cycle_payroll_runs')->cascadeOnDelete()->name('ocpi_run_fk');
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('ocpi_employee_fk');
            $table->string('component_code', 50);
            $table->string('component_name', 100);
            $table->decimal('amount', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('net_amount', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['off_cycle_payroll_run_id'], 'ocpi_run_idx');
            $table->index(['employee_id'], 'ocpi_employee_idx');
        });

        Schema::create('off_cycle_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('run_type', ['bonus', 'termination', 'correction', 'advance_recovery', 'other'])->default('bonus');
            $table->string('run_name', 100);
            $table->date('run_date');
            $table->enum('status', ['draft', 'processing', 'completed', 'cancelled'])->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 18, 4)->default(0);
            $table->decimal('total_net', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete()->name('ocpr_processed_by_fk');
            $table->datetime('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'run_date'], 'ocpr_org_date_idx');
        });

        Schema::create('om_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('task_code', 20);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('task_type', ['function', 'activity', 'responsibility'])->default('function');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'task_code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('overtime_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('policy_name', 100);
            $table->decimal('daily_standard_hours', 5, 2)->default(8);
            $table->decimal('weekly_standard_hours', 6, 2)->default(40);
            $table->decimal('ot_rate_weekday', 5, 2)->default(1.5);
            $table->decimal('ot_rate_weekend', 5, 2)->default(2.0);
            $table->decimal('ot_rate_holiday', 5, 2)->default(2.5);
            $table->decimal('max_daily_ot_hours', 5, 2)->default(4);
            $table->decimal('max_weekly_ot_hours', 6, 2)->default(12);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'op_org_active_idx');
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained('overtime_policies')->cascadeOnDelete();
            $table->date('ot_date');
            $table->time('ot_start');
            $table->time('ot_end');
            $table->decimal('ot_hours', 5, 2);
            $table->string('reason', 500)->nullable();
            $table->enum('day_type', ['weekday', 'weekend', 'holiday'])->default('weekday');
            $table->decimal('ot_rate', 5, 2);
            $table->decimal('ot_amount', 15, 4)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['employee_id', 'status'], 'or_emp_status_idx');
            $table->index(['employee_id', 'ot_date'], 'or_emp_date_idx');
        });

        Schema::create('pay_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('grade_code', 20);
            $table->string('grade_name', 100);
            $table->decimal('min_salary', 15, 4);
            $table->decimal('mid_salary', 15, 4);
            $table->decimal('max_salary', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->timestamps();
            $table->unique(['organization_id', 'grade_code'], 'pg_org_code_unique');
        });

        Schema::create('payroll_corrections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('pc_employee_fk');
            $table->foreignId('original_payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete()->name('pc_orig_period_fk');
            $table->foreignId('correction_payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete()->name('pc_corr_period_fk');
            $table->enum('correction_type', ['salary_change', 'component_adjustment', 'tax_correction', 'deduction_adjustment'])->default('salary_change');
            $table->enum('status', ['draft', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->decimal('original_amount', 18, 4);
            $table->decimal('corrected_amount', 18, 4);
            $table->decimal('difference_amount', 18, 4);
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->name('pc_approved_by_fk');
            $table->datetime('approved_at')->nullable();
            $table->datetime('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id'], 'pc_org_employee_idx');
            $table->index(['original_payroll_period_id'], 'pc_orig_period_idx');
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date')->nullable();
            $table->enum('status', ['open', 'processing', 'processed', 'closed'])->default('open');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('processed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'start_date', 'end_date']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('epf_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->string('uan', 12)->nullable();                    // Universal Account Number (EPFO)
            $table->decimal('pf_wage', 12, 2);                        // PF wage (basic + DA, capped at 15000)
            $table->decimal('employee_contribution', 12, 2);          // 12% of PF wage
            $table->decimal('employer_epf_contribution', 12, 2);      // 3.67% diff after EPS
            $table->decimal('employer_eps_contribution', 12, 2);      // 8.33% EPS (max ₹1250/month)
            $table->decimal('edli_contribution', 12, 2);              // 0.50% EDLI employer
            $table->decimal('admin_charges', 12, 2)->default(0);      // 0.50% EPF admin
            $table->enum('status', ['draft', 'submitted', 'challan_paid'])->default('draft');
            $table->string('challan_number', 50)->nullable();
            $table->date('challan_due_date')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'employee_id', 'payroll_period_id'],
                'epf_contrib_org_emp_period_uniq'
            );
            $table->index(['organization_id', 'payroll_period_id'], 'epf_contrib_org_period_idx');
        });

        Schema::create('esi_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->string('ip_number', 17)->nullable();              // Insurance Policy Number (ESIC)
            $table->decimal('gross_wage', 12, 2);
            $table->decimal('employee_contribution', 12, 2);         // 0.75% of gross wage
            $table->decimal('employer_contribution', 12, 2);         // 3.25% of gross wage
            $table->boolean('is_applicable')->default(true);         // false when gross > ₹21,000
            $table->enum('status', ['draft', 'submitted', 'challan_paid'])->default('draft');
            $table->string('challan_number', 50)->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'employee_id', 'payroll_period_id'],
                'esi_contrib_org_emp_period_uniq'
            );
            $table->index(['organization_id', 'payroll_period_id'], 'esi_contrib_org_period_idx');
        });

        Schema::create('leave_encashments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->foreignId('leave_balance_id')->constrained('leave_balances')->cascadeOnDelete();
            $table->decimal('requested_days', 5, 2);
            $table->decimal('approved_days', 5, 2)->nullable();
            $table->decimal('daily_rate', 15, 2); // Employee's daily salary
            $table->decimal('encashment_rate', 5, 2); // Percentage (100 = full)
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, paid
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('payroll_id')->nullable(); // Link to payroll when paid
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);

            $table->foreign('payroll_id', 'leave_encash_payroll_period_fk')
                ->references('id')->on('payroll_periods')->nullOnDelete();
        });

        Schema::create('per_diem_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('destination_country', 3); // ISO country code
            $table->string('destination_city', 100)->nullable();
            $table->decimal('daily_allowance', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('meal_allowance_type', ['included', 'separate'])->default('included');
            $table->decimal('meal_breakfast', 15, 4)->default(0);
            $table->decimal('meal_lunch', 15, 4)->default(0);
            $table->decimal('meal_dinner', 15, 4)->default(0);
            $table->decimal('mileage_rate', 10, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(
                ['organization_id', 'destination_country', 'destination_city'],
                'pdr_org_dest_unique'
            );
        });

        Schema::create('performance_appraisals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('appraisal_cycle_id')->constrained('appraisal_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('appraisal_template_id')->nullable()->constrained('appraisal_templates')->nullOnDelete();
            $table->enum('status', [
                'pending',
                'self_review_submitted',
                'manager_review_submitted',
                'acknowledged',
                'completed',
            ])->default('pending');
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('manager_submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->decimal('overall_self_rating', 3, 2)->nullable();
            $table->decimal('overall_manager_rating', 3, 2)->nullable();
            $table->decimal('final_rating', 3, 2)->nullable();
            $table->text('self_comments')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('employee_acknowledgement')->nullable();
            $table->timestamps();

            $table->unique(['appraisal_cycle_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('appraisal_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_appraisal_id')->constrained('performance_appraisals')->cascadeOnDelete();
            $table->foreignId('appraisal_template_question_id')->constrained('appraisal_template_questions')->cascadeOnDelete();
            $table->enum('respondent_type', ['self', 'manager']);
            $table->tinyInteger('rating')->nullable();
            $table->text('text_response')->nullable();
            $table->timestamps();

            $table->index(['performance_appraisal_id', 'respondent_type'], 'appraisal_resp_appraisal_type_idx');
        });

        Schema::create('appraisal_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')
                ->constrained('performance_appraisals')
                ->cascadeOnDelete();
            $table->foreignId('reviewer_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->string('reviewer_type', 50)
                ->comment('self, peer, subordinate, manager, external');
            $table->string('status', 50)->default('pending')
                ->comment('pending, in_progress, submitted, declined');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('comments')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->date('due_date')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['appraisal_id', 'status']);
            $table->index(['reviewer_id', 'status']);
            // One reviewer can only appear once per appraisal per type
            $table->unique(['appraisal_id', 'reviewer_id', 'reviewer_type'], 'appr_reviewer_appraisal_reviewer_type_unq');
        });

        Schema::create('appraisal_reviewer_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_reviewer_id')
                ->constrained('appraisal_reviewers')
                ->cascadeOnDelete();
            $table->foreignId('question_id')
                ->nullable()
                ->constrained('appraisal_template_questions')
                ->nullOnDelete();
            $table->string('question_text')
                ->comment('Denormalised in case the template changes after submission');
            $table->decimal('rating', 3, 2)->nullable();
            $table->text('response_text')->nullable();
            $table->timestamps();

            $table->index('appraisal_reviewer_id');
        });

        Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('appraisal_cycle_id')->nullable()->constrained('appraisal_cycles')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->tinyInteger('progress_percent')->default(0);
            $table->tinyInteger('self_rating')->nullable();
            $table->tinyInteger('manager_rating')->nullable();
            $table->text('self_comments')->nullable();
            $table->text('manager_comments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'employee_id', 'status']);
        });

        Schema::create('performance_goal_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_goal_id')->constrained('performance_goals')->cascadeOnDelete();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('progress_percent');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('performance_goal_id');
        });

        Schema::create('personnel_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('action_number')->unique();             // PA-2026-00001 (SAP PA40 action number)
            $table->unsignedBigInteger('employee_id');
            $table->string('action_type');                        // hire|transfer|promotion|demotion|exit|rehire|leave_of_absence
            $table->date('effective_date');
            $table->string('status')->default('draft');           // draft|submitted|approved|completed|reversed|rejected
            $table->json('payload')->nullable();                  // action-specific data (new dept, new salary, etc.)
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('initiated_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('personnel_action_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personnel_action_id');
            $table->string('step_name');                          // e.g. update_position, update_salary, notify_payroll
            $table->string('status')->default('pending');         // pending|completed|failed|skipped
            $table->json('result')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->foreign('personnel_action_id')->references('id')->on('personnel_actions')->cascadeOnDelete();
            $table->index(['personnel_action_id', 'status']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('position_code', 20);
            $table->string('position_title', 150);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('pay_grade_id')->nullable()->constrained('pay_grades')->nullOnDelete();
            $table->unsignedBigInteger('reports_to_position_id')->nullable(); // self-referential
            $table->integer('headcount_authorized')->default(1);
            $table->integer('headcount_filled')->default(0);
            $table->boolean('is_key_position')->default(false);
            $table->enum('status', ['active', 'frozen', 'abolished'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'position_code'], 'pos_org_code_unique');
            $table->index(['organization_id', 'department_id'], 'pos_org_dept_idx');
        });

        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('transfer_number', 50);
            $table->date('effective_date');
            $table->enum('transfer_type', [
                'department',
                'position',
                'designation',
                'location',
                'manager',
                'lateral',
                'promotion',
                'demotion',
            ]);
            $table->string('reason', 500)->nullable();

            // From fields
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('to_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('from_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('to_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('from_reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('to_reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Workflow
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'rejected',
                'applied',
            ])->default('draft');

            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'transfer_number']);
            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'effective_date']);
        });

        Schema::create('om_position_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('om_tasks')->cascadeOnDelete();
            $table->enum('responsibility_level', ['primary', 'secondary', 'additional'])->default('primary');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'task_id']);
            $table->index(['organization_id', 'position_id']);
        });

        Schema::create('probation_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('pp_employee_fk');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('extended_end_date')->nullable();
            $table->enum('status', ['active', 'completed', 'extended', 'failed', 'waived'])->default('active');
            $table->date('review_date')->nullable();
            $table->enum('outcome', ['confirmed', 'extended', 'terminated'])->nullable();
            $table->date('outcome_date')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete()->name('pp_reviewer_fk');
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id'], 'probation_org_employee_idx');
            $table->index(['organization_id', 'status'], 'probation_org_status_idx');
            $table->index(['end_date'], 'probation_end_date_idx');
        });

        Schema::create('professional_tax_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('state_code', 2);          // ISO IN-state: KA, MH, WB, TN, AP, TS...
            $table->decimal('salary_from', 12, 2);
            $table->decimal('salary_to', 12, 2)->nullable();  // null = no upper limit
            $table->decimal('monthly_tax', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'state_code'], 'pt_config_org_state_idx');
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->string('country_code', 3)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_optional')->default(false);
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->unique(['organization_id', 'holiday_date', 'branch_id']);
            $table->index(['organization_id', 'year']);
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->enum('type', ['earning', 'deduction'])->default('earning');
            $table->enum('category', [
                'basic',
                'allowance',
                'bonus',
                'reimbursement',
                'statutory_deduction', // PF, ESI, GOSI, etc.
                'voluntary_deduction', // Loan, advance, etc.
                'tax', // TDS, income tax, etc.
            ])->default('allowance');

            // Calculation
            $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->default('fixed');
            $table->decimal('default_value', 15, 4)->default(0);
            $table->string('percentage_of', 50)->nullable(); // Component code to calculate percentage of
            $table->string('formula', 500)->nullable();

            // Tax treatment
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pro_rata')->default(true); // Based on days worked
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_flexible_benefit')->default(false); // Part of flexible benefit plan

            // Display
            $table->boolean('show_in_payslip')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('payroll_frequency', ['monthly', 'bi_weekly', 'weekly'])->default('monthly');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('ctc', 15, 4)->default(0); // Cost to company (annual)
            $table->decimal('gross_salary', 15, 4)->default(0); // Monthly gross
            $table->decimal('net_salary', 15, 4)->default(0); // Monthly net
            $table->string('currency_code', 3)->default('SAR');
            $table->string('reason_for_change', 500)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['employee_salary_id', 'salary_component_id'], 'emp_salary_component_unique');
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_salary_id')->constrained()->cascadeOnDelete();

            // Period info
            $table->string('payslip_number', 50);
            $table->date('payment_date')->nullable();

            // Work days
            $table->decimal('total_working_days', 5, 2)->default(0);
            $table->decimal('days_worked', 5, 2)->default(0);
            $table->decimal('days_on_leave', 5, 2)->default(0);
            $table->decimal('unpaid_leave_days', 5, 2)->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);

            // Amounts
            $table->decimal('gross_earnings', 15, 4)->default(0);
            $table->decimal('total_deductions', 15, 4)->default(0);
            $table->decimal('net_salary', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');

            // Tax info (for India)
            $table->decimal('taxable_income', 15, 4)->default(0);
            $table->decimal('tax_deducted', 15, 4)->default(0);

            // Status
            $table->enum('status', ['draft', 'pending', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();

            // Payment
            $table->string('payment_mode', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->datetime('paid_at')->nullable();

            // Journal entry
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'payslip_number']);
            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['organization_id', 'status']);

            $table->index(['employee_id', 'status'], 'ps_employee_status_idx');
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_amount', 15, 4);
            $table->decimal('interest_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4);
            $table->decimal('amount_paid', 15, 4)->default(0);
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'partial', 'skipped'])->default('pending');
            $table->timestamps();

            $table->index(['employee_loan_id', 'status']);
            $table->index('due_date');
        });

        Schema::create('payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['earning', 'deduction']);
            $table->string('name', 100);
            $table->decimal('amount', 15, 4)->default(0);
            $table->decimal('ytd_amount', 15, 4)->default(0); // Year to date
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('payslip_id');

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained()->cascadeOnDelete();
            $table->enum('calculation_type', ['fixed', 'percentage', 'formula'])->nullable();
            $table->decimal('value', 15, 4)->default(0);
            $table->string('percentage_of', 50)->nullable();
            $table->string('formula', 500)->nullable();
            $table->timestamps();

            $table->unique(['salary_structure_id', 'salary_component_id'], 'structure_component_unique');
        });

        Schema::create('shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->json('days_of_week');
            $table->boolean('crosses_midnight')->default(false);
            $table->string('color_hex', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'shft_pat_org_active_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('shift_patterns');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('payslip_items');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('professional_tax_configs');
        Schema::dropIfExists('probation_periods');
        Schema::dropIfExists('om_position_tasks');
        Schema::dropIfExists('employee_transfers');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('personnel_action_steps');
        Schema::dropIfExists('personnel_actions');
        Schema::dropIfExists('performance_goal_updates');
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('appraisal_reviewer_responses');
        Schema::dropIfExists('appraisal_reviewers');
        Schema::dropIfExists('appraisal_responses');
        Schema::dropIfExists('performance_appraisals');
        Schema::dropIfExists('per_diem_rates');
        Schema::dropIfExists('leave_encashments');
        Schema::dropIfExists('esi_contributions');
        Schema::dropIfExists('epf_contributions');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_corrections');
        Schema::dropIfExists('pay_grades');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('overtime_policies');
        Schema::dropIfExists('om_tasks');
        Schema::dropIfExists('off_cycle_payroll_runs');
        Schema::dropIfExists('off_cycle_payroll_items');
        Schema::dropIfExists('manager_team_views');
        Schema::dropIfExists('manager_delegations');
        Schema::dropIfExists('leave_tier_approvers');
        Schema::dropIfExists('leave_tiers');
        Schema::dropIfExists('leave_calendar');
    }
};
