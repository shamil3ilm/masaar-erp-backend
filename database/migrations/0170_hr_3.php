<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_rosters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->date('roster_period_start');
            $table->date('roster_period_end');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'shft_ros_org_status_idx');
            $table->index(['organization_id', 'roster_period_start', 'roster_period_end'], 'shft_ros_org_period_idx');
        });

        Schema::create('shift_roster_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('roster_id')->constrained('shift_rosters')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
            $table->date('shift_date');
            $table->boolean('is_day_off')->default(false);
            $table->time('override_start_time')->nullable();
            $table->time('override_end_time')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['roster_id', 'employee_id', 'shift_date'], 'shft_line_ros_emp_date_uniq');
            $table->index(['employee_id', 'shift_date'], 'shft_line_emp_date_idx');
        });

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requested_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('requester_roster_line_id')->nullable()->constrained('shift_roster_lines')->nullOnDelete();
            $table->foreignId('requested_roster_line_id')->nullable()->constrained('shift_roster_lines')->nullOnDelete();
            $table->date('requester_shift_date');
            $table->date('requested_shift_date');
            $table->string('reason', 500)->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'approved', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'shft_swp_org_status_idx');
            $table->index(['requester_id', 'status'], 'shft_swp_req_status_idx');
            $table->index(['requested_employee_id', 'status'], 'shft_swp_reqd_status_idx');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_overnight')->default(false);
            $table->boolean('is_flexible')->default(false);
            $table->unsignedSmallInteger('flexible_start_window_minutes')->default(0);
            $table->boolean('overtime_eligible')->default(false);
            $table->string('color_hex', 7)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'shifts_org_active_idx');
        });

        Schema::create('employee_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'employee_id'], 'esa_org_emp_idx');
            $table->index(['employee_id', 'effective_from', 'effective_to'], 'esa_emp_period_idx');
        });

        Schema::create('skill_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id', 'skill_cat_parent_fk')->references('id')->on('skill_categories')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('skill_category_id');
            $table->foreign('skill_category_id', 'skill_cat_fk')->references('id')->on('skill_categories')->onDelete('restrict');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('proficiency_scale')->default(5); // 1-5 or 1-10
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('social_insurance_schemes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('country_code', 10);
            $table->string('scheme_code', 20)->nullable();
            $table->decimal('employee_contribution_pct', 5, 2)->default(0);
            $table->decimal('employer_contribution_pct', 5, 2)->default(0);
            $table->decimal('work_hazard_pct', 5, 2)->default(0);
            $table->enum('applicable_to', ['all', 'nationals_only', 'expats_only'])->default('all');
            $table->decimal('salary_ceiling', 15, 4)->nullable();
            $table->decimal('salary_floor', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'country_code'], 'si_sch_org_country_idx');
        });

        Schema::create('social_insurance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('social_insurance_schemes')->cascadeOnDelete();
            $table->string('employee_number_si', 50)->nullable();
            $table->date('enrollment_date');
            $table->date('termination_date')->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->decimal('insurable_salary', 15, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'scheme_id'], 'si_rec_emp_scheme_uniq');
            $table->index(['organization_id', 'status'], 'si_rec_org_status_idx');
        });

        Schema::create('social_insurance_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('social_insurance_schemes')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedInteger('total_employees')->default(0);
            $table->decimal('total_insurable_salary', 15, 4)->default(0);
            $table->decimal('total_employee_contrib', 15, 4)->default(0);
            $table->decimal('total_employer_contrib', 15, 4)->default(0);
            $table->decimal('total_work_hazard_contrib', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->enum('status', ['draft', 'submitted', 'acknowledged', 'rejected'])->default('draft');
            $table->string('reference_number', 100)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'scheme_id', 'period_year', 'period_month'], 'si_sub_org_sch_period_uniq');
            $table->index(['organization_id', 'status'], 'si_sub_org_status_idx');
        });

        Schema::create('social_insurance_submission_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('submission_id')->constrained('social_insurance_submissions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('social_insurance_records')->cascadeOnDelete();
            $table->string('employee_number_si', 50)->nullable();
            $table->decimal('insurable_salary', 15, 4)->default(0);
            $table->decimal('employee_contribution', 15, 4)->default(0);
            $table->decimal('employer_contribution', 15, 4)->default(0);
            $table->decimal('work_hazard_contribution', 15, 4)->default(0);
            $table->decimal('total_contribution', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['submission_id', 'employee_id'], 'si_line_sub_emp_idx');
        });

        Schema::create('succession_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('key_position_id')->constrained('key_positions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('readiness', ['ready_now', 'one_two_years', 'three_five_years'])->default('three_five_years');
            $table->unsignedTinyInteger('performance_rating')->nullable()->comment('1-5 rating');
            $table->unsignedTinyInteger('potential_rating')->nullable()->comment('1-5 rating');
            $table->foreignId('nominated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('nomination_date')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['key_position_id', 'employee_id'], 'succ_cand_pos_emp_uniq');
            $table->index(['employee_id', 'readiness'], 'succ_cand_emp_ready_idx');
            $table->index(['key_position_id', 'readiness'], 'succ_cand_pos_ready_idx');
        });

        Schema::create('succession_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('current_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('position_title')->nullable();
            $table->string('criticality', 20)->default('medium'); // critical, high, medium, low
            $table->string('status', 20)->default('active'); // active, inactive, completed
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'criticality']);
            $table->index('current_employee_id');
        });

        Schema::create('succession_plan_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('succession_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('readiness', 30)->default('development_needed'); // ready_now, ready_1_year, ready_2_years, development_needed
            $table->unsignedTinyInteger('rank')->default(1);
            $table->text('strengths')->nullable();
            $table->text('development_areas')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['succession_plan_id', 'employee_id']);
            $table->index(['succession_plan_id', 'readiness']);
            $table->index('employee_id');
        });

        Schema::create('succession_pool_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('candidate_id')->constrained('succession_candidates')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('activity_type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->text('outcome')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['candidate_id', 'status'], 'succ_act_cand_status_idx');
            $table->index(['employee_id', 'status'], 'succ_act_emp_status_idx');
        });

        Schema::create('time_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'transferred_to_payroll',
            ])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('total_regular_hours', 8, 2)->default(0);
            $table->decimal('total_overtime_hours', 8, 2)->default(0);
            $table->decimal('total_absence_hours', 8, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['employee_id', 'period_start', 'period_end'], 'ts_emp_period_unique');
            $table->index(['organization_id', 'status'], 'ts_org_status_idx');
        });

        Schema::create('time_wage_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10); // OT15=1.5x overtime, NT=night diff, WE=weekend
            $table->string('name', 100);
            $table->enum('wage_category', [
                'overtime',
                'night_differential',
                'weekend',
                'holiday',
                'absence_deduction',
                'other',
            ])->default('other');
            $table->decimal('rate_multiplier', 5, 4)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'twt_org_code_unique');
        });

        Schema::create('time_evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_sheet_id')->constrained('time_sheets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('evaluation_date');
            $table->foreignId('wage_type_id')->constrained('time_wage_types')->cascadeOnDelete();
            $table->decimal('hours', 5, 2);
            $table->decimal('amount', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('transferred_to_payroll')->default(false);
            $table->timestamps();
            $table->index(['time_sheet_id'], 'ter_sheet_idx');
            $table->index(['employee_id', 'evaluation_date'], 'ter_emp_date_idx');
        });

        Schema::create('training_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('training_providers')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', [
                'technical',
                'soft_skills',
                'compliance',
                'safety',
                'leadership',
                'onboarding',
                'other',
            ])->default('other');
            $table->enum('delivery_type', [
                'in_person',
                'online',
                'blended',
                'self_paced',
            ])->default('in_person');
            $table->decimal('duration_hours', 5, 1)->default(1);
            $table->unsignedInteger('max_participants')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->unsignedInteger('validity_months')->nullable()->comment('Months before recertification needed');
            $table->decimal('cost_per_participant', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
        });

        Schema::create('training_needs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete()->comment('null = department-wide');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('training_courses')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['identified', 'planned', 'fulfilled', 'cancelled'])->default('identified');
            $table->foreignId('identified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->string('session_number');
            $table->string('trainer_name')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->unsignedInteger('max_participants')->nullable();
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('status', [
                'enrolled',
                'attended',
                'completed',
                'failed',
                'cancelled',
                'no_show',
            ])->default('enrolled');
            $table->timestamp('enrolled_at');
            $table->date('completion_date')->nullable();
            $table->decimal('score', 5, 2)->nullable()->comment('Percentage');
            $table->text('feedback')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'employee_id']);
            $table->index(['employee_id', 'status']);
            $table->index('organization_id');
        });

        Schema::create('training_certifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('training_enrollments')->nullOnDelete();
            $table->string('certificate_number')->nullable();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('issued_by')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'course_id']);
            $table->index('organization_id');
        });

        Schema::create('travel_expense_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->enum('category', ['accommodation', 'transport', 'meals', 'entertainment', 'other']);
            $table->decimal('daily_limit', 15, 4)->nullable();
            $table->string('gl_account_code', 20)->nullable();
            $table->boolean('requires_receipt')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('request_number', 30);
            $table->string('purpose', 500);
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('destination_country', 3);
            $table->string('destination_city', 100)->nullable();
            $table->enum('travel_type', ['domestic', 'international'])->default('domestic');
            $table->decimal('estimated_cost', 15, 4)->default(0);
            $table->decimal('advance_requested', 15, 4)->default(0);
            $table->decimal('advance_approved', 15, 4)->default(0);
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'completed',
                'cancelled',
            ])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'request_number'], 'tr_org_number_unique');
            $table->index(['employee_id', 'status'], 'tr_emp_status_idx');
        });

        Schema::create('travel_expense_claims', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('travel_request_id')->nullable()->constrained('travel_requests')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('claim_number', 30);
            $table->date('claim_date');
            $table->decimal('total_claimed', 15, 4)->default(0);
            $table->decimal('advance_paid', 15, 4)->default(0);
            $table->decimal('amount_reimbursable', 15, 4)->default(0);
            $table->decimal('amount_deductible', 15, 4)->default(0);
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'paid',
            ])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'claim_number'], 'tec_org_number_unique');
            $table->index(['employee_id', 'status'], 'tec_emp_status_idx');
        });

        Schema::create('travel_expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('travel_expense_claims')->cascadeOnDelete();
            $table->date('expense_date');
            $table->enum('expense_category', [
                'flight',
                'hotel',
                'meal',
                'transport',
                'per_diem',
                'mileage',
                'visa',
                'other',
            ])->default('other');
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 4);
            $table->decimal('mileage_km', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('amount_in_base_currency', 15, 4)->default(0);
            $table->string('receipt_reference', 100)->nullable();
            $table->boolean('receipt_attached')->default(false);
            $table->decimal('policy_limit', 15, 4)->nullable();
            $table->boolean('within_policy')->default(true);
            $table->timestamps();
            $table->index(['claim_id'], 'tel_claim_idx');
        });

        Schema::create('travel_expense_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('report_number', 30)->unique();
            $table->foreignId('travel_request_id')->nullable()->constrained('travel_requests')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('report_date');
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'posted'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('journal_entry_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('travel_expense_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_report_id')->constrained('travel_expense_reports')->cascadeOnDelete();
            $table->foreignId('expense_type_id')->constrained('travel_expense_types')->restrictOnDelete();
            $table->date('expense_date');
            $table->text('description');
            $table->decimal('amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('amount_in_local', 15, 4)->nullable();
            $table->boolean('receipt_attached')->default(false);
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('break_duration', 4, 2)->default(0); // in hours
            $table->decimal('working_hours', 4, 2)->default(8);
            $table->json('work_days')->nullable(); // [1,2,3,4,5] for Mon-Fri
            $table->boolean('is_flexible')->default(false);
            $table->unsignedSmallInteger('grace_period_minutes')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();

            // Check in/out
            $table->datetime('check_in')->nullable();
            $table->datetime('check_out')->nullable();
            $table->datetime('break_start')->nullable();
            $table->datetime('break_end')->nullable();

            // Calculated hours
            $table->decimal('working_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('break_hours', 5, 2)->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leaving_minutes')->default(0);

            // Status
            $table->enum('status', [
                'present',
                'absent',
                'half_day',
                'on_leave',
                'holiday',
                'weekend',
                'work_from_home',
                'on_duty', // Official duty outside office
            ])->default('present');

            // Source of entry
            $table->enum('source', ['manual', 'biometric', 'geo_fence', 'import'])->default('manual');
            $table->string('device_id', 100)->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();

            // Approval for regularization
            $table->boolean('is_regularized')->default(false);
            $table->string('regularization_reason', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['organization_id', 'attendance_date']);
            $table->index(['employee_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('travel_expense_report_lines');
        Schema::dropIfExists('travel_expense_reports');
        Schema::dropIfExists('travel_expense_lines');
        Schema::dropIfExists('travel_expense_claims');
        Schema::dropIfExists('travel_requests');
        Schema::dropIfExists('travel_expense_types');
        Schema::dropIfExists('training_certifications');
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_needs');
        Schema::dropIfExists('training_courses');
        Schema::dropIfExists('training_providers');
        Schema::dropIfExists('time_evaluation_results');
        Schema::dropIfExists('time_wage_types');
        Schema::dropIfExists('time_sheets');
        Schema::dropIfExists('succession_pool_activities');
        Schema::dropIfExists('succession_plan_candidates');
        Schema::dropIfExists('succession_plans');
        Schema::dropIfExists('succession_candidates');
        Schema::dropIfExists('social_insurance_submission_lines');
        Schema::dropIfExists('social_insurance_submissions');
        Schema::dropIfExists('social_insurance_records');
        Schema::dropIfExists('social_insurance_schemes');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_categories');
        Schema::dropIfExists('employee_shift_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('shift_roster_lines');
        Schema::dropIfExists('shift_rosters');
    }
};
