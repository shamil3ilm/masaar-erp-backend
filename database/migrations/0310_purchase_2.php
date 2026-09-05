<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('contract_number', 30);
            $table->enum('contract_type', ['sales', 'purchase', 'service', 'maintenance']);
            $table->unsignedBigInteger('contact_id');
            $table->string('title', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('renewal_notice_days')->default(30);
            $table->string('currency_code', 3);
            $table->decimal('total_value', 15, 4)->nullable();
            $table->decimal('billed_amount', 15, 4)->default(0);
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'cancelled'])->default('draft');
            $table->date('signed_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('parent_contract_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('parent_contract_id')->references('id')->on('contracts')->nullOnDelete();

            $table->unique(['organization_id', 'contract_number'], 'contracts_org_number_unique');
            $table->index(['organization_id', 'status'], 'contracts_org_status_idx');
            $table->index(['organization_id', 'end_date'], 'contracts_org_end_date_idx');
        });

        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('document_type', 50);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users');

            $table->index('contract_id', 'contract_documents_contract_id_idx');
        });

        Schema::create('contract_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('milestone_name', 200);
            $table->date('due_date');
            $table->decimal('amount', 15, 4);
            $table->enum('status', ['pending', 'invoiced', 'paid'])->default('pending');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');

            $table->index(['contract_id', 'status'], 'contract_milestones_contract_status_idx');
            $table->index(['contract_id', 'due_date'], 'contract_milestones_contract_due_idx');
        });

        Schema::create('contract_releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('release_date');
            $table->decimal('amount', 15, 4);
            $table->enum('status', ['pending', 'fulfilled', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');

            $table->index(['contract_id', 'status'], 'contract_releases_contract_status_idx');
        });

        Schema::create('ers_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'ers_config_vendor_fk')->references('id')->on('contacts');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('auto_post')->default(false);
            $table->decimal('tolerance_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'vendor_id'], 'ers_config_org_vendor_unq');
        });

        Schema::create('outline_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id', 'oa_vendor_fk')->references('id')->on('contacts');
            $table->string('agreement_number', 50);
            $table->enum('agreement_type', ['quantity_contract', 'value_contract', 'scheduling_agreement'])->default('quantity_contract');
            $table->enum('status', ['draft', 'active', 'expired', 'cancelled'])->default('draft');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->char('currency_code', 3)->default('SAR');
            $table->decimal('target_quantity', 18, 4)->nullable();
            $table->decimal('target_value', 18, 4)->nullable();
            $table->decimal('released_quantity', 18, 4)->default(0);
            $table->decimal('released_value', 18, 4)->default(0);
            $table->string('payment_terms', 100)->nullable();
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by', 'oa_created_by_fk')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'agreement_number'], 'oa_org_number_unq');
            $table->index(['organization_id', 'vendor_id', 'status'], 'oa_org_vendor_status_idx');
        });

        Schema::create('payments_made', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('payment_number', 50);
            $table->date('payment_date');

            // Supplier
            $table->foreignId('supplier_id')->constrained('contacts');

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
            $table->decimal('base_amount', 18, 4);

            // Reference info
            $table->string('reference', 100)->nullable(); // Cheque number, transaction ID
            $table->text('notes')->nullable();

            // Status
            $table->enum('status', [
                'pending',
                'completed',
                'voided',
                'bounced',
            ])->default('pending');

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'payment_number']);
            $table->index(['organization_id', 'supplier_id']);
            $table->index(['organization_id', 'payment_date']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('order_number', 50);

            // Supplier info
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->string('supplier_name', 200);
            $table->string('supplier_email', 100)->nullable();
            $table->text('supplier_address')->nullable();

            // Delivery info
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->text('delivery_address')->nullable();

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
                'sent',
                'confirmed',
                'partially_received',
                'received',
                'billed',
                'cancelled',
            ])->default('draft');

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('reference', 100)->nullable(); // Supplier quote reference

            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'order_number']);
            $table->index(['organization_id', 'supplier_id']);
            $table->index(['organization_id', 'status']);

            $table->string('incoterm', 10)->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('country_of_origin', 3)->nullable();
            $table->boolean('is_international')->default(false);


            $table->index(['organization_id', 'order_date'], 'po_org_order_date_idx');
            $table->index(['organization_id', 'expected_delivery_date'], 'po_org_exp_delivery_idx');
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Document identifiers
            $table->string('bill_number', 50); // Internal reference
            $table->string('supplier_invoice_number', 100)->nullable(); // Supplier's invoice number

            $table->enum('bill_type', [
                'standard',
                'debit_note',   // Returns to supplier
                'credit_note',  // Credit from supplier
            ])->default('standard');

            // Related documents
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('original_bill_id')->nullable()->constrained('bills')->nullOnDelete();

            // Supplier info
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->string('supplier_name', 200);
            $table->string('supplier_tax_number', 50)->nullable();
            $table->text('supplier_address')->nullable();

            // Dates
            $table->date('bill_date');
            $table->date('due_date');
            $table->date('received_date')->nullable(); // Date goods/services received

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
            $table->decimal('base_total', 18, 4)->default(0);
            $table->decimal('amount_paid', 18, 4)->default(0);
            $table->decimal('amount_due', 18, 4)->default(0);

            // Status
            $table->enum('status', ['draft', 'pending', 'approved', 'partial', 'paid', 'voided', 'overdue'])->default('draft');

            // India GST specific
            $table->string('place_of_supply', 2)->nullable();
            $table->boolean('is_reverse_charge')->default(false);

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'bill_number']);
            $table->index(['organization_id', 'supplier_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'due_date', 'status']);

            $table->string('reference', 100)->nullable();
        });

        Schema::create('bill_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_made_id')->constrained('payments_made')->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 18, 4);
            $table->decimal('base_amount', 18, 4);

            $table->timestamp('allocated_at');
            $table->timestamps();

            $table->unique(['payment_made_id', 'bill_id']);
            $table->index('bill_id');
        });

        Schema::create('procurement_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('gr_number', 30)->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'quality_hold'])->default('draft');
            $table->string('supplier_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'proc_gr_org_status_idx');
            $table->index(['organization_id', 'purchase_order_id'], 'proc_gr_org_po_idx');
        });

        Schema::create('rfq_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamp('sent_at')->nullable();
            $table->date('response_deadline')->nullable();
            $table->enum('status', ['invited', 'responded', 'declined', 'awarded', 'rejected'])->default('invited');
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');

            $table->unique(['rfq_id', 'contact_id'], 'rfq_vendors_rfq_contact_unique');
            $table->index(['rfq_id', 'status'], 'rfq_vendors_rfq_status_idx');
        });

        Schema::create('rfq_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('rfq_id');
            $table->unsignedBigInteger('rfq_vendor_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('quote_number', 100)->nullable();
            $table->date('quote_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('currency_code', 3);
            $table->decimal('total_amount', 15, 4);
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->string('payment_terms', 200)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['received', 'evaluated', 'awarded', 'rejected'])->default('received');
            $table->timestamps();

            $table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade');
            $table->foreign('rfq_vendor_id')->references('id')->on('rfq_vendors')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');

            $table->index(['rfq_id', 'status'], 'rfq_quotes_rfq_status_idx');
        });

        Schema::create('service_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('contacts');
            $table->text('description');
            $table->decimal('total_value', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['draft', 'sent', 'partially_accepted', 'accepted', 'closed', 'cancelled'])->default('draft');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_entry_sheets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('ses_number')->unique();
            $table->unsignedBigInteger('service_purchase_order_id');
            $table->foreign('service_purchase_order_id', 'ses_po_fk')->references('id')->on('service_purchase_orders');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('contacts');
            $table->date('service_period_from');
            $table->date('service_period_to');
            $table->text('description');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'posted'])->default('draft');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->foreign('submitted_by')->references('id')->on('users');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_acceptances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('service_entry_sheet_id')->unique();
            $table->foreign('service_entry_sheet_id', 'svc_acc_ses_fk')->references('id')->on('service_entry_sheets');
            $table->unsignedBigInteger('accepted_by');
            $table->foreign('accepted_by')->references('id')->on('users');
            $table->timestamp('accepted_at');
            $table->text('rejection_reason')->nullable();
            $table->enum('status', ['accepted', 'rejected']);
            $table->timestamps();
        });

        Schema::create('service_po_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_purchase_order_id');
            $table->foreign('service_purchase_order_id', 'svc_po_line_po_fk')->references('id')->on('service_purchase_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->text('service_description');
            $table->string('service_number')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('uom', 20);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('total_price', 18, 4);
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'svc_po_line_cc_fk')->references('id')->on('cost_centers')->nullOnDelete();
            $table->unsignedBigInteger('internal_order_id')->nullable();
            $table->foreign('internal_order_id', 'svc_po_line_io_fk')->references('id')->on('internal_orders')->nullOnDelete();
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('service_entry_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_entry_sheet_id');
            $table->foreign('service_entry_sheet_id', 'ses_line_ses_fk')->references('id')->on('service_entry_sheets')->cascadeOnDelete();
            $table->unsignedBigInteger('service_po_line_id');
            $table->foreign('service_po_line_id', 'ses_line_pol_fk')->references('id')->on('service_po_lines');
            $table->decimal('actual_quantity', 18, 4);
            $table->string('uom', 20);
            $table->decimal('actual_price', 18, 4);
            $table->decimal('total_amount', 18, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_credits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts');

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

            $table->index(['organization_id', 'supplier_id']);
        });

        Schema::create('supplier_delivery_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('promised_date');
            $table->date('actual_date')->nullable();
            $table->decimal('quantity_ordered', 12, 4);
            $table->decimal('quantity_received', 12, 4)->default(0);
            $table->boolean('is_on_time')->nullable();
            $table->boolean('is_complete')->nullable();
            $table->boolean('quality_accepted')->nullable();
            $table->decimal('defect_quantity', 12, 4)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index(['organization_id', 'supplier_id']);
        });

        Schema::create('supplier_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->enum('incident_type', ['late_delivery', 'quality_issue', 'pricing_dispute', 'compliance_breach', 'communication'])->default('quality_issue');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');
            $table->date('occurred_at');
            $table->date('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'supplier_id']);
        });

        Schema::create('supplier_scorecards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('evaluation_period_start');
            $table->date('evaluation_period_end');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->decimal('delivery_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->decimal('service_score', 5, 2)->nullable();
            $table->decimal('compliance_score', 5, 2)->nullable();
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'evaluation_period_start', 'evaluation_period_end'], 'unique_supplier_scorecard_period');
            $table->index('organization_id');
        });

        Schema::create('supplier_scorecard_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_id')->constrained('supplier_scorecards')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('supplier_evaluation_criteria')->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_advance_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('request_number', 30);
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->decimal('requested_amount', 15, 4);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->text('purpose')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->enum('status', ['draft', 'approved', 'paid', 'cleared', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['organization_id', 'request_number'], 'vendor_adv_req_org_number_unique');
            $table->index(['organization_id', 'status'], 'vendor_adv_req_org_status_idx');
            $table->index(['organization_id', 'contact_id'], 'vendor_adv_req_org_contact_idx');
        });

        Schema::create('vendor_advance_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('advance_request_id');
            $table->date('payment_date');
            $table->decimal('amount', 15, 4);
            $table->string('payment_method', 50);
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('advance_request_id')->references('id')->on('vendor_advance_requests')->onDelete('cascade');
            $table->foreign('bank_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->index('advance_request_id', 'vendor_adv_pay_req_id_idx');
        });

        Schema::create('vendor_advance_clearings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advance_payment_id');
            $table->unsignedBigInteger('bill_id');
            $table->decimal('cleared_amount', 15, 4);
            $table->date('clearing_date');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('advance_payment_id')->references('id')->on('vendor_advance_payments')->onDelete('cascade');
            $table->foreign('bill_id')->references('id')->on('bills');

            $table->index('advance_payment_id', 'vendor_adv_clr_payment_id_idx');
            $table->index('bill_id', 'vendor_adv_clr_bill_id_idx');
        });

        Schema::create('vendor_advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('advance_number', 30)->nullable();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('adjusted_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->date('payment_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['paid', 'partially_adjusted', 'fully_adjusted', 'refunded'])->default('paid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contact_id'], 'vendor_adv_org_contact_idx');
            $table->index(['organization_id', 'status'], 'vendor_adv_org_status_idx');
        });

        Schema::create('vendor_advance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_advance_id')->constrained('vendor_advances')->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->decimal('adjusted_amount', 15, 2);
            $table->timestamp('adjusted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('vendor_advance_id', 'vendor_adv_adj_advance_id_idx');
            $table->index('bill_id', 'vendor_adv_adj_bill_id_idx');
        });

        Schema::create('vendor_consignment_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('vendor_id');
            $table->date('settlement_period_from');
            $table->date('settlement_period_to');
            $table->decimal('total_quantity', 18, 4);
            $table->decimal('total_value', 18, 4);
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('draft'); // draft/submitted/paid
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'vcse_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_id', 'vcse_vendor_fk')
                ->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('bill_id', 'vcse_bill_fk')
                ->references('id')->on('bills')->onDelete('set null');
            $table->foreign('settled_by', 'vcse_settled_by_fk')
                ->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'settlement_period_from'], 'vcse_status_period_idx');
            $table->index(['organization_id', 'vendor_id'], 'vcse_org_vendor_idx');
        });

        Schema::create('vendor_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('contract_number', 50)->nullable();
            $table->foreignId('contact_id')->constrained('contacts');
            $table->string('title', 200)->nullable();
            $table->enum('contract_type', ['supply', 'service', 'framework', 'blanket_order'])->default('supply');
            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('signed_at')->nullable();
            $table->date('terminated_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->string('payment_terms', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'vendor_contracts_org_status_idx');
            $table->index(['organization_id', 'contact_id'], 'vendor_contracts_org_contact_idx');
        });

        Schema::create('vendor_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('credit_note_number', 100);
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->date('issue_date');
            $table->date('credit_date');
            $table->enum('status', ['draft', 'posted', 'applied', 'void'])->default('draft');
            $table->string('reason', 255)->nullable();
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('applied_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('contacts');
            $table->foreign('bill_id')->references('id')->on('bills')->nullOnDelete();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'vendor_id', 'status']);
            $table->index(['organization_id', 'credit_date']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expense_number', 30);
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->string('payment_method', 30)->nullable(); // cash, card, bank_transfer, petty_cash
            $table->string('reference')->nullable();
            $table->text('description');
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('base_amount', 15, 2); // In base currency
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, rejected, paid, cancelled
            $table->boolean('is_reimbursable')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('recurring_expense_id')->nullable(); // Parent recurring expense
            $table->boolean('is_billable')->default(false);
            $table->foreignId('project_id')->nullable(); // If billable to project
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('bill_id')->nullable(); // Converted to payable
            $table->text('notes')->nullable();
            $table->json('custom_fields')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'expense_date']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'category_id']);
            $table->index(['employee_id', 'status']);

            $table->foreign('bill_id', 'expense_bill_fk')
                ->references('id')->on('bills')->nullOnDelete();
            $table->foreign('project_id', 'expense_project_fk')
                ->references('id')->on('projects')->nullOnDelete();
            $table->foreign('recurring_expense_id', 'expense_recurring_fk')
                ->references('id')->on('recurring_expenses')->nullOnDelete();
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->unsignedTinyInteger('line_order')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->text('ocr_text')->nullable(); // Extracted text from receipt
            $table->json('ocr_data')->nullable(); // Parsed OCR data (amount, date, vendor)
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('expense_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('expense_reports')->cascadeOnDelete();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->decimal('approved_amount')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'expense_id']);
        });

        Schema::create('subcontract_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('order_number', 30);
            $table->unsignedBigInteger('contact_id');
            $table->enum('status', ['draft', 'sent', 'material_transferred', 'in_process', 'received', 'closed', 'cancelled'])->default('draft');
            $table->date('issued_date')->nullable();
            $table->date('expected_receipt_date')->nullable();
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('service_charge', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('restrict');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->unique(['organization_id', 'order_number'], 'sco_org_number_unique');
            $table->index(['organization_id', 'status'], 'sco_org_status_idx');
        });

        Schema::create('subcontract_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('order_id');
            $table->date('receipt_date');
            $table->unsignedBigInteger('warehouse_id');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index('order_id', 'scr_order_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_receipts');
        Schema::dropIfExists('subcontract_orders');
        Schema::dropIfExists('expense_report_items');
        Schema::dropIfExists('expense_receipts');
        Schema::dropIfExists('expense_items');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('vendor_credit_notes');
        Schema::dropIfExists('vendor_contracts');
        Schema::dropIfExists('vendor_consignment_settlements');
        Schema::dropIfExists('vendor_advance_adjustments');
        Schema::dropIfExists('vendor_advances');
        Schema::dropIfExists('vendor_advance_clearings');
        Schema::dropIfExists('vendor_advance_payments');
        Schema::dropIfExists('vendor_advance_requests');
        Schema::dropIfExists('supplier_scorecard_ratings');
        Schema::dropIfExists('supplier_scorecards');
        Schema::dropIfExists('supplier_incidents');
        Schema::dropIfExists('supplier_delivery_records');
        Schema::dropIfExists('supplier_credits');
        Schema::dropIfExists('service_entry_sheet_lines');
        Schema::dropIfExists('service_po_lines');
        Schema::dropIfExists('service_acceptances');
        Schema::dropIfExists('service_entry_sheets');
        Schema::dropIfExists('service_purchase_orders');
        Schema::dropIfExists('rfq_quotes');
        Schema::dropIfExists('rfq_vendors');
        Schema::dropIfExists('procurement_goods_receipts');
        Schema::dropIfExists('bill_payment_allocations');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('payments_made');
        Schema::dropIfExists('outline_agreements');
        Schema::dropIfExists('ers_configurations');
        Schema::dropIfExists('contract_releases');
        Schema::dropIfExists('contract_milestones');
        Schema::dropIfExists('contract_documents');
        Schema::dropIfExists('contracts');
    }
};
