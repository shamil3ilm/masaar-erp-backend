<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_address', 45);
            $table->boolean('successful')->default(false);
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['email', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });

        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('metric_type', 50);
            $table->string('currency_code', 3)->default('SAR');

            $table->decimal('value', 20, 4)->default(0);
            $table->decimal('count', 15, 0)->default(0);
            $table->decimal('daily_average', 20, 4)->nullable();
            $table->decimal('previous_value', 20, 4)->nullable();
            $table->decimal('yoy_change_percent', 10, 2)->nullable(); // Year over year
            $table->decimal('mom_change_percent', 10, 2)->nullable(); // Month over month

            $table->json('daily_breakdown')->nullable();
            $table->json('category_breakdown')->nullable();

            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['organization_id', 'branch_id', 'year', 'month', 'metric_type', 'currency_code'], 'monthly_summary_unique');
            $table->index(['organization_id', 'metric_type', 'year', 'month']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('notification_type', 100);
            $table->string('channel', 20); // email, sms, database
            $table->string('language', 5)->default('en');
            $table->string('subject')->nullable(); // For email
            $table->text('body');
            $table->json('variables')->nullable(); // Available template variables
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'notification_type', 'channel', 'language'], 'notif_tpl_unique');
        });

        Schema::create('org_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('position_title');
            $table->unsignedBigInteger('org_unit_id');
            $table->foreign('org_unit_id', 'org_pos_unit_fk')->references('id')->on('org_units')->onDelete('cascade');
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->foreign('designation_id', 'org_pos_desig_fk')->references('id')->on('designations')->onDelete('set null');
            $table->unsignedBigInteger('reports_to_position_id')->nullable();
            $table->foreign('reports_to_position_id', 'org_pos_rpt_fk')->references('id')->on('org_positions')->onDelete('set null');
            $table->unsignedSmallInteger('headcount_budget')->default(1);
            $table->unsignedSmallInteger('current_headcount')->default(0);
            $table->boolean('is_key_position')->default(false);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('notification_type', 100);
            $table->boolean('is_enabled')->default(true);
            $table->json('default_channels')->nullable(); // ['database', 'email']
            $table->json('settings')->nullable(); // Type-specific settings
            $table->timestamps();

            $table->unique(['organization_id', 'notification_type'], 'org_notif_type_unique');
        });

        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('group', 50); // general, invoice, inventory, hr, etc.
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, integer, boolean, json, date
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be exposed to frontend
            $table->timestamps();

            $table->unique(['organization_id', 'group', 'key']);
            $table->index(['organization_id', 'group']);
        });

        Schema::create('password_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();

            $table->index(['user_id', 'changed_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payroll_schemas', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('schema_name');
            $table->text('description')->nullable();
            $table->char('country_code', 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payroll_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('schema_id');
            $table->foreign('schema_id', 'pr_run_schema_fk')->references('id')->on('payroll_schemas')->onDelete('restrict');
            $table->unsignedBigInteger('payroll_period_id');
            $table->foreign('payroll_period_id', 'pr_run_period_fk')->references('id')->on('payroll_periods')->onDelete('restrict');
            $table->enum('run_type', ['simulation', 'live', 'correction']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('employee_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('run_by');
            $table->foreign('run_by', 'pr_run_usr_fk')->references('id')->on('users')->onDelete('restrict');
            $table->json('error_log')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_schema_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('payroll_schema_id');
            $table->foreign('payroll_schema_id', 'pr_step_schema_fk')->references('id')->on('payroll_schemas')->onDelete('cascade');
            $table->unsignedSmallInteger('step_number');
            $table->string('function_name', 10);
            $table->string('parameter')->nullable();
            $table->string('condition_wage_type')->nullable();
            $table->string('processing_class')->nullable();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('period_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('cycle_length_weeks')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('period_work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_work_schedule_id');
            $table->foreign('period_work_schedule_id', 'pws_day_pws_fk')->references('id')->on('period_work_schedules')->onDelete('cascade');
            $table->unsignedTinyInteger('week_number');
            $table->tinyInteger('day_of_week'); // 1=Mon, 7=Sun
            $table->unsignedBigInteger('daily_work_schedule_id')->nullable();
            $table->foreign('daily_work_schedule_id', 'pws_day_dws_fk')->references('id')->on('daily_work_schedules')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('position_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id', 'pos_assign_emp_fk')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('org_position_id');
            $table->foreign('org_position_id', 'pos_assign_pos_fk')->references('id')->on('org_positions')->onDelete('cascade');
            $table->enum('assignment_type', ['primary', 'secondary', 'acting'])->default('primary');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('conversion_factor', 15, 6); // How many base units
            $table->string('barcode', 50)->nullable();
            $table->decimal('selling_price', 15, 4)->nullable();
            $table->decimal('purchase_price', 15, 4)->nullable();
            $table->boolean('is_purchase_unit')->default(false);
            $table->boolean('is_sales_unit')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });

        Schema::create('production_confirmations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('confirmation_number')->unique();
            $table->foreignId('work_order_id')->constrained('work_orders', 'id', 'prod_conf_wo_fk');
            $table->foreignId('operation_id')
                ->nullable()
                ->constrained('work_order_operations', 'id', 'prod_conf_op_fk');
            $table->enum('confirmation_type', ['partial', 'final', 'milestone']);
            $table->decimal('confirmed_quantity', 18, 4);
            $table->decimal('scrap_quantity', 18, 4)->default(0);
            $table->decimal('rework_quantity', 18, 4)->default(0);
            $table->decimal('actual_setup_time', 8, 2)->nullable();
            $table->decimal('actual_machine_time', 8, 2)->nullable();
            $table->decimal('actual_labor_time', 8, 2)->nullable();
            $table->enum('time_uom', ['minutes', 'hours'])->default('minutes');
            $table->foreignId('confirmed_by')->constrained('users', 'id', 'prod_conf_usr_fk');
            $table->timestamp('confirmed_at');
            $table->date('posting_date');
            $table->enum('shift', ['morning', 'afternoon', 'night'])->nullable();
            $table->boolean('is_final')->default(false);
            $table->foreignId('reversal_id')
                ->nullable()
                ->constrained('production_confirmations', 'id', 'prod_conf_rev_fk');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_budget_availability_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pbal_org_fk')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_budget_line_item_id');
            $table->foreign('project_budget_line_item_id', 'pbal_pbli_fk')
                ->references('id')->on('project_budget_line_items')->cascadeOnDelete();

            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->foreign('wbs_element_id', 'pbal_wbs_fk')->references('id')->on('wbs_elements')->nullOnDelete();

            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');

            $table->decimal('requested_amount', 18, 4);
            $table->decimal('available_amount', 18, 4);
            $table->enum('result', ['approved', 'warning', 'rejected']);
            $table->string('message', 255)->nullable();
            $table->dateTime('checked_at');

            $table->unsignedBigInteger('checked_by')->nullable();
            $table->foreign('checked_by', 'pbal_checked_by_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['wbs_element_id', 'checked_at'], 'pbal_wbs_checked_idx');
            $table->index(['document_type', 'document_id'], 'pbal_doc_idx');
        });

        Schema::create('promotion_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_excluded')->default(false);

            $table->index(['promotion_id', 'contact_id']);
            $table->index(['promotion_id', 'customer_group_id']);
        });

        Schema::create('promotion_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->boolean('is_excluded')->default(false); // true = exclude this product/category

            $table->index(['promotion_id', 'product_id']);
            $table->index(['promotion_id', 'category_id']);
        });

        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete(); // NULL for system reports
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('module', 30); // sales, purchase, inventory, accounting, hr, etc.
            $table->string('category', 30); // financial, operational, analytical, compliance
            $table->string('report_type', 30); // list, summary, chart, pivot, combined

            // Configuration
            $table->json('columns')->nullable(); // Available columns
            $table->json('filters')->nullable(); // Available filters
            $table->json('groupings')->nullable(); // Available groupings
            $table->json('aggregations')->nullable(); // Sum, avg, count, etc.
            $table->json('default_config')->nullable(); // Default settings

            // Output
            $table->json('available_formats'); // ['pdf', 'xlsx', 'csv', 'html']
            $table->string('default_format', 10)->default('pdf');

            // Access
            $table->string('required_permission')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['module', 'is_active']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('sales_order_free_goods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_order_id');
            $table->foreign('sales_order_id', 'so_fg_so_fk')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->unsignedBigInteger('free_goods_condition_id');
            $table->foreign('free_goods_condition_id', 'so_fg_cond_fk')->references('id')->on('free_goods_conditions')->onDelete('cascade');
            $table->unsignedBigInteger('triggered_line_id');
            $table->foreign('triggered_line_id', 'so_fg_trigger_fk')->references('id')->on('sales_order_lines')->onDelete('cascade');
            $table->unsignedBigInteger('free_product_id');
            $table->foreign('free_product_id', 'so_fg_prod_fk')->references('id')->on('products')->onDelete('cascade');
            $table->decimal('free_quantity', 18, 4);
            $table->timestamps();
        });

        Schema::create('scheduled_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('template_code', 50);
            $table->string('emailable_type', 100);
            $table->unsignedBigInteger('emailable_id');
            $table->string('to_email', 255);
            $table->string('to_name', 255)->nullable();
            $table->datetime('scheduled_at');
            $table->string('status', 20)->default('pending'); // pending, sent, cancelled
            $table->unsignedBigInteger('email_log_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);

            $table->foreign('email_log_id', 'sched_email_log_fk')
                ->references('id')->on('email_logs')->nullOnDelete();
        });

        Schema::create('scheduling_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('run_number')->unique();
            $table->enum('scheduling_type', ['forward', 'backward', 'finite', 'infinite']);
            $table->date('horizon_start');
            $table->date('horizon_end');
            $table->json('work_center_ids')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('run_by')->constrained('users', 'id', 'sched_run_usr_fk');
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduling_conflicts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('scheduling_run_id')->constrained('scheduling_runs', 'id', 'sched_conf_run_fk');
            $table->foreignId('work_center_id')->constrained('work_centers', 'id', 'sched_conf_wc_fk');
            $table->date('conflict_date');
            $table->unsignedInteger('required_minutes');
            $table->unsignedInteger('available_minutes');
            $table->unsignedInteger('overload_minutes');
            $table->json('affected_order_ids');
            $table->enum('resolution_applied', ['none', 'delayed', 'split', 'outsourced'])->default('none');
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('shop_floor_papers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('paper_number')->unique();
            $table->foreignId('work_order_id')->constrained('work_orders', 'id', 'sfp_wo_fk');
            $table->enum('paper_type', [
                'operation_sheet',
                'component_list',
                'routing_sheet',
                'traveler',
                'label',
            ]);
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users', 'id', 'sfp_usr_fk');
            $table->unsignedSmallInteger('reprint_count')->default(0);
            $table->json('paper_data');
            $table->timestamps();
        });

        Schema::create('shop_floor_confirmations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('shop_floor_paper_id')->nullable()->constrained('shop_floor_papers', 'id', 'sfc_sfp_fk');
            $table->foreignId('work_order_id')->constrained('work_orders', 'id', 'sfc_wo_fk');
            $table->unsignedSmallInteger('operation_number')->nullable();
            $table->decimal('confirmed_quantity', 18, 4);
            $table->decimal('scrap_quantity', 18, 4)->default(0);
            $table->foreignId('confirmed_by')->constrained('users', 'id', 'sfc_usr_fk');
            $table->timestamp('confirmed_at');
            $table->timestamps();
        });

        Schema::create('staging_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('request_number')->unique();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders', 'id', 'stag_req_wo_fk');
            $table->string('production_supply_area')->nullable();
            $table->foreignId('requested_by')->constrained('users', 'id', 'stag_req_usr_fk');
            $table->date('required_date');
            $table->enum('status', ['open', 'in_progress', 'staged', 'cancelled'])->default('open');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staging_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staging_request_id')->constrained('staging_requests', 'id', 'stag_line_req_fk');
            $table->foreignId('product_id')->constrained('products', 'id', 'stag_line_prod_fk');
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('staged_quantity', 18, 4)->default(0);
            $table->string('uom', 20);
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses', 'id', 'stag_line_wh_fk');
            $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations', 'id', 'stag_line_loc_fk');
            $table->enum('status', ['open', 'partial', 'complete'])->default('open');
            $table->timestamps();
        });

        Schema::create('staging_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('staging_request_line_id')->constrained('staging_request_lines', 'id', 'stag_mov_line_fk');
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements', 'id', 'stag_mov_sm_fk');
            $table->decimal('moved_quantity', 18, 4);
            $table->foreignId('moved_by')->constrained('users', 'id', 'stag_mov_usr_fk');
            $table->timestamp('moved_at');
            $table->timestamps();
        });

        Schema::create('technical_completion_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('work_order_id')->unique()->constrained('work_orders', 'id', 'teco_wo_fk');
            $table->date('teco_date');
            $table->foreignId('teco_by')->constrained('users', 'id', 'teco_usr_fk');
            $table->decimal('remaining_quantity', 18, 4)->nullable();
            $table->enum('settlement_status', ['pending', 'settled', 'cancelled'])->default('pending');
            $table->date('settlement_date')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users', 'id', 'teco_rev_usr_fk');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('technical_completion_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_completion_record_id')->constrained('technical_completion_records', 'id', 'teco_clr_fk');
            $table->foreignId('material_id')->constrained('products', 'id', 'teco_clr_mat_fk');
            $table->decimal('cleared_quantity', 18, 4);
            $table->timestamp('cleared_at');
            $table->timestamps();
        });

        Schema::create('tenant_rate_limit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('endpoint');
            $table->string('method', 10);
            $table->string('ip_address');
            $table->unsignedInteger('hits_in_window')->default(1);
            $table->enum('window_type', ['minute', 'hour', 'day']);
            $table->boolean('blocked')->default(false);
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'logged_at']);
        });

        Schema::create('token_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 64)->unique(); // JWT ID
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason', 50); // logout, password_change, revoked, user_deleted
            $table->timestamp('expires_at'); // When token would naturally expire (for cleanup)
            $table->timestamp('created_at')->useCurrent();

            $table->index('expires_at');
        });

        Schema::create('user_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
        });

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('name', 100)->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Created user
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
            $table->index(['email', 'expires_at']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete(); // null = all branches
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'branch_id']);
        });

        Schema::create('user_segment_memberships', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->references('id')->on('user_segments')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->primary(['user_id', 'segment_id']);
        });

        Schema::create('wage_type_catalog', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('wage_type_code', 4);
            $table->string('description');
            $table->enum('category', ['earnings', 'deductions', 'employer_contributions', 'informational']);
            $table->string('processing_class', 2)->nullable();
            $table->string('evaluation_class', 2)->nullable();
            $table->string('cumulation_class', 2)->nullable();
            $table->boolean('taxable')->default(true);
            $table->boolean('pensionable')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'wage_type_code'], 'wage_type_org_code_unique');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('wage_type_catalog');
        Schema::dropIfExists('user_segment_memberships');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('user_invitations');
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('token_blacklist');
        Schema::dropIfExists('tenant_rate_limit_logs');
        Schema::dropIfExists('technical_completion_clearances');
        Schema::dropIfExists('technical_completion_records');
        Schema::dropIfExists('staging_movements');
        Schema::dropIfExists('staging_request_lines');
        Schema::dropIfExists('staging_requests');
        Schema::dropIfExists('shop_floor_confirmations');
        Schema::dropIfExists('shop_floor_papers');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('scheduling_conflicts');
        Schema::dropIfExists('scheduling_runs');
        Schema::dropIfExists('scheduled_emails');
        Schema::dropIfExists('sales_order_free_goods');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotion_customers');
        Schema::dropIfExists('project_budget_availability_log');
        Schema::dropIfExists('production_confirmations');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('position_assignments');
        Schema::dropIfExists('period_work_schedule_days');
        Schema::dropIfExists('period_work_schedules');
        Schema::dropIfExists('payroll_schema_steps');
        Schema::dropIfExists('payroll_processing_runs');
        Schema::dropIfExists('payroll_schemas');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('password_changes');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('organization_notification_settings');
        Schema::dropIfExists('org_positions');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('monthly_summaries');
        Schema::dropIfExists('login_attempts');
    }
};
