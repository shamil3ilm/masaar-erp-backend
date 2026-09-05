<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backdated_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->morphs('transaction'); // invoice, payment, journal_entry, etc.
            $table->date('transaction_date'); // The backdated date used
            $table->date('entry_date'); // Actual date of entry
            $table->string('reason')->nullable(); // Reason for backdating
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'transaction_date']);
        });

        Schema::create('backorder_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete()->name('bor_so_fk');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete()->name('bor_sol_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('bor_product_fk');
            $table->decimal('original_quantity', 18, 4);
            $table->decimal('backordered_quantity', 18, 4);
            $table->decimal('fulfilled_quantity', 18, 4)->default(0);
            $table->enum('status', ['open', 'partially_fulfilled', 'fulfilled', 'cancelled'])->default('open');
            $table->date('original_delivery_date')->nullable();
            $table->date('rescheduled_delivery_date')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'bor_org_status_idx');
            $table->index(['organization_id', 'product_id'], 'bor_org_product_idx');
            $table->index(['rescheduled_delivery_date'], 'bor_reschedule_date_idx');
        });

        Schema::create('billing_plan_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('bpi_org_fk');
            $table->foreignId('billing_plan_id')->constrained('billing_plans')->cascadeOnDelete()->name('bpi_plan_fk');
            $table->string('milestone_description', 255)->nullable();
            $table->date('billing_date');
            $table->decimal('billing_percent', 5, 2)->nullable();
            $table->decimal('billing_amount', 18, 4);
            $table->enum('status', ['pending', 'billed', 'cancelled'])->default('pending');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->name('bpi_invoice_fk');
            $table->dateTime('billed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['billing_plan_id', 'billing_date'], 'bpi_plan_date_idx');
        });

        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete()->name('bp_so_fk');
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete()->name('bp_quot_fk');
            $table->enum('plan_type', ['milestone', 'periodic'])->default('milestone');
            $table->char('billing_currency', 3)->default('SAR');
            $table->decimal('total_value', 18, 4)->default(0);
            $table->decimal('billed_value', 18, 4)->default(0);
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('periodic_interval_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'sales_order_id'], 'bp_org_so_idx');
        });

        Schema::create('bulk_sale_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 30);
            $table->string('name')->nullable();
            $table->date('sale_date'); // Allows backdating
            $table->date('original_sale_date')->nullable(); // If different from entry date
            $table->string('currency_code', 3)->default('SAR');

            // Totals
            $table->unsignedInteger('total_invoices')->default(0);
            $table->decimal('total_subtotal', 15, 2)->default(0);
            $table->decimal('total_discount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Status
            $table->string('status', 20)->default('draft'); // draft, processing, completed, partially_completed, failed
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            // Processing
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Options
            $table->boolean('auto_post')->default(false);
            $table->boolean('auto_send_email')->default(false);
            $table->boolean('generate_receipts')->default(false);
            $table->string('payment_method')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'sale_date']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('commission_masters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('sales_rep_id');
            $table->foreign('sales_rep_id', 'comm_master_usr_fk')->references('id')->on('users')->onDelete('cascade');
            $table->string('commission_plan_name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('base_rate', 8, 4);
            $table->char('currency', 3)->default('SAR');
            $table->decimal('quota_amount', 18, 4)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('commission_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('payment_reference')->unique();
            $table->unsignedBigInteger('sales_rep_id');
            $table->foreign('sales_rep_id', 'comm_pay_usr_fk')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedSmallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('total_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->date('payment_date');
            $table->enum('status', ['pending', 'processed'])->default('pending');
            $table->unsignedBigInteger('payslip_id')->nullable();
            $table->foreign('payslip_id', 'comm_pay_payslip_fk')->references('id')->on('payslips')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('commission_master_id');
            $table->foreign('commission_master_id', 'comm_rule_master_fk')->references('id')->on('commission_masters')->onDelete('cascade');
            $table->enum('rule_type', ['flat', 'tiered', 'product_category', 'customer_group']);
            $table->string('condition_field')->nullable();
            $table->string('condition_value')->nullable();
            $table->decimal('rate', 8, 4);
            $table->decimal('tier_from', 18, 4)->nullable();
            $table->decimal('tier_to', 18, 4)->nullable();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->timestamps();
        });

        Schema::create('customer_account_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('group_code', 4)->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->decimal('default_discount_percent', 5, 2)->default(0);
            $table->decimal('credit_limit', 15, 4)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0); // 0 = cash
            $table->boolean('tax_exempt')->default(false);
            $table->boolean('wholesale')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0); // Higher = better pricing
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('delivery_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('type', 30); // pickup, standard, express, same_day, next_day, freight, digital, custom
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            // Pricing
            $table->string('pricing_type', 20)->default('flat'); // free, flat, weight_based, value_based, distance_based, custom
            $table->decimal('flat_rate', 15, 2)->default(0);
            $table->json('pricing_rules')->nullable(); // Complex pricing tiers

            // Delivery time
            $table->unsignedSmallInteger('min_delivery_days')->nullable();
            $table->unsignedSmallInteger('max_delivery_days')->nullable();
            $table->string('delivery_time_label')->nullable(); // "2-3 business days"

            // Free shipping threshold
            $table->decimal('free_shipping_min', 15, 2)->nullable();

            // Restrictions
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_value', 15, 2)->nullable();
            $table->json('supported_zones')->nullable(); // Delivery zone IDs
            $table->json('excluded_products')->nullable(); // Product IDs not eligible

            // Tracking
            $table->boolean('tracking_enabled')->default(false);
            $table->string('carrier_provider')->nullable(); // aramex, dhl, fedex, bluedart
            $table->json('carrier_config')->nullable();

            // Availability
            $table->json('available_days')->nullable(); // [1,2,3,4,5] Mon-Fri
            $table->string('cutoff_time', 5)->nullable(); // "14:00" for same-day
            $table->boolean('requires_address')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'type']);
        });

        Schema::create('delivery_split_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_name', 100);
            $table->enum('split_criteria', ['warehouse', 'delivery_date', 'route', 'weight', 'volume'])->default('warehouse');
            $table->enum('applies_to', ['all_customers', 'customer_group', 'specific_customer'])->default('all_customers');
            $table->unsignedBigInteger('applies_to_id')->nullable();
            $table->boolean('allow_partial_delivery')->default(true);
            $table->decimal('minimum_delivery_quantity_pct', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'is_active'], 'delivery_split_rules_org_active_idx');
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->json('countries')->nullable(); // Country codes
            $table->json('states')->nullable(); // State codes
            $table->json('cities')->nullable();
            $table->json('postal_codes')->nullable(); // Ranges or specific codes
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('delivery_zone_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_mode_id')->constrained('delivery_modes')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->decimal('rate', 15, 2);
            $table->decimal('additional_item_rate', 15, 2)->default(0);
            $table->decimal('min_weight', 10, 2)->default(0);
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->string('currency_code', 3);
            $table->timestamps();

            $table->unique(['delivery_mode_id', 'zone_id']);
        });

        Schema::create('handling_unit_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('hui_org_fk');
            $table->foreignId('handling_unit_id')->constrained('handling_units')->cascadeOnDelete()->name('hui_hu_fk');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete()->name('hui_product_fk');
            $table->foreignId('inventory_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete()->name('hui_batch_fk');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete()->name('hui_sol_fk');
            $table->decimal('quantity', 18, 4);
            $table->decimal('weight', 10, 4)->nullable();
            $table->timestamps();
            $table->index(['handling_unit_id'], 'hui_hu_idx');
        });

        Schema::create('handling_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete()->name('hu_shipment_fk');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete()->name('hu_so_fk');
            $table->enum('hu_type', ['box', 'pallet', 'container', 'bag', 'drum', 'other'])->default('box');
            $table->string('hu_number', 50);
            $table->string('sscc_number', 30)->nullable();
            $table->decimal('gross_weight', 10, 4)->nullable();
            $table->decimal('net_weight', 10, 4)->nullable();
            $table->decimal('volume', 10, 4)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->boolean('is_sealed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'hu_number'], 'hu_org_number_unq');
            $table->index(['shipment_id'], 'hu_shipment_idx');
        });

        Schema::create('material_account_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('group_code', 4)->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('output_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name', 100);
            $table->enum('document_type', ['invoice', 'sales_order', 'quotation', 'delivery_note', 'purchase_order', 'payment'])->default('invoice');
            $table->enum('output_medium', ['print', 'email', 'edi', 'portal'])->default('email');
            $table->string('email_template', 100)->nullable();
            $table->string('print_template', 100)->nullable();
            $table->enum('dispatch_time', ['immediately', 'on_save', 'on_post', 'scheduled'])->default('on_post');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code', 'document_type'], 'ot_org_code_doc_unique');
        });

        Schema::create('output_condition_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_type_id')->constrained('output_types')->cascadeOnDelete();
            $table->enum('key_combination', ['customer', 'customer_group', 'all'])->default('all');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['output_type_id', 'key_combination'], 'ocr_type_key_idx');
        });

        Schema::create('output_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('output_type_id')->constrained('output_types')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->enum('medium', ['print', 'email', 'edi', 'portal'])->default('email');
            $table->string('recipient', 255)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
            $table->index(['document_type', 'document_id'], 'om_doc_idx');
            $table->index(['status', 'scheduled_at'], 'om_status_sched_idx');
        });

        Schema::create('payment_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('type', 30); // cash, bank_transfer, card, cheque, upi, mobile_wallet, online, crypto, credit_term, cod
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            // Bank account link (for auto-reconciliation)
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Settings
            $table->boolean('is_online')->default(false); // Online payment method
            $table->boolean('requires_reference')->default(false); // Transaction ref required
            $table->boolean('requires_approval')->default(false);
            $table->decimal('surcharge_percent', 5, 2)->default(0); // Card processing fee
            $table->decimal('surcharge_flat', 15, 2)->default(0);
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->json('supported_currencies')->nullable();

            // Integration
            $table->string('gateway_provider')->nullable(); // stripe, paypal, razorpay, tap
            $table->json('gateway_config')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'type']);
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('selling'); // selling, buying
            $table->string('currency_code', 3);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_tax_inclusive')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'type', 'is_active']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Contact type
            $table->enum('contact_type', ['customer', 'supplier', 'both'])->default('customer');

            // Basic info
            $table->string('company_name', 200)->nullable();
            $table->string('contact_name', 100);
            $table->string('email', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('website', 255)->nullable();

            // Tax info
            $table->string('tax_number', 50)->nullable(); // TRN for GCC, GSTIN for India
            $table->string('tax_registration_name', 200)->nullable();

            // Financial terms
            $table->integer('payment_terms')->default(30); // Days
            $table->decimal('credit_limit', 18, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');

            // Linked accounts
            $table->foreignId('receivable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('payable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();

            // Billing address
            $table->string('billing_address_line_1', 255)->nullable();
            $table->string('billing_address_line_2', 255)->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_country_code', 2)->nullable();

            // Shipping address
            $table->string('shipping_address_line_1', 255)->nullable();
            $table->string('shipping_address_line_2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 100)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country_code', 2)->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contact_type']);
            $table->index(['organization_id', 'company_name']);
            $table->index('tax_number');

            $table->foreignId('customer_group_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('default_price_list_id')->nullable()
                ->constrained('price_lists')->nullOnDelete();
            $table->boolean('tax_exempt')->default(false);
            $table->string('tax_exemption_number', 50)->nullable();
            $table->date('tax_exemption_expiry')->nullable();


            $table->string('import_export_code', 30)->nullable(); // IEC for India, etc.
            $table->string('default_incoterm', 10)->nullable();
            $table->string('default_port')->nullable();


                $table->unsignedBigInteger('customer_account_group_id')->nullable();
                $table->foreign('customer_account_group_id', 'contact_cag_fk')
                    ->references('id')->on('customer_account_groups')->onDelete('set null');


            $table->boolean('payment_block')->default(false);
            $table->string('payment_block_reason', 500)->nullable();
        });

        Schema::create('consignment_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 30);
            $table->enum('order_type', ['fillup', 'issue', 'pickup', 'return']);
            $table->foreignId('contact_id')->constrained('contacts');
            $table->enum('status', ['draft', 'confirmed', 'shipped', 'completed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'order_number'], 'co_org_order_number_unique');
            $table->index(['organization_id', 'order_type'], 'co_org_type_idx');
            $table->index(['organization_id', 'contact_id'], 'co_org_contact_idx');
            $table->index(['organization_id', 'status'], 'co_org_status_idx');
        });

        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('contacts');

            $table->enum('source_type', [
                'advance_payment',
                'credit_note',
                'overpayment',
                'adjustment',
            ]);
            $table->unsignedBigInteger('source_id')->nullable();

            $table->decimal('original_amount', 18, 4);
            $table->decimal('remaining_amount', 18, 4);
            $table->string('currency_code', 3)->default('SAR');

            $table->date('credit_date');
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['organization_id', 'customer_id']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('payments_received', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('payment_number', 50);
            $table->date('payment_date');

            // Customer
            $table->foreignId('customer_id')->constrained('contacts');

            // Payment details
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->enum('payment_method', [
                'cash',
                'bank_transfer',
                'cheque',
                'credit_card',
                'online',
                'other',
            ])->default('bank_transfer');

            $table->decimal('amount', 18, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('base_amount', 18, 4); // In organization's base currency

            // Reference info
            $table->string('reference', 100)->nullable(); // Cheque number, transaction ID, etc.
            $table->text('notes')->nullable();

            // Status
            $table->enum('status', [
                'pending',      // Created but not confirmed
                'completed',    // Payment confirmed
                'voided',       // Cancelled
                'bounced',      // Cheque bounced
            ])->default('pending');

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'payment_number']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'payment_date']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('price_list_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->enum('assignment_type', ['contact', 'customer_group', 'all']);
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->tinyInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['price_list_id'], 'pla_list_idx');
            $table->index(['assignment_type', 'assignment_id'], 'pla_type_id_idx');
        });

        Schema::create('price_override_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // What can be overridden
            $table->boolean('allow_price_change')->default(true);
            $table->boolean('allow_discount')->default(true);
            $table->boolean('allow_markup')->default(false); // Above list price
            $table->boolean('allow_free_item')->default(false); // Price = 0

            // Limits
            $table->decimal('max_discount_percent', 5, 2)->nullable(); // Max % below list price
            $table->decimal('max_markup_percent', 5, 2)->nullable(); // Max % above list price
            $table->decimal('max_discount_amount', 15, 2)->nullable(); // Max flat discount per item
            $table->decimal('min_price_percent', 5, 2)->nullable(); // Floor price as % of cost
            $table->decimal('max_total_discount_percent', 5, 2)->nullable(); // Max % off entire order

            // Approval requirements
            $table->boolean('requires_approval')->default(false);
            $table->decimal('approval_threshold_percent', 5, 2)->nullable(); // Needs approval above this %
            $table->decimal('approval_threshold_amount', 15, 2)->nullable(); // Needs approval above this amount
            $table->boolean('requires_reason')->default(true);

            // Scope
            $table->string('applies_to', 30)->default('all'); // all, roles, users, branches
            $table->json('applicable_role_ids')->nullable();
            $table->json('applicable_user_ids')->nullable();
            $table->json('applicable_branch_ids')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('price_override_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('requires_evidence')->default(false); // Attach competitor price screenshot, etc.
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('pricing_condition_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 10); // PR00, K007, MWST, etc.
            $table->string('name', 100);
            $table->enum('condition_class', ['price', 'discount', 'surcharge', 'tax', 'freight'])->default('price');
            $table->enum('calculation_type', ['fixed', 'percentage', 'quantity', 'weight', 'volume'])->default('percentage');
            $table->boolean('is_mandatory')->default(false);
            $table->integer('step')->default(10);
            $table->integer('counter')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'pct_org_code_unique');
        });

        Schema::create('pricing_condition_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_type_id')->constrained('pricing_condition_types')->cascadeOnDelete();
            $table->enum('key_combination', ['customer_material', 'customer', 'material', 'price_list', 'all'])->default('material');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('price_list_id')->nullable();
            $table->decimal('rate', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->decimal('min_quantity', 15, 4)->nullable();
            $table->decimal('max_quantity', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['condition_type_id', 'key_combination'], 'pcr_type_key_idx');
            $table->index(['product_id', 'valid_from', 'valid_to'], 'pcr_product_dates_idx');
        });

        Schema::create('pricing_procedures', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code'], 'pp_org_code_unique');
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('type', 20); // text, number, select, multi_select, boolean, color
            $table->json('options')->nullable(); // For select/multi_select types
            $table->string('unit')->nullable(); // kg, cm, ml
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_comparable')->default(false); // Show in product comparison
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 50);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            // Pricing
            $table->string('pricing_type', 20)->default('fixed'); // fixed, percentage_discount, custom
            $table->decimal('bundle_price', 15, 2)->nullable(); // For fixed pricing
            $table->decimal('discount_percent', 5, 2)->nullable(); // For percentage discount
            $table->decimal('original_total', 15, 2)->default(0); // Sum of individual items
            $table->decimal('savings_amount', 15, 2)->default(0); // How much customer saves

            // Availability
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->boolean('is_limited_time')->default(false);

            // Stock
            $table->unsignedInteger('max_quantity')->nullable(); // Max bundles available
            $table->unsignedInteger('sold_quantity')->default(0);

            // Restrictions
            $table->unsignedSmallInteger('min_order_quantity')->default(1);
            $table->unsignedSmallInteger('max_order_quantity')->nullable();
            $table->json('eligible_customer_tiers')->nullable(); // Tier codes allowed

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'sku']);
            $table->index(['organization_id', 'is_active']);
            $table->index(['available_from', 'available_until']);
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 50);
            $table->string('color', 7)->nullable();
            $table->string('tag_group', 30)->nullable(); // season, material, brand, origin, diet, etc.
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'tag_group']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 30)->nullable(); // Promo code for manual entry
            $table->text('description')->nullable();
            $table->string('type', 30);
            // Types: percentage, fixed_amount, fixed_price, buy_x_get_y, bundle, tiered

            $table->string('apply_to', 20)->default('line'); // line, order, shipping
            $table->string('target', 30)->default('all');
            // Targets: all, specific_products, specific_categories, specific_customers, customer_groups

            // Discount value
            $table->decimal('discount_value', 15, 4)->nullable();
            $table->decimal('max_discount_amount', 15, 4)->nullable(); // Cap for percentage discounts

            // Buy X Get Y configuration
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_quantity')->nullable();
            $table->decimal('get_discount_percent', 5, 2)->nullable(); // 100 = free

            // Tiered discount configuration (JSON)
            $table->json('tiers')->nullable();
            // [{ min_quantity: 10, discount_percent: 5 }, { min_quantity: 50, discount_percent: 10 }]

            // Conditions
            $table->decimal('min_order_amount', 15, 4)->nullable();
            $table->decimal('min_quantity', 15, 4)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('current_uses')->default(0);

            // Validity
            $table->datetime('start_date');
            $table->datetime('end_date')->nullable();
            $table->json('valid_days')->nullable(); // [0,1,2,3,4,5,6] days of week
            $table->time('valid_time_start')->nullable();
            $table->time('valid_time_end')->nullable();

            // Stacking
            $table->boolean('is_stackable')->default(false); // Can combine with other promotions
            $table->boolean('is_exclusive')->default(false); // Only one exclusive promo per order
            $table->unsignedSmallInteger('priority')->default(0);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_code')->default(false);

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active', 'start_date', 'end_date']);
        });

        Schema::create('coupon_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('current_uses')->default(0);
            $table->unsignedInteger('times_used')->default(0); // Alias for current_uses
            $table->foreignId('assigned_to')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('assigned_to_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->datetime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });

        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('order_type', 50); // Invoice, SalesOrder
            $table->unsignedBigInteger('order_id');
            $table->decimal('discount_amount', 15, 4);
            $table->timestamps();

            $table->index(['promotion_id', 'contact_id']);
            $table->index(['order_type', 'order_id']);
        });

        Schema::create('quick_sale_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('default_items')->nullable(); // Pre-configured line items
            $table->foreignId('default_customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('default_payment_method')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('quotation_number', 50);

            // Customer info
            $table->foreignId('customer_id')->constrained('contacts');
            $table->string('customer_name', 200);
            $table->string('customer_email', 100)->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();

            // Dates
            $table->date('quotation_date');
            $table->date('valid_until');

            // Currency
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 18, 8)->default(1);

            // Amounts
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);

            // Status
            $table->enum('status', [
                'draft',
                'sent',
                'accepted',
                'declined',
                'expired',
                'converted', // Converted to sales order or invoice
            ])->default('draft');

            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('reference', 100)->nullable();

            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'quotation_number']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('quick_sale_templates');
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('coupon_codes');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('pricing_procedures');
        Schema::dropIfExists('pricing_condition_records');
        Schema::dropIfExists('pricing_condition_types');
        Schema::dropIfExists('price_override_reasons');
        Schema::dropIfExists('price_override_policies');
        Schema::dropIfExists('price_list_assignments');
        Schema::dropIfExists('payments_received');
        Schema::dropIfExists('customer_credits');
        Schema::dropIfExists('consignment_orders');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('payment_modes');
        Schema::dropIfExists('output_messages');
        Schema::dropIfExists('output_condition_records');
        Schema::dropIfExists('output_types');
        Schema::dropIfExists('material_account_groups');
        Schema::dropIfExists('handling_units');
        Schema::dropIfExists('handling_unit_items');
        Schema::dropIfExists('delivery_zone_rates');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('delivery_split_rules');
        Schema::dropIfExists('delivery_modes');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('customer_account_groups');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('commission_payments');
        Schema::dropIfExists('commission_masters');
        Schema::dropIfExists('bulk_sale_batches');
        Schema::dropIfExists('billing_plans');
        Schema::dropIfExists('billing_plan_items');
        Schema::dropIfExists('backorder_records');
        Schema::dropIfExists('backdated_transactions');
    }
};
