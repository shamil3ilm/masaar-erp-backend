<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            // credit_note, refund and return reverse a sale and carry negative amounts.
            $table->enum('transaction_type', ['sale', 'purchase', 'credit_note', 'refund', 'return']);
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
        Schema::dropIfExists('tax_determination_rules');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_categories');
        Schema::dropIfExists('hsn_sac_codes');
    }
};
