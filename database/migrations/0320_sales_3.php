<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('credit_note_number', 30)->nullable();
            $table->string('credit_note_type', 20)->default('sales'); // sales, purchase

            // Reference
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('bill_id')->nullable();

            // Contact
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_tax_number')->nullable();

            // Dates
            $table->date('credit_note_date')->nullable();
            $table->date('original_invoice_date')->nullable();

            // Amounts
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('base_total', 15, 2)->default(0);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->decimal('available_amount', 15, 2);

            // Reason
            $table->string('reason_code', 30)->nullable(); // return, discount, error, damaged, etc.
            $table->text('reason')->nullable();

            // Status
            $table->string('status', 20)->default('draft'); // draft, approved, applied, refunded, cancelled

            // Compliance
            $table->string('compliance_status', 30)->nullable();
            $table->string('compliance_uuid')->nullable();

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable();

            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'credit_note_type', 'status']);
            $table->index(['contact_id', 'status']);
            $table->index(['invoice_id']);

            $table->foreign('bill_id', 'credit_note_bill_fk')
                ->references('id')->on('bills')->nullOnDelete();
            $table->foreign('wallet_transaction_id', 'credit_note_wallet_txn_fk')
                ->references('id')->on('wallet_transactions')->nullOnDelete();
        });

        Schema::create('credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->nullableMorphs('applied_to'); // invoice, bill
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->date('applied_date')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('debit_note_number', 30);

            // Reference
            $table->foreignId('bill_id')->nullable();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('contact_name');

            // Dates and amounts (similar structure to credit notes)
            $table->date('debit_note_date');
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('available_amount', 15, 2);

            $table->string('reason_code', 30)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('draft');

            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['contact_id', 'status']);

            $table->foreign('bill_id', 'debit_note_bill_fk')
                ->references('id')->on('bills')->nullOnDelete();
        });

        Schema::create('intercompany_purchase_order_links', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('intercompany_sales_order_id');
            $table->foreign('intercompany_sales_order_id', 'icpol_icso_fk')
                ->references('id')->on('intercompany_sales_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id', 'icpol_po_fk')
                ->references('id')->on('purchase_orders')->nullOnDelete();

            $table->unsignedBigInteger('buying_organization_id');
            $table->foreign('buying_organization_id', 'icpol_buying_org_fk')
                ->references('id')->on('organizations')->restrictOnDelete();

            $table->enum('status', ['pending', 'linked', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->unique(['intercompany_sales_order_id'], 'icpol_icso_unq');
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number', 30);
            $table->foreignId('supplier_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();

            $table->date('return_date');
            $table->foreignId('return_reason_id')->nullable()->constrained('return_reasons')->nullOnDelete();
            $table->text('reason_notes')->nullable();
            $table->string('return_type', 20)->default('debit_note'); // debit_note, replacement, refund

            // Amounts
            $table->string('currency_code', 3);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            // Status
            $table->string('status', 30)->default('draft'); // draft, approved, shipped, received_by_supplier, completed, cancelled
            $table->string('resolution_type', 30)->nullable(); // debit_note, replacement, refund
            $table->foreignId('debit_note_id')->nullable();
            $table->foreignId('replacement_po_id')->nullable();

            // Shipping
            $table->string('shipping_method')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('supplier_received_at')->nullable();

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'return_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['bill_id']);

            $table->foreign('debit_note_id', 'pur_ret_debit_note_fk')
                ->references('id')->on('debit_notes')->nullOnDelete();
            $table->foreign('replacement_po_id', 'pur_ret_replacement_po_fk')
                ->references('id')->on('purchase_orders')->nullOnDelete();
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number', 30);
            $table->foreignId('customer_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();

            // Return details
            $table->date('return_date');
            $table->foreignId('return_reason_id')->nullable()->constrained('return_reasons')->nullOnDelete();
            $table->text('reason_notes')->nullable();
            $table->string('return_type', 20)->default('refund'); // refund, exchange, credit_note, replacement

            // Amounts
            $table->string('currency_code', 3);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('restocking_fee', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);

            // Status workflow
            $table->string('status', 30)->default('pending'); // pending, approved, received, inspected, completed, rejected, cancelled
            $table->string('inspection_status', 20)->nullable(); // pending, passed, failed, partial
            $table->text('inspection_notes')->nullable();

            // Resolution
            $table->string('resolution_type', 30)->nullable(); // full_refund, partial_refund, exchange, credit_note, replacement, rejected
            $table->foreignId('credit_note_id')->nullable(); // Link to generated credit note
            $table->foreignId('refund_id')->nullable(); // Link to refund record
            $table->foreignId('exchange_order_id')->nullable(); // Link to replacement sales order

            // Inventory
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->boolean('restock_items')->default(true);
            $table->boolean('items_received')->default(false);
            $table->timestamp('received_at')->nullable();

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'return_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['invoice_id']);
            $table->index(['organization_id', 'return_date']);
        });

        Schema::create('exchange_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('exchange_number', 30);
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('contacts')->cascadeOnDelete();

            // Price difference
            $table->decimal('original_total', 15, 2);
            $table->decimal('exchange_total', 15, 2);
            $table->decimal('price_difference', 15, 2)->default(0); // Positive = customer pays, negative = refund
            $table->string('difference_resolution', 30)->nullable(); // payment, credit_note, waived

            // Linked documents
            $table->foreignId('new_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('new_sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();

            $table->string('status', 30)->default('pending'); // pending, processing, shipped, completed, cancelled
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'exchange_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['customer_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('refund_number', 30);
            $table->string('refund_type', 20); // customer_refund, supplier_refund

            // Source
            $table->morphs('refundable'); // credit_note, advance_payment, wallet
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('sales_return_id')->nullable();
            $table->unsignedBigInteger('payment_received_id')->nullable();

            // Refund details
            $table->date('refund_date');
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('amount', 15, 2);
            $table->string('refund_method', 30); // cash, bank_transfer, original_payment_method
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('transaction_reference')->nullable();

            $table->string('status', 20)->default('pending'); // pending, approved, processed, cancelled
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'refund_type', 'status']);
            $table->index(['contact_id']);

            $table->foreign('sales_return_id', 'refund_sales_return_fk')
                ->references('id')->on('sales_returns')->nullOnDelete();
            $table->foreign('payment_received_id', 'refund_payment_received_fk')
                ->references('id')->on('payments_received')->nullOnDelete();
        });

        Schema::create('rma_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('rma_number', 30);
            $table->string('rma_type', 20); // sales_return, purchase_return
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();

            $table->text('description');
            $table->string('requested_resolution', 30); // refund, exchange, repair, credit
            $table->string('status', 30)->default('pending'); // pending, approved, in_progress, completed, rejected, expired
            $table->date('request_date');
            $table->date('expiry_date')->nullable(); // RMA validity period

            $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->nullOnDelete();
            $table->foreignId('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'rma_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['rma_type', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Polymorphic relationship
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            // Event type
            $table->string('event', 20); // created, updated, deleted, restored

            // Changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();

            // Timestamp (no updated_at needed for audit logs)
            $table->timestamp('created_at')->useCurrent();

            // Indexes for querying
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('organization_id');
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('group', 50); // e.g., 'general', 'invoice', 'email', 'tax'
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, integer, boolean, json, array

            $table->timestamps();

            // Unique key within organization/group
            $table->unique(['organization_id', 'group', 'key']);
            $table->index('group');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('rma_requests');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('exchange_orders');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('intercompany_purchase_order_links');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('credit_note_applications');
        Schema::dropIfExists('credit_notes');
    }
};
