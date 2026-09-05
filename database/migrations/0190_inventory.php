<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_classes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('class_code', 30);
            $table->string('class_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'class_code'], 'bc_org_code_unq');
        });

        Schema::create('batch_characteristics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'bchar_org_fk')->references('id')->on('organizations');
            $table->unsignedBigInteger('batch_class_id');
            $table->foreign('batch_class_id', 'bchar_class_fk')->references('id')->on('batch_classes');
            $table->string('characteristic_code', 30);
            $table->string('characteristic_name', 100);
            $table->enum('data_type', ['text', 'numeric', 'date', 'boolean'])->default('text');
            $table->string('unit_of_measure', 20)->nullable();
            $table->boolean('is_required')->default(false);
            $table->decimal('min_value', 18, 4)->nullable();
            $table->decimal('max_value', 18, 4)->nullable();
            $table->json('allowed_values')->nullable();
            $table->timestamps();

            $table->unique(['batch_class_id', 'characteristic_code'], 'bchar_class_code_unq');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->unsignedInteger('level')->default(1);
            $table->string('path', 255)->nullable(); // e.g., "1.2.3" for hierarchy
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'parent_id']);
        });

        Schema::create('hazmat_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('classification_system', 30); // ghs/un/adr/iata
            $table->string('code', 20);
            $table->string('name');
            $table->string('hazard_class', 50);
            $table->string('packing_group', 5)->nullable(); // I/II/III
            $table->string('signal_word', 20)->nullable(); // danger/warning
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id', 'hc_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->index(['classification_system', 'code'], 'hc_system_code_idx');
        });

        Schema::create('hazmat_storage_classes', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('max_quantity_kg', 10, 2)->nullable();
            $table->boolean('requires_ventilation')->default(false);
            $table->boolean('requires_grounding')->default(false);
            $table->string('fire_resistance_class', 10)->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'hsc_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('hazmat_storage_compatibility_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('storage_class_a_id');
            $table->unsignedBigInteger('storage_class_b_id');
            $table->boolean('is_compatible');
            $table->text('restriction_notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'hscr_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('storage_class_a_id', 'hscr_sc_a_fk')
                ->references('id')->on('hazmat_storage_classes')->onDelete('cascade');
            $table->foreign('storage_class_b_id', 'hscr_sc_b_fk')
                ->references('id')->on('hazmat_storage_classes')->onDelete('cascade');
        });

        Schema::create('inventory_split_valuations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('valuation_type_id');

            // Stock quantities
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);

            // Valuation
            $table->string('valuation_method', 30)->default('moving_average'); // moving_average | standard
            $table->decimal('moving_average_price', 15, 6)->default(0);
            $table->decimal('standard_price', 15, 6)->default(0);
            $table->decimal('total_stock_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'warehouse_id', 'valuation_type_id'], 'inv_split_val_org_prod_wh_type_unique');
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('inventory_valuation_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->string('category_code', 50);   // e.g. "ORIG" – Origin-based split
            $table->string('category_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'category_code'], 'inv_val_cat_org_prod_code_unique');
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('inventory_valuation_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('valuation_category_id');
            $table->string('type_code', 50);        // e.g. "DOM", "IMP"
            $table->string('type_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['valuation_category_id', 'type_code']);
            $table->index(['organization_id', 'valuation_category_id'], 'inv_val_types_org_cat_idx');
        });

        Schema::create('qr_code_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50); // product, invoice, receipt, price_tag, shelf_label
            $table->string('name');

            // QR content format
            $table->string('content_type', 30); // url, json, vcard, text, custom
            $table->text('content_template'); // Template with {{placeholders}}
            $table->json('included_fields')->nullable(); // Which fields to include

            // Appearance
            $table->unsignedSmallInteger('size_px')->default(200);
            $table->string('foreground_color', 7)->default('#000000');
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('logo_path')->nullable(); // Center logo
            $table->string('error_correction', 1)->default('M'); // L, M, Q, H

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'entity_type']);
        });

        Schema::create('storage_type_determination_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('stdr_org_fk');
            $table->foreignId('storage_type_id')->constrained('storage_types')->name('stdr_st_fk');
            $table->foreignId('warehouse_id')->constrained('warehouses')->name('stdr_warehouse_fk');
            $table->enum('movement_type', ['goods_receipt', 'goods_issue', 'transfer', 'returns'])
                ->default('goods_receipt');
            $table->string('product_storage_class', 50)->nullable();
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->unsignedTinyInteger('priority')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['warehouse_id', 'movement_type'], 'stdr_wh_movement_idx');
        });

        Schema::create('storage_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('warehouse_id')->constrained('warehouses')->name('st_warehouse_fk');
            $table->string('storage_type_code', 20);
            $table->string('storage_type_name', 100);
            $table->enum('storage_class', [
                'bulk',
                'rack',
                'floor',
                'refrigerated',
                'hazmat',
                'high_security',
                'quarantine',
            ])->default('rack');
            $table->enum('capacity_management', [
                'no_check',
                'total_weight',
                'total_qty',
                'occupied_bins',
            ])->default('no_check');
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->decimal('max_quantity', 18, 4)->nullable();
            $table->unsignedInteger('total_bins')->nullable();
            $table->decimal('current_utilization_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'storage_type_code'], 'st_warehouse_code_unq');
            $table->index(['organization_id', 'warehouse_id'], 'st_org_warehouse_idx');
        });

        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50); // e.g., "Kilogram", "Piece", "Box"
            $table->string('symbol', 10); // e.g., "kg", "pc", "box"
            $table->foreignId('base_unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('conversion_factor', 18, 8)->default(1); // How many base units
            $table->string('code', 20)->nullable(); // e.g., "KG", "PCS"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'symbol']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();

            // Warehouse manager
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_negative_stock')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('cross_docking_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('inbound_source_type', 30)
                ->comment('purchase_order/transfer_order/return');
            $table->unsignedBigInteger('inbound_source_id');
            $table->string('outbound_dest_type', 30)
                ->comment('sales_order/transfer_order/delivery');
            $table->unsignedBigInteger('outbound_dest_id');
            $table->dateTime('planned_date');
            $table->dateTime('actual_date')->nullable();
            $table->string('status', 20)->default('planned')
                ->comment('planned/in_progress/completed/cancelled');
            $table->unsignedBigInteger('dock_door_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'xdock_wh_status_idx');
            $table->index(['status', 'planned_date'], 'xdock_status_date_idx');
            $table->index(['inbound_source_type', 'inbound_source_id'], 'xdock_inbound_idx');
            $table->index(['outbound_dest_type', 'outbound_dest_id'], 'xdock_outbound_idx');
        });

        Schema::create('cycle_count_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('plan_name');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('count_frequency', ['A', 'B', 'C', 'custom']);
            $table->unsignedSmallInteger('products_per_day')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('warehouse_id', 'cc_plan_wh_fk')->references('id')->on('warehouses')->cascadeOnDelete();
        });

        Schema::create('cycle_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->date('session_date');
            $table->unsignedBigInteger('counted_by');
            $table->enum('status', ['open', 'in_progress', 'completed', 'posted'])->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('plan_id', 'cc_sess_plan_fk')->references('id')->on('cycle_count_plans')->nullOnDelete();
            $table->foreign('warehouse_id', 'cc_sess_wh_fk')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('counted_by', 'cc_sess_usr_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ewm_storage_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('code', 20);  // BLK, SHF, HBY, PLT, FRZ
            $table->string('name', 100);
            $table->enum('type', ['bulk', 'shelving', 'high_bay', 'pallet', 'freezer', 'hazmat', 'open_storage']);
            $table->boolean('allow_partial_putaway')->default(true);
            $table->boolean('mixed_storage')->default(false);
            $table->unsignedSmallInteger('max_weight_kg')->nullable();
            $table->enum('putaway_strategy', ['fifo', 'fefo', 'lifo', 'nearest_bin', 'fixed_bin', 'max_fill', 'open_storage'])->default('fifo');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
            $table->index(['organization_id', 'warehouse_id']);
        });

        Schema::create('ewm_putaway_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('storage_type_id')->nullable()->constrained('ewm_storage_types')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedSmallInteger('priority')->default(10);  // lower = higher priority
            $table->enum('strategy', ['fifo', 'fefo', 'lifo', 'nearest_bin', 'fixed_bin', 'max_fill']);
            $table->string('fixed_bin_code', 50)->nullable();  // for fixed-bin strategy
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'warehouse_id', 'priority']);
        });

        Schema::create('ewm_storage_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('storage_type_id')->constrained('ewm_storage_types')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->enum('velocity_class', ['A', 'B', 'C', 'D'])->default('B');  // A=fast-moving
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('ewm_bins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('storage_type_id')->constrained('ewm_storage_types')->restrictOnDelete();
            $table->foreignId('storage_section_id')->nullable()->constrained('ewm_storage_sections')->nullOnDelete();
            $table->string('bin_code', 50);       // A-01-01-01 (aisle-row-column-level)
            $table->string('aisle', 10)->nullable();
            $table->string('row_number', 10)->nullable();
            $table->string('column_number', 10)->nullable();
            $table->string('level', 10)->nullable();
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_volume_m3', 10, 4)->nullable();
            $table->decimal('current_weight_kg', 10, 2)->default(0);
            $table->decimal('fill_pct', 5, 2)->default(0);
            $table->enum('status', ['active', 'blocked', 'inactive', 'reserved'])->default('active');
            $table->boolean('mixed_products')->default(false);
            $table->unsignedBigInteger('current_product_id')->nullable();  // for single-product bins
            $table->timestamps();
            $table->unique(['warehouse_id', 'bin_code']);
            $table->index(['organization_id', 'warehouse_id', 'status']);
            $table->index(['warehouse_id', 'storage_type_id', 'fill_pct']);
        });

        Schema::create('goods_issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->string('gi_number')->unique();
            $table->date('gi_date');

            // Movement type: what triggered this goods issue
            $table->string('movement_type'); // sales_delivery, production_issue, scrapping, transfer, other

            // Polymorphic reference to the source document (Invoice, SalesOrder, WorkOrder, etc.)
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table->string('status')->default('draft'); // draft, posted, reversed

            $table->decimal('total_quantity', 10, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);

            $table->text('notes')->nullable();

            // Posting metadata
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();

            // Reversal metadata
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->foreign('reversed_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();

            // GL journal entry generated on posting
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'gi_date']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('physical_inventory_documents', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_number', 30);
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('count_date');
            $table->enum('inventory_type', ['full', 'cycle', 'spot'])->default('full');
            $table->enum('status', ['created', 'in_progress', 'counted', 'posted', 'cancelled'])->default('created');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'document_number'], 'pid_org_doc_unique');
            $table->index(['organization_id', 'status'], 'pid_org_status_idx');
            $table->index(['warehouse_id', 'count_date'], 'pid_wh_date_idx');
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            $table->string('adjustment_number', 50);
            $table->date('adjustment_date');
            $table->enum('reason', [
                'damage',
                'theft',
                'expiry',
                'count_correction',
                'opening_balance',
                'other',
            ]);
            $table->text('notes')->nullable();

            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'adjustment_number']);

            $table->index(['organization_id', 'status'], 'sa_org_status_idx');
            $table->index(['organization_id', 'warehouse_id'], 'sa_org_warehouse_idx');
            $table->index(['organization_id', 'adjustment_date'], 'sa_org_date_idx');
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('transfer_number', 50);
            $table->date('transfer_date');
            $table->date('expected_arrival_date')->nullable();

            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');

            $table->text('notes')->nullable();

            $table->enum('status', ['draft', 'in_transit', 'received', 'cancelled'])->default('draft');
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'transfer_number']);

            $table->index(['organization_id', 'status'], 'st_org_status_idx');
            $table->index(['organization_id', 'transfer_date'], 'st_org_date_idx');
            $table->index(['organization_id', 'from_warehouse_id'], 'st_org_from_wh_idx');
            $table->index(['organization_id', 'to_warehouse_id'], 'st_org_to_wh_idx');
        });

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('name', 50);
            $table->string('code', 20);
            $table->enum('type', ['zone', 'aisle', 'rack', 'shelf', 'bin'])->default('bin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('warehouse_transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('to_number', 30);
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('movement_type', ['goods_receipt', 'goods_issue', 'internal_transfer', 'replenishment'])->default('internal_transfer');
            $table->string('source_document_type', 50)->nullable();
            $table->string('source_document_ref', 50)->nullable();
            $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('dest_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->enum('status', ['created', 'in_progress', 'confirmed', 'cancelled'])->default('created');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'to_number'], 'wto_org_number_unique');
            $table->index(['warehouse_id', 'status'], 'wto_wh_status_idx');
        });

        Schema::create('wave_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('wave_number');
            $table->enum('wave_type', ['outbound', 'replenishment', 'returns'])->default('outbound');
            $table->enum('status', ['draft', 'released', 'picking', 'completed', 'cancelled'])->default('draft');
            $table->date('planned_date');
            $table->integer('total_orders')->default(0);
            $table->integer('total_lines')->default(0);
            $table->decimal('total_units', 12, 4)->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('picking_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('wave_plan_id')->nullable()->constrained('wave_plans')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('list_number');
            $table->foreignId('picker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'partial', 'cancelled'])->default('pending');
            $table->enum('picking_type', ['single_order', 'multi_order', 'zone', 'cluster'])->default('single_order');
            $table->integer('total_lines')->default(0);
            $table->integer('picked_lines')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['warehouse_id', 'picker_id']);
        });

        Schema::create('wave_plan_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wave_plan_id')->constrained('wave_plans')->cascadeOnDelete();
            $table->enum('order_type', ['sales_order', 'stock_transfer', 'purchase_return'])->default('sales_order');
            $table->unsignedBigInteger('order_id');
            $table->timestamps();

            $table->unique(['wave_plan_id', 'order_type', 'order_id']);
        });

        Schema::create('yard_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('zone_code', 20);
            $table->string('name', 100);
            $table->string('zone_type', 20)->default('staging')
                ->comment('staging/parking/inspection/dock');
            $table->unsignedInteger('capacity_vehicles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'is_active'], 'yard_zone_wh_active_idx');
            $table->unique(['warehouse_id', 'zone_code'], 'yard_zone_wh_code_uq');
        });

        Schema::create('dock_doors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('door_code', 10);
            $table->string('door_type', 20)->default('combined')
                ->comment('inbound/outbound/combined');
            $table->foreignId('yard_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('available')
                ->comment('available/occupied/maintenance');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'dock_door_wh_status_idx');
            $table->unique(['warehouse_id', 'door_code'], 'dock_door_wh_code_uq');
        });

        Schema::create('dim_warehouse', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'dim_warehouse_wh_id_fk')
                ->references('id')->on('warehouses')->onDelete('set null');
            $table->string('warehouse_code', 30);
            $table->string('warehouse_name');
            $table->string('location', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id', 'dim_warehouse_org_id_idx');
        });

        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('currency_name', 50)->default('Points'); // Points, Stars, Miles, etc.
            $table->string('currency_symbol', 10)->default('pts');
            $table->decimal('point_value', 10, 4)->default(0.01); // Monetary value per point
            $table->decimal('earn_rate', 10, 4)->default(1); // Points per currency unit spent
            $table->unsignedInteger('min_redeem_points')->default(100);
            $table->unsignedInteger('points_expiry_days')->nullable(); // NULL = never expire
            $table->boolean('allow_partial_redeem')->default(true);
            $table->boolean('earn_on_tax')->default(false);
            $table->boolean('earn_on_shipping')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('customer_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('color', 7)->nullable();
            $table->string('icon')->nullable();

            // Qualification criteria
            $table->string('qualification_type', 30)->default('spending'); // spending, points, manual
            $table->decimal('min_spending', 15, 2)->default(0); // Min spending to qualify
            $table->unsignedInteger('min_points')->default(0); // Min points to qualify
            $table->unsignedSmallInteger('qualification_period_months')->nullable(); // Rolling period

            // Benefits
            $table->decimal('earn_rate_multiplier', 5, 2)->default(1.00); // 1.5x, 2x points
            $table->decimal('discount_percent', 5, 2)->default(0); // Auto discount on purchases
            $table->boolean('free_shipping')->default(false);
            $table->unsignedSmallInteger('priority_support_level')->default(0); // 0 = none
            $table->json('perks')->nullable(); // Additional tier benefits

            // Downgrade/upgrade
            $table->boolean('auto_upgrade')->default(true);
            $table->boolean('auto_downgrade')->default(true);
            $table->unsignedSmallInteger('grace_period_days')->default(30); // Before downgrade

            $table->unsignedSmallInteger('tier_level')->default(0); // 0 = base, 1, 2, 3...
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'tier_level']);
        });

        Schema::create('points_earning_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type', 50); // purchase, registration, birthday, referral, review, category_purchase, product_purchase
            $table->unsignedInteger('bonus_points')->default(0);
            $table->decimal('bonus_multiplier', 5, 2)->default(1.00); // 2x = double points
            $table->json('conditions')->nullable(); // Min amount, specific products/categories
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'trigger_type', 'is_active'], 'pts_rules_org_trigger_active_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('points_earning_rules');
        Schema::dropIfExists('customer_tiers');
        Schema::dropIfExists('loyalty_programs');
        Schema::dropIfExists('dim_warehouse');
        Schema::dropIfExists('dock_doors');
        Schema::dropIfExists('yard_zones');
        Schema::dropIfExists('wave_plan_orders');
        Schema::dropIfExists('picking_lists');
        Schema::dropIfExists('wave_plans');
        Schema::dropIfExists('warehouse_transfer_orders');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('physical_inventory_documents');
        Schema::dropIfExists('goods_issues');
        Schema::dropIfExists('ewm_bins');
        Schema::dropIfExists('ewm_storage_sections');
        Schema::dropIfExists('ewm_putaway_rules');
        Schema::dropIfExists('ewm_storage_types');
        Schema::dropIfExists('cycle_count_sessions');
        Schema::dropIfExists('cycle_count_plans');
        Schema::dropIfExists('cross_docking_orders');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('storage_types');
        Schema::dropIfExists('storage_type_determination_rules');
        Schema::dropIfExists('qr_code_configs');
        Schema::dropIfExists('inventory_valuation_types');
        Schema::dropIfExists('inventory_valuation_categories');
        Schema::dropIfExists('inventory_split_valuations');
        Schema::dropIfExists('hazmat_storage_compatibility_rules');
        Schema::dropIfExists('hazmat_storage_classes');
        Schema::dropIfExists('hazmat_classifications');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('batch_characteristics');
        Schema::dropIfExists('batch_classes');
    }
};
