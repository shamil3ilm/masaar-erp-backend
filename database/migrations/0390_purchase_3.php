<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->text('description');

            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('unit_price', 18, 4);

            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->string('tax_code', 10)->nullable();

            // GST split (for India)
            $table->decimal('cgst_rate', 8, 4)->default(0);
            $table->decimal('cgst_amount', 18, 4)->default(0);
            $table->decimal('sgst_rate', 8, 4)->default(0);
            $table->decimal('sgst_amount', 18, 4)->default(0);
            $table->decimal('igst_rate', 8, 4)->default(0);
            $table->decimal('igst_amount', 18, 4)->default(0);
            $table->string('hsn_code', 20)->nullable();

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            // Accounting
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('line_order')->default(0);
            $table->timestamps();

            $table->index('bill_id');
        });

        Schema::create('contract_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('line_total', 15, 4)->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->json('delivery_schedule')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete();

            $table->index('contract_id', 'contract_lines_contract_id_idx');
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('gr_number', 30);
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->date('gr_date');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['organization_id', 'gr_number'], 'goods_receipts_org_number_unique');
            $table->index(['organization_id', 'status'], 'goods_receipts_org_status_idx');
            $table->index(['organization_id', 'gr_date'], 'goods_receipts_org_date_idx');

            // FK to the inspection lot created for this GR (nullable — only set when QI triggered)
            $table->unsignedBigInteger('inspection_lot_id')->nullable();
            $table->foreign('inspection_lot_id')
                ->references('id')
                ->on('inspection_lots')
                ->nullOnDelete();

            // Note: the status column in goods_receipts is a string (VARCHAR).
            // The 'in_inspection' value is added as a valid application-level status;
            // no enum alteration is required — existing rows and constraints remain valid.
        });

        Schema::create('ers_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ers_item_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('ers_run_id');
            $table->foreign('ers_run_id', 'ers_item_run_fk')->references('id')->on('ers_runs');
            $table->unsignedBigInteger('goods_receipt_id');
            $table->foreign('goods_receipt_id', 'ers_item_gr_fk')->references('id')->on('goods_receipts');
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->foreign('bill_id', 'ers_item_bill_fk')->references('id')->on('bills');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'ers_item_vendor_fk')->references('id')->on('contacts');
            $table->decimal('gross_amount', 18, 4);
            $table->enum('status', ['processed', 'failed', 'skipped'])->default('processed');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['ers_run_id'], 'ers_item_run_idx');
        });

        Schema::create('outline_agreement_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'oai_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('outline_agreement_id');
            $table->foreign('outline_agreement_id', 'oai_agreement_fk')->references('id')->on('outline_agreements');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'oai_product_fk')->references('id')->on('products');
            $table->unsignedSmallInteger('line_number');
            $table->string('description')->nullable();
            $table->decimal('target_quantity', 18, 4)->nullable();
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->decimal('released_value', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->timestamps();

            $table->index(['outline_agreement_id'], 'oai_agreement_idx');
        });

        Schema::create('outline_agreement_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'oar_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('outline_agreement_id');
            $table->foreign('outline_agreement_id', 'oar_agreement_fk')->references('id')->on('outline_agreements');
            $table->unsignedBigInteger('outline_agreement_item_id')->nullable();
            $table->foreign('outline_agreement_item_id', 'oar_item_fk')->references('id')->on('outline_agreement_items');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id', 'oar_po_fk')->references('id')->on('purchase_orders');
            $table->date('release_date');
            $table->decimal('release_quantity', 18, 4)->nullable();
            $table->decimal('release_value', 18, 4)->nullable();
            $table->enum('status', ['open', 'goods_received', 'invoiced', 'cancelled'])->default('open');
            $table->timestamps();

            $table->index(['outline_agreement_id'], 'oar_agreement_idx');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->text('description');

            $table->decimal('quantity', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('quantity_billed', 18, 4)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('unit_price', 18, 4);

            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->string('tax_code', 10)->nullable();

            // GST split (for India)
            $table->decimal('cgst_rate', 8, 4)->default(0);
            $table->decimal('cgst_amount', 18, 4)->default(0);
            $table->decimal('sgst_rate', 8, 4)->default(0);
            $table->decimal('sgst_amount', 18, 4)->default(0);
            $table->decimal('igst_rate', 8, 4)->default(0);
            $table->decimal('igst_amount', 18, 4)->default(0);

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('line_order')->default(0);
            $table->timestamps();

            $table->unsignedBigInteger('wbs_element_id')->nullable()
                ->comment('WBS element for project account assignment');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('account_assignment_type', 20)->nullable()
                ->comment('K=cost_center, P=project/wbs, F=order, blank=stock');


                $table->foreign('wbs_element_id', 'pol_wbs_element_fk')
                    ->references('id')->on('wbs_elements')->nullOnDelete();



                $table->foreign('project_id', 'pol_project_fk')
                    ->references('id')->on('projects')->nullOnDelete();


            $table->index('wbs_element_id', 'pol_wbs_element_idx');
            $table->index('project_id', 'pol_project_idx');


            $table->index('purchase_order_id', 'pol_purchase_order_id_idx');
            $table->index('product_id', 'pol_product_id_idx');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gr_id');
            $table->unsignedBigInteger('po_line_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity_ordered', 15, 4);
            $table->decimal('quantity_received', 15, 4);
            $table->decimal('quantity_rejected', 15, 4)->default(0);
            $table->unsignedBigInteger('unit_id');
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 4);
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->foreign('gr_id')->references('id')->on('goods_receipts')->onDelete('cascade');
            $table->foreign('po_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units_of_measure');
            $table->foreign('location_id')->references('id')->on('warehouse_locations')->nullOnDelete();

            $table->index('gr_id', 'gr_lines_gr_id_idx');
            $table->index('po_line_id', 'gr_lines_po_line_id_idx');
        });

        Schema::create('po_wbs_commitments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedBigInteger('wbs_element_id');
            $table->decimal('committed_amount', 18, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('commitment_date');
            $table->string('status', 20)->default('open')
                ->comment('open/partially_delivered/closed');
            $table->timestamps();

            $table->index(['wbs_element_id', 'status'], 'po_wbs_comm_wbs_status_idx');
            $table->index('purchase_order_id', 'po_wbs_comm_po_idx');
        });

        Schema::create('procurement_gr_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('procurement_goods_receipts')->cascadeOnDelete();
            $table->foreignId('po_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('ordered_qty', 15, 3)->default(0);
            $table->decimal('received_qty', 15, 3)->default(0);
            $table->decimal('accepted_qty', 15, 3)->default(0);
            $table->decimal('rejected_qty', 15, 3)->default(0);
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('goods_receipt_id', 'proc_gr_lines_gr_id_idx');
            $table->index('product_id', 'proc_gr_lines_product_idx');
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('purchase_requisitions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('estimated_unit_price', 15, 4)->nullable();
            $table->foreignId('preferred_vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->date('required_by_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'partially_converted', 'converted', 'cancelled'])->default('open');
            $table->timestamps();
            $table->index(['requisition_id'], 'prl_req_idx');
            $table->index(['product_id', 'status'], 'prl_product_status_idx');
        });

        Schema::create('purchasing_info_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pir_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id', 'pir_vendor_fk')
                ->references('id')->on('contacts')->onDelete('set null');

            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'pir_product_fk')
                ->references('id')->on('products')->onDelete('set null');

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'pir_warehouse_fk')
                ->references('id')->on('warehouses')->onDelete('set null');

            $table->enum('info_category', ['standard', 'subcontracting', 'consignment', 'pipeline'])
                ->default('standard');
            $table->boolean('is_active')->default(true);

            $table->unsignedSmallInteger('planned_delivery_days')->nullable();
            $table->unsignedSmallInteger('reminder_days')->nullable()
                ->comment('Days before delivery to send reminder');

            $table->decimal('overdelivery_tolerance', 5, 2)->nullable()
                ->comment('Over-delivery tolerance percentage');
            $table->decimal('underdelivery_tolerance', 5, 2)->nullable()
                ->comment('Under-delivery tolerance percentage');
            $table->boolean('is_underdelivery_tolerated')->default(false);

            $table->decimal('net_price', 18, 4)->nullable();
            $table->unsignedInteger('price_unit')->default(1);
            $table->char('currency_code', 3)->default('SAR');

            $table->decimal('minimum_order_quantity', 18, 4)->nullable();
            $table->decimal('standard_order_quantity', 18, 4)->nullable();

            $table->date('last_purchase_date')->nullable();
            $table->decimal('last_purchase_price', 18, 4)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'vendor_id', 'product_id', 'info_category'],
                'pir_org_vendor_product_category_unq'
            );
            $table->index(['organization_id', 'product_id'], 'pir_org_product_idx');
        });

        Schema::create('purchasing_info_record_conditions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pir_cond_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('purchasing_info_record_id');
            $table->foreign('purchasing_info_record_id', 'pir_cond_pir_fk')
                ->references('id')->on('purchasing_info_records')->onDelete('cascade');

            $table->date('valid_from');
            $table->date('valid_to')->nullable();

            $table->decimal('net_price', 18, 4);
            $table->unsignedInteger('price_unit')->default(1);
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['purchasing_info_record_id', 'valid_from'],
                'pir_cond_pir_valid_idx'
            );
        });

        Schema::create('quota_arrangements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'qa_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'qa_product_fk')
                ->references('id')->on('products')->onDelete('cascade');

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'qa_warehouse_fk')
                ->references('id')->on('warehouses')->onDelete('set null');

            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['organization_id', 'product_id', 'valid_from'],
                'qa_org_product_valid_idx'
            );
        });

        Schema::create('quota_arrangement_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'qai_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unsignedBigInteger('quota_arrangement_id');
            $table->foreign('quota_arrangement_id', 'qai_arrangement_fk')
                ->references('id')->on('quota_arrangements')->onDelete('cascade');

            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'qai_vendor_fk')
                ->references('id')->on('contacts')->onDelete('cascade');

            $table->unsignedBigInteger('purchasing_info_record_id')->nullable();
            $table->foreign('purchasing_info_record_id', 'qai_pir_fk')
                ->references('id')->on('purchasing_info_records')->onDelete('set null');

            $table->decimal('quota_percentage', 5, 2)
                ->comment('Must sum to 100 across all items in the arrangement');
            $table->decimal('min_lot_size', 18, 4)->nullable();
            $table->decimal('max_lot_size', 18, 4)->nullable();
            $table->decimal('allocated_quantity', 18, 4)->default(0)
                ->comment('Running total of quantity assigned via this quota item');
            $table->dateTime('last_assigned_at')->nullable();
            $table->boolean('is_blocked')->default(false);

            $table->timestamps();

            $table->index(['quota_arrangement_id'], 'qai_arrangement_idx');
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete();

            $table->index('rfq_id', 'rfq_items_rfq_id_idx');
        });

        Schema::create('rfq_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfq_headers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('unit_of_measure', 20)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('rfq_id', 'rfq_lines_rfq_id_idx');
        });

        Schema::create('rfq_quote_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_quote_id');
            $table->unsignedBigInteger('rfq_item_id');
            $table->decimal('unit_price', 15, 4);
            $table->decimal('quantity', 15, 4);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('rfq_quote_id')->references('id')->on('rfq_quotes')->onDelete('cascade');
            $table->foreign('rfq_item_id')->references('id')->on('rfq_items')->onDelete('cascade');

            $table->index('rfq_quote_id', 'rfq_quote_lines_quote_id_idx');
        });

        Schema::create('rfq_vendor_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_vendor_id')->constrained('rfq_vendors')->cascadeOnDelete();
            $table->foreignId('rfq_line_id')->constrained('rfq_lines')->cascadeOnDelete();
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('total_price', 15, 4)->default(0);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rfq_vendor_id', 'rfq_line_id'], 'rfq_vq_vendor_line_idx');
        });

        Schema::create('scheduling_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'sa_vendor_fk')->references('id')->on('contacts');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'sa_product_fk')->references('id')->on('products');
            $table->string('agreement_number', 50);
            $table->enum('status', ['draft', 'active', 'expired', 'cancelled'])->default('draft');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('target_quantity', 18, 4);
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4);
            $table->char('currency_code', 3)->default('SAR');
            $table->string('unit_of_measure', 20)->nullable();
            $table->unsignedSmallInteger('delivery_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'agreement_number'], 'sched_ag_org_number_unq');
            $table->index(['organization_id', 'vendor_id', 'product_id'], 'sched_ag_org_vendor_prod_idx');
        });

        Schema::create('sa_delivery_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'sads_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('scheduling_agreement_id');
            $table->foreign('scheduling_agreement_id', 'sads_sa_fk')->references('id')->on('scheduling_agreements');
            $table->date('schedule_date');
            $table->decimal('scheduled_quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->enum('status', ['open', 'partial', 'complete', 'cancelled'])->default('open');
            $table->unsignedBigInteger('goods_receipt_id')->nullable();
            $table->foreign('goods_receipt_id', 'sads_gr_fk')->references('id')->on('goods_receipts');
            $table->timestamps();

            $table->index(['scheduling_agreement_id', 'schedule_date'], 'sads_sa_date_idx');
        });

        Schema::create('three_way_match_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('bill_id');
            $table->unsignedBigInteger('bill_line_id')->nullable();
            $table->unsignedBigInteger('po_line_id')->nullable();
            $table->unsignedBigInteger('gr_line_id')->nullable();
            $table->decimal('po_quantity', 15, 4)->nullable();
            $table->decimal('gr_quantity', 15, 4)->nullable();
            $table->decimal('invoice_quantity', 15, 4)->nullable();
            $table->decimal('po_unit_price', 15, 4)->nullable();
            $table->decimal('invoice_unit_price', 15, 4)->nullable();
            $table->boolean('quantity_match')->default(false);
            $table->boolean('price_match')->default(false);
            $table->enum('match_status', ['matched', 'quantity_variance', 'price_variance', 'missing_gr', 'pending'])->default('pending');
            $table->decimal('variance_amount', 15, 4)->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('bill_id')->references('id')->on('bills')->onDelete('cascade');
            $table->foreign('po_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete();
            $table->foreign('gr_line_id')->references('id')->on('goods_receipt_lines')->nullOnDelete();

            $table->index(['organization_id', 'bill_id'], 'twm_org_bill_idx');
            $table->index(['organization_id', 'match_status'], 'twm_org_status_idx');
        });

        Schema::create('vendor_consignment_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->decimal('vendor_price', 18, 4);
            $table->string('currency_code', 3);
            $table->dateTime('last_movement_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'vcs_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_id', 'vcs_vendor_fk')
                ->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('product_id', 'vcs_product_fk')
                ->references('id')->on('products')->onDelete('cascade');
            $table->foreign('warehouse_id', 'vcs_warehouse_fk')
                ->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('warehouse_location_id', 'vcs_wh_loc_fk')
                ->references('id')->on('warehouse_locations')->onDelete('set null');
            $table->foreign('unit_id', 'vcs_unit_fk')
                ->references('id')->on('units_of_measure')->onDelete('set null');

            $table->unique(
                ['organization_id', 'vendor_id', 'product_id', 'warehouse_id'],
                'vcs_org_vendor_product_wh_unique'
            );
            $table->index(['vendor_id', 'product_id'], 'vcs_vendor_product_idx');
        });

        Schema::create('vendor_consignment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_consignment_stock_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->date('receipt_date');
            $table->decimal('quantity_received', 18, 4);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('vendor_delivery_note', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'vcr_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_consignment_stock_id', 'vcr_stock_fk')
                ->references('id')->on('vendor_consignment_stocks')->onDelete('cascade');
            $table->foreign('purchase_order_id', 'vcr_po_fk')
                ->references('id')->on('purchase_orders')->onDelete('set null');
            $table->foreign('unit_id', 'vcr_unit_fk')
                ->references('id')->on('units_of_measure')->onDelete('set null');
            $table->foreign('created_by', 'vcr_created_by_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('vendor_consignment_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_consignment_stock_id');
            $table->date('withdrawal_date');
            $table->decimal('quantity_withdrawn', 18, 4);
            $table->string('withdrawal_type', 30); // production/sales/transfer/scrapping
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'vcw_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_consignment_stock_id', 'vcw_stock_fk')
                ->references('id')->on('vendor_consignment_stocks')->onDelete('cascade');
            $table->foreign('unit_id', 'vcw_unit_fk')
                ->references('id')->on('units_of_measure')->onDelete('set null');
            $table->foreign('created_by', 'vcw_created_by_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('vendor_contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_contract_id')->constrained('vendor_contracts')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->string('unit_of_measure', 20)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('vendor_contract_id', 'vendor_contract_items_contract_idx');
        });

        Schema::create('vendor_credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_credit_note_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_credit_note_id')->references('id')->on('vendor_credit_notes')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('vendor_product_pricing', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->index();

            // The product this pricing record applies to
            $table->unsignedBigInteger('product_id');
            // The vendor (contact) offering this price
            $table->unsignedBigInteger('vendor_id');

            // Vendor's own code and description for this product
            $table->string('vendor_product_code', 100)->nullable();
            $table->string('vendor_product_description', 500)->nullable();

            // Pricing
            $table->decimal('unit_price', 15, 4);
            $table->char('currency_code', 3)->default('SAR');

            // Procurement terms
            $table->unsignedInteger('lead_time_days')->default(7);
            $table->decimal('minimum_order_quantity', 10, 4)->default(1);
            $table->decimal('order_quantity_multiple', 10, 4)->nullable()
                ->comment('Quantity must be ordered in multiples of this value');

            // Validity window (null = no restriction)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Whether this is the default vendor for the product
            $table->boolean('is_preferred_vendor')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('contacts')->onDelete('cascade');

            // Composite indexes with explicit short names (MySQL max 64 chars)
            $table->index(['organization_id', 'product_id', 'vendor_id'], 'vpp_org_product_vendor_idx');
            $table->index(['organization_id', 'product_id', 'is_preferred_vendor'], 'vpp_org_product_preferred_idx');
        });

        Schema::create('vendor_source_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->index();

            // The product being sourced
            $table->unsignedBigInteger('product_id');
            // The approved vendor
            $table->unsignedBigInteger('vendor_id');
            // Optional link to the negotiated pricing record for this vendor/product
            $table->unsignedBigInteger('vendor_product_pricing_id')->nullable();

            // Optional plant/location scope (null = all locations)
            $table->string('plant_code', 50)->nullable();

            // Validity window (null = no restriction)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // If true, purchasing orders MUST use this vendor (no alternatives)
            $table->boolean('is_fixed_vendor')->default(false);
            // If true, this vendor cannot be used for new orders
            $table->boolean('is_blocked')->default(false);

            // Lower number = higher preference (1 = most preferred)
            $table->unsignedInteger('priority')->default(1);
            // Percentage of demand volume to route to this vendor (null = 100%)
            $table->decimal('quota_percentage', 5, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('vendor_product_pricing_id')
                ->references('id')
                ->on('vendor_product_pricing')
                ->onDelete('set null');

            // Composite index with explicit short name
            $table->index(
                ['organization_id', 'product_id', 'is_blocked', 'priority'],
                'vsl_org_product_blocked_priority_idx'
            );
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_source_lists');
        Schema::dropIfExists('vendor_product_pricing');
        Schema::dropIfExists('vendor_credit_note_lines');
        Schema::dropIfExists('vendor_contract_items');
        Schema::dropIfExists('vendor_consignment_withdrawals');
        Schema::dropIfExists('vendor_consignment_receipts');
        Schema::dropIfExists('vendor_consignment_stocks');
        Schema::dropIfExists('three_way_match_results');
        Schema::dropIfExists('sa_delivery_schedules');
        Schema::dropIfExists('scheduling_agreements');
        Schema::dropIfExists('rfq_vendor_quotes');
        Schema::dropIfExists('rfq_quote_lines');
        Schema::dropIfExists('rfq_lines');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('quota_arrangement_items');
        Schema::dropIfExists('quota_arrangements');
        Schema::dropIfExists('purchasing_info_record_conditions');
        Schema::dropIfExists('purchasing_info_records');
        Schema::dropIfExists('purchase_requisition_lines');
        Schema::dropIfExists('procurement_gr_lines');
        Schema::dropIfExists('po_wbs_commitments');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('outline_agreement_releases');
        Schema::dropIfExists('outline_agreement_items');
        Schema::dropIfExists('ers_run_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('contract_lines');
        Schema::dropIfExists('bill_lines');
    }
};
