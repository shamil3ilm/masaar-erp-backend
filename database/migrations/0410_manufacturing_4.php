<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Work order info
            $table->string('work_order_number', 50);
            $table->foreignId('bom_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable();
            $table->foreignId('sales_order_line_id')->nullable();

            // Product to manufacture
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('planned_quantity', 15, 4);
            $table->decimal('produced_quantity', 15, 4)->default(0);
            $table->decimal('rejected_quantity', 15, 4)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();

            // Dates
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->datetime('actual_start_datetime')->nullable();
            $table->datetime('actual_end_datetime')->nullable();

            // Warehouse
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            // Costs
            $table->decimal('estimated_material_cost', 15, 4)->default(0);
            $table->decimal('estimated_labor_cost', 15, 4)->default(0);
            $table->decimal('estimated_overhead_cost', 15, 4)->default(0);
            $table->decimal('actual_material_cost', 15, 4)->default(0);
            $table->decimal('actual_labor_cost', 15, 4)->default(0);
            $table->decimal('actual_overhead_cost', 15, 4)->default(0);

            // Status
            $table->enum('status', ['draft', 'pending', 'scheduled', 'in_progress', 'completed', 'cancelled', 'released', 'closed'])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'work_order_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'planned_start_date']);

            $table->foreign('sales_order_id', 'wo_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreign('sales_order_line_id', 'wo_sales_order_line_fk')
                ->references('id')->on('sales_order_lines')->nullOnDelete();
        });

        Schema::create('capacity_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->unsignedBigInteger('operation_id')->nullable();
            $table->decimal('required_hours', 8, 2);
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            $table->enum('status', ['planned', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->timestamps();

            $table->index(['work_center_id', 'status']);
            $table->index('organization_id');
        });

        Schema::create('cost_variances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('costing_version_id');
            $table->decimal('standard_material_cost', 15, 4)->default(0);
            $table->decimal('actual_material_cost', 15, 4)->default(0);
            $table->decimal('standard_labor_cost', 15, 4)->default(0);
            $table->decimal('actual_labor_cost', 15, 4)->default(0);
            $table->decimal('standard_overhead_cost', 15, 4)->default(0);
            $table->decimal('actual_overhead_cost', 15, 4)->default(0);
            $table->decimal('total_standard', 15, 4)->default(0);
            $table->decimal('total_actual', 15, 4)->default(0);
            $table->decimal('total_variance', 15, 4)->default(0);
            $table->decimal('variance_pct', 7, 2)->default(0);
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade');
            $table->unique(['work_order_id', 'costing_version_id'], 'cvar_wo_version_unique');
            $table->index(['organization_id', 'period_year', 'period_month'], 'cvar_org_period_idx');
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->datetime('logged_at');

            // Quantities
            $table->decimal('quantity_produced', 15, 4);
            $table->decimal('quantity_rejected', 15, 4)->default(0);
            $table->string('rejection_reason', 500)->nullable();

            // Quality
            $table->boolean('quality_checked')->default(false);
            $table->foreignId('quality_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('quality_checked_at')->nullable();
            $table->json('quality_parameters')->nullable();

            // Batch/Lot tracking
            $table->string('batch_number', 100)->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiry_date')->nullable();

            // Movement
            $table->foreignId('stock_movement_id')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'work_order_id']);
            $table->index(['organization_id', 'logged_at']);

            $table->foreign('stock_movement_id', 'prod_log_stock_movement_fk')
                ->references('id')->on('stock_movements')->nullOnDelete();


            $table->renameColumn('quality_checked', 'is_quality_checked');
        });

        Schema::create('production_variances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('cost_version_id')->nullable()->constrained('cost_versions')->nullOnDelete();
            $table->enum('variance_type', ['material', 'labour', 'overhead', 'yield'])->default('material');
            $table->decimal('standard_cost', 15, 4)->default(0);
            $table->decimal('actual_cost', 15, 4)->default(0);
            $table->decimal('variance_amount', 15, 4)->default(0);
            $table->decimal('variance_pct', 8, 2)->default(0);
            $table->date('period_date');
            $table->boolean('posted_to_gl')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'period_date'], 'prod_var_org_period_idx');
            $table->index(['organization_id', 'variance_type'], 'prod_var_org_type_idx');
            $table->index('work_order_id', 'prod_var_wo_idx');
        });

        Schema::create('scheduling_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_board_id')
                ->nullable()
                ->constrained('scheduling_boards')
                ->nullOnDelete();
            $table->foreignId('work_order_id')
                ->nullable()
                ->constrained('work_orders')
                ->nullOnDelete();
            $table->foreignId('process_order_id')
                ->nullable()
                ->constrained('process_orders')
                ->nullOnDelete();
            $table->foreignId('work_center_id')
                ->constrained('work_centers')
                ->cascadeOnDelete();
            $table->unsignedInteger('operation_number');
            $table->string('description');
            $table->dateTime('planned_start');
            $table->dateTime('planned_finish');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_finish')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('setup_minutes')->default(0);
            $table->unsignedInteger('teardown_minutes')->default(0);
            $table->unsignedInteger('priority')->default(50);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_fixed')->default(false);
            $table->unsignedInteger('sequence_number')->nullable();
            $table->timestamps();

            $table->index(
                ['work_center_id', 'planned_start'],
                'sched_op_wc_start_idx'
            );
            $table->index(
                ['scheduling_board_id'],
                'sched_op_board_idx'
            );
            $table->index(
                ['work_order_id'],
                'sched_op_wo_idx'
            );
            $table->index(
                ['priority', 'planned_start'],
                'sched_op_prio_start_idx'
            );
        });

        Schema::create('scheduling_pegging_relationships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('predecessor_operation_id');
            $table->unsignedBigInteger('successor_operation_id');
            $table->foreign('predecessor_operation_id', 'sched_peg_predecessor_fk')
                ->references('id')->on('scheduling_operations')->cascadeOnDelete();
            $table->foreign('successor_operation_id', 'sched_peg_successor_fk')
                ->references('id')->on('scheduling_operations')->cascadeOnDelete();
            $table->string('relationship_type', 20)->default('fs');
            $table->integer('lag_minutes')->default(0);
            $table->timestamps();
        });

        Schema::create('wip_valuations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('work_order_id');
            $table->date('valuation_date');
            $table->decimal('completed_qty', 15, 4)->default(0);
            $table->decimal('wip_qty', 15, 4)->default(0);
            $table->decimal('material_wip', 15, 4)->default(0);
            $table->decimal('labor_wip', 15, 4)->default(0);
            $table->decimal('overhead_wip', 15, 4)->default(0);
            $table->decimal('total_wip', 15, 4)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->unique(['work_order_id', 'valuation_date'], 'wip_wo_date_unique');
            $table->index(['organization_id', 'valuation_date'], 'wip_org_date_idx');
        });

        Schema::create('work_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('description', 500)->nullable();

            // Quantities
            $table->decimal('required_quantity', 15, 4);
            $table->decimal('issued_quantity', 15, 4)->default(0);
            $table->decimal('consumed_quantity', 15, 4)->default(0);
            $table->decimal('returned_quantity', 15, 4)->default(0);
            $table->decimal('wastage_quantity', 15, 4)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();

            // Cost
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);

            // Warehouse
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('line_order')->default(0);
            $table->timestamps();

            $table->index(['work_order_id', 'product_id']);
        });

        Schema::create('material_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_material_id')->constrained()->cascadeOnDelete();
            $table->enum('transaction_type', ['issue', 'return', 'wastage']);
            $table->datetime('transaction_datetime');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('stock_movement_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'work_order_id']);
            $table->index(['organization_id', 'transaction_datetime']);

            $table->foreign('stock_movement_id', 'mat_txn_stock_movement_fk')
                ->references('id')->on('stock_movements')->nullOnDelete();
        });

        Schema::create('work_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);

            // Time
            $table->unsignedSmallInteger('estimated_minutes')->default(0);
            $table->unsignedSmallInteger('actual_minutes')->default(0);
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();

            // Status
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'skipped',
            ])->default('pending');

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);

            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            $table->unsignedBigInteger('work_center_id')->nullable();
            $table->foreign('work_center_id')->references('id')->on('work_centers')->nullOnDelete();
        });

        Schema::create('co_activity_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('confirmation_number', 50)->unique();
            $table->foreignId('work_order_id')
                ->nullable()
                ->constrained('work_orders', 'id', 'co_act_conf_wo_fk')
                ->nullOnDelete();
            $table->foreignId('work_center_id')
                ->nullable()
                ->constrained('work_centers', 'id', 'co_act_conf_wc_fk')
                ->nullOnDelete();
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_act_conf_cc_fk')
                ->nullOnDelete();
            $table->foreignId('activity_type_id')
                ->nullable()
                ->constrained('activity_types', 'id', 'co_act_conf_at_fk')
                ->nullOnDelete();
            $table->decimal('confirmed_quantity', 18, 4);
            $table->decimal('planned_quantity', 18, 4)->nullable();
            $table->string('uom', 20)->default('HR');
            $table->decimal('actual_rate', 18, 4)->nullable();
            $table->decimal('planned_rate', 18, 4)->nullable();
            $table->decimal('actual_cost', 18, 4)->nullable();
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period')->unsigned();
            $table->date('confirmation_date');
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users', 'id', 'co_act_conf_by_fk')
                ->nullOnDelete();
            $table->enum('status', ['confirmed', 'reversed'])->default('confirmed');
            // Self-referential FK for reversal
            $table->unsignedBigInteger('reversal_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('reversal_id', 'co_act_conf_reversal_fk')
                ->references('id')
                ->on('co_activity_confirmations')
                ->nullOnDelete();

            $table->index(['organization_id', 'fiscal_year', 'period'], 'co_act_conf_org_fy_period_idx');
            $table->index(['work_order_id'], 'co_act_conf_wo_idx');
            $table->index(['cost_center_id', 'activity_type_id'], 'co_act_conf_cc_at_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('co_activity_confirmations');
        Schema::dropIfExists('work_order_operations');
        Schema::dropIfExists('material_transactions');
        Schema::dropIfExists('work_order_materials');
        Schema::dropIfExists('wip_valuations');
        Schema::dropIfExists('scheduling_pegging_relationships');
        Schema::dropIfExists('scheduling_operations');
        Schema::dropIfExists('production_variances');
        Schema::dropIfExists('production_logs');
        Schema::dropIfExists('cost_variances');
        Schema::dropIfExists('capacity_requirements');
        Schema::dropIfExists('work_orders');
    }
};
