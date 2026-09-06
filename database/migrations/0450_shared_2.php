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
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('token_blacklist');
        Schema::dropIfExists('technical_completion_records');
        Schema::dropIfExists('staging_request_lines');
        Schema::dropIfExists('staging_requests');
        Schema::dropIfExists('shop_floor_papers');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('scheduling_runs');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotion_customers');
        Schema::dropIfExists('production_confirmations');
        Schema::dropIfExists('period_work_schedules');
        Schema::dropIfExists('payroll_schemas');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('password_changes');
        Schema::dropIfExists('org_positions');
        Schema::dropIfExists('login_attempts');
    }
};
