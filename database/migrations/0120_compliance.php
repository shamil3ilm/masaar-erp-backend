<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahrain_vat_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Return period
            $table->enum('period_type', ['quarterly', 'monthly'])->default('quarterly');
            $table->tinyInteger('period_quarter')->nullable();  // 1-4 for quarterly
            $table->tinyInteger('period_month')->nullable();    // 1-12 for monthly
            $table->year('period_year');
            $table->date('period_start');
            $table->date('period_end');

            // Output VAT (sales)
            $table->decimal('standard_rated_supplies', 18, 4)->default(0);   // Box 1: taxable sales (BHD)
            $table->decimal('zero_rated_supplies', 18, 4)->default(0);       // Box 2
            $table->decimal('exempt_supplies', 18, 4)->default(0);           // Box 3
            $table->decimal('output_vat', 18, 4)->default(0);                // Box 4: 10% × Box 1

            // Input VAT (purchases)
            $table->decimal('standard_rated_purchases', 18, 4)->default(0); // Box 5
            $table->decimal('capital_goods_input_tax', 18, 4)->default(0);  // Box 6
            $table->decimal('total_input_vat', 18, 4)->default(0);          // Box 7

            // Net position
            $table->decimal('net_vat_payable', 18, 4)->default(0);          // Box 8 (negative = refund)

            // Tax parameters
            $table->decimal('vat_rate', 7, 4)->default(10.0);               // 10% (effective Jan 2022)

            // Workflow
            $table->enum('status', ['draft', 'submitted', 'accepted', 'paid'])->default('draft');
            $table->string('nbr_reference', 100)->nullable();
            $table->date('filing_due_date')->nullable();
            $table->date('filed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'period_year', 'period_quarter', 'period_month'], 'bh_vat_period_unique');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('dps_sanction_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('list_name', 100);
            $table->string('list_authority', 50); // OFAC|EU|UN|HMT|local|other
            $table->string('list_type', 20); // denied_party|embargo|debarred
            $table->dateTime('last_updated_at')->nullable();
            $table->integer('entry_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(false);
            $table->string('sync_url', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dps_list_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dps_sanction_list_id')
                ->constrained('dps_sanction_lists')
                ->cascadeOnDelete();
            $table->string('entry_type', 20); // person|entity|vessel|aircraft
            $table->string('name', 200);
            $table->json('aliases')->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('id_number', 100)->nullable();
            $table->string('program', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['dps_sanction_list_id', 'is_active'], 'dps_entries_list_active_idx');
            $table->index('name', 'dps_entries_name_idx');
            $table->index('country_code', 'dps_entries_country_idx');
        });

        Schema::create('dps_screening_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('screened_entity_type', 20); // contact|vendor|customer
            $table->unsignedBigInteger('screened_entity_id');
            $table->dateTime('screening_date');
            $table->decimal('match_threshold', 5, 2)->default(80);
            $table->string('status', 20)->default('clean'); // clean|potential_match|confirmed_match|cleared
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cleared_at')->nullable();
            $table->text('clearance_notes')->nullable();
            $table->string('triggered_by', 50); // manual|auto_transaction|batch
            $table->timestamps();

            $table->index(['screened_entity_type', 'screened_entity_id'], 'dps_runs_entity_idx');
            $table->index(['status', 'screening_date'], 'dps_runs_status_date_idx');
        });

        Schema::create('dps_screening_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dps_screening_run_id')
                ->constrained('dps_screening_runs')
                ->cascadeOnDelete();
            $table->foreignId('dps_list_entry_id')
                ->constrained('dps_list_entries')
                ->cascadeOnDelete();
            $table->decimal('match_score', 5, 2);
            $table->string('matched_field', 30); // name|alias|id_number|address
            $table->boolean('is_false_positive')->default(false);
            $table->timestamps();

            $table->index('dps_screening_run_id', 'dps_results_run_idx');
        });

        Schema::create('uae_cit_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();

            $table->year('tax_year');

            // Income computation
            $table->decimal('accounting_income', 18, 4)->default(0);    // net profit per books
            $table->decimal('add_backs', 18, 4)->default(0);            // non-deductible expenses
            $table->decimal('deductions', 18, 4)->default(0);           // exempt income / allowances
            $table->decimal('taxable_income', 18, 4)->default(0);       // accounting_income + add_backs - deductions

            // Thresholds (AED)
            $table->decimal('zero_rate_threshold', 18, 4)->default(375000.0);   // AED 375,000
            $table->decimal('small_business_threshold', 18, 4)->default(3000000.0); // AED 3,000,000

            // Tax computation
            $table->decimal('cit_rate', 7, 4)->default(9.0000);         // 9%
            $table->boolean('small_business_relief')->default(false);   // elected SBR
            $table->decimal('cit_due', 18, 4)->default(0);              // payable tax

            // Payments
            $table->decimal('cit_paid', 18, 4)->default(0);
            $table->decimal('cit_remaining', 18, 4)->default(0);

            // Workflow
            $table->enum('status', ['draft', 'submitted', 'assessed', 'paid'])->default('draft');
            $table->string('emara_tax_reference', 100)->nullable();
            $table->date('filing_due_date')->nullable();
            $table->date('filed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'tax_year'], 'cit_org_year_unique');
            $table->index(['organization_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('uae_cit_assessments');
        Schema::dropIfExists('dps_screening_results');
        Schema::dropIfExists('dps_screening_runs');
        Schema::dropIfExists('dps_list_entries');
        Schema::dropIfExists('dps_sanction_lists');
        Schema::dropIfExists('bahrain_vat_returns');
    }
};
