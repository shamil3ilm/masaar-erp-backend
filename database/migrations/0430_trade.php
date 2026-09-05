<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoterms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10); // EXW, FCA, CPT, CIP, DAP, DPU, DDP, FAS, FOB, CFR, CIF
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 4)->default('2020'); // 2010, 2020
            $table->text('seller_responsibilities')->nullable();
            $table->text('buyer_responsibilities')->nullable();
            $table->string('risk_transfer_point')->nullable();
            $table->string('cost_transfer_point')->nullable();
            $table->string('transport_modes', 50)->nullable(); // all, sea_inland_waterway
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        Schema::create('letters_of_credit', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('lc_number', 50);
            $table->string('lc_type', 20); // import, export, standby, revolving, transferable, back_to_back
            $table->boolean('is_irrevocable')->default(true);
            $table->boolean('is_confirmed')->default(false);

            // Banks
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('issuing_bank')->nullable();
            $table->string('issuing_bank_swift', 11)->nullable();
            $table->string('advising_bank')->nullable();
            $table->string('advising_bank_swift', 11)->nullable();
            $table->string('confirming_bank')->nullable();
            $table->string('negotiating_bank')->nullable();

            // Parties
            $table->foreignId('applicant_id')->nullable()->constrained('contacts')->nullOnDelete(); // Importer
            $table->foreignId('beneficiary_id')->nullable()->constrained('contacts')->nullOnDelete(); // Exporter

            // Financial
            $table->string('currency_code', 3);
            $table->decimal('amount', 18, 4);
            $table->decimal('tolerance_percent', 5, 2)->default(0); // +/- % tolerance
            $table->decimal('utilized_amount', 18, 4)->default(0);
            $table->decimal('available_amount', 18, 4)->default(0);

            // Dates
            $table->date('issue_date')->nullable();
            $table->date('expiry_date');
            $table->date('latest_shipment_date')->nullable();
            $table->string('place_of_expiry')->nullable();
            $table->unsignedSmallInteger('presentation_days')->default(21); // Days after shipment to present docs

            // Trade terms
            $table->string('incoterm', 10)->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->boolean('partial_shipments_allowed')->default(false);
            $table->boolean('transhipment_allowed')->default(false);

            // Required documents
            $table->json('required_documents')->nullable(); // List of required trade documents

            // Terms
            $table->text('terms_and_conditions')->nullable();
            $table->text('special_conditions')->nullable();

            // Status
            $table->string('status', 20)->default('draft'); // draft, applied, issued, amended, partially_utilized, fully_utilized, expired, cancelled
            $table->text('notes')->nullable();

            // Linked
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'lc_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['beneficiary_id']);
            $table->index(['applicant_id']);
            $table->index(['expiry_date']);
        });

        Schema::create('import_export_shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipment_number', 50);
            $table->string('shipment_type', 10); // import, export

            // Source
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete(); // Supplier or Customer

            // Trade terms
            $table->string('incoterm', 10)->nullable();
            $table->string('transport_mode', 20); // sea, air, road, rail, multimodal, courier
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number', 50)->nullable();
            $table->json('container_numbers')->nullable();
            $table->string('bill_of_lading', 50)->nullable();
            $table->string('airway_bill', 50)->nullable();

            // Ports
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('place_of_delivery')->nullable();
            $table->string('country_of_origin', 3)->nullable();
            $table->string('country_of_destination', 3)->nullable();

            // Dates
            $table->date('estimated_departure')->nullable();
            $table->date('actual_departure')->nullable();
            $table->date('estimated_arrival')->nullable();
            $table->date('actual_arrival')->nullable();
            $table->date('delivery_date')->nullable();

            // Values
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('fob_value', 18, 4)->default(0);
            $table->decimal('freight_value', 18, 4)->default(0);
            $table->decimal('insurance_value', 18, 4)->default(0);
            $table->decimal('cif_value', 18, 4)->default(0);
            $table->decimal('other_charges', 18, 4)->default(0);

            // Weights & dimensions
            $table->decimal('gross_weight_kg', 15, 4)->nullable();
            $table->decimal('net_weight_kg', 15, 4)->nullable();
            $table->unsignedInteger('total_packages')->nullable();
            $table->decimal('total_cbm', 12, 4)->nullable(); // Cubic meters

            // Linked
            $table->foreignId('customs_declaration_id')->nullable()->constrained('customs_declarations')->nullOnDelete();
            $table->foreignId('lc_id')->nullable()->constrained('letters_of_credit')->nullOnDelete();
            $table->foreignId('landed_cost_voucher_id')->nullable(); // Will be linked after creation

            // Insurance
            $table->string('insurance_policy_number')->nullable();
            $table->string('insurance_company')->nullable();

            // Status
            $table->string('status', 20)->default('pending'); // pending, in_transit, at_port, customs_clearance, cleared, delivered, cancelled
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'shipment_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'shipment_type']);
            $table->index(['contact_id']);
            $table->index(['purchase_order_id']);
            $table->index(['invoice_id']);
        });

        Schema::create('import_export_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('import_export_shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('total_value', 18, 4);
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->string('tariff_code', 12)->nullable();
            $table->string('country_of_origin', 3)->nullable();
            $table->timestamps();

            $table->index(['shipment_id']);
        });

        Schema::create('landed_cost_vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_number', 30);
            $table->date('voucher_date');

            // Source
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('import_export_shipments')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();

            // Totals
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('total_purchase_value', 18, 4)->default(0);
            $table->decimal('total_additional_charges', 18, 4)->default(0);
            $table->decimal('total_landed_cost', 18, 4)->default(0);

            // Allocation
            $table->string('allocation_method', 20)->default('value'); // value, quantity, weight, volume, manual

            // Status
            $table->string('status', 20)->default('draft'); // draft, posted, cancelled
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'voucher_number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('landed_cost_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('landed_cost_vouchers')->cascadeOnDelete();
            $table->string('charge_type', 30); // customs_duty, freight, insurance, clearing_charges, port_charges, handling, demurrage, inspection, fumigation, documentation, exchange_difference, other
            $table->string('description');
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();

            // Amount
            $table->decimal('amount', 18, 4);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('base_amount', 18, 4); // In org base currency

            // Accounting
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_allocated')->default(false);

            $table->timestamps();

            $table->index(['voucher_id']);
        });

        Schema::create('landed_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('landed_cost_vouchers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('purchase_value', 18, 4); // Original purchase value
            $table->decimal('weight_kg', 12, 4)->nullable();
            $table->decimal('volume_cbm', 12, 4)->nullable();

            // Allocated charges
            $table->decimal('allocated_customs_duty', 18, 4)->default(0);
            $table->decimal('allocated_freight', 18, 4)->default(0);
            $table->decimal('allocated_insurance', 18, 4)->default(0);
            $table->decimal('allocated_clearing', 18, 4)->default(0);
            $table->decimal('allocated_other', 18, 4)->default(0);
            $table->decimal('total_additional_cost', 18, 4)->default(0);
            $table->decimal('total_landed_cost', 18, 4)->default(0);
            $table->decimal('landed_cost_per_unit', 15, 4)->default(0);

            $table->timestamps();

            $table->index(['voucher_id']);
            $table->index(['product_id']);
        });

        Schema::create('lc_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lc_id')->constrained('letters_of_credit')->cascadeOnDelete();
            $table->unsignedSmallInteger('amendment_number');
            $table->date('amendment_date');
            $table->text('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['lc_id', 'amendment_number']);
        });

        Schema::create('trade_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->json('member_countries'); // Country codes
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code']);
        });

        Schema::create('preferential_duty_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_agreement_id')->constrained('trade_agreements')->cascadeOnDelete();
            $table->string('tariff_code', 12);
            $table->string('origin_country', 3); // Exporting country
            $table->string('destination_country', 3); // Importing country
            $table->decimal('preferential_rate', 8, 4); // Reduced duty rate
            $table->decimal('normal_rate', 8, 4)->nullable(); // Normal MFN rate for comparison
            $table->string('rule_of_origin')->nullable(); // What qualifies for preferential treatment
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tariff_code', 'origin_country', 'destination_country'], 'pref_duty_tariff_origin_dest_idx');
            $table->index(['trade_agreement_id']);
        });

        Schema::create('trade_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30); // bill_of_lading, airway_bill, certificate_of_origin, packing_list, commercial_invoice, insurance_cert, inspection_cert, phytosanitary, fumigation, customs_invoice, consular_invoice
            $table->string('document_number', 100);
            $table->string('reference', 100)->nullable();

            // Linked entity
            $table->string('source_type', 100)->nullable(); // PurchaseOrder, Invoice, ImportShipment, CustomsDeclaration
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Details
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->string('issuing_country', 3)->nullable();

            // File
            $table->string('file_path')->nullable();
            $table->string('file_type', 50)->nullable();
            $table->unsignedInteger('file_size')->nullable();

            $table->string('status', 20)->default('active'); // active, expired, cancelled, draft
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type']);
            $table->index(['source_type', 'source_id']);
            $table->index(['document_number']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('trade_documents');
        Schema::dropIfExists('preferential_duty_rates');
        Schema::dropIfExists('trade_agreements');
        Schema::dropIfExists('lc_amendments');
        Schema::dropIfExists('landed_cost_items');
        Schema::dropIfExists('landed_cost_charges');
        Schema::dropIfExists('landed_cost_vouchers');
        Schema::dropIfExists('import_export_shipment_items');
        Schema::dropIfExists('import_export_shipments');
        Schema::dropIfExists('letters_of_credit');
        Schema::dropIfExists('incoterms');
    }
};
