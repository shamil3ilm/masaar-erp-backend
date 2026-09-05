<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('re_occupancy_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('snapshot_type');                        // building|property|portfolio
            $table->unsignedBigInteger('reference_id');             // building_id|property_id|portfolio_id
            $table->date('snapshot_date');
            $table->unsignedInteger('total_units');
            $table->unsignedInteger('occupied_units');
            $table->unsignedInteger('vacant_units');
            $table->decimal('occupancy_rate', 5, 2);               // percentage 0–100
            $table->decimal('total_area_sqm', 15, 4)->default(0);
            $table->decimal('occupied_area_sqm', 15, 4)->default(0);
            $table->decimal('area_occupancy_rate', 5, 2)->default(0);
            $table->decimal('potential_rent', 15, 2)->default(0);  // sum of market rent of all units
            $table->decimal('actual_rent', 15, 2)->default(0);     // sum of contract rent for occupied units
            $table->string('currency', 3)->default('SAR');
            $table->timestamps();

            $table->unique(['organization_id', 'snapshot_type', 'reference_id', 'snapshot_date'], 're_occ_snap_org_type_ref_date_unique');
            $table->index(['organization_id', 'snapshot_type', 'reference_id'], 're_occ_snap_org_type_ref_idx');
        });

        Schema::create('re_portfolios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('type', 30)->default('commercial');
            // type: commercial|residential|industrial|mixed|retail|hospitality
            $table->string('currency_code', 5)->default('SAR');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 're_portfolios_org_code_uniq');
            $table->index(['organization_id', 'is_active'], 're_portfolios_org_active_idx');
        });

        Schema::create('re_posting_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('run_number', 50);
            $table->string('type', 30)->default('rent');
            // type: rent|service_charge|deposit_interest|all
            $table->date('posting_date');
            $table->integer('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 20)->default('draft');
            // status: draft|simulated|posted|reversed
            $table->unsignedInteger('contracts_processed')->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('currency_code', 5)->default('SAR');
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'run_number'], 're_posting_runs_org_num_uniq');
            $table->index(['organization_id', 'type', 'period_year', 'period_month'], 're_posting_runs_org_type_period_idx');
        });

        Schema::create('re_properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained('re_portfolios')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('type', 30)->default('commercial'); // commercial|residential|industrial|mixed
            $table->string('street_address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->decimal('total_area_sqm', 14, 4)->default(0);
            $table->decimal('land_area_sqm', 14, 4)->nullable();
            $table->decimal('current_valuation', 18, 4)->nullable();
            $table->string('valuation_currency', 5)->default('SAR');
            $table->date('valuation_date')->nullable();
            $table->string('ownership_type', 30)->default('owned'); // owned|leased_in|managed
            $table->string('status', 20)->default('active'); // active|inactive|under_development|disposed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 're_properties_org_code_uniq');
            $table->index(['organization_id', 'portfolio_id', 'status'], 're_properties_org_portfolio_status_idx');
        });

        Schema::create('re_buildings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('re_properties')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->unsignedSmallInteger('floors_above_ground')->default(1);
            $table->unsignedSmallInteger('floors_below_ground')->default(0);
            $table->decimal('gross_area_sqm', 14, 4)->default(0);
            $table->decimal('net_lettable_area_sqm', 14, 4)->default(0); // NLA
            $table->year('year_built')->nullable();
            $table->string('construction_type', 50)->nullable(); // concrete|steel|wood|etc
            $table->string('status', 20)->default('active'); // active|under_renovation|demolished
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'property_id', 'code'], 're_buildings_org_property_code_uniq');
            $table->index(['organization_id', 'property_id'], 're_buildings_org_property_idx');
        });

        Schema::create('re_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('re_buildings')->cascadeOnDelete();
            $table->smallInteger('floor_number'); // negative = basement
            $table->string('floor_label', 50)->nullable(); // "Ground Floor", "Mezzanine", "B1"
            $table->decimal('total_area_sqm', 14, 4)->default(0);
            $table->decimal('lettable_area_sqm', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['building_id', 'floor_number'], 're_floors_building_num_uniq');
        });

        Schema::create('re_rental_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('re_buildings')->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('re_floors')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 200)->nullable();
            $table->string('unit_type', 30)->default('office');
            // unit_type: office|retail|residential|parking|storage|warehouse|land
            $table->decimal('area_sqm', 14, 4)->default(0);
            $table->string('status', 20)->default('vacant');
            // status: vacant|occupied|reserved|under_maintenance|decommissioned
            $table->string('usage_type', 50)->nullable(); // sub-classification
            $table->unsignedTinyInteger('rooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->boolean('has_parking')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'building_id', 'code'], 're_rental_units_org_building_code_uniq');
            $table->index(['organization_id', 'status'], 're_rental_units_org_status_idx');
            $table->index(['organization_id', 'building_id', 'status'], 're_rental_units_org_building_status_idx');
        });

        Schema::create('re_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number', 50);
            $table->string('contract_type', 20)->default('lease_out');
            // contract_type: lease_out (we are landlord) | lease_in (we are tenant)
            $table->foreignId('rental_unit_id')->constrained('re_rental_units')->cascadeOnDelete();
            // counterparty: tenant (lease_out) or landlord (lease_in) — flexible reference
            $table->string('counterparty_type', 30)->nullable(); // contact|vendor|other
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->string('counterparty_name', 200)->nullable(); // denormalized for display
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = indefinite
            $table->date('notice_date')->nullable(); // required notice for termination
            $table->unsignedSmallInteger('notice_period_months')->default(1);
            $table->string('status', 20)->default('draft');
            // status: draft|active|notice_given|expired|terminated|cancelled
            $table->string('currency_code', 5)->default('SAR');
            $table->unsignedSmallInteger('payment_day')->default(1); // day of month rent is due
            $table->string('payment_frequency', 20)->default('monthly');
            // payment_frequency: monthly|quarterly|semi_annual|annual
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('auto_renew_months')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'contract_number'], 're_contracts_org_num_uniq');
            $table->index(['organization_id', 'status', 'end_date'], 're_contracts_org_status_end_idx');
            $table->index(['organization_id', 'rental_unit_id'], 're_contracts_org_unit_idx');

            // Only lessee contracts (contract_type = 'lease_in') use IFRS 16.
            $table->decimal('ibr_percent', 8, 4)->nullable()
                ->comment('Incremental Borrowing Rate used for PV calculation (annual %)');
            $table->decimal('rou_asset_amount', 20, 4)->nullable()
                ->comment('Right-of-Use asset value at commencement (= initial lease liability)');
            $table->decimal('lease_liability_amount', 20, 4)->nullable()
                ->comment('Remaining lease liability (updated each period)');
            $table->date('ifrs16_commencement_date')->nullable()
                ->comment('Date on which IFRS 16 recognition started');
            $table->boolean('ifrs16_applied')->default(false);
        });

        Schema::create('re_contract_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->string('condition_type', 30)->default('base_rent');
            // condition_type: base_rent|service_charge|deposit|parking|storage|other
            $table->string('description', 200)->nullable();
            $table->decimal('amount', 18, 4);
            $table->string('basis', 20)->default('flat');
            // basis: flat|per_sqm|pct_of_rent
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            // Escalation rules
            $table->string('escalation_type', 20)->nullable();
            // escalation_type: none|fixed_pct|cpi|stepped
            $table->decimal('escalation_rate', 8, 4)->nullable(); // pct for fixed_pct
            $table->string('escalation_index', 50)->nullable(); // CPI index name
            $table->string('escalation_frequency', 20)->nullable(); // annual|biennial
            $table->date('next_escalation_date')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['contract_id', 'condition_type', 'is_active'], 're_contract_conditions_contract_type_active_idx');
            $table->index(['next_escalation_date', 'is_active'], 're_contract_conditions_escalation_due_idx');
        });

        Schema::create('re_contract_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->string('option_type', 30)->default('renewal');
            // option_type: renewal|break|purchase|expansion|contraction
            $table->date('exercise_window_start')->nullable();
            $table->date('exercise_window_end')->nullable();
            $table->date('exercise_deadline'); // latest date to exercise
            $table->unsignedSmallInteger('new_term_months')->nullable(); // for renewal options
            $table->decimal('new_rent_amount', 18, 4)->nullable(); // fixed rent if exercised
            $table->string('status', 20)->default('pending');
            // status: pending|exercised|expired|waived
            $table->date('exercised_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status'], 're_contract_options_contract_status_idx');
            $table->index(['exercise_deadline', 'status'], 're_contract_options_deadline_status_idx');
        });

        Schema::create('re_ifrs16_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->date('period_date')->comment('First day of the accounting period');
            $table->decimal('opening_liability', 20, 4);
            $table->decimal('interest_expense', 20, 4)->comment('Opening liability × monthly IBR');
            $table->decimal('lease_payment', 20, 4)->comment('Contractual rent payment');
            $table->decimal('principal_reduction', 20, 4)->comment('Payment minus interest');
            $table->decimal('closing_liability', 20, 4);
            $table->decimal('rou_depreciation', 20, 4)->comment('Straight-line depreciation of ROU asset');
            $table->decimal('rou_book_value', 20, 4)->comment('ROU asset net book value at end of period');
            $table->boolean('gl_posted')->default(false);
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('re_contracts')->cascadeOnDelete();
            $table->unique(['contract_id', 'period_date']);
            $table->index(['contract_id', 'gl_posted']);
        });

        Schema::create('re_posting_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_run_id')->constrained('re_posting_runs')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->foreignId('condition_id')->constrained('re_contract_conditions')->cascadeOnDelete();
            $table->string('condition_type', 30);
            $table->decimal('amount', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->string('status', 20)->default('pending'); // pending|posted|skipped|error
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->index(['posting_run_id', 'status'], 're_posting_run_items_run_status_idx');
            $table->index(['contract_id'], 're_posting_run_items_contract_idx');
        });

        Schema::create('re_security_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->string('deposit_number', 50);
            $table->decimal('required_amount', 18, 4);
            $table->decimal('collected_amount', 18, 4)->default(0);
            $table->string('currency_code', 5)->default('SAR');
            $table->date('collected_date')->nullable();
            $table->decimal('interest_rate_pct', 8, 4)->default(0); // annual interest on deposit
            $table->decimal('accrued_interest', 18, 4)->default(0);
            $table->string('status', 20)->default('pending');
            // status: pending|partial|collected|partially_refunded|refunded|forfeited
            $table->decimal('refunded_amount', 18, 4)->default(0);
            $table->date('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'deposit_number'], 're_security_deposits_org_num_uniq');
            $table->index(['organization_id', 'contract_id'], 're_security_deposits_org_contract_idx');
        });

        Schema::create('re_service_charge_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('settlement_number', 50);
            $table->foreignId('property_id')->constrained('re_properties')->cascadeOnDelete();
            $table->integer('settlement_year');
            $table->string('status', 20)->default('draft');
            // status: draft|calculated|approved|invoiced|closed
            $table->decimal('total_actual_costs', 18, 4)->default(0);
            $table->decimal('total_billed_to_tenants', 18, 4)->default(0);
            $table->decimal('total_adjustment', 18, 4)->default(0); // positive = tenant owes, negative = refund
            $table->string('currency_code', 5)->default('SAR');
            $table->date('settlement_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'settlement_number'], 're_service_charge_settlements_org_num_uniq');
            $table->index(['organization_id', 'property_id', 'settlement_year'], 're_service_charge_settlements_org_property_year_idx');
        });

        Schema::create('re_service_charge_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('re_service_charge_settlements')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('re_contracts')->cascadeOnDelete();
            $table->decimal('unit_area_sqm', 14, 4)->default(0);
            $table->decimal('allocation_pct', 8, 4)->default(0);
            $table->decimal('actual_amount', 18, 4)->default(0); // tenant's share of actual costs
            $table->decimal('billed_amount', 18, 4)->default(0); // what tenant already paid on account
            $table->decimal('adjustment_amount', 18, 4)->default(0); // positive = additional charge, negative = refund
            $table->timestamps();

            $table->unique(['settlement_id', 'contract_id'], 're_service_charge_allocations_settlement_contract_uniq');
        });

        Schema::create('re_service_charge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('re_service_charge_settlements')->cascadeOnDelete();
            $table->string('cost_category', 100); // electricity|water|cleaning|security|maintenance|insurance|etc
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->decimal('lettable_area_sqm', 14, 4)->default(0); // total apportionable area
            $table->decimal('cost_per_sqm', 14, 6)->default(0); // computed
            $table->string('allocation_basis', 30)->default('area');
            // allocation_basis: area|equal|usage|custom
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['settlement_id'], 're_service_charge_items_settlement_idx');
        });

        Schema::create('re_vacancy_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('building_id');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('portfolio_id')->nullable();
            $table->date('vacant_from');
            $table->date('vacant_to')->nullable();                  // null = still vacant
            $table->string('vacancy_reason')->nullable();           // lease_expired|early_termination|new_unit|renovation|owner_use
            $table->decimal('market_rent', 15, 2)->nullable();     // expected rent while vacant (for revenue loss calc)
            $table->string('currency', 3)->default('SAR');
            $table->decimal('vacancy_loss', 15, 2)->nullable();    // computed: days_vacant × daily_market_rent
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rental_unit_id']);
            $table->index(['organization_id', 'building_id']);
            $table->index(['vacant_from', 'vacant_to']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('re_vacancy_periods');
        Schema::dropIfExists('re_service_charge_items');
        Schema::dropIfExists('re_service_charge_allocations');
        Schema::dropIfExists('re_service_charge_settlements');
        Schema::dropIfExists('re_security_deposits');
        Schema::dropIfExists('re_posting_run_items');
        Schema::dropIfExists('re_ifrs16_schedules');
        Schema::dropIfExists('re_contract_options');
        Schema::dropIfExists('re_contract_conditions');
        Schema::dropIfExists('re_contracts');
        Schema::dropIfExists('re_rental_units');
        Schema::dropIfExists('re_floors');
        Schema::dropIfExists('re_buildings');
        Schema::dropIfExists('re_properties');
        Schema::dropIfExists('re_posting_runs');
        Schema::dropIfExists('re_portfolios');
        Schema::dropIfExists('re_occupancy_snapshots');
    }
};
