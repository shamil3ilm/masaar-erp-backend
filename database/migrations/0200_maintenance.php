<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('functional_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('location_type', ['plant', 'area', 'line', 'machine', 'component'])->default('area');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('functional_locations')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->index('organization_id');


                $table->string('planner_group')->nullable();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('functional_location_id')->nullable();
            $table->unsignedBigInteger('equipment_category_id')->nullable();
            $table->string('equipment_number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->enum('status', ['active', 'under_maintenance', 'decommissioned', 'scrapped'])->default('active');
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'equipment_number']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('functional_location_id')->references('id')->on('functional_locations')->nullOnDelete();
            $table->foreign('equipment_category_id')->references('id')->on('equipment_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('maintenance_condition_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_name', 100);
            $table->unsignedBigInteger('equipment_id');
            $table->string('measurement_point', 100);
            $table->enum('condition_operator', ['greater_than', 'less_than', 'equals', 'between'])->default('greater_than');
            $table->decimal('threshold_value', 15, 4);
            $table->decimal('threshold_value_to', 15, 4)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->enum('trigger_action', ['create_order', 'notify', 'both'])->default('both');
            $table->enum('maintenance_type', ['inspection', 'repair', 'overhaul', 'replacement'])->default('inspection');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'equipment_id'], 'mcr_org_equip_idx');
        });

        Schema::create('maintenance_fault_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('description', 255);
            $table->enum('fault_type', ['mechanical', 'electrical', 'hydraulic', 'software', 'operator', 'wear', 'other'])->default('other');
            $table->text('cause')->nullable();
            $table->text('recommended_action')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_kpis', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('equipment_id')->nullable()->comment('NULL = org-level aggregate');
            $table->string('period_year', 4);
            $table->string('period_month', 2);
            $table->decimal('mtbf_hours', 10, 2)->default(0)->comment('Mean Time Between Failures');
            $table->decimal('mttr_hours', 10, 2)->default(0)->comment('Mean Time To Repair');
            $table->decimal('availability_pct', 5, 2)->default(0)->comment('(MTBF / (MTBF + MTTR)) * 100');
            $table->decimal('oee_pct', 5, 2)->default(0)->comment('Overall Equipment Effectiveness');
            $table->integer('breakdown_count')->default(0);
            $table->decimal('total_downtime_hours', 10, 2)->default(0);
            $table->decimal('planned_maintenance_hours', 10, 2)->default(0);
            $table->decimal('unplanned_maintenance_hours', 10, 2)->default(0);
            $table->decimal('maintenance_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'equipment_id', 'period_year', 'period_month'], 'maintenance_kpis_org_equipment_period_yr_mo_uniq');
            $table->index(['organization_id', 'period_year', 'period_month']);
        });

        Schema::create('maintenance_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->string('measurement_point', 100);
            $table->decimal('measurement_value', 15, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->timestamp('measured_at');
            $table->foreignId('recorded_by')->constrained('users');
            $table->boolean('threshold_breached')->default(false);
            $table->foreignId('triggered_rule_id')->nullable()->constrained('maintenance_condition_rules')->nullOnDelete();
            $table->timestamps();
            $table->index(['equipment_id', 'measurement_point', 'measured_at'], 'mm_equip_point_date_idx');
        });

        Schema::create('maintenance_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('notification_number', 20)->unique();
            $table->enum('notification_type', ['M1', 'M2', 'M3', 'S1', 'S4'])->default('M2');
            $table->string('short_text', 200);
            $table->text('long_text')->nullable();

            // Equipment / functional location linkage
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->foreign('equipment_id')
                ->references('id')
                ->on('equipment')
                ->nullOnDelete();
            $table->string('functional_location_code', 50)->nullable();

            // Priority and categorisation
            $table->enum('priority', ['1_very_high', '2_high', '3_medium', '4_low'])->default('3_medium');
            $table->date('malfunction_start_date')->nullable();
            $table->date('malfunction_end_date')->nullable();
            $table->decimal('malfunction_duration_hours', 8, 2)->nullable();
            $table->boolean('breakdown')->default(false);
            $table->boolean('production_stop')->default(false);

            // Coding
            $table->string('damage_code', 20)->nullable();
            $table->string('cause_code', 20)->nullable();
            $table->string('activity_code', 20)->nullable();
            $table->text('cause_text')->nullable();
            $table->text('task_text')->nullable();

            // Workflow status (SAP IW-style)
            $table->enum('status', ['OSNO', 'NOPR', 'INIT', 'ORAS', 'NOCO', 'COMP'])->default('OSNO');

            // Maintenance order linkage (when converted)
            $table->unsignedBigInteger('maintenance_order_id')->nullable();

            // People
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();

            // Completion
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_text')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'notification_type'], 'maintenance_notifications_org_notif_type_idx');
            $table->index(['organization_id', 'equipment_id']);
            $table->index(['organization_id', 'priority']);
        });

        Schema::create('maintenance_notification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('maintenance_notifications')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('item_number');
            $table->string('short_text', 200);
            $table->text('long_text')->nullable();
            $table->string('damage_code', 20)->nullable();
            $table->string('cause_code', 20)->nullable();
            $table->enum('status', ['outstanding', 'in_process', 'completed', 'cleared'])->default('outstanding');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'item_number'], 'maintenance_notification_items_notif_item_num_uniq');
        });

        Schema::create('maintenance_notification_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('maintenance_notifications')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('task_number');
            $table->string('description', 200);
            $table->text('details')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->enum('status', ['outstanding', 'in_process', 'completed', 'cleared'])->default('outstanding');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'task_number'], 'maintenance_notification_tasks_notif_task_num_uniq');
        });

        Schema::create('maintenance_order_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->string('settlement_rule_type', 30)->comment('full/partial');
            $table->string('receiver_type', 30)->comment('cost_center/asset/order/wbs');
            $table->unsignedBigInteger('receiver_id');
            $table->decimal('percentage', 5, 2)->default(100);
            $table->decimal('settled_amount', 18, 4);
            $table->date('settlement_date');
            $table->smallInteger('fiscal_year');
            $table->tinyInteger('period');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_order_id', 'settlement_date'], 'mo_settle_order_date_idx');
            $table->index(['receiver_type', 'receiver_id'], 'mo_settle_recv_idx');
        });

        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('equipment_id');
            $table->string('name');
            $table->enum('maintenance_type', ['preventive', 'predictive', 'condition_based'])->default('preventive');
            $table->enum('frequency_type', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'hours', 'kilometers'])->default('monthly');
            $table->unsignedInteger('frequency_value')->default(1);
            $table->decimal('estimated_duration_hours', 5, 2)->default(1.00);
            $table->text('description')->nullable();
            $table->json('tasks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_generated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('organization_id');
            $table->index('equipment_id');
        });

        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('order_number');
            $table->unsignedBigInteger('maintenance_plan_id')->nullable();
            $table->unsignedBigInteger('equipment_id');
            $table->enum('order_type', ['preventive', 'corrective', 'emergency', 'inspection'])->default('corrective');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'on_hold', 'completed', 'cancelled'])->default('open');
            $table->text('description');
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->decimal('downtime_hours', 6, 2)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'order_number']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('maintenance_plan_id')->references('id')->on('maintenance_plans')->nullOnDelete();
            $table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'status', 'priority']);
            $table->index(['equipment_id', 'status']);
        });

        Schema::create('maintenance_order_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->string('task_description');
            $table->boolean('is_safety_critical')->default(false);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->index('maintenance_order_id');
        });

        Schema::create('maintenance_permits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('maintenance_order_id')->nullable();
            $table->foreign('maintenance_order_id', 'mp_mo_fk')->references('id')->on('maintenance_orders');
            $table->string('permit_number', 50);
            $table->enum('permit_type', ['hot_work', 'confined_space', 'electrical_isolation', 'height_work', 'chemical', 'general'])->default('general');
            $table->enum('status', ['requested', 'approved', 'active', 'suspended', 'closed', 'cancelled'])->default('requested');
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('work_description')->nullable();
            $table->text('hazards_identified')->nullable();
            $table->text('precautions_required')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->foreign('requested_by', 'mp_requested_by_fk')->references('id')->on('users');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'mp_approved_by_fk')->references('id')->on('users');
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->foreign('closed_by', 'mp_closed_by_fk')->references('id')->on('users');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'permit_number'], 'mp_org_number_unq');
            $table->index(['organization_id', 'status'], 'mp_org_status_idx');
            $table->index(['maintenance_order_id'], 'mp_mo_idx');
        });

        Schema::create('maintenance_root_cause_analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('fault_code_id')->nullable();
            $table->enum('rca_method', ['5_why', 'fishbone', 'fault_tree', 'fmea', 'other'])->default('5_why');
            $table->json('whys')->nullable()->comment('For 5-Why: array of why strings');
            $table->text('root_cause')->nullable();
            $table->text('contributing_factors')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->text('preventive_actions')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('target_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'equipment_id'], 'maintenance_root_cause_analyses_org_equipment_idx');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('maintenance_service_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('service_order_number', 30)->unique();
            $table->unsignedBigInteger('maintenance_order_id')->nullable();
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable()->comment('FK to suppliers/vendors');
            $table->enum('status', ['draft', 'issued', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->enum('service_type', ['repair', 'inspection', 'installation', 'calibration', 'overhaul'])->default('repair');
            $table->text('description');
            $table->date('requested_date');
            $table->date('due_date');
            $table->date('completed_date')->nullable();
            $table->decimal('estimated_cost', 15, 4)->default(0);
            $table->decimal('actual_cost', 15, 4)->default(0);
            $table->string('sla_response_hours', 10)->nullable()->comment('SLA: response time in hours');
            $table->string('sla_resolution_hours', 10)->nullable()->comment('SLA: resolution time in hours');
            $table->timestamp('sla_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->timestamp('vendor_responded_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->unsignedBigInteger('purchase_order_id')->nullable()->comment('Linked PO');
            $table->unsignedBigInteger('bill_id')->nullable()->comment('Vendor invoice when completed');
            $table->text('vendor_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'vendor_id']);
        });

        Schema::create('maintenance_task_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('task_list_number')->unique();
            $table->string('description');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('permit_safety_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'psc_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('maintenance_permit_id');
            $table->foreign('maintenance_permit_id', 'psc_permit_fk')->references('id')->on('maintenance_permits');
            $table->string('check_description', 255);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->foreign('completed_by', 'psc_completed_by_fk')->references('id')->on('users');
            $table->dateTime('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['maintenance_permit_id'], 'psc_permit_idx');
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('fleet_number', 20);
            $table->string('license_plate', 20);
            $table->string('make', 50);
            $table->string('model', 50);
            $table->smallInteger('year');
            $table->string('vin', 50)->nullable();
            $table->string('vehicle_type', 30)->default('car')
                ->comment('car/van/truck/motorcycle/bus/other');
            $table->string('fuel_type', 20)->default('petrol')
                ->comment('petrol/diesel/electric/hybrid/cng');
            $table->string('color', 30)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->integer('current_mileage_km')->default(0);
            $table->integer('last_service_km')->nullable();
            $table->integer('next_service_km')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fleet_number'], 'vehicle_org_fleet_idx');
            $table->index(['is_active', 'insurance_expiry'], 'vehicle_active_ins_idx');
        });

        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('log_date');
            $table->integer('odometer_reading');
            $table->decimal('fuel_quantity_liters', 10, 3);
            $table->decimal('fuel_cost', 18, 4);
            $table->string('currency_code', 3);
            $table->string('fuel_type', 20);
            $table->string('station', 100)->nullable();
            $table->foreignId('filled_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'log_date'], 'fuel_veh_date_idx');
        });

        Schema::create('mileage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('log_date');
            $table->integer('odometer_start');
            $table->integer('odometer_end');
            $table->integer('distance_km');
            $table->string('trip_purpose', 100)->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('route', 200)->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'log_date'], 'mileage_veh_date_idx');
        });

        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('assigned_from');
            $table->dateTime('assigned_to')->nullable();
            $table->string('purpose', 100)->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index(['vehicle_id', 'is_current'], 'veh_assign_veh_curr_idx');
            $table->index(['driver_id', 'is_current'], 'veh_assign_drv_curr_idx');
        });

        Schema::create('vehicle_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('maintenance_type', 30)
                ->comment('scheduled/unscheduled/repair/inspection');
            $table->date('service_date');
            $table->integer('odometer_reading')->nullable();
            $table->text('description');
            $table->decimal('cost', 18, 4)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('service_provider', 100)->nullable();
            $table->date('next_service_date')->nullable();
            $table->integer('next_service_km')->nullable();
            $table->unsignedBigInteger('maintenance_order_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'service_date'], 'veh_maint_veh_date_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_records');
        Schema::dropIfExists('vehicle_assignments');
        Schema::dropIfExists('mileage_logs');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('permit_safety_checks');
        Schema::dropIfExists('maintenance_task_lists');
        Schema::dropIfExists('maintenance_service_orders');
        Schema::dropIfExists('maintenance_root_cause_analyses');
        Schema::dropIfExists('maintenance_permits');
        Schema::dropIfExists('maintenance_order_tasks');
        Schema::dropIfExists('maintenance_orders');
        Schema::dropIfExists('maintenance_plans');
        Schema::dropIfExists('maintenance_order_settlements');
        Schema::dropIfExists('maintenance_notification_tasks');
        Schema::dropIfExists('maintenance_notification_items');
        Schema::dropIfExists('maintenance_notifications');
        Schema::dropIfExists('maintenance_measurements');
        Schema::dropIfExists('maintenance_kpis');
        Schema::dropIfExists('maintenance_fault_codes');
        Schema::dropIfExists('maintenance_condition_rules');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('functional_locations');
        Schema::dropIfExists('equipment_categories');
    }
};
