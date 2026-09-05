<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atp_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('source_document_id');
            $table->string('source_document_type', 30);
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('requested_quantity', 15, 4);
            $table->decimal('confirmed_quantity', 15, 4)->default(0);
            $table->date('requested_date');
            $table->date('confirmed_date')->nullable();
            $table->json('availability_breakdown')->nullable();
            $table->enum('result', ['full', 'partial', 'none'])->default('none');
            $table->timestamps();
            $table->index(['source_document_type', 'source_document_id'], 'atp_doc_idx');
            $table->index(['organization_id', 'product_id'], 'atp_org_product_idx');
        });

        Schema::create('bulk_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('bulk_sale_batches')->cascadeOnDelete();
            $table->unsignedInteger('line_number');

            // Customer
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_tax_number')->nullable();

            // Sale details (can be simplified or use product)
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            // Payment
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, paid, partial
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('payment_reference')->nullable();

            // Processing status
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, skipped
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable(); // payments_received
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['batch_id', 'status']);

            $table->foreign('payment_id', 'bulk_sale_item_payment_fk')
                ->references('id')->on('payments_received')->nullOnDelete();
        });

        Schema::create('cash_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cash_sale_id');
            $table->foreign('cash_sale_id', 'cs_line_fk')->references('id')->on('cash_sales')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'cs_line_prod_fk')->references('id')->on('products')->onDelete('restrict');
            $table->decimal('quantity', 18, 4);
            $table->string('uom', 20);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_pct', 8, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('consignment_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('consignment_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')->constrained('units_of_measure');
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 4)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('notes', 200)->nullable();
            $table->timestamps();

            $table->index(['order_id'], 'col_order_idx');
        });

        Schema::create('consignment_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('on_hand_quantity', 15, 4)->default(0);
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'contact_id', 'product_id', 'variant_id', 'warehouse_id'],
                'cs_org_contact_prod_variant_wh_unique'
            );
            $table->index(['organization_id', 'contact_id'], 'cs_org_contact_idx');
        });

        Schema::create('consignment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_stock_id')->constrained('consignment_stocks')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('consignment_orders')->cascadeOnDelete();
            $table->enum('movement_type', ['in', 'out']);
            $table->decimal('quantity', 15, 4);
            $table->decimal('balance_after', 15, 4);
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['consignment_stock_id'], 'cm_stock_idx');
            $table->index(['order_id'], 'cm_order_idx');
        });

        Schema::create('cpq_configurable_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_price', 18, 4)->default(0);
            $table->string('currency_code', 3);
            $table->integer('configuration_validity_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'cpq_prod_org_active_idx');
        });

        Schema::create('cpq_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('configuration_code', 30);
            $table->string('status', 20)->default('draft'); // draft|valid|expired|converted
            $table->decimal('total_price', 18, 4);
            $table->string('currency_code', 3);
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contact_id', 'status'], 'cpq_cfg_contact_status_idx');
            $table->index(['status', 'valid_until'], 'cpq_cfg_status_valid_idx');
        });

        Schema::create('cpq_option_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->string('group_code', 30);
            $table->string('name');
            $table->string('selection_type', 20)->default('single'); // single|multi
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cpq_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_option_group_id')
                ->constrained('cpq_option_groups')
                ->cascadeOnDelete();
            $table->string('option_code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('price_modifier_type', 20)->default('none'); // fixed|percentage|none
            $table->decimal('price_modifier_value', 18, 4)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('linked_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cpq_configuration_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configuration_id')
                ->constrained('cpq_configurations')
                ->cascadeOnDelete();
            $table->foreignId('cpq_option_group_id')
                ->constrained('cpq_option_groups')
                ->cascadeOnDelete();
            $table->foreignId('cpq_option_id')
                ->constrained('cpq_options')
                ->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_total', 18, 4);
            $table->timestamps();
        });

        Schema::create('cpq_constraint_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->string('rule_type', 20); // requires|excludes|includes
            $table->foreignId('if_option_id')
                ->nullable()
                ->constrained('cpq_options')
                ->nullOnDelete();
            $table->foreignId('then_option_id')
                ->nullable()
                ->constrained('cpq_options')
                ->nullOnDelete();
            $table->string('error_message', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cpq_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('cpq_configurable_product_id')
                ->constrained('cpq_configurable_products')
                ->cascadeOnDelete();
            $table->string('rule_name');
            $table->json('condition_json');
            $table->string('discount_type', 20); // percentage|fixed|price_override
            $table->decimal('discount_value', 18, 4);
            $table->integer('priority')->default(50);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_material_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('customer_material_number', 100)->nullable();
            $table->string('customer_material_description', 255)->nullable();
            $table->integer('delivery_lead_time_days')->default(0);
            $table->decimal('minimum_order_quantity', 15, 4)->nullable();
            $table->string('unit_of_measure', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'contact_id', 'product_id'], 'cmi_org_contact_product_unique');
            $table->index(['contact_id', 'customer_material_number'], 'cmi_contact_mat_num_idx');
        });

        Schema::create('debit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_note_id')->constrained('debit_notes')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();

            $table->index('debit_note_id', 'debit_note_items_dn_id_idx');
        });

        Schema::create('exchange_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_order_id')->constrained('exchange_orders')->cascadeOnDelete();
            $table->foreignId('original_product_id')->constrained('products')->cascadeOnDelete(); // What was returned
            $table->foreignId('replacement_product_id')->constrained('products')->cascadeOnDelete(); // What they get
            $table->foreignId('replacement_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('original_quantity', 15, 4);
            $table->decimal('replacement_quantity', 15, 4);
            $table->decimal('original_unit_price', 15, 4);
            $table->decimal('replacement_unit_price', 15, 4);
            $table->decimal('price_difference', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['exchange_order_id']);
        });

        Schema::create('free_goods_conditions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('condition_number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id', 'fg_cond_cust_fk')->references('id')->on('contacts')->onDelete('set null');
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->foreign('customer_group_id', 'fg_cond_cg_fk')->references('id')->on('customer_groups')->onDelete('set null');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'fg_cond_prod_fk')->references('id')->on('products')->onDelete('cascade');
            $table->unsignedBigInteger('free_product_id')->nullable();
            $table->foreign('free_product_id', 'fg_cond_free_prod_fk')->references('id')->on('products')->onDelete('set null');
            $table->enum('free_goods_type', ['inclusive', 'exclusive'])->default('exclusive');
            $table->decimal('minimum_quantity', 18, 4);
            $table->decimal('free_quantity', 18, 4);
            $table->enum('calculation_type', ['quantity', 'percentage'])->default('quantity');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('intercompany_sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('intercompany_sales_order_id');
            $table->foreign('intercompany_sales_order_id', 'icsol_order_fk')
                ->references('id')->on('intercompany_sales_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'icsol_product_fk')
                ->references('id')->on('products')->restrictOnDelete();

            $table->unsignedSmallInteger('line_number');
            $table->string('description')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('unit_of_measure', 20)->nullable();
            $table->decimal('transfer_price', 18, 4);
            $table->decimal('list_price', 18, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->decimal('delivered_quantity', 18, 4)->default(0);
            $table->decimal('billed_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['intercompany_sales_order_id'], 'icsol_order_idx');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // Product reference
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->text('description');

            // Quantity and pricing
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('unit_price', 18, 4);

            // Discount
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            // Tax
            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->string('tax_code', 10)->nullable(); // S, Z, E, O

            // GST split (for India)
            $table->decimal('cgst_rate', 8, 4)->default(0);
            $table->decimal('cgst_amount', 18, 4)->default(0);
            $table->decimal('sgst_rate', 8, 4)->default(0);
            $table->decimal('sgst_amount', 18, 4)->default(0);
            $table->decimal('igst_rate', 8, 4)->default(0);
            $table->decimal('igst_amount', 18, 4)->default(0);
            $table->string('hsn_code', 20)->nullable();

            // Totals
            $table->decimal('subtotal', 18, 4)->default(0); // Before tax
            $table->decimal('total', 18, 4)->default(0); // After tax

            // Accounting
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('line_order')->default(0);
            $table->timestamps();

            $table->index('invoice_id');

            $table->decimal('original_price', 15, 4)->nullable();
            $table->boolean('price_overridden')->default(false);
            $table->string('override_reason', 100)->nullable();
            $table->foreignId('override_approved_by')->nullable();


            // VATEX-SA-* exemption reason codes (e.g. VATEX-SA-29-7, VATEX-SA-HEA)
            $table->string('tax_exemption_code', 30)->nullable();
            // Human-readable reason text required alongside the code
            $table->string('tax_exemption_reason', 255)->nullable();


            $table->index('product_id');
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('original_invoice_line_id')->nullable(); // Link to original invoice line
            $table->text('description');
            $table->decimal('quantity', 15, 4);
            $table->foreignId('unit_id')->nullable();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('tax_code', 10)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->unsignedTinyInteger('line_order')->default(0);
            $table->timestamps();

            $table->index(['credit_note_id', 'line_order']);

            $table->foreign('original_invoice_line_id', 'cn_item_orig_inv_line_fk')
                ->references('id')->on('invoice_lines')->nullOnDelete();
            $table->foreign('unit_id', 'cn_item_unit_fk')
                ->references('id')->on('units_of_measure')->nullOnDelete();
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('min_quantity', 15, 4)->default(1); // For bulk pricing tiers
            $table->decimal('max_quantity', 15, 4)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id', 'min_quantity']);
            $table->index(['product_id', 'min_quantity']);
        });

        Schema::create('price_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Source document
            $table->string('document_type', 100)->nullable(); // Invoice, Quotation, SalesOrder, Bill, PurchaseOrder
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('line_item_id')->nullable(); // The specific line item

            // Product
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Prices
            $table->decimal('original_price', 15, 4)->default(0); // List/catalog price
            $table->decimal('override_price', 15, 4)->default(0); // New price set at billing
            $table->decimal('cost_price', 15, 4)->nullable(); // Product cost (for margin check)
            $table->decimal('price_difference', 15, 4)->default(0); // override - original
            $table->decimal('discount_percent', 5, 2)->default(0); // % change
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('total_impact', 15, 2)->default(0); // Total monetary impact

            // Override type
            $table->string('override_type', 30)->nullable(); // discount, markup, custom_price, price_match, negotiated, manager_override
            $table->string('reason_code', 30)->nullable(); // competitor_match, bulk_order, loyalty, damaged, negotiated, clearance
            $table->text('reason')->nullable(); // Free-text reason
            $table->text('notes')->nullable();

            // Approval
            $table->string('approval_status', 20)->default('auto_approved'); // auto_approved, pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Reference
            $table->foreignId('policy_id')->nullable()->constrained('price_override_policies')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Margin impact
            $table->decimal('margin_before', 5, 2)->nullable(); // Margin % at original price
            $table->decimal('margin_after', 5, 2)->nullable(); // Margin % at override price

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['document_type', 'document_id']);
            $table->index(['product_id']);
            $table->index(['created_by', 'created_at']);
            $table->index(['approval_status']);
            $table->index(['override_type']);
        });

        Schema::create('price_volume_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('min_qty', 15, 4);
            $table->decimal('max_qty', 15, 4)->nullable();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['price_list_id', 'product_id'], 'pvb_list_product_idx');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 15, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable(); // For multi_select
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('product_bundles')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->text('description')->nullable();
            $table->decimal('original_price', 15, 4)->nullable(); // Individual item price
            $table->decimal('unit_price', 15, 4)->nullable(); // Unit price alias
            $table->decimal('bundle_price', 15, 4)->nullable(); // Override price in bundle
            $table->decimal('discount_percentage', 8, 4)->nullable()->default(0); // Discount percentage
            $table->boolean('is_optional')->default(false); // Customer can choose to exclude
            $table->boolean('is_default_selected')->default(true); // Pre-selected for optional items
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['bundle_id', 'display_order']);
        });

        Schema::create('product_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('product_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'tag_id']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('bill_item_id')->nullable();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('description')->nullable();

            $table->decimal('quantity_returned', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);

            $table->string('condition', 30)->nullable(); // defective, damaged, wrong_item, quality_issue
            $table->text('condition_notes')->nullable();
            $table->string('item_status', 20)->default('pending');

            $table->timestamps();

            $table->index(['purchase_return_id']);
            $table->index(['product_id']);

            $table->foreign('bill_item_id', 'pur_ret_item_bill_line_fk')
                ->references('id')->on('bill_lines')->nullOnDelete();
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();

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

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            $table->unsignedSmallInteger('line_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rma_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rma_request_id')->constrained('rma_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->string('reason', 50); // defective, wrong_item, not_as_described, damaged_in_transit, quality_issue
            $table->text('description')->nullable();
            $table->json('evidence_paths')->nullable(); // Photos/docs
            $table->timestamps();

            $table->index(['rma_request_id']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->text('description');

            $table->decimal('quantity', 18, 4);
            $table->decimal('quantity_delivered', 18, 4)->default(0);
            $table->decimal('quantity_invoiced', 18, 4)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('unit_price', 18, 4);

            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);

            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('line_order')->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_document_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_document_id');
            $table->foreign('delivery_document_id', 'del_doc_line_fk')->references('id')->on('delivery_documents')->onDelete('cascade');
            $table->unsignedBigInteger('sales_order_line_id')->nullable();
            $table->foreign('sales_order_line_id', 'del_doc_line_sol_fk')->references('id')->on('sales_order_lines')->onDelete('set null');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id', 'del_doc_line_prod_fk')->references('id')->on('products')->onDelete('restrict');
            $table->decimal('delivery_quantity', 18, 4);
            $table->decimal('picked_quantity', 18, 4)->default(0);
            $table->decimal('packed_quantity', 18, 4)->default(0);
            $table->decimal('issued_quantity', 18, 4)->default(0);
            $table->string('uom', 20);
            $table->string('batch_number')->nullable();
            $table->unsignedBigInteger('warehouse_location_id')->nullable();
            $table->foreign('warehouse_location_id', 'del_doc_line_wloc_fk')->references('id')->on('warehouse_locations')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('pick_document_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pick_document_id');
            $table->foreign('pick_document_id', 'pick_doc_line_fk')->references('id')->on('pick_documents')->onDelete('cascade');
            $table->unsignedBigInteger('delivery_document_line_id');
            $table->foreign('delivery_document_line_id', 'pick_doc_line_dl_fk')->references('id')->on('delivery_document_lines')->onDelete('cascade');
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('picked_quantity', 18, 4)->default(0);
            $table->string('storage_bin')->nullable();
            $table->enum('status', ['open', 'partial', 'completed'])->default('open');
            $table->timestamps();
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable(); // Link to original invoice item
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->string('description')->nullable();

            $table->decimal('quantity_returned', 15, 4);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->decimal('quantity_restocked', 15, 4)->default(0);
            $table->decimal('quantity_damaged', 15, 4)->default(0);

            $table->decimal('unit_price', 15, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);

            $table->string('condition', 30)->nullable(); // new, like_new, used, damaged, defective
            $table->text('condition_notes')->nullable();

            $table->string('item_status', 20)->default('pending'); // pending, received, inspected, restocked, disposed
            $table->foreignId('warehouse_location_id')->nullable();

            $table->timestamps();

            $table->index(['sales_return_id']);
            $table->index(['product_id']);

            $table->foreign('invoice_item_id', 'sales_ret_item_inv_line_fk')
                ->references('id')->on('invoice_lines')->nullOnDelete();
            $table->foreign('warehouse_location_id', 'sales_ret_item_wh_location_fk')
                ->references('id')->on('warehouse_locations')->nullOnDelete();
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->string('serial_numbers')->nullable();
            $table->timestamps();

            $table->index(['shipment_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('pick_document_lines');
        Schema::dropIfExists('delivery_document_lines');
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('rma_items');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('product_tag_assignments');
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('price_volume_breaks');
        Schema::dropIfExists('price_overrides');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('intercompany_sales_order_lines');
        Schema::dropIfExists('free_goods_conditions');
        Schema::dropIfExists('exchange_order_items');
        Schema::dropIfExists('debit_note_items');
        Schema::dropIfExists('customer_material_infos');
        Schema::dropIfExists('cpq_pricing_rules');
        Schema::dropIfExists('cpq_constraint_rules');
        Schema::dropIfExists('cpq_configuration_items');
        Schema::dropIfExists('cpq_options');
        Schema::dropIfExists('cpq_option_groups');
        Schema::dropIfExists('cpq_configurations');
        Schema::dropIfExists('cpq_configurable_products');
        Schema::dropIfExists('consignment_movements');
        Schema::dropIfExists('consignment_stocks');
        Schema::dropIfExists('consignment_order_lines');
        Schema::dropIfExists('cash_sale_lines');
        Schema::dropIfExists('bulk_sale_items');
        Schema::dropIfExists('atp_checks');
    }
};
