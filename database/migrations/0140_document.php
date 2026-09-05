<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->string('name');
            $table->string('color', 7)->nullable(); // Hex color
            $table->string('icon', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // System folders can't be deleted
            $table->string('access_level', 20)->default('organization'); // organization, branch, private
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'parent_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->string('name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('extension', 10);
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('document_type', 50)->nullable(); // contract, invoice, receipt, id_proof, etc.
            $table->date('document_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expiry_notified')->default(false);
            $table->nullableMorphs('documentable'); // Attached to: employee, customer, invoice, etc.
            $table->string('access_level', 20)->default('organization'); // organization, branch, private
            $table->boolean('is_archived')->default(false);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'folder_id']);
            $table->index(['organization_id', 'document_type']);
            // morphs() already creates the documentable index
            $table->index(['organization_id', 'expiry_date']);
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['name', 'description']);
            }
        });

        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_email');
            $table->string('signer_name');
            $table->string('status', 20)->default('pending'); // pending, signed, declined, expired
            $table->text('signature_data')->nullable(); // Base64 signature image
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('verification_code', 32)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status']);
            $table->index(['signer_email', 'status']);
        });

        Schema::create('document_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30); // viewed, downloaded, uploaded, edited, shared, deleted
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });

        Schema::create('document_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('document_folders')->cascadeOnDelete();
            $table->morphs('permissible'); // user or role
            $table->string('permission', 20); // view, download, edit, delete, manage
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'permissible_type', 'permissible_id'], 'doc_perm_doc_permissible_idx');
            $table->index(['folder_id', 'permissible_type', 'permissible_id'], 'doc_perm_folder_permissible_idx');
        });

        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->string('share_type', 20)->default('link'); // link, email
            $table->string('recipient_email')->nullable();
            $table->string('access_code', 32)->nullable(); // Optional password
            $table->boolean('allow_download')->default(true);
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['uuid', 'is_active']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('change_summary')->nullable();
            $table->text('change_notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 30); // stripe, paypal, tap, moyasar, hyperpay, mada
            $table->json('credentials')->nullable(); // Encrypted
            $table->json('settings')->nullable();
            $table->string('mode', 10)->default('test'); // test, live
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('supported_currencies')->nullable();
            $table->json('supported_methods')->nullable(); // card, mada, apple_pay, etc.
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('default_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_receipt')->default(false);
            $table->decimal('budget_limit', 15, 2)->nullable(); // Monthly budget
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'parent_id']);
        });

        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name', 100);
            $table->foreignId('custodian_id')->constrained('users');
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->decimal('opening_balance', 15, 4)->default(0);
            $table->decimal('current_balance', 15, 4)->default(0);
            $table->decimal('max_transaction_limit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'petty_cash_funds_org_active_idx');
            $table->index(['organization_id', 'branch_id'], 'petty_cash_funds_org_branch_idx');
        });

        Schema::create('petty_cash_replenishments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->date('replenishment_date');
            $table->decimal('amount', 15, 4);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['requested', 'approved', 'disbursed'])->default('requested');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'status'], 'petty_cash_replen_fund_status_idx');
            $table->index(['fund_id', 'replenishment_date'], 'petty_cash_replen_fund_date_idx');
        });

        Schema::create('petty_cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->string('voucher_number', 30)->unique();
            $table->date('voucher_date');
            $table->enum('transaction_type', ['receipt', 'payment']);
            $table->decimal('amount', 15, 4);
            $table->string('description', 500);
            $table->string('category', 100)->nullable();
            $table->string('payee_payer', 200)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'voucher_date'], 'petty_cash_vouchers_fund_date_idx');
            $table->index(['fund_id', 'status'], 'petty_cash_vouchers_fund_status_idx');
        });

        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->string('transaction_number', 50)->nullable();
            $table->date('transaction_date');
            $table->enum('transaction_type', ['receipt', 'payment', 'replenishment'])->default('payment');
            $table->decimal('amount', 15, 2);
            $table->string('description', 500)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('payee_payer', 200)->nullable();
            $table->string('receipt_reference', 100)->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'posted', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fund_id', 'transaction_date'], 'pct_org_fund_date_idx');
            $table->index(['organization_id', 'status'], 'pct_org_status_idx');
        });

        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rule_type');        // velocity, amount, geographic, behavioral, pattern
            $table->string('entity_type');      // invoice, payment, login, contact
            $table->json('conditions');         // rule-specific condition parameters
            $table->string('severity');         // low, medium, high, critical
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_block')->default(false); // block transaction automatically
            $table->unsignedInteger('score_impact')->default(10); // fraud score contribution
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active', 'rule_type']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_rules');
        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('petty_cash_vouchers');
        Schema::dropIfExists('petty_cash_replenishments');
        Schema::dropIfExists('petty_cash_funds');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('document_permissions');
        Schema::dropIfExists('document_activities');
        Schema::dropIfExists('digital_signatures');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_folders');
    }
};
