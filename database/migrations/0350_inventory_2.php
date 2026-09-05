<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Identification
            $table->string('sku', 50);
            $table->string('barcode', 50)->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Classification
            $table->enum('type', ['goods', 'service'])->default('goods');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units_of_measure');

            // Pricing
            $table->decimal('purchase_price', 18, 4)->default(0);
            $table->decimal('selling_price', 18, 4)->default(0);
            $table->decimal('minimum_price', 18, 4)->nullable(); // Floor price

            // Tax
            $table->foreignId('tax_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hsn_code', 20)->nullable(); // HSN/SAC for India GST

            // Accounting Links
            $table->foreignId('income_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Inventory Settings
            $table->enum('costing_method', ['fifo', 'weighted_average', 'standard'])->default('weighted_average');
            $table->boolean('track_inventory')->default(true);
            $table->decimal('reorder_level', 18, 4)->nullable();
            $table->decimal('reorder_quantity', 18, 4)->nullable();

            // Physical Properties
            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 10)->nullable();
            $table->decimal('length', 10, 3)->nullable();
            $table->decimal('width', 10, 3)->nullable();
            $table->decimal('height', 10, 3)->nullable();
            $table->string('dimension_unit', 10)->nullable();

            // Media
            $table->string('image_url', 500)->nullable();
            $table->json('gallery_urls')->nullable();

            // Variants
            $table->boolean('has_variants')->default(false);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_sellable')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'sku']);
            $table->index(['organization_id', 'barcode']);
            $table->index(['organization_id', 'category_id']);
            $table->index(['organization_id', 'is_active']);

            $table->string('product_type', 30)->default('goods');
            // goods, service, consumable, digital, bundle
            $table->foreignId('base_unit_id')->nullable()
                ->constrained('units_of_measure')->nullOnDelete();
            // track_inventory, reorder_quantity, weight/dimensions already exist from products create migration
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->boolean('has_expiry')->default(false);
            $table->unsignedSmallInteger('expiry_warning_days')->nullable();
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('sell_below_cost')->default(true);
            $table->decimal('minimum_stock', 15, 4)->nullable();
            $table->decimal('maximum_stock', 15, 4)->nullable();
            $table->decimal('reorder_point', 15, 4)->nullable();
            $table->boolean('is_loose_item')->default(false);
            $table->decimal('tare_weight', 10, 4)->nullable();



                $table->string('barcode_type', 30)->nullable();



            $table->text('long_description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('brand')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->string('country_of_origin', 3)->nullable();
            $table->decimal('mrp', 15, 4)->nullable();
            $table->decimal('wholesale_price', 15, 4)->nullable();
            $table->decimal('minimum_order_qty', 15, 4)->nullable();
            $table->decimal('maximum_order_qty', 15, 4)->nullable();
            $table->string('warranty_type', 30)->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->text('warranty_terms')->nullable();
            $table->string('shelf_life_days')->nullable();
            $table->json('seo_meta')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            $table->boolean('is_bestseller')->default(false);
            $table->boolean('is_returnable')->default(true);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('requires_shipping')->default(true);


            $table->unsignedSmallInteger('lead_time_days')->default(0)->comment('Procurement/manufacturing lead time in days');
            $table->unsignedSmallInteger('default_supplier_lead_days')->default(0)->comment('Default supplier delivery lead time in days');


            $table->boolean('requires_inspection')->default(false);


            $table->string('batch_selection_strategy')->nullable()
                ->comment('Override batch deduction order: fifo, lifo, fefo. Null falls back to costing_method.');


                $table->unsignedBigInteger('material_account_group_id')->nullable();
                $table->foreign('material_account_group_id', 'product_mag_fk')
                    ->references('id')->on('material_account_groups')->onDelete('set null');
        });

        Schema::create('cross_docking_order_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cross_docking_order_id')
                ->constrained('cross_docking_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity_transferred', 18, 4)->default(0);
            $table->string('status', 20)->default('pending')
                ->comment('pending/transferred/partial');
            $table->timestamps();

            $table->index('cross_docking_order_id', 'xdock_line_order_idx');
        });

        Schema::create('cycle_count_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('cycle_count_session_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('variance_percentage', 8, 4)->nullable();
            $table->boolean('recount_required')->default(false);
            $table->enum('status', ['pending', 'counted', 'recounted', 'approved'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('cycle_count_session_id', 'cc_line_sess_fk')->references('id')->on('cycle_count_sessions')->cascadeOnDelete();
            $table->foreign('product_id', 'cc_line_prod_fk')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('warehouse_location_id', 'cc_line_loc_fk')->references('id')->on('warehouse_locations')->nullOnDelete();
            $table->foreign('approved_by', 'cc_line_appr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ewm_transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('to_number', 30)->unique();  // TO-2026-001234
            $table->enum('movement_type', [
                'goods_receipt',
                'goods_issue',
                'internal_move',
                'replenishment',
                'stock_transfer',
                'physical_inventory',
            ]);
            $table->enum('status', ['created', 'assigned', 'in_progress', 'confirmed', 'cancelled'])->default('created');

            // Source
            $table->foreignId('source_bin_id')->nullable()->constrained('ewm_bins')->nullOnDelete();
            $table->string('source_bin_code', 50)->nullable();

            // Destination
            $table->foreignId('dest_bin_id')->nullable()->constrained('ewm_bins')->nullOnDelete();
            $table->string('dest_bin_code', 50)->nullable();

            // Product
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('requested_qty', 15, 4);
            $table->decimal('confirmed_qty', 15, 4)->nullable();
            $table->string('unit_of_measure', 20)->default('EA');
            $table->string('batch_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();

            // Traceability
            $table->string('reference_type', 50)->nullable();  // sales_order, purchase_order, etc.
            $table->unsignedBigInteger('reference_id')->nullable();

            // Labor
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('actual_duration_minutes', 8, 2)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'warehouse_id', 'status']);
            $table->index(['organization_id', 'movement_type', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('ewm_labor_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('transfer_order_id')->nullable()->constrained('ewm_transfer_orders')->nullOnDelete();
            $table->enum('task_type', ['pick', 'put', 'move', 'count', 'pack', 'load', 'unload']);
            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])->default('normal');
            $table->enum('status', ['queued', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('queued');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('standard_minutes', 8, 2)->nullable();  // expected task duration
            $table->decimal('actual_minutes', 8, 2)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'warehouse_id', 'status', 'priority'], 'ewm_labor_tasks_org_warehouse_status_priority_idx');
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('hazmat_transport_regulations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->string('un_number', 10)->nullable();
            $table->string('proper_shipping_name', 200)->nullable();
            $table->string('hazard_class', 20)->nullable();
            $table->string('packing_group', 5)->nullable();
            $table->string('transport_mode', 20); // road/air/sea/rail
            $table->boolean('is_forbidden')->default(false);
            $table->text('special_provisions')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'htr_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('product_id', 'htr_product_fk')
                ->references('id')->on('products')->onDelete('cascade');

            $table->index(['transport_mode', 'product_id'], 'htr_mode_product_idx');
        });

        Schema::create('product_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('certification_name'); // Halal, ISO 9001, Organic, CE, FDA
            $table->string('certification_body')->nullable(); // Issuing authority
            $table->string('certificate_number')->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('certificate_file_path')->nullable();
            $table->string('status', 20)->default('active'); // active, expired, pending, revoked
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['expiry_date']);
        });

        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size');
            $table->string('document_type', 30); // manual, datasheet, certificate, warranty, safety, brochure
            $table->string('language', 5)->default('en');
            $table->boolean('is_public')->default(true); // Visible to customers
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'document_type']);
        });

        Schema::create('product_hazmat_classifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('hazmat_classification_id');
            $table->unsignedBigInteger('storage_class_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('product_id', 'phc_product_fk')
                ->references('id')->on('products')->onDelete('cascade');
            $table->foreign('hazmat_classification_id', 'phc_classification_fk')
                ->references('id')->on('hazmat_classifications')->onDelete('cascade');
            $table->foreign('storage_class_id', 'phc_storage_class_fk')
                ->references('id')->on('hazmat_storage_classes')->onDelete('set null');
        });

        Schema::create('product_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('relation_type', 20); // related, cross_sell, up_sell, accessory, spare_part, substitute, frequently_bought
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id', 'relation_type'], 'prod_rels_prod_related_type_unique');
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('reviewer_name');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('review_text')->nullable();
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, flagged
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status', 'rating']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('spec_group')->nullable(); // "Physical", "Technical", "Packaging"
            $table->string('spec_name'); // "Weight", "Dimensions", "Color"
            $table->text('spec_value'); // "500g", "10x20x5 cm"
            $table->string('unit')->nullable(); // "kg", "cm", "ml"
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'spec_group']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('barcode', 50)->nullable();
            $table->string('name', 200);
            $table->json('attributes'); // e.g., {"size": "XL", "color": "Red"}
            $table->decimal('purchase_price', 18, 4)->nullable(); // Override parent
            $table->decimal('selling_price', 18, 4)->nullable(); // Override parent
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'sku']);
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 50);
            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 100)->nullable(); // For serialized items
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('received_date');
            $table->decimal('quantity', 15, 4);
            $table->decimal('reserved_quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4);
            $table->string('status', 20)->default('available'); // available, reserved, expired, damaged, quarantine
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('grn_number', 50)->nullable(); // Goods Receipt Note reference
            $table->json('metadata')->nullable(); // Additional batch attributes
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'warehouse_id', 'batch_number'], 'inv_batches_org_prod_wh_batch_unique');
            $table->index(['organization_id', 'expiry_date']);
            $table->index('serial_number');

            $table->unsignedBigInteger('batch_class_id')->nullable();
            $table->foreign('batch_class_id', 'ib_class_fk')->references('id')->on('batch_classes');
        });

        Schema::create('batch_characteristic_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'bcv_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('inventory_batch_id');
            $table->foreign('inventory_batch_id', 'bcv_batch_fk')->references('id')->on('inventory_batches');
            $table->unsignedBigInteger('batch_characteristic_id');
            $table->foreign('batch_characteristic_id', 'bcv_char_fk')->references('id')->on('batch_characteristics');
            $table->string('text_value', 255)->nullable();
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->date('date_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->timestamps();

            $table->unique(['inventory_batch_id', 'batch_characteristic_id'], 'bcv_batch_char_unq');
        });

        Schema::create('batch_where_used_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('inventory_batch_id');
            $table->foreign('inventory_batch_id', 'bwu_batch_fk')->references('id')->on('inventory_batches');
            $table->enum('usage_type', ['work_order', 'process_order', 'sales_invoice', 'stock_transfer', 'adjustment'])->default('work_order');
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_number', 100)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'bwu_product_fk')->references('id')->on('products');
            $table->decimal('quantity_used', 18, 4);
            $table->dateTime('used_at');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'bwu_warehouse_fk')->references('id')->on('warehouses');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by', 'bwu_recorded_by_fk')->references('id')->on('users');
            $table->timestamps();

            $table->index(['inventory_batch_id'], 'bwu_batch_idx');
            $table->index(['usage_type', 'reference_id'], 'bwu_ref_idx');
            $table->index(['organization_id', 'used_at'], 'bwu_org_used_at_idx');
        });

        Schema::create('goods_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_issue_id');
            $table->foreign('goods_issue_id')->references('id')->on('goods_issues')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products');

            $table->unsignedBigInteger('variant_id')->nullable();
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table->unsignedBigInteger('location_id')->nullable();
            $table->foreign('location_id')->references('id')->on('warehouse_locations')->nullOnDelete();

            $table->unsignedBigInteger('batch_id')->nullable();
            $table->foreign('batch_id')->references('id')->on('inventory_batches')->nullOnDelete();

            $table->decimal('quantity', 10, 4);

            $table->unsignedBigInteger('unit_id')->nullable();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete();

            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);

            $table->string('serial_number')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index('goods_issue_id');
            $table->index('product_id');
        });

        Schema::create('physical_inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('physical_inventory_documents')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('book_quantity', 15, 4);
            $table->decimal('counted_quantity', 15, 4)->nullable();
            $table->decimal('difference_quantity', 15, 4)->nullable();
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('difference_value', 15, 4)->nullable();
            $table->enum('adjustment_status', ['pending', 'adjusted', 'skipped'])->default('pending');
            $table->timestamps();
            $table->index(['document_id'], 'pil_doc_idx');
            $table->index(['product_id', 'adjustment_status'], 'pil_product_adj_idx');
        });

        Schema::create('picking_list_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_list_id')->constrained('picking_lists')->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('required_quantity', 12, 4);
            $table->decimal('picked_quantity', 12, 4)->default(0);
            $table->enum('status', ['pending', 'partial', 'completed', 'skipped'])->default('pending');
            $table->timestamp('picked_at')->nullable();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['picking_list_id', 'status']);
            $table->index('product_id');
        });

        Schema::create('price_check_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('price_check_stations')->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            // Scan details
            $table->string('scan_type', 20); // barcode, qr, rfid, nfc, manual, sku
            $table->string('scan_value', 255); // What was scanned
            $table->boolean('scan_successful')->default(true);

            // Product found
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('product_sku')->nullable();

            // Price shown
            $table->decimal('displayed_price', 15, 4)->nullable();
            $table->decimal('original_price', 15, 4)->nullable(); // Before promotion
            $table->string('currency_code', 3)->nullable();
            $table->boolean('has_promotion')->default(false);
            $table->string('promotion_name')->nullable();
            $table->decimal('promotion_discount', 15, 2)->nullable();

            // Customer context
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('loyalty_tier')->nullable();

            // Stock info
            $table->decimal('stock_available', 15, 4)->nullable();
            $table->string('stock_status', 20)->nullable(); // in_stock, low_stock, out_of_stock

            // Error handling
            $table->string('error_type', 30)->nullable(); // not_found, inactive, no_price, scan_error
            $table->text('error_message')->nullable();

            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['organization_id', 'scanned_at']);
            $table->index(['branch_id', 'scanned_at']);
            $table->index(['product_id', 'scanned_at']);
            $table->index(['scan_value']);
            $table->index(['station_id', 'scanned_at']);
            $table->index(['error_type']); // Track scan failures
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('barcode_value', 100);
            $table->string('barcode_type', 20)->default('code128'); // ean13, ean8, upc, code128, qr
            $table->string('usage', 30)->default('product'); // product, unit, pack
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->decimal('quantity', 15, 4)->default(1);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'barcode_value']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['product_id', 'is_primary']);



                    $table->string('barcode_image_path')->nullable();



                    $table->string('gtin', 14)->nullable();


                    $table->string('gs1_company_prefix', 12)->nullable();


                // Earlier migration uses product_variant_id; model uses variant_id, so add it if missing

                    $table->unsignedBigInteger('variant_id')->nullable();

                // Add batch_id without foreign key constraint (table may be inventory_batches, not product_batches)

                    $table->unsignedBigInteger('batch_id')->nullable();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('image_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('image_type', 20)->default('gallery'); // gallery, thumbnail, cover, swatch, zoom, lifestyle
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_primary']);
            $table->index(['variant_id']);
        });

        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('price_type', 30); // selling_price, purchase_price, mrp, wholesale, special
            $table->decimal('old_price', 15, 4);
            $table->decimal('new_price', 15, 4);
            $table->decimal('change_percent', 8, 2);
            $table->string('currency_code', 3);
            $table->string('reason')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'price_type', 'effective_from']);
        });

        Schema::create('product_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title');
            $table->string('video_type', 20); // uploaded, youtube, vimeo, external
            $table->string('video_url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id']);
        });

        Schema::create('putaway_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('warehouse_zone')->nullable();
            $table->foreignId('preferred_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->tinyInteger('priority')->default(10)->comment('Lower number = higher priority');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'warehouse_id']);
        });

        Schema::create('safety_data_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sds_number', 50);
            $table->string('version', 20);
            $table->date('revision_date');
            $table->string('language_code', 5)->default('en');
            $table->string('supplier_name', 150)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'sds_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('product_id', 'sds_product_fk')
                ->references('id')->on('products')->onDelete('cascade');

            $table->index(['product_id', 'is_current'], 'sds_product_current_idx');
        });

        Schema::create('safety_data_sheet_sections', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('safety_data_sheet_id');
            $table->tinyInteger('section_number');
            $table->string('section_title', 100);
            $table->longText('content');
            $table->timestamps();

            $table->foreign('safety_data_sheet_id', 'sdss_sds_fk')
                ->references('id')->on('safety_data_sheets')->onDelete('cascade');
        });

        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('serial_number', 100);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->enum('status', ['in_stock', 'sold', 'returned', 'scrapped', 'in_transit'])->default('in_stock');
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('sold_to_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('current_document_type', 50)->nullable();
            $table->unsignedBigInteger('current_document_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'product_id', 'serial_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'warehouse_id']);
        });

        Schema::create('serial_number_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('serial_number_id')->constrained('serial_numbers')->cascadeOnDelete();
            $table->enum('movement_type', ['receipt', 'issue', 'transfer', 'return', 'scrap']);
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('document_type', 50)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'serial_number_id']);
            $table->index(['organization_id', 'movement_type']);
        });

        Schema::create('shelf_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Label content
            $table->string('product_name');
            $table->string('sku');
            $table->string('barcode_value')->nullable();
            $table->decimal('price', 15, 4);
            $table->decimal('compare_at_price', 15, 4)->nullable(); // Strikethrough price
            $table->string('currency_code', 3);
            $table->string('unit_label')->nullable(); // "per kg", "each", "per pack"
            $table->decimal('price_per_unit', 15, 4)->nullable(); // Price per base unit (per 100g, per liter)
            $table->string('unit_measure_label')->nullable(); // "per 100g"

            // Location
            $table->string('aisle')->nullable();
            $table->string('shelf')->nullable();
            $table->string('position')->nullable();

            // Label type
            $table->string('label_type', 20)->default('standard'); // standard, promotional, clearance, new_arrival, organic, halal
            $table->string('label_size', 20)->default('standard'); // small, standard, large, shelf_strip

            // Digital label (ESL - Electronic Shelf Label)
            $table->boolean('is_digital')->default(false);
            $table->string('esl_device_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            // Print status
            $table->boolean('needs_reprint')->default(false);
            $table->timestamp('last_printed_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'branch_id']);
            $table->index(['product_id']);
            $table->index(['barcode_value']);
            $table->index(['needs_reprint']);
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations');

            $table->decimal('system_quantity', 18, 4); // Before adjustment
            $table->decimal('actual_quantity', 18, 4); // After count
            $table->decimal('difference', 18, 4); // Computed difference
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('stock_adjustment_id', 'sal_adjustment_id_idx');
        });

        Schema::create('stock_level_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('quantity_on_hand', 20, 4)->default(0);
            $table->decimal('quantity_reserved', 20, 4)->default(0);
            $table->decimal('quantity_available', 20, 4)->default(0);
            $table->decimal('reorder_point', 20, 4)->default(0);
            $table->boolean('is_low_stock')->default(false);
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['organization_id', 'product_id', 'warehouse_id'], 'sls_org_product_warehouse_unique');
            $table->index(['organization_id', 'is_low_stock'], 'sls_org_low_stock_index');
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0); // Reserved for orders
            $table->decimal('available_quantity', 18, 4)->default(0);

            // Costing
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->decimal('last_purchase_price', 18, 4)->nullable();
            $table->decimal('total_value', 18, 4)->default(0);

            // Reorder
            $table->decimal('reorder_level', 18, 4)->nullable();
            $table->decimal('reorder_quantity', 18, 4)->nullable();
            $table->decimal('maximum_stock', 18, 4)->nullable();

            $table->timestamp('last_count_date')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'variant_id', 'warehouse_id', 'location_id'], 'stock_level_unique');
            $table->index(['organization_id', 'warehouse_id']);
            $table->index(['product_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

            // Movement type
            $table->enum('movement_type', [
                'purchase',      // Goods received from purchase
                'sale',          // Goods sold
                'transfer_in',   // Received from another warehouse
                'transfer_out',  // Sent to another warehouse
                'adjustment',    // Manual adjustment
                'return_in',     // Customer return
                'return_out',    // Return to supplier
                'production_in', // Finished goods from manufacturing
                'production_out',// Raw materials consumed
                'opening',       // Opening balance
            ]);

            // Direction and quantity
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);

            // Running balance after this movement
            $table->decimal('balance_after', 18, 4);

            // Source document reference
            $table->string('reference_type', 50)->nullable(); // invoice, bill, transfer, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 50)->nullable();

            // For transfers
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'product_id']);
            $table->index(['organization_id', 'warehouse_id']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');

            $table->decimal('quantity_sent', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('stock_transfer_id', 'stl_transfer_id_idx');
        });

        Schema::create('warehouse_transfer_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_order_id')->constrained('warehouse_transfer_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('dest_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->decimal('requested_quantity', 15, 4);
            $table->decimal('transferred_quantity', 15, 4)->default(0);
            $table->enum('status', ['open', 'partially_transferred', 'transferred', 'cancelled'])->default('open');
            $table->timestamps();
            $table->index(['transfer_order_id'], 'wtoi_to_idx');
        });

        Schema::create('transfer_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('from_profit_center_id')->nullable();
            $table->unsignedBigInteger('to_profit_center_id')->nullable();
            $table->unsignedBigInteger('from_cost_center_id')->nullable();
            $table->unsignedBigInteger('to_cost_center_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('cost_element_id')->nullable();
            $table->string('transfer_price_method', 30)->default('standard_cost');
            $table->decimal('base_price', 18, 4);
            $table->decimal('markup_percentage', 8, 4)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'tp_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('from_profit_center_id', 'tp_from_pc_fk')
                ->references('id')->on('profit_centers')->onDelete('set null');
            $table->foreign('to_profit_center_id', 'tp_to_pc_fk')
                ->references('id')->on('profit_centers')->onDelete('set null');
            $table->foreign('from_cost_center_id', 'tp_from_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('to_cost_center_id', 'tp_to_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('product_id', 'tp_product_fk')
                ->references('id')->on('products')->onDelete('set null');
            $table->foreign('cost_element_id', 'tp_cost_elem_fk')
                ->references('id')->on('cost_elements')->onDelete('set null');

            $table->index(['from_profit_center_id', 'to_profit_center_id'], 'tp_pc_idx');
            $table->index(['product_id', 'is_active'], 'tp_product_active_idx');
            $table->index(['effective_from', 'effective_to'], 'tp_effectivity_idx');
        });

        Schema::create('transfer_price_conditions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('transfer_price_id');
            $table->unsignedBigInteger('version_id');
            $table->string('condition_type', 30);
            $table->decimal('amount', 18, 4);
            $table->boolean('is_percentage')->default(false);
            $table->timestamps();

            $table->foreign('transfer_price_id', 'tpc_tp_fk')
                ->references('id')->on('transfer_prices')->onDelete('cascade');
            $table->foreign('version_id', 'tpc_version_fk')
                ->references('id')->on('transfer_price_versions')->onDelete('cascade');
        });

        Schema::create('transfer_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_price_id');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->decimal('old_price', 18, 4);
            $table->decimal('new_price', 18, 4);
            $table->text('change_reason')->nullable();
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->foreign('transfer_price_id', 'tph_tp_fk')
                ->references('id')->on('transfer_prices')->onDelete('cascade');
            $table->foreign('changed_by', 'tph_user_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_price_history');
        Schema::dropIfExists('transfer_price_conditions');
        Schema::dropIfExists('transfer_prices');
        Schema::dropIfExists('warehouse_transfer_order_items');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_level_snapshots');
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('shelf_labels');
        Schema::dropIfExists('serial_number_movements');
        Schema::dropIfExists('serial_numbers');
        Schema::dropIfExists('safety_data_sheet_sections');
        Schema::dropIfExists('safety_data_sheets');
        Schema::dropIfExists('putaway_rules');
        Schema::dropIfExists('product_videos');
        Schema::dropIfExists('product_price_history');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('price_check_logs');
        Schema::dropIfExists('picking_list_lines');
        Schema::dropIfExists('physical_inventory_lines');
        Schema::dropIfExists('goods_issue_lines');
        Schema::dropIfExists('batch_where_used_records');
        Schema::dropIfExists('batch_characteristic_values');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_specifications');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_hazmat_classifications');
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('product_certifications');
        Schema::dropIfExists('hazmat_transport_regulations');
        Schema::dropIfExists('ewm_labor_tasks');
        Schema::dropIfExists('ewm_transfer_orders');
        Schema::dropIfExists('cycle_count_lines');
        Schema::dropIfExists('cross_docking_order_lines');
        Schema::dropIfExists('products');
    }
};
