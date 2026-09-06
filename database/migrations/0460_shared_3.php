<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->unsignedBigInteger('period_work_schedule_id');
            $table->foreign('period_work_schedule_id', 'wsr_pws_fk')->references('id')->on('period_work_schedules')->onDelete('restrict');
            $table->date('reference_date');
            $table->decimal('daily_hours', 4, 2);
            $table->decimal('weekly_hours', 5, 2);
            $table->decimal('monthly_hours', 6, 2);
            $table->decimal('overtime_threshold_daily', 4, 2)->nullable();
            $table->decimal('overtime_threshold_weekly', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('equipment_counters', function (Blueprint $table) {
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
            $table->foreign('equipment_id', 'pm_ctr_eq_fk')->references('id')->on('location_equipment')->nullOnDelete();
            $table->foreign('floc_id', 'pm_ctr_floc_fk')->references('id')->on('functional_locations')->nullOnDelete();
        });

        Schema::create('counter_based_plans', function (Blueprint $table) {
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

        Schema::create('counter_based_orders', function (Blueprint $table) {
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

        Schema::create('counter_readings', function (Blueprint $table) {
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

        Schema::create('calibration_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('calibration_equipment_id')
                ->constrained('calibration_equipment')
                ->cascadeOnDelete();
            $table->string('plan_code', 30);
            $table->integer('calibration_interval_days');
            $table->decimal('tolerance_low', 10, 4)->nullable();
            $table->decimal('tolerance_high', 10, 4)->nullable();
            $table->string('measurement_unit', 20)->nullable();
            $table->text('calibration_procedure')->nullable();
            $table->string('external_lab', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['calibration_equipment_id', 'is_active'], 'cal_plan_equip_active_idx');
            $table->index(['organization_id', 'plan_code'], 'cal_plan_org_code_idx');
        });

        Schema::create('calibration_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('calibration_equipment_id')
                ->constrained('calibration_equipment')
                ->cascadeOnDelete();
            $table->foreignId('calibration_plan_id')
                ->nullable()
                ->constrained('calibration_plans')
                ->nullOnDelete();
            $table->string('order_number', 30);
            $table->date('scheduled_date');
            $table->date('completed_date')->nullable();
            $table->string('status', 20)->default('planned')
                ->comment('planned/in_progress/completed/overdue/cancelled');
            $table->foreignId('calibrated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_lab', 100)->nullable();
            $table->string('result', 20)->nullable()->comment('pass/fail/conditional');
            $table->decimal('actual_measurement', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->date('next_calibration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['calibration_equipment_id', 'status'], 'cal_order_equip_status_idx');
            $table->index(['scheduled_date', 'status'], 'cal_order_date_status_idx');
            $table->index('calibration_plan_id', 'cal_order_plan_idx');
        });

        Schema::create('calibration_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('calibration_order_id')
                ->constrained('calibration_orders')
                ->cascadeOnDelete();
            $table->string('certificate_number', 50);
            $table->date('issued_date');
            $table->date('valid_until');
            $table->string('issued_by', 100)->nullable();
            $table->string('accreditation_body', 100)->nullable();
            $table->json('certificate_data')->nullable();
            $table->timestamps();

            $table->index('calibration_order_id', 'cal_cert_order_idx');
            $table->index(['organization_id', 'certificate_number'], 'cal_cert_org_num_idx');
        });

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_definition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // Custom configuration
            $table->json('selected_columns')->nullable();
            $table->json('filters')->nullable();
            $table->json('groupings')->nullable();
            $table->json('sorting')->nullable();
            $table->string('default_format', 10)->nullable();
            $table->json('chart_config')->nullable();

            // Scheduling
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_frequency')->nullable(); // daily, weekly, monthly, quarterly, yearly
            $table->unsignedTinyInteger('schedule_day')->nullable(); // Day of week (1-7) or month (1-31)
            $table->time('schedule_time')->nullable();
            $table->json('schedule_recipients')->nullable(); // Email addresses
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();

            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_shared')->default(false); // Shared with organization
            $table->timestamps();

            $table->index(['organization_id', 'user_id']);
            $table->index(['is_scheduled', 'next_run_at']);
        });

        Schema::create('report_executions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('report_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Execution parameters
            $table->json('parameters')->nullable(); // Filters, date range, etc.
            $table->string('format', 10);
            $table->string('trigger', 20); // manual, scheduled, api

            // Status
            $table->string('status', 20)->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->unsignedInteger('row_count')->nullable();

            // Output
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('report_executions');
        Schema::dropIfExists('saved_reports');
        Schema::dropIfExists('calibration_certificates');
        Schema::dropIfExists('calibration_orders');
        Schema::dropIfExists('calibration_plans');
        Schema::dropIfExists('counter_readings');
        Schema::dropIfExists('counter_based_orders');
        Schema::dropIfExists('counter_based_plans');
        Schema::dropIfExists('equipment_counters');
        Schema::dropIfExists('work_schedule_rules');
    }
};
