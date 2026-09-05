<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_id')->constrained('routing_headers')->cascadeOnDelete();
            $table->integer('sequence_number'); // 10, 20, 30...
            $table->string('operation_code', 20);
            $table->string('description', 255);
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->decimal('setup_time', 10, 4)->default(0); // hours
            $table->decimal('machine_time', 10, 4)->default(0); // hours per unit
            $table->decimal('labor_time', 10, 4)->default(0); // hours per unit
            $table->string('control_key', 10)->nullable(); // PP01=internal, PP02=external
            $table->timestamps();
            $table->index(['routing_id', 'sequence_number'], 'ro_routing_seq_idx');

            $table->decimal('inter_operation_time', 8, 2)->default(0)
                ->comment('Buffer/transit hours between operations');
        });

        Schema::create('skip_lot_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'sld_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('skip_lot_sampling_plan_id');
            $table->foreign('skip_lot_sampling_plan_id', 'sld_plan_fk')->references('id')->on('skip_lot_sampling_plans');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id', 'sld_vendor_fk')->references('id')->on('contacts');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'sld_product_fk')->references('id')->on('products');
            $table->enum('current_level', ['skip_lot', 'reduced', 'normal', 'tightened', 'rejected'])->default('normal');
            $table->unsignedInteger('lots_inspected_at_level')->default(0);
            $table->unsignedInteger('consecutive_accepted')->default(0);
            $table->unsignedInteger('consecutive_rejected')->default(0);
            $table->unsignedBigInteger('last_inspection_lot_id')->nullable();
            $table->foreign('last_inspection_lot_id', 'sld_last_lot_fk')->references('id')->on('inspection_lots');
            $table->dateTime('last_evaluated_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'vendor_id', 'product_id'], 'sld_org_vendor_product_idx');
        });

        Schema::create('stability_studies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('study_number', 50);
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'ss_product_fk')->references('id')->on('products');
            $table->unsignedBigInteger('inventory_batch_id')->nullable();
            $table->foreign('inventory_batch_id', 'ss_batch_fk')->references('id')->on('inventory_batches');
            $table->enum('study_type', ['real_time', 'accelerated', 'intermediate'])->default('real_time');
            $table->enum('status', ['planned', 'active', 'completed', 'discontinued'])->default('planned');
            $table->date('start_date');
            $table->date('planned_end_date')->nullable();
            $table->string('storage_condition', 100)->nullable();
            $table->string('protocol_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'study_number'], 'ss_org_number_unq');
        });

        Schema::create('stability_study_time_points', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'sstp_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('stability_study_id');
            $table->foreign('stability_study_id', 'sstp_study_fk')->references('id')->on('stability_studies');
            $table->string('time_point', 20);
            $table->date('scheduled_date');
            $table->date('actual_date')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'missed'])->default('scheduled');
            $table->timestamps();
            $table->index(['stability_study_id'], 'sstp_study_idx');
        });

        Schema::create('stability_study_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ssr_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('stability_study_time_point_id');
            $table->foreign('stability_study_time_point_id', 'ssr_timepoint_fk')->references('id')->on('stability_study_time_points');
            $table->string('parameter_name', 100);
            $table->decimal('specification_min', 18, 4)->nullable();
            $table->decimal('specification_max', 18, 4)->nullable();
            $table->decimal('result_value', 18, 4)->nullable();
            $table->string('result_text', 255)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->boolean('is_pass')->nullable();
            $table->unsignedBigInteger('tested_by')->nullable();
            $table->foreign('tested_by', 'ssr_tested_by_fk')->references('id')->on('users');
            $table->timestamps();
            $table->index(['stability_study_time_point_id'], 'ssr_timepoint_idx');
        });

        Schema::create('subcontract_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('required_quantity', 15, 4);
            $table->decimal('transferred_quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->index('order_id', 'sccomp_order_idx');
        });

        Schema::create('subcontract_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('ordered_quantity', 15, 4);
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->decimal('unit_service_charge', 15, 4)->default(0);
            $table->decimal('total_service_charge', 15, 4)->default(0);
            $table->decimal('scrap_quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->index('order_id', 'scol_order_idx');
        });

        Schema::create('subcontract_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receipt_id');
            $table->unsignedBigInteger('order_line_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_rejected', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('subcontract_receipts')->onDelete('cascade');
            $table->foreign('order_line_id')->references('id')->on('subcontract_order_lines')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->index('receipt_id', 'scrl_receipt_idx');
        });

        Schema::create('subcontract_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id');
            $table->date('transfer_date');
            $table->enum('transfer_type', ['outward', 'inward']);
            $table->unsignedBigInteger('warehouse_id');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('stock_movement_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->onDelete('set null');
            $table->index(['order_id', 'transfer_type'], 'sct_order_type_idx');
        });

        Schema::create('subcontract_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('component_line_id')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->unsignedBigInteger('unit_id');
            $table->string('batch_number', 100)->nullable();
            $table->timestamps();

            $table->foreign('transfer_id')->references('id')->on('subcontract_transfers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null');
            $table->foreign('component_line_id')->references('id')->on('subcontract_components')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->index('transfer_id', 'sctl_transfer_idx');
        });

        Schema::create('supplier_ncr_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('ncr_number', 50)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id', 'sq_ncr_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'sq_ncr_product_fk')->references('id')->on('products')->nullOnDelete();
            $table->string('po_number', 50)->nullable();
            $table->text('nonconformance_description');
            $table->enum('severity', ['critical', 'major', 'minor'])->default('minor');
            $table->enum('disposition', ['use_as_is', 'rework', 'repair', 'return_to_supplier', 'scrap'])->nullable();
            $table->enum('status', ['open', 'supplier_response_pending', 'under_review', 'closed'])->default('open');
            $table->date('detected_date');
            $table->date('closed_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('usage_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_lot_id')->constrained('inspection_lots')->cascadeOnDelete();
            $table->string('decision_number')->unique();

            // Overall decision outcome for the lot
            $table->enum('decision_code', ['accept', 'reject', 'partial'])->default('accept');

            // Quantitative split across stock types
            $table->decimal('qty_unrestricted', 12, 4)->default(0); // → movement 321
            $table->decimal('qty_blocked', 12, 4)->default(0);       // → movement 346
            $table->decimal('qty_scrap', 12, 4)->default(0);         // → movement 551

            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'inspection_lot_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('usage_decisions');
        Schema::dropIfExists('supplier_ncr_records');
        Schema::dropIfExists('subcontract_transfer_lines');
        Schema::dropIfExists('subcontract_transfers');
        Schema::dropIfExists('subcontract_receipt_lines');
        Schema::dropIfExists('subcontract_order_lines');
        Schema::dropIfExists('subcontract_components');
        Schema::dropIfExists('stability_study_results');
        Schema::dropIfExists('stability_study_time_points');
        Schema::dropIfExists('stability_studies');
        Schema::dropIfExists('skip_lot_decisions');
        Schema::dropIfExists('routing_operations');
    }
};
