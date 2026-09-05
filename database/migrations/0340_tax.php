<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eway_bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('eway_bill_number', 50)->nullable();
            $table->string('gstin_supplier', 20)->nullable();
            $table->string('gstin_recipient', 20)->nullable();
            $table->string('transport_mode', 20)->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('transporter_id', 50)->nullable();
            $table->string('transporter_doc_number', 100)->nullable();
            $table->decimal('taxable_value', 15, 2)->default(0);
            $table->decimal('cgst_value', 15, 2)->default(0);
            $table->decimal('sgst_value', 15, 2)->default(0);
            $table->decimal('igst_value', 15, 2)->default(0);
            $table->decimal('cess_value', 15, 2)->default(0);
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
            $table->timestamp('valid_upto')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 200)->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'eway_bills_org_status_idx');
            $table->index(['organization_id', 'invoice_id'], 'eway_bills_org_invoice_idx');
        });

        Schema::create('ewaybills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('ewb_number', 20)->nullable()->unique();
            $table->string('gstin_supplier', 15);
            $table->string('gstin_recipient', 15);
            $table->string('supply_type', 20);
            $table->string('transporter_id', 15)->nullable();
            $table->string('vehicle_number', 15)->nullable();
            $table->unsignedInteger('distance_km')->default(0);
            $table->enum('status', ['generated', 'cancelled', 'expired'])->default('generated');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'ewb_org_id_idx');
            $table->index(['source_type', 'source_id'], 'ewb_source_idx');
            $table->index('status', 'ewb_status_idx');
            $table->index('gstin_supplier', 'ewb_gstin_supplier_idx');
        });

        Schema::create('gst_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('gstin', 15);
            $table->string('state_code', 2);
            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->date('registration_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'gstin'], 'gst_reg_org_gstin_unq');
            $table->index('organization_id', 'gst_reg_org_id_idx');
            $table->index('gstin', 'gst_reg_gstin_idx');
        });

        Schema::create('gst_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('return_period', 20);
            $table->enum('return_type', ['GSTR-1', 'GSTR-2', 'GSTR-3B', 'GSTR-9', 'GSTR-9C'])->default('GSTR-1');
            $table->string('gstin', 20)->nullable();
            $table->decimal('total_taxable_value', 15, 2)->default(0);
            $table->decimal('total_cgst', 15, 2)->default(0);
            $table->decimal('total_sgst', 15, 2)->default(0);
            $table->decimal('total_igst', 15, 2)->default(0);
            $table->decimal('total_cess', 15, 2)->default(0);
            $table->enum('status', ['draft', 'filed', 'late_filed', 'cancelled'])->default('draft');
            $table->string('arn', 100)->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'return_type', 'return_period'], 'gst_ret_org_type_period_idx');
            $table->index(['organization_id', 'status'], 'gst_ret_org_status_idx');
        });

        Schema::create('gstr1_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('gstin_id');
            $table->tinyInteger('period_month')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->enum('filing_type', ['monthly', 'quarterly'])->default('monthly');
            $table->enum('status', ['draft', 'ready', 'filed', 'amended'])->default('draft');
            $table->decimal('total_taxable_value', 15, 4)->default(0);
            $table->decimal('total_igst', 15, 4)->default(0);
            $table->decimal('total_cgst', 15, 4)->default(0);
            $table->decimal('total_sgst', 15, 4)->default(0);
            $table->decimal('total_cess', 15, 4)->default(0);
            $table->timestamp('filed_at')->nullable();
            $table->string('arn', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'gstr1_org_id_idx');
            $table->index('gstin_id', 'gstr1_gstin_id_idx');
            $table->unique(['gstin_id', 'period_month', 'period_year', 'filing_type'], 'gstr1_period_unq');

            $table->foreign('gstin_id', 'gstr1_gstin_fk')
                ->references('id')
                ->on('gst_registrations')
                ->onDelete('restrict');
        });

        Schema::create('gstr1_b2b_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gstr1_return_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('buyer_gstin', 15);
            $table->date('invoice_date');
            $table->string('invoice_number', 50);
            $table->decimal('invoice_value', 15, 4)->default(0);
            $table->string('place_of_supply', 2);
            $table->decimal('taxable_value', 15, 4)->default(0);
            $table->decimal('igst', 15, 4)->default(0);
            $table->decimal('cgst', 15, 4)->default(0);
            $table->decimal('sgst', 15, 4)->default(0);
            $table->decimal('cess', 15, 4)->default(0);
            $table->timestamps();

            $table->index('gstr1_return_id', 'gstr1_b2b_return_id_idx');
            $table->index('buyer_gstin', 'gstr1_b2b_buyer_gstin_idx');
            $table->index('invoice_id', 'gstr1_b2b_invoice_id_idx');

            $table->foreign('gstr1_return_id', 'gstr1_b2b_return_fk')
                ->references('id')
                ->on('gstr1_returns')
                ->onDelete('cascade');
        });

        Schema::create('gstr3b_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('gstin_id');
            $table->tinyInteger('period_month')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->decimal('outward_taxable_supplies', 15, 4)->default(0);
            $table->decimal('outward_zero_rated', 15, 4)->default(0);
            $table->decimal('inward_supplies_itc', 15, 4)->default(0);
            $table->decimal('net_tax_payable', 15, 4)->default(0);
            $table->enum('status', ['draft', 'ready', 'filed', 'amended'])->default('draft');
            $table->timestamp('filed_at')->nullable();
            $table->string('arn', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'gstr3b_org_id_idx');
            $table->index('gstin_id', 'gstr3b_gstin_id_idx');
            $table->unique(['gstin_id', 'period_month', 'period_year'], 'gstr3b_period_unq');

            $table->foreign('gstin_id', 'gstr3b_gstin_fk')
                ->references('id')
                ->on('gst_registrations')
                ->onDelete('restrict');
        });

        Schema::create('gstr9_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('gstin', 15);             // 15-char GSTIN
            $table->year('financial_year_start');    // e.g. 2024 for FY 2024-25

            // Table 4: Taxable outward supplies
            $table->decimal('t4a_taxable_supplies', 18, 2)->default(0);   // 4A: taxable B2B
            $table->decimal('t4b_zero_rated', 18, 2)->default(0);         // 4B: zero-rated supplies
            $table->decimal('t4c_nil_rated', 18, 2)->default(0);          // 4C: nil-rated / exempt B2B

            // Output tax (Table 9)
            $table->decimal('t9_igst_payable', 18, 2)->default(0);
            $table->decimal('t9_cgst_payable', 18, 2)->default(0);
            $table->decimal('t9_sgst_payable', 18, 2)->default(0);
            $table->decimal('t9_cess_payable', 18, 2)->default(0);
            $table->decimal('t9_igst_paid', 18, 2)->default(0);
            $table->decimal('t9_cgst_paid', 18, 2)->default(0);
            $table->decimal('t9_sgst_paid', 18, 2)->default(0);
            $table->decimal('t9_cess_paid', 18, 2)->default(0);

            // ITC (Table 6)
            $table->decimal('t6a_itc_inputs', 18, 2)->default(0);        // inputs
            $table->decimal('t6b_itc_input_services', 18, 2)->default(0); // input services
            $table->decimal('t6c_itc_capital_goods', 18, 2)->default(0); // capital goods
            $table->decimal('t6_total_itc', 18, 2)->default(0);          // total availed

            // ITC reversed (Table 7)
            $table->decimal('t7_itc_reversed', 18, 2)->default(0);

            // Net ITC available
            $table->decimal('net_itc', 18, 2)->default(0);               // t6_total_itc - t7_itc_reversed

            // Late fees (Table 18)
            $table->decimal('t18_late_fee_cgst', 18, 2)->default(0);
            $table->decimal('t18_late_fee_sgst', 18, 2)->default(0);

            // Workflow
            $table->enum('status', ['draft', 'filed', 'accepted'])->default('draft');
            $table->string('gstn_arn', 30)->nullable();   // ARN after filing
            $table->date('filed_date')->nullable();
            $table->date('due_date')->nullable();         // 31 Dec following FY end

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'gstin', 'financial_year_start'], 'gstr9_org_gstin_fy_unique');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('hsn_sac_codes', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique();
            $table->text('description');
            $table->decimal('gst_rate', 5, 2);  // 0, 5, 12, 18, 28
            $table->enum('type', ['goods', 'service'])->default('goods');

            $table->timestamps();

            $table->index('gst_rate');
            $table->index('type');
        });

        Schema::create('india_einvoice_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Document identifiers
            $table->string('document_number', 100);
            $table->enum('document_type', ['INV', 'CRN', 'DBN'])->default('INV'); // INV/credit/debit
            $table->date('document_date');
            $table->string('gstin_seller', 15);    // 15-char GSTIN
            $table->string('gstin_buyer', 15)->nullable();

            // Parties
            $table->string('seller_name', 200)->nullable();
            $table->string('buyer_name', 200)->nullable();
            $table->string('seller_state_code', 2)->nullable();   // 2-digit state code
            $table->string('buyer_state_code', 2)->nullable();

            // Amounts (INR)
            $table->decimal('taxable_value', 18, 2)->default(0);
            $table->decimal('cgst_amount', 18, 2)->default(0);
            $table->decimal('sgst_amount', 18, 2)->default(0);
            $table->decimal('igst_amount', 18, 2)->default(0);
            $table->decimal('cess_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            // IRP response
            $table->string('irn', 64)->nullable()->unique();        // 64-char SHA-256 hash
            $table->text('signed_invoice')->nullable();             // IRP-signed JSON
            $table->text('signed_qr_code')->nullable();             // base64 QR
            $table->longText('einvoice_json')->nullable();          // full e-invoice payload

            // Submission status
            $table->enum('status', ['pending', 'submitted', 'accepted', 'rejected', 'cancelled'])
                ->default('pending');
            $table->string('irp_ack_number', 50)->nullable();
            $table->timestamp('irp_ack_date')->nullable();
            $table->text('irp_response')->nullable();

            // Cancellation
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'document_number']);
            $table->index(['gstin_seller', 'document_date']);
        });

        Schema::create('itc_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('gstin_id');
            $table->tinyInteger('period_month')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->decimal('igst_available', 15, 4)->default(0);
            $table->decimal('cgst_available', 15, 4)->default(0);
            $table->decimal('sgst_available', 15, 4)->default(0);
            $table->decimal('igst_utilized', 15, 4)->default(0);
            $table->decimal('cgst_utilized', 15, 4)->default(0);
            $table->decimal('sgst_utilized', 15, 4)->default(0);
            $table->decimal('igst_closing', 15, 4)->default(0);
            $table->decimal('cgst_closing', 15, 4)->default(0);
            $table->decimal('sgst_closing', 15, 4)->default(0);
            $table->timestamps();

            $table->index('organization_id', 'itc_ledger_org_id_idx');
            $table->index('gstin_id', 'itc_ledger_gstin_id_idx');
            $table->unique(['gstin_id', 'period_month', 'period_year'], 'itc_ledger_period_unq');

            $table->foreign('gstin_id', 'itc_ledger_gstin_fk')
                ->references('id')
                ->on('gst_registrations')
                ->onDelete('restrict');
        });

        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50); // e.g., "Standard Rate", "Zero Rated"
            $table->string('code', 10); // S, Z, E, O (ZATCA codes)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_category_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->decimal('rate', 5, 2); // e.g., 15.00 for 15%
            $table->string('country_code', 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tax_category_id', 'country_code', 'effective_from']);
        });

        Schema::create('tax_determination_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('document_type', [
                'sales_invoice',
                'purchase_bill',
                'sales_order',
                'purchase_order',
                'all',
            ]);
            $table->char('from_country_code', 2)->nullable();
            $table->char('to_country_code', 2)->nullable();
            $table->string('from_region', 100)->nullable();
            $table->string('to_region', 100)->nullable();
            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete();
            $table->enum('customer_type', ['b2b', 'b2c', 'government', 'exempt', 'any'])->default('any');
            $table->enum('tax_type', ['standard', 'zero', 'exempt', 'reverse_charge', 'out_of_scope']);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->boolean('is_reverse_charge')->default(false);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type', 'is_active'], 'tax_determination_rules_org_doc_type_active_idx');
            $table->index(['organization_id', 'priority']);
        });

        Schema::create('tcs_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->date('collection_date');
            $table->decimal('collection_amount', 15, 4);
            $table->decimal('tcs_rate', 5, 2);
            $table->decimal('tcs_amount', 15, 4);
            $table->boolean('deposited')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tcs_col_org_id_idx');
            $table->index(['organization_id', 'collection_date'], 'tcs_col_org_date_idx');
            $table->index('contact_id', 'tcs_col_contact_id_idx');
            $table->index('invoice_id', 'tcs_col_invoice_id_idx');
        });

        Schema::create('tcs_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('section_code', 10);
            $table->string('description', 200);
            $table->decimal('rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id', 'tcs_cfg_org_id_idx');
            $table->unique(['organization_id', 'section_code'], 'tcs_cfg_org_section_unq');
        });

        Schema::create('tds_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('deductee_type', 20);
            $table->unsignedBigInteger('deductee_id');
            $table->tinyInteger('period_quarter')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->string('certificate_number', 30)->unique();
            $table->decimal('total_amount', 15, 4);
            $table->decimal('total_tds', 15, 4);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tds_cert_org_id_idx');
            $table->index(['organization_id', 'period_quarter', 'period_year'], 'tds_cert_org_period_idx');
            $table->index(['deductee_type', 'deductee_id'], 'tds_cert_deductee_idx');
        });

        Schema::create('tds_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->string('tan', 10)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('deductor_name', 200);
            $table->string('deductor_type', 20);
            $table->string('responsible_person', 100)->nullable();
            $table->string('designation', 100)->nullable();
            $table->timestamps();

            $table->index('organization_id', 'tds_cfg_org_id_idx');
        });

        Schema::create('tds_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->tinyInteger('quarter')->unsigned();
            $table->smallInteger('financial_year')->unsigned();
            $table->unsignedInteger('total_deductees')->default(0);
            $table->unsignedInteger('total_transactions')->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('total_tds', 15, 4)->default(0);
            $table->enum('status', ['draft', 'filed', 'revised'])->default('draft');
            $table->timestamp('filed_at')->nullable();
            $table->string('acknowledgement_number', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tds_ret_org_id_idx');
            $table->unique(['organization_id', 'quarter', 'financial_year'], 'tds_ret_period_unq');
        });

        Schema::create('tds_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_code', 10)->unique();
            $table->string('description', 255);
            $table->decimal('threshold_amount', 15, 4)->default(0);
            $table->decimal('rate_individual', 5, 2)->default(0);
            $table->decimal('rate_company', 5, 2)->default(0);
            $table->decimal('rate_no_pan', 5, 2)->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'tds_sections_active_idx');
        });

        Schema::create('tds_deductions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->enum('deductee_type', ['vendor', 'employee', 'other']);
            $table->unsignedBigInteger('deductee_id');
            $table->unsignedBigInteger('section_id');
            $table->date('payment_date');
            $table->decimal('payment_amount', 15, 4);
            $table->decimal('tds_rate', 5, 2);
            $table->decimal('tds_amount', 15, 4);
            $table->decimal('surcharge', 15, 4)->default(0);
            $table->decimal('education_cess', 15, 4)->default(0);
            $table->decimal('net_tds', 15, 4);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('challan_number', 30)->nullable();
            $table->timestamp('deposited_at')->nullable();
            $table->tinyInteger('period_quarter')->unsigned();
            $table->smallInteger('period_year')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'tds_ded_org_id_idx');
            $table->index(['organization_id', 'period_quarter', 'period_year'], 'tds_ded_org_period_idx');
            $table->index(['deductee_type', 'deductee_id'], 'tds_ded_deductee_idx');
            $table->index(['source_type', 'source_id'], 'tds_ded_source_idx');
            $table->index('section_id', 'tds_ded_section_id_idx');

            $table->foreign('section_id', 'tds_ded_section_fk')
                ->references('id')
                ->on('tds_sections')
                ->onDelete('restrict');
        });

        Schema::create('vat_return_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->char('country_code', 3);
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'ready', 'submitted', 'accepted', 'amended'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'vat_periods_org_id_idx');
            $table->index(['organization_id', 'country_code'], 'vat_periods_org_country_idx');
            $table->index(['organization_id', 'status'], 'vat_periods_org_status_idx');
            $table->index(['period_start', 'period_end'], 'vat_periods_dates_idx');
        });

        Schema::create('vat_return_boxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vat_return_period_id');
            $table->string('box_number', 10);
            $table->string('box_label', 200);
            $table->decimal('output_amount', 15, 4)->default(0);
            $table->decimal('input_amount', 15, 4)->default(0);
            $table->decimal('net_vat', 15, 4)->default(0);
            $table->timestamps();

            $table->index('vat_return_period_id', 'vat_boxes_period_id_idx');
            $table->unique(['vat_return_period_id', 'box_number'], 'vat_boxes_period_box_unq');

            $table->foreign('vat_return_period_id', 'vat_boxes_period_fk')
                ->references('id')
                ->on('vat_return_periods')
                ->onDelete('cascade');
        });

        Schema::create('vat_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->enum('transaction_type', ['sale', 'purchase', 'adjustment']);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('tax_period');
            $table->decimal('taxable_amount', 15, 4)->default(0);
            $table->decimal('vat_amount', 15, 4)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->char('country_code', 3);
            $table->boolean('is_exempt')->default(false);
            $table->boolean('is_zero_rated')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'vat_txn_org_id_idx');
            $table->index(['organization_id', 'tax_period'], 'vat_txn_org_period_idx');
            $table->index(['organization_id', 'transaction_type'], 'vat_txn_org_type_idx');
            $table->index(['source_type', 'source_id'], 'vat_txn_source_idx');
            $table->index('country_code', 'vat_txn_country_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('vat_transactions');
        Schema::dropIfExists('vat_return_boxes');
        Schema::dropIfExists('vat_return_periods');
        Schema::dropIfExists('tds_deductions');
        Schema::dropIfExists('tds_sections');
        Schema::dropIfExists('tds_returns');
        Schema::dropIfExists('tds_configurations');
        Schema::dropIfExists('tds_certificates');
        Schema::dropIfExists('tcs_configurations');
        Schema::dropIfExists('tcs_collections');
        Schema::dropIfExists('tax_determination_rules');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_categories');
        Schema::dropIfExists('itc_ledger');
        Schema::dropIfExists('india_einvoice_submissions');
        Schema::dropIfExists('hsn_sac_codes');
        Schema::dropIfExists('gstr9_returns');
        Schema::dropIfExists('gstr3b_returns');
        Schema::dropIfExists('gstr1_b2b_invoices');
        Schema::dropIfExists('gstr1_returns');
        Schema::dropIfExists('gst_returns');
        Schema::dropIfExists('gst_registrations');
        Schema::dropIfExists('ewaybills');
        Schema::dropIfExists('eway_bills');
    }
};
