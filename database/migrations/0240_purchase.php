<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ers_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ers_run_org_fk')->references('id')->on('organizations');
            $table->date('run_date');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedBigInteger('run_by')->nullable();
            $table->foreign('run_by', 'ers_run_by_fk')->references('id')->on('users');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pcards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->string('card_number_masked', 20);
            $table->unsignedBigInteger('card_holder_id');
            $table->foreign('card_holder_id', 'pcard_holder_fk')->references('id')->on('users');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'pcard_cc_fk')->references('id')->on('cost_centers')->nullOnDelete();
            $table->decimal('credit_limit', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->date('valid_from');
            $table->date('valid_to');
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->decimal('single_transaction_limit', 18, 4)->nullable();
            $table->decimal('monthly_limit', 18, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pcard_statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('pcard_id');
            $table->foreign('pcard_id', 'pcard_stmt_card_fk')->references('id')->on('pcards');
            $table->date('statement_period_start');
            $table->date('statement_period_end');
            $table->decimal('total_amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['uploaded', 'reconciled', 'posted'])->default('uploaded');
            $table->unsignedBigInteger('uploaded_by');
            $table->foreign('uploaded_by', 'pcard_stmt_usr_fk')->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pcard_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->unsignedBigInteger('pcard_statement_id');
            $table->foreign('pcard_statement_id', 'pcard_txn_stmt_fk')->references('id')->on('pcard_statements')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('merchant_name');
            $table->string('merchant_category_code', 10)->nullable();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->unsignedBigInteger('gl_account_id')->nullable();
            $table->foreign('gl_account_id', 'pcard_txn_gl_fk')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id', 'pcard_txn_cc_fk')->references('id')->on('cost_centers')->nullOnDelete();
            $table->enum('status', ['unreconciled', 'reconciled', 'disputed'])->default('unreconciled');
            $table->boolean('receipt_attached')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('requisition_number', 30);
            $table->date('requisition_date');
            $table->date('required_by_date')->nullable();
            $table->enum('requisition_type', ['standard', 'subcontracting', 'consignment', 'stock_transfer'])->default('standard');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'converted_to_po', 'cancelled'])->default('draft');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'requisition_number'], 'pr_org_num_unique');
            $table->index(['organization_id', 'status'], 'pr_org_status_idx');
        });

        Schema::create('release_strategies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('document_type', ['purchase_order', 'purchase_requisition']);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type', 'is_active']);
        });

        Schema::create('release_strategy_levels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('release_strategy_id')->constrained('release_strategies')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('role', 100);
            $table->decimal('min_amount', 15, 4)->nullable();
            $table->decimal('max_amount', 15, 4)->nullable();
            $table->string('label', 100);
            $table->timestamps();

            $table->unique(['release_strategy_id', 'level']);
            $table->index(['organization_id', 'release_strategy_id'], 'release_strategy_levels_org_strategy_idx');
        });

        Schema::create('release_strategy_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('release_strategy_id')->constrained('release_strategies')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('release_strategy_levels')->cascadeOnDelete();
            $table->enum('document_type', ['purchase_order', 'purchase_requisition']);
            $table->unsignedBigInteger('document_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comments')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'document_type', 'document_id'], 'release_strategy_approvals_org_doc_type_doc_id_idx');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('rfq_headers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('rfq_number', 30);
            $table->string('title', 200);
            $table->enum('status', ['draft', 'sent', 'closed', 'cancelled', 'awarded'])->default('draft');
            $table->date('submission_deadline')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->unique(['organization_id', 'rfq_number'], 'rfq_headers_org_number_unique');
            $table->index(['organization_id', 'status'], 'rfq_headers_org_status_idx');
        });

        Schema::create('supplier_evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['quality', 'delivery', 'price', 'service', 'compliance'])->default('quality');
            $table->decimal('weight_percent', 5, 2)->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_evaluation_criteria');
        Schema::dropIfExists('rfq_headers');
        Schema::dropIfExists('release_strategy_approvals');
        Schema::dropIfExists('release_strategy_levels');
        Schema::dropIfExists('release_strategies');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('pcard_transactions');
        Schema::dropIfExists('pcard_statements');
        Schema::dropIfExists('pcards');
        Schema::dropIfExists('ers_runs');
    }
};
