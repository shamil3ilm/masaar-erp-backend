<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->json('entity_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['delegator_id', 'is_active']);
            $table->index(['delegate_id', 'is_active']);
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegate_to')->constrained('users')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 500)->nullable();

            // Optional: only delegate specific workflow types
            $table->string('approvable_type', 100)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('attendance_regularizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->datetime('requested_check_in')->nullable();
            $table->datetime('requested_check_out')->nullable();
            $table->string('reason', 500);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('audit_log_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('event', 50);
            $table->string('auditable_type', 255);
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->index('archived_at');
        });

        Schema::create('bulk_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('min_quantity', 15, 4);
            $table->decimal('max_quantity', 15, 4)->nullable();
            $table->string('discount_type', 20); // percent, fixed_amount, fixed_price
            $table->decimal('discount_value', 15, 4);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'is_active']);
            $table->index(['organization_id', 'category_id', 'is_active']);
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });

        Schema::create('calibration_equipment', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('equipment_code', 30);
            $table->string('name');
            $table->string('manufacturer', 100)->nullable();
            $table->string('model_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->string('category', 50)->nullable()
                ->comment('thermometer/pressure_gauge/scale/caliper/multimeter/other');
            $table->string('location', 100)->nullable();
            $table->foreignId('responsible_person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'equipment_code'], 'cal_equip_org_code_idx');
            $table->index(['organization_id', 'is_active'], 'cal_equip_org_active_idx');
        });

        Schema::create('capacity_slots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('work_center_id')->constrained('work_centers', 'id', 'cap_slot_wc_fk');
            $table->date('slot_date');
            $table->time('slot_start');
            $table->time('slot_end');
            $table->unsignedSmallInteger('available_minutes');
            $table->unsignedSmallInteger('allocated_minutes')->default(0);
            $table->decimal('utilization_pct', 5, 2)->storedAs(
                'CASE WHEN available_minutes = 0 THEN 0 ELSE ROUND(allocated_minutes / available_minutes * 100, 2) END'
            );
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('capacity_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('capacity_slot_id')->constrained('capacity_slots', 'id', 'cap_res_slot_fk');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders', 'id', 'cap_res_wo_fk');
            $table->foreignId('routing_operation_id')->nullable()->constrained('routing_operations', 'id', 'cap_res_op_fk');
            $table->unsignedSmallInteger('reserved_minutes');
            $table->enum('status', ['tentative', 'confirmed', 'released'])->default('tentative');
            $table->timestamps();
        });

        Schema::create('compensatory_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('worked_date');
            $table->string('reason', 500);
            $table->decimal('days_earned', 3, 1)->default(1);
            $table->date('valid_until');
            $table->decimal('days_used', 3, 1)->default(0);
            $table->decimal('days_expired', 3, 1)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'used', 'expired'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('competency_frameworks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('applicable_to', ['all', 'department', 'designation', 'position'])->default('all');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('competency_framework_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competency_framework_id');
            $table->foreign('competency_framework_id', 'cf_skill_cf_fk')->references('id')->on('competency_frameworks')->onDelete('cascade');
            $table->unsignedBigInteger('skill_id');
            $table->foreign('skill_id', 'cf_skill_skill_fk')->references('id')->on('skills')->onDelete('cascade');
            $table->unsignedTinyInteger('required_level');
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('competency_gap_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id', 'comp_gap_emp_fk')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('framework_id');
            $table->foreign('framework_id', 'comp_gap_cf_fk')->references('id')->on('competency_frameworks')->onDelete('cascade');
            $table->date('analysis_date');
            $table->decimal('overall_score', 5, 2);
            $table->json('gaps'); // array of {skill_id, required, current, gap}
            $table->json('recommended_training')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('crm_contact_territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['territory_id', 'contact_id']);
        });

        Schema::create('customer_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete(); // Customer
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('min_quantity', 15, 4)->default(1);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['contact_id', 'product_id', 'min_quantity']);
        });

        Schema::create('cycle_count_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('cycle_count_session_id');
            $table->unsignedBigInteger('stock_adjustment_id')->nullable();
            $table->unsignedBigInteger('posted_by');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('cycle_count_session_id', 'cc_adj_sess_fk')->references('id')->on('cycle_count_sessions')->cascadeOnDelete();
            $table->foreign('stock_adjustment_id', 'cc_adj_sa_fk')->references('id')->on('stock_adjustments')->nullOnDelete();
            $table->foreign('posted_by', 'cc_adj_usr_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('summary_date');
            $table->string('metric_type', 50); // sales_total, invoice_count, payment_received, etc.
            $table->string('currency_code', 3)->default('SAR');

            // Values
            $table->decimal('value', 20, 4)->default(0);
            $table->decimal('count', 15, 0)->default(0);
            $table->decimal('previous_value', 20, 4)->nullable(); // Previous period comparison
            $table->decimal('change_percent', 10, 2)->nullable();

            // Breakdown
            $table->json('breakdown')->nullable(); // By category, product, customer, etc.

            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['organization_id', 'branch_id', 'summary_date', 'metric_type', 'currency_code'], 'daily_summary_unique');
            $table->index(['organization_id', 'metric_type', 'summary_date']);
        });

        Schema::create('daily_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->time('work_start');
            $table->time('work_end');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->decimal('planned_hours', 4, 2);
            $table->enum('day_type', ['normal', 'reduced', 'off', 'public_holiday'])->default('normal');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dashboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('snapshot_type', 20); // daily, weekly, monthly
            $table->json('data'); // Full dashboard data snapshot
            $table->timestamp('created_at');

            $table->unique(['organization_id', 'snapshot_date', 'snapshot_type'], 'dashboard_snapshots_org_date_type_unique');
            $table->index(['organization_id', 'snapshot_date']);
        });

        Schema::create('employee_skill_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id', 'emp_skill_emp_fk')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('skill_id');
            $table->foreign('skill_id', 'emp_skill_skill_fk')->references('id')->on('skills')->onDelete('cascade');
            $table->unsignedTinyInteger('current_level');
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->unsignedBigInteger('assessed_by')->nullable();
            $table->foreign('assessed_by', 'emp_skill_assessor_fk')->references('id')->on('users')->onDelete('set null');
            $table->date('assessed_at')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('certification_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('financial_close_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('financial_close_task_id');
            $table->unsignedBigInteger('depends_on_task_id');
            $table->timestamps();

            $table->foreign('financial_close_task_id', 'fk_fctdep_task')
                ->references('id')->on('financial_close_tasks')->onDelete('cascade');
            $table->foreign('depends_on_task_id', 'fk_fctdep_depends')
                ->references('id')->on('financial_close_tasks')->onDelete('cascade');

            $table->unique(
                ['financial_close_task_id', 'depends_on_task_id'],
                'uq_fctdep_task_dep'
            );
        });

        Schema::create('financial_idempotency_keys', function (Blueprint $table) {
            $table->id();

            // Scoped key: the caller-supplied string (e.g. "invoice:42:send")
            $table->string('key', 255);

            // Logical operation name (e.g. "invoice.send", "payroll.generate")
            $table->string('operation', 100);

            // Tenant scope — prevents cross-org key collisions
            $table->unsignedBigInteger('organization_id');

            // Processing state
            $table->enum('status', ['processing', 'completed', 'failed'])
                ->default('processing');

            // SHA-256 of the request payload for integrity checking (optional)
            $table->string('request_hash', 64)->nullable();

            // Cached result returned to duplicate callers
            $table->json('response_payload')->nullable();

            // When this key expires and may be reused
            $table->timestamp('expires_at')->index();

            $table->timestamps();

            // Serialisation point: DB enforces uniqueness, not application code
            $table->unique(['key', 'organization_id', 'operation'], 'fin_idempotency_scope_unique');

            $table->index('organization_id');
        });

        Schema::create('financial_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('period_type', 20); // daily, monthly, quarterly, yearly
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->decimal('debit_total', 20, 4)->default(0);
            $table->decimal('credit_total', 20, 4)->default(0);
            $table->decimal('closing_balance', 20, 4)->default(0);
            $table->decimal('base_opening_balance', 20, 4)->default(0);
            $table->decimal('base_closing_balance', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'fiscal_year_id', 'account_id', 'snapshot_date', 'period_type'], 'financial_snapshots_unique');
            $table->index(['organization_id', 'snapshot_date']);
        });

        Schema::create('location_characteristics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('floc_id');
            $table->string('characteristic_name');
            $table->string('characteristic_value');
            $table->string('uom', 20)->nullable();
            $table->timestamps();

            $table->foreign('floc_id', 'floc_char_floc_fk')->references('id')->on('functional_locations')->cascadeOnDelete();
        });

        Schema::create('location_equipment', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('floc_id');
            $table->string('equipment_number');
            $table->string('description');
            $table->string('category')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('installed_at')->nullable();
            $table->date('removed_at')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('floc_id', 'floc_eq_floc_fk')->references('id')->on('functional_locations')->cascadeOnDelete();
            $table->foreign('product_id', 'floc_eq_prod_fk')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('hr_budget_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id', 'hr_budget_dept_fk')->references('id')->on('departments')->onDelete('set null');
            $table->string('plan_name');
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'hr_budget_appr_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('total_headcount')->default(0);
            $table->decimal('total_salary_budget', 18, 4)->default(0);
            $table->decimal('total_benefits_budget', 18, 4)->default(0);
            $table->char('currency', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('hr_budget_plan_id');
            $table->foreign('hr_budget_plan_id', 'hr_budget_line_fk')->references('id')->on('hr_budget_plans')->onDelete('cascade');
            $table->unsignedBigInteger('position_id')->nullable();
            $table->foreign('position_id', 'hr_budget_line_pos_fk')->references('id')->on('positions')->onDelete('set null');
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->foreign('designation_id', 'hr_budget_line_des_fk')->references('id')->on('designations')->onDelete('set null');
            $table->unsignedSmallInteger('planned_headcount')->default(1);
            $table->decimal('planned_salary', 18, 4);
            $table->decimal('planned_benefits', 18, 4)->default(0);
            $table->decimal('quarter_1', 18, 4)->default(0);
            $table->decimal('quarter_2', 18, 4)->default(0);
            $table->decimal('quarter_3', 18, 4)->default(0);
            $table->decimal('quarter_4', 18, 4)->default(0);
            $table->unsignedSmallInteger('actual_headcount')->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('hr_headcount_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('department_id');
            $table->foreign('department_id', 'hc_plan_dept_fk')->references('id')->on('departments')->onDelete('cascade');
            $table->tinyInteger('month');
            $table->unsignedSmallInteger('planned_headcount');
            $table->unsignedSmallInteger('actual_headcount')->default(0);
            $table->unsignedSmallInteger('new_hires')->default(0);
            $table->unsignedSmallInteger('terminations')->default(0);
            $table->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint');
            $table->text('response')->nullable();
            $table->smallInteger('status_code');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->index('expires_at');
        });

        Schema::create('invoice_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->string('status', 30)->default('paid');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('amount_paid', 20, 4)->default(0);
            $table->decimal('amount_due', 20, 4)->default(0);
            $table->string('currency_code', 10)->default('SAR');
            $table->json('snapshot')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'invoice_date']);
            $table->index('archived_at');
        });

        Schema::create('ip_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address');
            $table->enum('action', ['allowed', 'denied', 'challenged']);
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->string('endpoint')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id', 'ip_log_usr_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rule_id', 'ip_log_rule_fk')->references('id')->on('ip_allowlist_rules')->nullOnDelete();
            $table->index(['organization_id', 'logged_at']);
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('journal_entry_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('entry_number', 50)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('description')->nullable();
            $table->date('entry_date');
            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);
            $table->string('status', 20)->default('posted');
            $table->string('currency_code', 10)->default('SAR');
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'entry_date']);
            $table->index('archived_at');
        });

        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // sales, inventory, finance, hr
            $table->string('calculation_type'); // sum, avg, count, formula, custom
            $table->text('formula')->nullable(); // SQL or calculation formula
            $table->string('data_type'); // number, currency, percentage, duration
            $table->string('trend_direction')->default('higher_better'); // higher_better, lower_better, neutral
            $table->json('thresholds')->nullable(); // {"good": 80, "warning": 50, "danger": 30}
            $table->string('comparison_period')->nullable(); // day, week, month, quarter, year
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kpi_code');
            $table->decimal('target_value', 20, 4);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_type'); // daily, weekly, monthly, quarterly, yearly
            $table->json('breakdown')->nullable(); // Monthly targets within period
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'kpi_code', 'period_start']);
        });

        Schema::create('leave_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['leave_request_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_attachments');
        Schema::dropIfExists('kpi_targets');
        Schema::dropIfExists('kpi_definitions');
        Schema::dropIfExists('journal_entry_archives');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('ip_access_logs');
        Schema::dropIfExists('invoice_archives');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('hr_headcount_plans');
        Schema::dropIfExists('hr_budget_lines');
        Schema::dropIfExists('hr_budget_plans');
        Schema::dropIfExists('location_equipment');
        Schema::dropIfExists('location_characteristics');
        Schema::dropIfExists('financial_snapshots');
        Schema::dropIfExists('financial_idempotency_keys');
        Schema::dropIfExists('financial_close_task_dependencies');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('employee_skill_profiles');
        Schema::dropIfExists('dashboard_snapshots');
        Schema::dropIfExists('daily_work_schedules');
        Schema::dropIfExists('daily_summaries');
        Schema::dropIfExists('cycle_count_adjustments');
        Schema::dropIfExists('customer_prices');
        Schema::dropIfExists('crm_contact_territories');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('competency_gap_analyses');
        Schema::dropIfExists('competency_framework_skills');
        Schema::dropIfExists('competency_frameworks');
        Schema::dropIfExists('compensatory_offs');
        Schema::dropIfExists('capacity_reservations');
        Schema::dropIfExists('capacity_slots');
        Schema::dropIfExists('calibration_equipment');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('bulk_pricing_rules');
        Schema::dropIfExists('audit_log_archives');
        Schema::dropIfExists('attendance_regularizations');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_delegates');
    }
};
