<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('counter_based_orders');
        Schema::dropIfExists('task_list_operations');
        Schema::dropIfExists('maintenance_task_lists');
        Schema::dropIfExists('counter_based_plans');
        Schema::dropIfExists('counter_readings');
        Schema::dropIfExists('equipment_counters');

        Schema::create('equipment_counters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('counter_name');
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->unsignedBigInteger('floc_id')->nullable();
            $table->string('uom', 20);
            $table->decimal('current_reading', 14, 3)->default(0);
            $table->decimal('overflow_value', 14, 3)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('equipment_id', 'pm_ctr_eq_fk')->references('id')->on('floc_equipment')->nullOnDelete();
            $table->foreign('floc_id', 'pm_ctr_floc_fk')->references('id')->on('functional_locations')->nullOnDelete();
        });

        Schema::create('counter_readings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('counter_id');
            $table->decimal('reading_value', 14, 3);
            $table->dateTime('reading_date');
            $table->decimal('delta_value', 14, 3)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('counter_id', 'pm_ctr_read_ctr_fk')->references('id')->on('equipment_counters')->cascadeOnDelete();
            $table->foreign('recorded_by', 'pm_ctr_read_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('maintenance_task_lists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('task_list_number')->unique();
            $table->string('description');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('task_list_operations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_list_id');
            $table->unsignedSmallInteger('operation_number');
            $table->string('description');
            $table->unsignedBigInteger('work_center_id')->nullable();
            $table->decimal('planned_hours', 6, 2);
            $table->timestamps();

            $table->foreign('task_list_id', 'pm_tl_op_tl_fk')->references('id')->on('maintenance_task_lists')->cascadeOnDelete();
            $table->foreign('work_center_id', 'pm_tl_op_wc_fk')->references('id')->on('work_centers')->nullOnDelete();
        });

        Schema::create('counter_based_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('plan_number')->unique();
            $table->enum('plan_type', ['time_based', 'counter_based', 'condition_based']);
            $table->unsignedBigInteger('floc_id')->nullable();
            $table->unsignedBigInteger('counter_id')->nullable();
            $table->unsignedBigInteger('task_list_id')->nullable();
            $table->decimal('counter_interval', 14, 3)->nullable();
            $table->decimal('threshold_warning', 14, 3)->nullable();
            $table->decimal('last_maintenance_reading', 14, 3)->nullable();
            $table->decimal('next_due_reading', 14, 3)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('floc_id', 'pm_plan_floc_fk')->references('id')->on('functional_locations')->nullOnDelete();
            $table->foreign('counter_id', 'pm_plan_ctr_fk')->references('id')->on('equipment_counters')->nullOnDelete();
            $table->foreign('task_list_id', 'pm_plan_tl_fk')->references('id')->on('maintenance_task_lists')->nullOnDelete();
        });

        Schema::create('counter_based_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('order_number')->unique();
            $table->unsignedBigInteger('maintenance_plan_id')->nullable();
            $table->unsignedBigInteger('floc_id')->nullable();
            $table->enum('order_type', ['preventive', 'corrective', 'breakdown', 'inspection']);
            $table->text('description');
            $table->enum('status', ['created', 'released', 'in_progress', 'completed', 'closed', 'cancelled'])->default('created');
            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])->default('normal');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->decimal('counter_reading_at_trigger', 14, 3)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('maintenance_plan_id', 'pm_order_plan_fk')->references('id')->on('counter_based_plans')->nullOnDelete();
            $table->foreign('floc_id', 'pm_order_floc_fk')->references('id')->on('functional_locations')->nullOnDelete();
            $table->foreign('assigned_to', 'pm_order_usr_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_based_orders');
        Schema::dropIfExists('task_list_operations');
        Schema::dropIfExists('maintenance_task_lists');
        Schema::dropIfExists('counter_based_plans');
        Schema::dropIfExists('counter_readings');
        Schema::dropIfExists('equipment_counters');
    }
};
