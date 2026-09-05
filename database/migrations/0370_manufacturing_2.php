<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_vendor_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id', 'avl_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'avl_product_fk')->references('id')->on('products')->nullOnDelete();
            $table->date('approved_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['active', 'suspended', 'expired', 'revoked'])->default('active');
            $table->text('approval_conditions')->nullable();
            $table->timestamps();
        });

        Schema::create('bom_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('bom_number', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Finished product
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('output_quantity', 15, 4)->default(1);
            $table->foreignId('output_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();

            // Defaults
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->unsignedSmallInteger('estimated_hours')->nullable();
            $table->decimal('estimated_labor_cost', 15, 4)->nullable();
            $table->decimal('overhead_cost', 15, 4)->default(0);

            // Status
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->unsignedSmallInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'bom_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('wastage_percentage', 5, 2)->default(0);
            $table->boolean('is_critical')->default(false); // Production stops if unavailable
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('line_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bom_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_template_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->unsignedSmallInteger('estimated_minutes')->default(0);
            $table->decimal('labor_cost_per_hour', 15, 4)->nullable();
            $table->string('workstation', 100)->nullable();
            $table->json('required_skills')->nullable();
            $table->boolean('is_subcontracted')->default(false);
            $table->timestamps();
        });

        Schema::create('certificates_of_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('certificate_number', 30);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->unsignedBigInteger('inspection_lot_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->date('issue_date');
            $table->date('test_date')->nullable();
            $table->json('test_results');
            $table->enum('overall_result', ['pass', 'fail', 'conditional'])->default('pass');
            $table->text('remarks')->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'issued', 'revoked'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'certificate_number'], 'coa_org_number_unique');
            $table->index(['organization_id', 'product_id', 'status'], 'coa_org_product_status_idx');
        });

        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->date('forecast_date');
            $table->decimal('forecast_quantity', 12, 4);
            $table->decimal('actual_quantity', 12, 4)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'forecast_date']);
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('kanban_control_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supply_area_id')->constrained('kanban_supply_areas')->cascadeOnDelete();
            $table->enum('replenishment_strategy', ['production', 'purchase', 'stock_transfer'])->default('production');
            $table->integer('number_of_cards')->default(1);
            $table->decimal('replenishment_quantity', 15, 4);
            $table->decimal('safety_stock_quantity', 15, 4)->default(0);
            $table->integer('replenishment_lead_time_days')->default(1);
            $table->foreignId('source_vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'product_id'], 'kcc_org_product_idx');
        });

        Schema::create('kanban_cards', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('control_cycle_id')->constrained('kanban_control_cycles')->cascadeOnDelete();
            $table->string('card_number', 30);
            $table->enum('status', ['full', 'empty', 'in_replenishment', 'waiting'])->default('full');
            $table->decimal('current_quantity', 15, 4)->default(0);
            $table->timestamp('emptied_at')->nullable();
            $table->timestamp('replenishment_triggered_at')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->unsignedBigInteger('triggered_document_id')->nullable();
            $table->string('triggered_document_type', 30)->nullable();
            $table->timestamps();
            $table->unique(['control_cycle_id', 'card_number'], 'kc_cycle_number_unique');
            $table->index(['control_cycle_id', 'status'], 'kc_cycle_status_idx');
        });

        Schema::create('mrp_demand_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mrp_run_id')->constrained('mrp_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->enum('source_type', ['sales_order', 'forecast', 'safety_stock', 'bom', 'pir'])->default('sales_order');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('required_date');
            $table->decimal('required_quantity', 12, 4);
            $table->timestamps();

            $table->index('mrp_run_id');
            $table->index('product_id');
        });

        Schema::create('mrp_planned_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('mrp_run_id')->constrained('mrp_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->enum('order_type', ['purchase', 'production', 'transfer'])->default('purchase');
            $table->decimal('planned_quantity', 12, 4);
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->enum('status', ['planned', 'firmed', 'converted', 'cancelled'])->default('planned');
            $table->unsignedBigInteger('source_demand_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('converted_to_type')->nullable();
            $table->unsignedBigInteger('converted_to_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['product_id', 'status']);

            $table->unsignedBigInteger('purchase_requisition_id')
                ->nullable()
                ;

            $table->foreign('purchase_requisition_id')
                ->references('id')
                ->on('purchase_requisitions')
                ->onDelete('set null');
        });

        Schema::create('planned_independent_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // PIR version — allows multiple planning versions (SAP concept: active version vs. simulation)
            $table->unsignedTinyInteger('version')->default(1);
            $table->boolean('is_active')->default(true);

            // Requirement quantity and date
            $table->decimal('quantity', 12, 4);
            $table->date('requirement_date');

            // Optional planning horizon / validity
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // How much has already been consumed by confirmed sales orders (backflush)
            $table->decimal('consumed_quantity', 12, 4)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'is_active'], 'pir_org_prod_active_idx');
            $table->index(['organization_id', 'requirement_date'], 'pir_org_req_date_idx');
            $table->index(['organization_id', 'version', 'is_active'], 'pir_org_ver_active_idx');
        });

        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('cost_version_id')->nullable()->constrained('costing_versions')->nullOnDelete();
            $table->string('cost_type', 30)->default('standard')->comment('standard, actual, planned');
            $table->decimal('material_cost', 18, 4)->default(0);
            $table->decimal('labour_cost', 18, 4)->default(0);
            $table->decimal('overhead_cost', 18, 4)->default(0);
            $table->decimal('subcontracting_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('costed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'product_id']);
            $table->index(['product_id', 'cost_type', 'effective_from']);
        });

        Schema::create('product_standard_costs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('costing_version_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('material_cost', 15, 4)->default(0);
            $table->decimal('labor_cost', 15, 4)->default(0);
            $table->decimal('overhead_cost', 15, 4)->default(0);
            $table->decimal('subcontracting_cost', 15, 4)->default(0);
            $table->decimal('total_standard_cost', 15, 4)->default(0);
            $table->decimal('cost_per_unit', 15, 4)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('bom_id')->nullable();
            $table->timestamps();

            $table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('bom_id')->references('id')->on('bom_templates')->onDelete('set null');
            $table->unique(['costing_version_id', 'product_id', 'variant_id'], 'psc_version_product_variant_unique');
            $table->index(['costing_version_id', 'product_id'], 'psc_version_product_idx');
        });

        Schema::create('cost_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('standard_cost_id');
            $table->enum('component_type', ['material', 'labor', 'overhead', 'subcontracting']);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 200);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('standard_cost_id')->references('id')->on('product_standard_costs')->onDelete('cascade');
            $table->index(['standard_cost_id', 'component_type'], 'cc_sc_type_idx');
        });

        Schema::create('quality_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->enum('cost_category', ['prevention', 'appraisal', 'internal_failure', 'external_failure'])->default('internal_failure');
            $table->string('cost_subcategory', 100)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'qce_product_fk')->references('id')->on('products');
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('amount', 18, 4);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by', 'qce_recorded_by_fk')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'period', 'fiscal_year'], 'qce_org_period_fy_idx');
            $table->index(['organization_id', 'cost_category'], 'qce_org_category_idx');
        });

        Schema::create('quality_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('notification_number');
            $table->enum('notification_type', [
                'defect',
                'complaint',
                'improvement',
                'deviation',
            ])->default('defect');
            $table->enum('source_type', [
                'inspection_lot',
                'customer',
                'supplier',
                'internal',
            ])->default('internal');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', [
                'open',
                'in_progress',
                'resolved',
                'closed',
            ])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'notification_number']);
            $table->index(['organization_id', 'status', 'priority']);
        });

        Schema::create('defect_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_notification_id')
                ->constrained('quality_notifications')
                ->cascadeOnDelete();
            $table->string('defect_type');
            $table->string('defect_code')->nullable();
            $table->integer('quantity')->default(1);
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->index('quality_notification_id');
        });

        Schema::create('quality_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->enum('inspection_stage', [
                'goods_receipt',
                'production',
                'pre_shipment',
                'in_process',
                'final',
            ])->default('goods_receipt');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('inspection_lot_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('inspection_trigger', ['goods_receipt', 'goods_issue', 'production_completion', 'manual'])->default('goods_receipt');
            $table->boolean('auto_create')->default(true);
            $table->decimal('sample_percentage', 5, 2)->default(100);
            $table->foreignId('quality_plan_id')->nullable()->constrained('quality_plans')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'product_id', 'inspection_trigger'], 'ilc_org_prod_trigger_unique');
        });

        Schema::create('inspection_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number');
            $table->foreignId('quality_plan_id')->nullable()->constrained('quality_plans')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->enum('source_type', [
                'purchase_order',
                'production',
                'transfer',
                'manual',
            ])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->decimal('inspected_quantity', 12, 4)->default(0);
            $table->decimal('accepted_quantity', 12, 4)->default(0);
            $table->decimal('rejected_quantity', 12, 4)->default(0);
            $table->enum('status', [
                'pending',
                'in_inspection',
                'accepted',
                'rejected',
                'partial_accept',
            ])->default('pending');
            $table->date('inspection_date')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'lot_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('procurement_inspection_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->boolean('inspection_required')->default(false);
            $table->decimal('sampling_percentage', 5, 2)->default(100);
            $table->decimal('auto_approve_below_defect_rate', 5, 2)->nullable();
            $table->foreignId('quality_plan_id')->nullable()->constrained('quality_plans')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'vendor_id'], 'proc_insp_cfg_org_prod_vnd');
        });

        Schema::create('procurement_inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->unsignedBigInteger('goods_receipt_id')->nullable();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('inspection_lot_id')->nullable()->constrained('inspection_lots')->nullOnDelete();
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_to_inspect', 18, 4);
            $table->decimal('quantity_inspected', 18, 4)->default(0);
            $table->decimal('quantity_accepted', 18, 4)->default(0);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->string('status', 20)->default('pending')
                ->comment('pending/in_progress/completed/approved/rejected');
            $table->decimal('defect_rate', 5, 2)->nullable();
            $table->dateTime('inspection_date')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_order_id', 'status'], 'proc_insp_po_status_idx');
            $table->index(['product_id', 'vendor_id'], 'proc_insp_prod_vnd_idx');
            $table->index(['status', 'inspection_date'], 'proc_insp_status_date_idx');
        });

        Schema::create('procurement_inspection_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('procurement_inspection_id')
                ->constrained('procurement_inspections')
                ->cascadeOnDelete();
            $table->string('characteristic_name', 100);
            $table->string('specification_min', 50)->nullable();
            $table->string('specification_max', 50)->nullable();
            $table->string('actual_value', 100)->nullable();
            $table->boolean('is_within_spec')->nullable();
            $table->text('defect_description')->nullable();
            $table->timestamps();

            $table->index('procurement_inspection_id', 'proc_insp_res_insp_idx');
        });

        Schema::create('q_info_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id', 'qir_vendor_fk')->references('id')->on('contacts');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'qir_product_fk')->references('id')->on('products');
            $table->enum('inspection_type', ['goods_receipt', 'in_process', 'final', 'delivery', 'returns'])->default('goods_receipt');
            $table->unsignedBigInteger('skip_lot_plan_id')->nullable();
            $table->foreign('skip_lot_plan_id', 'qir_slsp_fk')->references('id')->on('skip_lot_sampling_plans');
            $table->unsignedBigInteger('quality_plan_id')->nullable();
            $table->foreign('quality_plan_id', 'qir_qp_fk')->references('id')->on('quality_plans');
            $table->boolean('is_active')->default(true);
            $table->boolean('release_required')->default(false);
            $table->boolean('cert_required')->default(false);
            $table->string('cert_type', 50)->nullable();
            $table->unsignedSmallInteger('inspection_interval_days')->nullable();
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['organization_id', 'vendor_id', 'product_id', 'inspection_type'],
                'qir_org_vendor_prod_type_unq'
            );
            $table->index(['organization_id', 'product_id'], 'qir_org_product_idx');
        });

        Schema::create('quality_plan_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_plan_id')->constrained('quality_plans')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('inspection_method')->nullable();
            $table->string('measurement_unit')->nullable();
            $table->decimal('lower_limit', 12, 4)->nullable();
            $table->decimal('upper_limit', 12, 4)->nullable();
            $table->decimal('target_value', 12, 4)->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('quality_plan_id');
        });

        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_lot_id')->constrained('inspection_lots')->cascadeOnDelete();
            $table->foreignId('quality_plan_characteristic_id')
                ->nullable()
                ->constrained('quality_plan_characteristics')
                ->nullOnDelete();
            $table->string('characteristic_name');
            $table->decimal('measured_value', 12, 4)->nullable();
            $table->string('text_result')->nullable();
            $table->boolean('is_conforming')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index('inspection_lot_id');
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('recipe_code', 30);
            $table->string('name');
            $table->decimal('base_quantity', 18, 4);
            $table->foreignId('base_unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->string('recipe_type', 20)->default('master');
            $table->date('validity_from');
            $table->date('validity_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'recipe_code'],
                'recipes_org_code_unique'
            );
            $table->index(['product_id', 'is_active'], 'recipes_product_active_idx');
        });

        Schema::create('recipe_phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedInteger('phase_number');
            $table->string('name');
            $table->text('operation_description')->nullable();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->decimal('duration_hours', 8, 2);
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('pressure', 6, 2)->nullable();
            $table->unsignedInteger('agitation_rpm')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('recipe_phase_id')
                ->nullable()
                ->constrained('recipe_phases')
                ->nullOnDelete();
            $table->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->boolean('is_co_product')->default(false);
            $table->boolean('is_by_product')->default(false);
            $table->timestamps();
        });

        Schema::create('returns_inspection_lots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('rma_request_id')->nullable();
            $table->foreign('rma_request_id', 'ril_rma_fk')
                ->references('id')->on('rma_requests')->nullOnDelete();

            $table->unsignedBigInteger('sales_return_id')->nullable();
            $table->foreign('sales_return_id', 'ril_sales_return_fk')
                ->references('id')->on('sales_returns')->nullOnDelete();

            $table->unsignedBigInteger('purchase_return_id')->nullable();
            $table->foreign('purchase_return_id', 'ril_purchase_return_fk')
                ->references('id')->on('purchase_returns')->nullOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'ril_product_fk')
                ->references('id')->on('products');

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'ril_warehouse_fk')
                ->references('id')->on('warehouses')->nullOnDelete();

            $table->string('lot_number', 50);

            $table->enum('return_type', ['customer_return', 'vendor_return', 'internal_return'])
                ->default('customer_return');

            $table->enum('status', ['open', 'in_inspection', 'usage_decision_made', 'closed', 'cancelled'])
                ->default('open');

            $table->decimal('received_quantity', 18, 4);
            $table->decimal('inspected_quantity', 18, 4)->default(0);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->decimal('rework_quantity', 18, 4)->default(0);

            $table->enum('usage_decision', ['accept', 'reject', 'rework', 'partial_accept'])->nullable();

            $table->unsignedBigInteger('usage_decision_by')->nullable();
            $table->foreign('usage_decision_by', 'ril_ud_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->dateTime('usage_decision_at')->nullable();
            $table->text('usage_decision_notes')->nullable();

            $table->date('inspection_start_date')->nullable();
            $table->date('inspection_end_date')->nullable();

            $table->unsignedBigInteger('quality_plan_id')->nullable();
            $table->foreign('quality_plan_id', 'ril_qp_fk')
                ->references('id')->on('quality_plans')->nullOnDelete();

            $table->boolean('stock_posted')->default(false);
            $table->dateTime('stock_posted_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'ril_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'lot_number'], 'ril_org_lot_unq');
            $table->index(['organization_id', 'status'], 'ril_org_status_idx');
            $table->index(['product_id'], 'ril_product_idx');
        });

        Schema::create('returns_inspection_defects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'rid_org_fk')
                ->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('returns_inspection_lot_id');
            $table->foreign('returns_inspection_lot_id', 'rid_lot_fk')
                ->references('id')->on('returns_inspection_lots')->cascadeOnDelete();

            $table->string('defect_code', 50);
            $table->text('defect_description')->nullable();

            $table->enum('severity', ['critical', 'major', 'minor', 'cosmetic'])->default('minor');

            $table->decimal('quantity_affected', 18, 4)->default(0);

            $table->enum('recommended_action', ['scrap', 'return_to_vendor', 'rework', 'repack', 'accept'])->nullable();
            $table->enum('actual_action_taken', ['scrapped', 'returned_to_vendor', 'reworked', 'repacked', 'accepted'])->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by', 'rid_recorded_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['returns_inspection_lot_id'], 'rid_lot_idx');
        });

        Schema::create('routing_headers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('routing_number', 30);
            $table->string('alternative', 5)->default('1');
            $table->boolean('is_default')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'product_id', 'routing_number', 'alternative'], 'rh_org_prod_num_alt_unique');
        });

        Schema::create('production_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version_code', 20);
            $table->string('description')->nullable();
            $table->foreignId('bom_id')
                ->nullable()
                ->constrained('bom_templates')
                ->nullOnDelete();
            $table->foreignId('routing_id')
                ->nullable()
                ->constrained('routing_headers')
                ->nullOnDelete();
            $table->decimal('lot_size_from', 18, 4)->default(0);
            $table->decimal('lot_size_to', 18, 4)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('production_plant', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'product_id', 'version_code'],
                'pv_org_product_code_unique'
            );
            $table->index(['product_id', 'is_active'], 'pv_product_active_idx');
            $table->index(['product_id', 'is_default'], 'pv_product_default_idx');
        });

        Schema::create('ltp_planned_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ltp_simulation_id')
                ->constrained('ltp_simulations')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('planned_order_type', 20);
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->date('planned_start');
            $table->date('planned_finish');
            $table->foreignId('production_version_id')
                ->nullable()
                ->constrained('production_versions')
                ->nullOnDelete();
            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['product_id', 'planned_start'],
                'ltp_po_product_start_idx'
            );
        });

        Schema::create('process_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 30);
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4)->nullable();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->string('batch_number', 50)->nullable();
            $table->dateTime('planned_start');
            $table->dateTime('planned_finish');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_finish')->nullable();
            $table->string('status', 20)->default('created');
            $table->foreignId('production_version_id')
                ->nullable()
                ->constrained('production_versions')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'order_number'],
                'proc_ord_org_number_unique'
            );
            $table->index(['product_id', 'status'], 'proc_ord_product_status_idx');
            $table->index(['status', 'planned_start'], 'proc_ord_status_start_idx');
        });

        Schema::create('process_order_phases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('process_order_id')
                ->constrained('process_orders')
                ->cascadeOnDelete();
            $table->foreignId('recipe_phase_id')
                ->nullable()
                ->constrained('recipe_phases')
                ->nullOnDelete();
            $table->unsignedInteger('phase_number');
            $table->string('name');
            $table->string('status', 20)->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('actual_temperature', 6, 2)->nullable();
            $table->decimal('actual_pressure', 6, 2)->nullable();
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->text('operator_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('process_order_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('process_order_id')
                ->constrained('process_orders')
                ->cascadeOnDelete();
            $table->foreignId('recipe_resource_id')
                ->nullable()
                ->constrained('recipe_resources')
                ->nullOnDelete();
            $table->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('actual_quantity', 18, 4)->nullable();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('repetitive_mfg_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_version_id')
                ->nullable()
                ->constrained('production_versions')
                ->nullOnDelete();
            $table->foreignId('production_line_id')
                ->constrained('production_lines')
                ->cascadeOnDelete();
            $table->date('schedule_date_from');
            $table->date('schedule_date_to');
            $table->decimal('total_planned_quantity', 18, 4);
            $table->decimal('total_confirmed_quantity', 18, 4)->default(0);
            $table->string('status', 20)->default('planned');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['product_id', 'schedule_date_from'],
                'rms_product_date_idx'
            );
            $table->index(
                ['production_line_id', 'status'],
                'rms_line_status_idx'
            );
            $table->index(
                ['status', 'schedule_date_from'],
                'rms_status_date_idx'
            );
        });

        Schema::create('repetitive_mfg_backflushes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repetitive_mfg_schedule_id')
                ->constrained('repetitive_mfg_schedules')
                ->cascadeOnDelete();
            $table->dateTime('backflush_date');
            $table->decimal('quantity_produced', 18, 4);
            $table->decimal('quantity_scrapped', 18, 4)->default(0);
            $table->json('component_movements')->nullable();
            $table->decimal('labor_time_minutes', 10, 2)->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('repetitive_mfg_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('repetitive_mfg_schedule_id')
                ->constrained('repetitive_mfg_schedules')
                ->cascadeOnDelete();
            $table->date('schedule_date');
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('confirmed_quantity', 18, 4)->default(0);
            $table->string('status', 20)->default('planned');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('repetitive_mfg_schedule_lines');
        Schema::dropIfExists('repetitive_mfg_backflushes');
        Schema::dropIfExists('repetitive_mfg_schedules');
        Schema::dropIfExists('process_order_resources');
        Schema::dropIfExists('process_order_phases');
        Schema::dropIfExists('process_orders');
        Schema::dropIfExists('ltp_planned_orders');
        Schema::dropIfExists('production_versions');
        Schema::dropIfExists('routing_headers');
        Schema::dropIfExists('returns_inspection_defects');
        Schema::dropIfExists('returns_inspection_lots');
        Schema::dropIfExists('recipe_resources');
        Schema::dropIfExists('recipe_phases');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('inspection_results');
        Schema::dropIfExists('quality_plan_characteristics');
        Schema::dropIfExists('q_info_records');
        Schema::dropIfExists('procurement_inspection_results');
        Schema::dropIfExists('procurement_inspections');
        Schema::dropIfExists('procurement_inspection_configs');
        Schema::dropIfExists('inspection_lots');
        Schema::dropIfExists('inspection_lot_configs');
        Schema::dropIfExists('quality_plans');
        Schema::dropIfExists('defect_records');
        Schema::dropIfExists('quality_notifications');
        Schema::dropIfExists('quality_cost_entries');
        Schema::dropIfExists('cost_components');
        Schema::dropIfExists('product_standard_costs');
        Schema::dropIfExists('product_costs');
        Schema::dropIfExists('planned_independent_requirements');
        Schema::dropIfExists('mrp_planned_orders');
        Schema::dropIfExists('mrp_demand_items');
        Schema::dropIfExists('kanban_cards');
        Schema::dropIfExists('kanban_control_cycles');
        Schema::dropIfExists('demand_forecasts');
        Schema::dropIfExists('certificates_of_analysis');
        Schema::dropIfExists('bom_operations');
        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('bom_templates');
        Schema::dropIfExists('approved_vendor_lists');
    }
};
