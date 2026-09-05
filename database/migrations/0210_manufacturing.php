<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('plan_number', 50)->unique();
            $table->string('title');
            $table->enum('audit_type', ['internal', 'supplier', 'customer', 'regulatory', 'certification'])->default('internal');
            $table->date('planned_start');
            $table->date('planned_end');
            $table->unsignedBigInteger('lead_auditor_id')->nullable();
            $table->foreign('lead_auditor_id', 'audit_plan_auditor_fk')->references('id')->on('users')->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('scope')->nullable();
            $table->text('objectives')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_checklists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('audit_plan_id');
            $table->foreign('audit_plan_id', 'audit_cl_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete();
            $table->string('item_number', 20);
            $table->text('question');
            $table->enum('response', ['yes', 'no', 'partial', 'na'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('audit_plan_id');
            $table->foreign('audit_plan_id', 'audit_rpt_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete();
            $table->date('report_date');
            $table->text('executive_summary')->nullable();
            $table->text('conclusions')->nullable();
            $table->enum('overall_rating', ['satisfactory', 'needs_improvement', 'unsatisfactory'])->nullable();
            $table->timestamps();
        });

        Schema::create('bom_alternatives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('ba_product_fk');
            $table->unsignedSmallInteger('alternative_number');
            $table->string('alternative_name', 100)->nullable();
            $table->foreignId('bom_template_id')->nullable()->constrained('bom_templates')->nullOnDelete()->name('ba_bom_template_fk');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('usage_type', ['production', 'engineering', 'costing', 'plant_maintenance'])->default('production');
            $table->decimal('lot_size_from', 18, 4)->nullable();
            $table->decimal('lot_size_to', 18, 4)->nullable();
            $table->enum('status', ['active', 'inactive', 'obsolete'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'product_id', 'alternative_number'], 'ba_org_prod_alt_unq');
            $table->index(['organization_id', 'product_id', 'valid_from'], 'ba_org_prod_valid_idx');
        });

        Schema::create('bom_co_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_template_id')->constrained('bom_templates')->cascadeOnDelete()->name('bcp_bom_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('bcp_product_fk');
            $table->enum('co_product_type', ['co_product', 'by_product', 'scrap'])->default('co_product');
            $table->decimal('quantity_per_base', 18, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->decimal('cost_allocation_percent', 5, 2)->default(0);
            $table->boolean('is_valuated')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bom_template_id'], 'bcp_bom_idx');
            $table->index(['product_id'], 'bcp_product_idx');
        });

        Schema::create('capa_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('capa_number', 50)->unique();
            $table->enum('capa_type', ['corrective', 'preventive'])->default('corrective');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('problem_statement');
            $table->text('root_cause')->nullable();
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'pending_verification', 'closed', 'cancelled'])->default('open');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreign('owner_id', 'capa_owner_fk')->references('id')->on('users')->nullOnDelete();
            $table->date('target_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('capa_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('capa_record_id');
            $table->foreign('capa_record_id', 'capa_action_record_fk')->references('id')->on('capa_records')->cascadeOnDelete();
            $table->string('action_number', 20);
            $table->text('description');
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->foreign('assigned_to_id', 'capa_action_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->date('due_date');
            $table->date('completed_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->text('completion_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('capa_effectiveness_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('capa_record_id');
            $table->foreign('capa_record_id', 'capa_eff_record_fk')->references('id')->on('capa_records')->cascadeOnDelete();
            $table->date('review_date');
            $table->unsignedBigInteger('reviewed_by_id');
            $table->foreign('reviewed_by_id', 'capa_eff_reviewer_fk')->references('id')->on('users');
            $table->enum('effectiveness', ['effective', 'partially_effective', 'not_effective'])->default('effective');
            $table->text('evidence')->nullable();
            $table->text('conclusions')->nullable();
            $table->timestamps();
        });

        Schema::create('cost_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('version_code', 20);
            $table->string('description', 200)->nullable();
            $table->enum('costing_type', ['standard', 'actual', 'planned'])->default('standard');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'version_code'], 'cost_versions_org_code_unique');
            $table->index(['organization_id', 'is_active'], 'cost_versions_org_active_idx');
            $table->index(['organization_id', 'costing_type'], 'cost_versions_org_type_idx');
        });

        Schema::create('cost_rollup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cost_version_id')->nullable()->constrained('cost_versions')->nullOnDelete();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('run_at')->nullable();
            $table->integer('products_costed')->default(0);
            $table->integer('levels_processed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['organization_id', 'status'], 'cost_rollup_org_status_idx');
            $table->index('cost_version_id', 'cost_rollup_version_idx');
        });

        Schema::create('costing_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('version_code', 20);
            $table->string('description', 200);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->enum('status', ['draft', 'active', 'frozen', 'archived'])->default('draft');
            $table->enum('costing_type', ['standard', 'actual', 'planned'])->default('standard');
            $table->string('currency_code', 3)->default('USD');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['organization_id', 'version_code'], 'cv_org_code_unique');
            $table->index(['organization_id', 'status'], 'cv_org_status_idx');

            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
        });

        Schema::create('costing_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('costing_version_id');
            $table->date('run_date');
            $table->unsignedInteger('products_processed')->default(0);
            $table->unsignedInteger('products_failed')->default(0);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['organization_id', 'run_date'], 'cr_org_date_idx');
        });

        Schema::create('engineering_change_objects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('eao_org_fk');
            $table->foreignId('engineering_change_id')->constrained('engineering_changes')->cascadeOnDelete()->name('eao_ec_fk');
            $table->enum('object_type', ['bom', 'routing', 'product', 'drawing'])->default('bom');
            $table->unsignedBigInteger('object_id');
            $table->string('object_reference', 100)->nullable();
            $table->text('change_description')->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->timestamps();

            $table->index(['engineering_change_id'], 'eao_ec_idx');
            $table->index(['object_type', 'object_id'], 'eao_obj_idx');
        });

        Schema::create('engineering_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('change_number', 50);
            $table->enum('change_type', ['bom_change', 'routing_change', 'product_spec_change', 'drawing_change'])->default('bom_change');
            $table->text('description');
            $table->text('reason')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'implemented', 'cancelled'])->default('draft');
            $table->date('effectivity_date')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->name('ec_requested_by_fk');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->name('ec_approved_by_fk');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('implemented_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'change_number'], 'ec_org_number_unq');
            $table->index(['organization_id', 'status'], 'ec_org_status_idx');
        });

        Schema::create('kanban_supply_areas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'ksa_org_code_unique');
        });

        Schema::create('mrp_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->dateTime('run_date');
            $table->integer('planning_horizon_days')->default(30);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->integer('total_products_analyzed')->default(0);
            $table->integer('total_planned_orders')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('run_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('planning_simulations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('planning_horizon_from');
            $table->date('planning_horizon_to');
            $table->string('status', 20)->default('draft');
            $table->foreignId('mrp_run_id')
                ->nullable()
                ->constrained('mrp_runs')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['organization_id', 'status'],
                'ltp_sim_org_status_idx'
            );
        });

        Schema::create('product_cost_collector_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('pcci_org_fk');
            $table->foreignId('product_cost_collector_id')
                ->constrained('product_cost_collectors')
                ->name('pcci_pcc_fk');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('pcci_ce_fk');
            $table->enum('cost_category', ['material', 'labor', 'overhead', 'other'])->default('material');
            $table->decimal('standard_cost', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->decimal('variance', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['product_cost_collector_id'], 'pcci_pcc_idx');
        });

        Schema::create('product_cost_collectors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('product_id')
                ->constrained('products')
                ->name('pcc_product_fk');
            // production_lines table may not exist; use nullable unsignedBigInteger without FK
            $table->unsignedBigInteger('production_line_id')->nullable();
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->decimal('standard_cost_total', 18, 4)->default(0);
            $table->decimal('actual_cost_total', 18, 4)->default(0);
            $table->decimal('total_variance', 18, 4)->default(0);
            $table->decimal('quantity_produced', 18, 4)->default(0);
            $table->decimal('cost_per_unit_standard', 18, 4)->default(0);
            $table->decimal('cost_per_unit_actual', 18, 4)->default(0);
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'product_id', 'production_line_id', 'period', 'fiscal_year'],
                'pcc_org_prod_line_period_fy_unq'
            );
        });

        Schema::create('production_resource_tools', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('prt_number', 50);
            $table->string('prt_name', 100);
            $table->enum('prt_type', ['tool', 'fixture', 'jig', 'test_equipment', 'document', 'program'])->default('tool');
            $table->enum('status', ['available', 'in_use', 'maintenance', 'retired'])->default('available');
            $table->string('location', 100)->nullable();
            $table->unsignedInteger('quantity_available')->default(1);
            $table->unsignedInteger('quantity_in_use')->default(0);
            $table->string('serial_number', 100)->nullable();
            $table->date('next_calibration_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'prt_number'], 'prt_org_number_unq');
            $table->index(['organization_id', 'status'], 'prt_org_status_idx');
        });

        Schema::create('tool_operation_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('poa_org_fk');
            $table->foreignId('production_resource_tool_id')->constrained('production_resource_tools')->cascadeOnDelete()->name('poa_prt_fk');
            $table->foreignId('routing_operation_id')->nullable()->constrained('routing_operations')->nullOnDelete()->name('poa_routing_op_fk');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete()->name('poa_wo_fk');
            $table->enum('usage_type', ['required', 'optional'])->default('required');
            $table->unsignedInteger('quantity_required')->default(1);
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->enum('status', ['planned', 'assigned', 'in_use', 'released'])->default('planned');
            $table->timestamps();

            $table->index(['production_resource_tool_id'], 'poa_prt_idx');
            $table->index(['work_order_id'], 'poa_wo_idx');
        });

        Schema::create('qm_capa_8d', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('capa_number', 30)->unique();
            $table->string('title', 200);

            // D0 — Emergency response
            $table->text('d0_emergency_response')->nullable();
            $table->date('d0_date')->nullable();

            // D1 — Team
            $table->json('d1_team_members')->nullable();
            $table->foreignId('d1_champion_id')->nullable()->constrained('users')->nullOnDelete();

            // D2 — Problem description
            $table->text('d2_problem_description')->nullable();
            $table->text('d2_is_is_not')->nullable();

            // D3 — Containment actions
            $table->text('d3_containment_actions')->nullable();
            $table->date('d3_implemented_date')->nullable();
            $table->boolean('d3_verified')->default(false);

            // D4 — Root cause
            $table->text('d4_root_cause')->nullable();
            $table->text('d4_escape_point')->nullable();

            // D5 — Corrective actions chosen
            $table->text('d5_corrective_actions')->nullable();

            // D6 — Corrective action implementation
            $table->text('d6_implementation_plan')->nullable();
            $table->date('d6_target_date')->nullable();
            $table->date('d6_completed_date')->nullable();
            $table->boolean('d6_verified')->default(false);

            // D7 — Prevent recurrence
            $table->text('d7_systemic_preventions')->nullable();
            $table->text('d7_lessons_learned')->nullable();

            // D8 — Recognise team
            $table->text('d8_recognition')->nullable();
            $table->date('d8_closure_date')->nullable();

            $table->enum('status', [
                'd0_open',
                'd1_team',
                'd2_problem',
                'd3_containment',
                'd4_root_cause',
                'd5_actions',
                'd6_implemented',
                'd7_prevention',
                'd8_closed',
            ])->default('d0_open');

            // Source linkages
            $table->unsignedBigInteger('source_complaint_id')->nullable();
            $table->string('source_type', 50)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('qm_dynamic_modification_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_code', 20);
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Tightened inspection trigger
            $table->unsignedSmallInteger('tighten_consecutive_fails')->default(2);
            // Reduced inspection trigger
            $table->unsignedSmallInteger('reduce_after_consecutive_pass')->default(5);
            // Skip inspection trigger
            $table->unsignedSmallInteger('skip_after_reduced_pass')->default(10);
            // Reinstate reduced→normal after consecutive passes while tightened
            $table->unsignedSmallInteger('reinstate_after_tightened_fail')->default(5);

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'rule_code']);
        });

        Schema::create('qm_inspection_stage_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('rule_id')
                ->constrained('qm_dynamic_modification_rules')
                ->restrictOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->enum('current_stage', ['tightened', 'normal', 'reduced', 'skip'])->default('normal');
            $table->unsignedSmallInteger('consecutive_pass')->default(0);
            $table->unsignedSmallInteger('consecutive_fail')->default(0);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'supplier_id'], 'qm_inspection_stage_log_org_product_supplier_idx');
        });

        Schema::create('scheduling_boards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('horizon_days')->default(14);
            $table->json('work_center_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scrap_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete()->name('scr_wo_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('scr_product_fk');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->name('scr_warehouse_fk');
            $table->date('scrap_date');
            $table->decimal('scrap_quantity', 18, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->enum('scrap_cause', ['defect', 'damage', 'obsolete', 'process_loss', 'machine_failure', 'other'])->default('defect');
            $table->string('scrap_code', 30)->nullable();
            $table->text('description')->nullable();
            $table->decimal('estimated_value', 18, 4)->default(0);
            $table->boolean('is_recoverable')->default(false);
            $table->decimal('recovery_value', 18, 4)->default(0);
            $table->boolean('gl_posted')->default(false);
            $table->dateTime('gl_posted_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete()->name('scr_reported_by_fk');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'scrap_date'], 'scr_org_date_idx');
            $table->index(['work_order_id'], 'scr_wo_idx');
            $table->index(['product_id'], 'scr_product_idx');
        });

        Schema::create('skip_lot_sampling_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('plan_code', 30);
            $table->string('plan_name', 100);
            $table->enum('plan_type', ['skip_lot', 'reduced', 'normal', 'tightened'])->default('skip_lot');
            $table->unsignedTinyInteger('inspection_frequency')->default(1);
            $table->decimal('sample_size_percent', 5, 2)->default(100);
            $table->unsignedSmallInteger('accept_number')->default(0);
            $table->unsignedSmallInteger('reject_number')->default(1);
            $table->unsignedTinyInteger('switch_rule_reduced_to_normal')->nullable();
            $table->unsignedTinyInteger('switch_rule_normal_to_tightened')->nullable();
            $table->unsignedTinyInteger('switch_rule_tightened_to_rejected')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'plan_code'], 'slsp_org_code_unq');
        });

        Schema::create('spc_charts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('characteristic_name', 100);
            $table->string('chart_type', 20)->default('xbar_r')
                ->comment('xbar_r, individual_mr, p_chart, c_chart');
            $table->unsignedTinyInteger('subgroup_size')->default(5);
            $table->decimal('ucl', 15, 6)->nullable()->comment('Upper Control Limit');
            $table->decimal('lcl', 15, 6)->nullable()->comment('Lower Control Limit');
            $table->decimal('center_line', 15, 6)->nullable()->comment('Process mean (X-bar-bar)');
            $table->decimal('usl', 15, 6)->nullable()->comment('Upper Specification Limit');
            $table->decimal('lsl', 15, 6)->nullable()->comment('Lower Specification Limit');
            $table->decimal('cpk', 8, 4)->nullable()->comment('Latest computed Cpk');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('spc_subgroups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spc_chart_id')->constrained('spc_charts')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->json('measurements')->comment('Array of measured values');
            $table->decimal('subgroup_mean', 15, 6)->nullable();
            $table->decimal('subgroup_range', 15, 6)->nullable();
            $table->boolean('out_of_control')->default(false);
            $table->json('violated_rules')->nullable()->comment('List of violated Western Electric rules');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['spc_chart_id', 'measured_at']);
        });

        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('work_center_type', ['machine', 'labor', 'assembly', 'inspection', 'other'])->default('machine');
            $table->decimal('capacity_per_day', 8, 2)->default(8)->comment('hours');
            $table->decimal('efficiency_percent', 5, 2)->default(100);
            $table->enum('calendar_type', ['5day', '6day', '7day'])->default('5day');
            $table->decimal('cost_per_hour', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('capacity_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->date('load_date');
            $table->decimal('planned_hours', 8, 2)->default(0);
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->decimal('available_hours', 8, 2)->default(8);
            $table->timestamps();

            $table->unique(['work_center_id', 'load_date']);
        });

        Schema::create('planning_capacity_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('planning_simulation_id')
                ->constrained('planning_simulations')
                ->cascadeOnDelete();
            $table->foreignId('work_center_id')
                ->constrained('work_centers')
                ->cascadeOnDelete();
            $table->date('calendar_date');
            $table->decimal('required_hours', 10, 4);
            $table->decimal('available_hours', 10, 4);
            $table->decimal('utilization_percentage', 6, 2);
            $table->timestamps();

            $table->index(
                ['work_center_id', 'calendar_date'],
                'ltp_cap_wc_date_idx'
            );
        });

        Schema::create('mrp_capacity_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('mrp_run_id')->nullable()->comment('FK to mrp_runs if present');
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->unsignedBigInteger('planned_order_id')->nullable()->comment('Links to mrp_planned_orders');
            $table->date('required_date');
            $table->decimal('required_hours', 10, 2);
            $table->decimal('available_hours', 10, 2);
            $table->decimal('load_pct', 6, 2)->comment('required_hours / available_hours * 100');
            $table->enum('status', ['feasible', 'overloaded'])->default('feasible');
            $table->timestamps();

            $table->index(['organization_id', 'work_center_id', 'required_date'], 'mrp_capacity_requirements_org_work_center_req_date_idx');
            $table->index(['mrp_run_id']);
        });

        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->foreignId('work_center_id')
                ->nullable()
                ->constrained('work_centers')
                ->nullOnDelete();
            $table->decimal('capacity_per_hour', 10, 4)->nullable();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'pl_org_code_unique');
        });

        Schema::create('work_center_capacities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('available_hours_per_day', 8, 2)->comment('Raw capacity in hours per working day');
            $table->unsignedTinyInteger('days_per_week')->default(5)->comment('Number of working days per week (1-7)');
            $table->decimal('efficiency_pct', 5, 2)->default(100.00)->comment('Percentage of time that is actually productive');
            $table->timestamps();

            $table->index(['organization_id', 'work_center_id', 'valid_from'], 'work_center_capacities_org_work_center_valid_from_idx');
        });

        Schema::create('work_center_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->date('exception_date');
            $table->decimal('available_hours', 5, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['work_center_id', 'exception_date']);
        });

        Schema::create('work_order_co_product_actuals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('wocpa_org_fk');
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete()->name('wocpa_wo_fk');
            $table->foreignId('bom_co_product_id')->nullable()->constrained('bom_co_products')->nullOnDelete()->name('wocpa_bcp_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('wocpa_product_fk');
            $table->enum('co_product_type', ['co_product', 'by_product', 'scrap'])->default('co_product');
            $table->decimal('planned_quantity', 18, 4)->default(0);
            $table->decimal('actual_quantity', 18, 4)->default(0);
            $table->string('unit_of_measure', 20)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->name('wocpa_warehouse_fk');
            $table->boolean('posted_to_stock')->default(false);
            $table->timestamps();

            $table->index(['work_order_id'], 'wocpa_wo_idx');
        });

        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('audit_plan_id');
            $table->foreign('audit_plan_id', 'audit_finding_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete();
            $table->string('finding_number', 30);
            $table->enum('finding_type', ['major_nc', 'minor_nc', 'observation', 'positive'])->default('minor_nc');
            $table->text('description');
            $table->text('requirement_reference')->nullable();
            $table->text('evidence')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed', 'verified'])->default('open');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('task_list_operations', function (Blueprint $table) {
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

    }

    public function down(): void
    {
        Schema::dropIfExists('task_list_operations');
        Schema::dropIfExists('audit_findings');
        Schema::dropIfExists('work_order_co_product_actuals');
        Schema::dropIfExists('work_center_exceptions');
        Schema::dropIfExists('work_center_capacities');
        Schema::dropIfExists('production_lines');
        Schema::dropIfExists('mrp_capacity_requirements');
        Schema::dropIfExists('planning_capacity_requirements');
        Schema::dropIfExists('capacity_loads');
        Schema::dropIfExists('work_centers');
        Schema::dropIfExists('spc_subgroups');
        Schema::dropIfExists('spc_charts');
        Schema::dropIfExists('skip_lot_sampling_plans');
        Schema::dropIfExists('scrap_reports');
        Schema::dropIfExists('scheduling_boards');
        Schema::dropIfExists('qm_inspection_stage_log');
        Schema::dropIfExists('qm_dynamic_modification_rules');
        Schema::dropIfExists('qm_capa_8d');
        Schema::dropIfExists('tool_operation_assignments');
        Schema::dropIfExists('production_resource_tools');
        Schema::dropIfExists('product_cost_collectors');
        Schema::dropIfExists('product_cost_collector_items');
        Schema::dropIfExists('planning_simulations');
        Schema::dropIfExists('mrp_runs');
        Schema::dropIfExists('kanban_supply_areas');
        Schema::dropIfExists('engineering_changes');
        Schema::dropIfExists('engineering_change_objects');
        Schema::dropIfExists('costing_runs');
        Schema::dropIfExists('costing_versions');
        Schema::dropIfExists('cost_rollup_logs');
        Schema::dropIfExists('cost_versions');
        Schema::dropIfExists('capa_effectiveness_reviews');
        Schema::dropIfExists('capa_actions');
        Schema::dropIfExists('capa_records');
        Schema::dropIfExists('bom_co_products');
        Schema::dropIfExists('bom_alternatives');
        Schema::dropIfExists('audit_reports');
        Schema::dropIfExists('audit_checklists');
        Schema::dropIfExists('audit_plans');
    }
};
