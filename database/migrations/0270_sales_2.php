<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_masters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('contact_id');
            $table->string('rebate_type'); // percentage, fixed_amount, tiered
            $table->string('calculation_base'); // invoice_value, quantity, gross_profit
            $table->decimal('rebate_rate', 8, 4)->default(0);
            $table->string('accrual_method'); // periodic, on_invoice
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('minimum_purchase', 15, 4)->nullable();
            $table->decimal('maximum_rebate', 15, 4)->nullable();
            $table->unsignedBigInteger('accrual_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('accrual_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('expense_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');

            $table->index(['organization_id', 'contact_id', 'status']);
            $table->index(['organization_id', 'valid_from', 'valid_to']);
        });

        Schema::create('return_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('return_window_days')->default(30); // Days after purchase
            $table->boolean('allow_exchange')->default(true);
            $table->boolean('allow_refund')->default(true);
            $table->boolean('allow_credit_note')->default(true);
            $table->boolean('require_receipt')->default(true);
            $table->boolean('require_original_packaging')->default(false);
            $table->boolean('require_approval')->default(true);
            $table->decimal('restocking_fee_percent', 5, 2)->default(0);
            $table->json('non_returnable_categories')->nullable(); // Category IDs that can't be returned
            $table->json('condition_requirements')->nullable(); // Condition must be X to return
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('return_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('revenue_account_determination_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('customer_account_group', 4)->nullable();
            $table->string('material_account_group', 4)->nullable();
            $table->string('condition_type', 4)->nullable();
            $table->unsignedBigInteger('gl_account_id');
            $table->foreign('gl_account_id', 'rev_acct_det_gl_fk')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });

        Schema::create('revenue_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('contract_number');
            $table->unsignedBigInteger('contact_id');
            $table->date('contract_date');
            $table->decimal('total_transaction_price', 15, 4)->default(0);
            $table->decimal('allocated_price', 15, 4)->default(0);
            $table->string('status')->default('draft'); // draft, active, completed, cancelled
            $table->string('recognition_method'); // point_in_time, over_time
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');

            $table->unique(['organization_id', 'contract_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'contact_id']);
        });

        Schema::create('performance_obligations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('revenue_contract_id');
            $table->string('description');
            $table->decimal('standalone_selling_price', 15, 4)->default(0);
            $table->decimal('allocated_transaction_price', 15, 4)->default(0);
            $table->string('recognition_method'); // point_in_time, over_time, milestone
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->decimal('recognized_amount', 15, 4)->default(0);
            $table->decimal('deferred_amount', 15, 4)->default(0);
            $table->unsignedBigInteger('revenue_account_id')->nullable();
            $table->unsignedBigInteger('deferred_account_id')->nullable();
            $table->timestamps();

            $table->foreign('revenue_contract_id')->references('id')->on('revenue_contracts')->onDelete('cascade');
            $table->foreign('revenue_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('deferred_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');

            $table->index(['revenue_contract_id', 'status']);
        });

        Schema::create('revenue_recognition_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('performance_obligation_id');
            $table->date('event_date');
            $table->decimal('amount_recognized', 15, 4);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('performance_obligation_id')->references('id')->on('performance_obligations')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['performance_obligation_id', 'event_date'], 'rev_rec_event_perf_oblig_date_idx');
        });

        Schema::create('sales_order_cost_estimate_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('socei_org_fk');
            $table->foreignId('sales_order_cost_estimate_id')
                ->constrained('sales_order_cost_estimates')
                ->name('socei_estimate_fk');
            $table->foreignId('sales_order_line_id')
                ->nullable()
                ->constrained('sales_order_lines')
                ->name('socei_sol_fk');
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->name('socei_product_fk');
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements')
                ->name('socei_ce_fk');
            $table->enum('cost_category', ['material', 'labor', 'overhead', 'other']);
            $table->decimal('quantity', 18, 4);
            $table->decimal('cost_per_unit', 18, 4);
            $table->decimal('total_cost', 18, 4);
            $table->decimal('revenue', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['sales_order_cost_estimate_id'], 'socei_estimate_idx');
        });

        Schema::create('sales_order_cost_estimates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('sales_order_id')
                ->nullable()
                ->constrained('sales_orders')
                ->name('soce_so_fk');
            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('quotations')
                ->name('soce_quot_fk');
            $table->foreignId('costing_version_id')
                ->nullable()
                ->constrained('costing_versions')
                ->name('soce_cv_fk');
            $table->enum('status', ['draft', 'released', 'obsolete'])->default('draft');
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('total_revenue', 18, 4)->default(0);
            $table->decimal('gross_margin', 18, 4)->default(0);
            $table->decimal('gross_margin_percent', 8, 4)->default(0);
            $table->foreignId('costed_by')
                ->nullable()
                ->constrained('users')
                ->name('soce_costed_by_fk');
            $table->dateTime('costed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'sales_order_id'], 'soce_org_so_idx');
            $table->index(['organization_id', 'quotation_id'], 'soce_org_quot_idx');
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('order_number', 50);
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();

            // Customer info
            $table->foreignId('customer_id')->constrained('contacts');
            $table->string('customer_name', 200);
            $table->string('customer_email', 100)->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();

            // Dates
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('delivery_date')->nullable();

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
                'confirmed',
                'processing',
                'partially_delivered',
                'delivered',
                'invoiced',
                'cancelled',
            ])->default('draft');

            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete(); // Default fulfillment warehouse
            $table->text('notes')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->string('reference', 100)->nullable(); // Customer PO number

            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'order_number']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('delivery_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('delivery_number')->unique();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id', 'del_doc_so_fk')->references('id')->on('sales_orders')->onDelete('set null');
            $table->unsignedBigInteger('ship_to_contact_id')->nullable();
            $table->foreign('ship_to_contact_id', 'del_doc_ship_fk')->references('id')->on('contacts')->onDelete('set null');
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id', 'del_doc_wh_fk')->references('id')->on('warehouses')->onDelete('set null');
            $table->date('planned_goods_issue_date');
            $table->date('actual_goods_issue_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->enum('status', ['created', 'picking', 'picked', 'packed', 'goods_issued', 'cancelled'])->default('created');
            $table->decimal('weight_gross', 10, 3)->nullable();
            $table->decimal('weight_net', 10, 3)->nullable();
            $table->decimal('volume', 10, 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('intercompany_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('selling_organization_id');
            $table->foreign('selling_organization_id', 'icso_selling_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->unsignedBigInteger('buying_organization_id');
            $table->foreign('buying_organization_id', 'icso_buying_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id', 'icso_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();

            $table->string('order_number', 50);
            $table->enum('status', ['draft', 'confirmed', 'in_delivery', 'billed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();

            $table->unsignedBigInteger('transfer_price_version_id')->nullable();
            $table->foreign('transfer_price_version_id', 'icso_tpv_fk')
                ->references('id')->on('transfer_price_versions')->nullOnDelete();

            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'icso_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['selling_organization_id', 'status'], 'icso_selling_org_status_idx');
            $table->index(['buying_organization_id'], 'icso_buying_org_idx');
            $table->unique(['selling_organization_id', 'order_number'], 'icso_org_order_number_unq');
        });

        Schema::create('intercompany_billing_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('intercompany_sales_order_id');
            $table->foreign('intercompany_sales_order_id', 'icbd_icso_fk')
                ->references('id')->on('intercompany_sales_orders')->restrictOnDelete();

            $table->unsignedBigInteger('selling_organization_id');
            $table->foreign('selling_organization_id', 'icbd_selling_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->unsignedBigInteger('buying_organization_id');
            $table->foreign('buying_organization_id', 'icbd_buying_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->string('document_number', 50);
            $table->date('billing_date');
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');

            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->foreign('journal_entry_id', 'icbd_je_fk')
                ->references('id')->on('journal_entries')->nullOnDelete();

            $table->dateTime('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['selling_organization_id', 'document_number'], 'icbd_org_doc_unq');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Document identifiers
            $table->string('invoice_number', 50);
            $table->enum('invoice_type', [
                'standard',      // Regular invoice
                'simplified',    // Simplified invoice (B2C, under threshold)
                'credit_note',   // Credit note (returns, corrections)
                'debit_note',    // Debit note (additional charges)
            ])->default('standard');

            // Related documents
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices')->nullOnDelete(); // For credit/debit notes

            // Customer info (denormalized for historical record)
            $table->foreignId('customer_id')->constrained('contacts');
            $table->string('customer_name', 200);
            $table->string('customer_email', 100)->nullable();
            $table->string('customer_tax_number', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();

            // Dates
            $table->date('invoice_date');
            $table->date('due_date');

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
            $table->decimal('base_total', 18, 4)->default(0); // In base currency
            $table->decimal('amount_paid', 18, 4)->default(0);
            $table->decimal('amount_due', 18, 4)->default(0);

            // Status
            $table->enum('status', [
                'draft',
                'sent',
                'partial',
                'paid',
                'overdue',
                'voided',
            ])->default('draft');

            // Compliance fields (populated by Masaar)
            $table->enum('compliance_status', [
                'not_applicable',
                'pending',
                'submitted',
                'cleared',
                'reported',
                'rejected',
            ])->default('not_applicable');
            $table->string('compliance_uuid', 100)->nullable();
            $table->string('compliance_hash', 64)->nullable();
            $table->text('compliance_qr_code')->nullable();
            $table->json('compliance_response')->nullable();
            $table->timestamp('compliance_submitted_at')->nullable();

            // India GST specific
            $table->string('place_of_supply', 2)->nullable(); // State code
            $table->boolean('is_reverse_charge')->default(false);

            // Additional info
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('reference', 100)->nullable(); // Customer PO number

            // Optimistic locking
            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'invoice_date']);
            $table->index(['organization_id', 'due_date', 'status']);
            $table->index('compliance_uuid');

            $table->integer('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();

            $table->string('incoterm', 10)->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('country_of_destination', 3)->nullable();
            $table->boolean('is_international')->default(false);
            $table->boolean('is_export')->default(false);

            $table->foreign('quotation_id')
                ->references('id')
                ->on('quotations')
                ->nullOnDelete();

            $table->foreign('sales_order_id')
                ->references('id')
                ->on('sales_orders')
                ->nullOnDelete();

            $table->index('quotation_id');
            $table->index('sales_order_id');

            $table->text('compliance_notes')->nullable();

            $table->index('compliance_status', 'inv_compliance_status_idx');
        });

        Schema::create('cash_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('cash_sale_number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id', 'cs_cust_fk')->references('id')->on('contacts')->onDelete('set null');
            $table->unsignedBigInteger('cashier_id');
            $table->foreign('cashier_id', 'cs_cashier_fk')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreign('branch_id', 'cs_branch_fk')->references('id')->on('branches')->onDelete('set null');
            $table->timestamp('sale_date');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('payment_method', ['cash', 'card', 'wallet', 'mixed']);
            $table->decimal('amount_tendered', 18, 4)->nullable();
            $table->decimal('change_given', 18, 4)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreign('invoice_id', 'cs_inv_fk')->references('id')->on('invoices')->onDelete('set null');
            $table->enum('status', ['open', 'completed', 'voided'])->default('open');
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->foreign('voided_by', 'cs_void_usr_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('commission_calculations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedBigInteger('commission_master_id');
            $table->foreign('commission_master_id', 'comm_calc_master_fk')->references('id')->on('commission_masters')->onDelete('cascade');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->foreign('invoice_id', 'comm_calc_inv_fk')->references('id')->on('invoices')->onDelete('set null');
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id', 'comm_calc_so_fk')->references('id')->on('sales_orders')->onDelete('set null');
            $table->unsignedSmallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('base_amount', 18, 4);
            $table->decimal('commission_rate', 8, 4);
            $table->decimal('commission_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['calculated', 'approved', 'paid', 'reversed'])->default('calculated');
            $table->timestamp('calculated_at');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'comm_calc_appr_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_received_id')->constrained('payments_received')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 18, 4);
            $table->decimal('base_amount', 18, 4);

            $table->timestamp('allocated_at');
            $table->timestamps();

            $table->unique(['payment_received_id', 'invoice_id']);
            $table->index('invoice_id');
        });

        Schema::create('pick_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('pick_number')->unique();
            $table->unsignedBigInteger('delivery_document_id');
            $table->foreign('delivery_document_id', 'pick_doc_del_fk')->references('id')->on('delivery_documents')->onDelete('cascade');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreign('assigned_to', 'pick_doc_usr_fk')->references('id')->on('users')->onDelete('set null');
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled'])->default('open');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rebate_accruals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rebate_master_id');
            $table->unsignedBigInteger('invoice_id');
            $table->date('accrual_date');
            $table->decimal('invoice_amount', 15, 4);
            $table->decimal('rebate_amount', 15, 4);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('status')->default('pending'); // pending, posted, settled
            $table->timestamps();

            $table->foreign('rebate_master_id')->references('id')->on('rebate_masters')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');

            $table->index(['rebate_master_id', 'status']);
            $table->index(['invoice_id']);

            $table->string('settlement_ref', 100)->nullable();
            $table->timestamp('settled_at')->nullable();
        });

        Schema::create('seasonal_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('campaign_type', 30); // seasonal, flash_sale, clearance, holiday, back_to_school, ramadan, diwali, eid, national_day
            $table->string('banner_image')->nullable();
            $table->string('theme_color', 7)->nullable();

            // Dates
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_recurring')->default(false); // Same campaign every year
            $table->string('recurrence_rule')->nullable(); // yearly, quarterly

            // Discount settings
            $table->string('discount_type', 20)->nullable(); // percentage, fixed_amount, tiered
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('min_purchase', 15, 2)->nullable();

            // Scope
            $table->string('applies_to', 30)->default('all'); // all, categories, products, bundles
            $table->json('applicable_category_ids')->nullable();
            $table->json('applicable_product_ids')->nullable();
            $table->json('applicable_bundle_ids')->nullable();
            $table->json('excluded_product_ids')->nullable();

            // Limits
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->decimal('budget_limit', 15, 2)->nullable(); // Max total discount given
            $table->decimal('budget_used', 15, 2)->default(0);

            // Messaging
            $table->text('promotional_message')->nullable();
            $table->boolean('send_notification')->default(false);
            $table->boolean('show_countdown')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0); // Higher = checked first
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active', 'starts_at', 'ends_at'], 'season_camp_org_active_dates_idx');
            $table->index(['campaign_type']);
        });

        Schema::create('campaign_tier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('seasonal_campaigns')->cascadeOnDelete();
            $table->string('tier_code', 30)->nullable(); // Links to customer_tiers.code
            $table->string('tier_name', 100)->nullable();
            $table->decimal('min_purchase_amount', 15, 2)->nullable();
            $table->string('discount_type', 20)->nullable(); // percentage, fixed_amount
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('extra_discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('bonus_points')->default(0);
            $table->boolean('early_access')->default(false);
            $table->unsignedSmallInteger('early_access_hours')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campaign_id', 'tier_code']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_mode_id')->constrained('delivery_modes')->cascadeOnDelete();
            $table->string('shipment_number', 30);

            // Source document
            $table->string('source_type', 100); // SalesOrder, Invoice, ExchangeOrder
            $table->unsignedBigInteger('source_id')->nullable();

            // Customer
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();

            // Tracking
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_url')->nullable();

            // Dates
            $table->date('ship_date')->nullable();
            $table->date('estimated_delivery')->nullable();
            $table->date('actual_delivery')->nullable();

            // Weight & dimensions
            $table->decimal('total_weight_kg', 10, 2)->nullable();
            $table->json('dimensions')->nullable(); // {length, width, height}

            // Cost
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->string('currency_code', 3);

            // Status
            $table->string('status', 30)->default('pending'); // pending, picked, packed, shipped, in_transit, out_for_delivery, delivered, failed, returned
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('proof_of_delivery_path')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'shipment_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['contact_id']);
            $table->index(['tracking_number']);
        });

        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('event_at');
            $table->json('raw_data')->nullable(); // Carrier API response
            $table->timestamps();

            $table->index(['shipment_id', 'event_at']);
        });

        Schema::create('shipping_route_determinations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('srd_org_fk');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete()->name('srd_so_fk');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete()->name('srd_shipment_fk');
            $table->foreignId('shipping_route_id')->nullable()->constrained('shipping_routes')->nullOnDelete()->name('srd_route_fk');
            $table->foreignId('departure_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete()->name('srd_dep_zone_fk');
            $table->foreignId('destination_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete()->name('srd_dest_zone_fk');
            $table->dateTime('determined_at');
            $table->timestamps();
            $table->index(['sales_order_id'], 'srd_so_idx');
        });

        Schema::create('shipping_routes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('sr_org_fk');
            $table->string('route_code', 30);
            $table->string('route_name', 100);
            $table->foreignId('departure_zone_id')->constrained('shipping_zones')->cascadeOnDelete()->name('sr_dep_zone_fk');
            $table->foreignId('destination_zone_id')->constrained('shipping_zones')->cascadeOnDelete()->name('sr_dest_zone_fk');
            $table->enum('transportation_mode', ['road', 'air', 'sea', 'rail', 'courier'])->default('road');
            $table->unsignedSmallInteger('transit_days')->default(1);
            $table->string('carrier', 100)->nullable();
            $table->decimal('freight_cost', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'route_code'], 'sr_org_code_unq');
            $table->index(['departure_zone_id', 'destination_zone_id'], 'sr_dep_dest_idx');
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('zone_code', 20);
            $table->string('zone_name', 100);
            $table->json('country_codes')->nullable();
            $table->string('postal_code_pattern', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'zone_code'], 'sz_org_code_unq');
        });

        Schema::create('third_party_order_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('tpol_org_fk');
            $table->foreignId('third_party_order_id')->constrained('third_party_orders')->cascadeOnDelete()->name('tpol_tpo_fk');
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete()->name('tpol_sol_fk');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('tpol_product_fk');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('vendor_price', 18, 4)->nullable();
            $table->timestamps();
            $table->index(['third_party_order_id'], 'tpol_tpo_idx');
        });

        Schema::create('third_party_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete()->name('tpo_so_fk');
            $table->foreignId('vendor_id')->constrained('contacts')->cascadeOnDelete()->name('tpo_vendor_fk');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete()->name('tpo_po_fk');
            $table->enum('status', ['pending', 'po_created', 'shipped', 'delivered', 'invoiced', 'cancelled'])->default('pending');
            $table->string('shipping_address_line1', 255)->nullable();
            $table->string('shipping_address_line2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->char('shipping_country_code', 2)->nullable();
            $table->string('vendor_reference', 100)->nullable();
            $table->string('shipping_confirmation', 100)->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'tpo_org_status_idx');
            $table->index(['sales_order_id'], 'tpo_so_idx');
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('wallet_type', 20); // customer, supplier
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('balance', 15, 2)->default(0); // Current balance
            $table->decimal('credit_limit', 15, 2)->default(0); // For credit wallets
            $table->decimal('total_credits', 15, 2)->default(0); // Lifetime credits added
            $table->decimal('total_debits', 15, 2)->default(0); // Lifetime debits
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_negative')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id', 'currency_code']);
            $table->index(['organization_id', 'wallet_type']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type', 30); // credit, debit, adjustment, refund, transfer
            $table->string('reference_number', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description');

            // Source of transaction
            $table->nullableMorphs('source'); // advance_payment, invoice, credit_note, refund, etc.

            $table->date('transaction_date')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['wallet_id', 'transaction_date']);
        });

        Schema::create('advance_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_number', 30);
            $table->string('payment_type', 20); // customer_advance, supplier_advance

            // Contact
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('contact_name');

            // Payment details
            $table->date('payment_date');
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->decimal('amount', 15, 2);
            $table->decimal('base_amount', 15, 2);
            $table->decimal('applied_amount', 15, 2)->default(0); // Amount used against invoices
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->decimal('available_amount', 15, 2); // amount - applied - refunded

            // Payment method
            $table->string('payment_method', 30);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('cheque_number')->nullable();
            $table->date('cheque_date')->nullable();

            // Status
            $table->string('status', 20)->default('active'); // active, fully_applied, refunded, cancelled

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'payment_type', 'status']);
            $table->index(['contact_id', 'status']);

            $table->foreign('wallet_transaction_id', 'adv_pay_wallet_txn_fk')
                ->references('id')->on('wallet_transactions')->nullOnDelete();
        });

        Schema::create('advance_payment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_payment_id')->constrained()->cascadeOnDelete();
            $table->morphs('applied_to'); // invoice, bill
            $table->decimal('applied_amount', 15, 2);
            $table->date('applied_date');
            $table->foreignId('applied_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('advance_payment_applications');
        Schema::dropIfExists('advance_payments');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('third_party_orders');
        Schema::dropIfExists('third_party_order_lines');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_routes');
        Schema::dropIfExists('shipping_route_determinations');
        Schema::dropIfExists('shipment_tracking_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('campaign_tier_offers');
        Schema::dropIfExists('seasonal_campaigns');
        Schema::dropIfExists('rebate_accruals');
        Schema::dropIfExists('pick_documents');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('commission_calculations');
        Schema::dropIfExists('cash_sales');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('intercompany_billing_documents');
        Schema::dropIfExists('intercompany_sales_orders');
        Schema::dropIfExists('delivery_documents');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_order_cost_estimates');
        Schema::dropIfExists('sales_order_cost_estimate_items');
        Schema::dropIfExists('revenue_recognition_events');
        Schema::dropIfExists('performance_obligations');
        Schema::dropIfExists('revenue_contracts');
        Schema::dropIfExists('revenue_account_determination_keys');
        Schema::dropIfExists('return_reasons');
        Schema::dropIfExists('return_policies');
        Schema::dropIfExists('rebate_masters');
    }
};
