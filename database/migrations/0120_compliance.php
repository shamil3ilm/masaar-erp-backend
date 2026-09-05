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

        Schema::create('grc_audit_engagements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('engagement_number', 30)->unique();
            $table->string('title', 200);
            $table->enum('audit_type', ['internal', 'external', 'regulatory', 'it', 'operational', 'financial', 'compliance']);
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->enum('status', ['planning', 'fieldwork', 'review', 'issued', 'closed'])->default('planning');
            $table->text('scope')->nullable();
            $table->text('objectives')->nullable();
            $table->foreignId('lead_auditor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('grc_audit_findings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('engagement_id')->nullable()->constrained('grc_audit_engagements')->nullOnDelete();
            $table->string('finding_number', 30)->unique();
            $table->string('title', 200);
            $table->text('description');
            $table->text('criteria')->nullable();
            $table->text('condition')->nullable();
            $table->text('cause')->nullable();
            $table->text('effect')->nullable();
            $table->text('recommendation')->nullable();
            $table->enum('severity', ['critical', 'high', 'medium', 'low', 'informational'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'in_remediation', 'remediated', 'verified', 'closed', 'risk_accepted'])->default('open');
            $table->enum('finding_type', ['control_deficiency', 'process_gap', 'policy_violation', 'fraud_risk', 'it_risk', 'compliance_gap']);
            $table->string('module_reference', 50)->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('management_response')->nullable();
            $table->date('management_response_date')->nullable();
            $table->text('remediation_plan')->nullable();
            $table->date('remediation_target_date')->nullable();
            $table->date('remediation_completed_date')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('repeat_finding')->default(false);
            $table->unsignedBigInteger('parent_finding_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'severity']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('grc_ccm_monitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('monitor_code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('control_type', ['preventive', 'detective', 'corrective']);
            $table->string('data_source', 100);
            $table->json('rules');
            $table->enum('frequency', ['real_time', 'hourly', 'daily', 'weekly', 'monthly'])->default('daily');
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('total_exceptions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'monitor_code']);
        });

        Schema::create('grc_ccm_exceptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained('grc_ccm_monitors')->restrictOnDelete();
            $table->string('record_type', 100);
            $table->unsignedBigInteger('record_id');
            $table->string('record_reference', 100)->nullable();
            $table->text('exception_details');
            $table->enum('severity', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'investigated', 'resolved', 'false_positive'])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'monitor_id', 'detected_at']);
        });

        Schema::create('grc_control_library', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('control_code', 30);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('control_type', ['preventive', 'detective', 'corrective', 'directive']);
            $table->enum('control_category', ['it', 'manual', 'automated', 'semi_automated']);
            $table->string('module_reference', 50)->nullable();
            $table->enum('frequency', ['continuous', 'daily', 'weekly', 'monthly', 'quarterly', 'annual', 'ad_hoc'])->default('monthly');
            $table->enum('status', ['active', 'inactive', 'under_review'])->default('active');
            $table->foreignId('control_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'control_code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('grc_csa_questionnaires', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('questionnaire_number', 30)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('control_area', ['financial_reporting', 'it_general', 'operational', 'compliance', 'fraud_prevention']);
            $table->date('due_date');
            $table->enum('status', ['draft', 'published', 'in_progress', 'completed', 'reviewed'])->default('draft');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('grc_csa_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('grc_csa_questionnaires')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->text('question_text');
            $table->text('guidance')->nullable();
            $table->enum('response_type', ['yes_no', 'rating_1_5', 'text', 'date', 'percentage'])->default('yes_no');
            $table->boolean('is_required')->default(true);
            $table->string('control_objective', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('grc_csa_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('grc_csa_questionnaires')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('grc_csa_questions')->cascadeOnDelete();
            $table->foreignId('respondent_id')->constrained('users')->restrictOnDelete();
            $table->string('response_value', 500)->nullable();
            $table->text('comments')->nullable();
            $table->boolean('is_effective')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();
            $table->unique(['questionnaire_id', 'question_id', 'respondent_id'], 'grc_csa_responses_questionnaire_question_respondent_uniq');
        });

        Schema::create('grc_finding_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained('grc_audit_findings')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date');
            $table->enum('status', ['open', 'in_progress', 'completed', 'overdue'])->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['finding_id', 'status']);
        });

        Schema::create('grc_risk_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('risk_type', ['strategic', 'operational', 'financial', 'compliance', 'reputational', 'it', 'ehs']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'parent_id']);
        });

        Schema::create('grc_risks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('risk_number', 30)->unique();
            $table->string('title', 200);
            $table->text('description');
            $table->foreignId('category_id')->nullable()->constrained('grc_risk_categories')->nullOnDelete();
            $table->enum('risk_type', ['strategic', 'operational', 'financial', 'compliance', 'reputational', 'it', 'ehs'])->default('operational');

            // Inherent risk (before controls)
            $table->unsignedTinyInteger('inherent_likelihood')->default(3); // 1-5
            $table->unsignedTinyInteger('inherent_impact')->default(3);     // 1-5
            $table->unsignedTinyInteger('inherent_score')->default(9);      // computed manually: likelihood * impact

            // Residual risk (after controls)
            $table->unsignedTinyInteger('residual_likelihood')->default(3);
            $table->unsignedTinyInteger('residual_impact')->default(3);
            $table->unsignedTinyInteger('residual_score')->default(9);      // computed manually: likelihood * impact

            $table->enum('risk_status', ['identified', 'assessed', 'treated', 'monitored', 'closed', 'accepted'])->default('identified');
            $table->foreignId('risk_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module_reference', 50)->nullable(); // Accounting, Sales, HR, etc.
            $table->text('existing_controls')->nullable();
            $table->date('next_review_date')->nullable();
            $table->date('identified_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'risk_status']);
            $table->index(['organization_id', 'risk_type']);
        });

        Schema::create('grc_kris', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('risk_id')->nullable()->constrained('grc_risks')->nullOnDelete();
            $table->string('kri_code', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('data_source', 100);      // e.g., 'invoices', 'journal_entries', 'manual'
            $table->string('metric_field', 100)->nullable(); // field to measure
            $table->enum('aggregation', ['count', 'sum', 'avg', 'max', 'min', 'percentage'])->default('count');
            $table->decimal('threshold_green', 15, 4);  // Below this = green
            $table->decimal('threshold_amber', 15, 4);  // Below this = amber
            $table->decimal('threshold_red', 15, 4);    // At or above = red
            $table->enum('direction', ['lower_is_better', 'higher_is_better'])->default('lower_is_better');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly'])->default('monthly');
            $table->timestamp('last_measured_at')->nullable();
            $table->decimal('last_value', 15, 4)->nullable();
            $table->enum('last_status', ['green', 'amber', 'red'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'kri_code']);
            $table->index(['organization_id', 'last_status']);
        });

        Schema::create('grc_kri_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kri_id')->constrained('grc_kris')->cascadeOnDelete();
            $table->date('reading_date');
            $table->decimal('value', 15, 4);
            $table->enum('status', ['green', 'amber', 'red']);
            $table->text('notes')->nullable();
            $table->boolean('is_auto')->default(true); // auto-computed vs manual
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['kri_id', 'reading_date']);
        });

        Schema::create('grc_risk_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('grc_risks')->cascadeOnDelete();
            $table->date('review_date');
            $table->unsignedTinyInteger('reviewed_likelihood');
            $table->unsignedTinyInteger('reviewed_impact');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['risk_id', 'review_date']);
        });

        Schema::create('grc_risk_treatments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('risk_id')->constrained('grc_risks')->cascadeOnDelete();
            $table->enum('treatment_type', ['avoid', 'reduce', 'transfer', 'accept']);
            $table->text('description');
            $table->text('action_plan')->nullable();
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            // Target residual risk after treatment
            $table->unsignedTinyInteger('target_likelihood')->nullable();
            $table->unsignedTinyInteger('target_impact')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'risk_id', 'status']);
        });

        Schema::create('grc_sod_functions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('function_code', 50);
            $table->string('name', 150);
            $table->string('module', 50);
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'function_code']);
        });

        Schema::create('grc_sod_conflicts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('function_a_id')->constrained('grc_sod_functions')->restrictOnDelete();
            $table->foreignId('function_b_id')->constrained('grc_sod_functions')->restrictOnDelete();
            $table->enum('risk_level', ['critical', 'high', 'medium', 'low'])->default('high');
            $table->text('description')->nullable();
            $table->text('mitigation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'function_a_id', 'function_b_id'], 'grc_sod_conflicts_org_func_a_func_b_uniq');
            $table->index(['organization_id']);
        });

        Schema::create('grc_sod_violations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('conflict_id')->constrained('grc_sod_conflicts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['open', 'risk_accepted', 'mitigated', 'remediated'])->default('open');
            $table->text('mitigation_description')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->date('review_date')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['organization_id', 'user_id']);
            $table->index(['organization_id', 'status']);
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
        Schema::dropIfExists('grc_sod_violations');
        Schema::dropIfExists('grc_sod_conflicts');
        Schema::dropIfExists('grc_sod_functions');
        Schema::dropIfExists('grc_risk_treatments');
        Schema::dropIfExists('grc_risk_reviews');
        Schema::dropIfExists('grc_kri_readings');
        Schema::dropIfExists('grc_kris');
        Schema::dropIfExists('grc_risks');
        Schema::dropIfExists('grc_risk_categories');
        Schema::dropIfExists('grc_finding_actions');
        Schema::dropIfExists('grc_csa_responses');
        Schema::dropIfExists('grc_csa_questions');
        Schema::dropIfExists('grc_csa_questionnaires');
        Schema::dropIfExists('grc_control_library');
        Schema::dropIfExists('grc_ccm_exceptions');
        Schema::dropIfExists('grc_ccm_monitors');
        Schema::dropIfExists('grc_audit_findings');
        Schema::dropIfExists('grc_audit_engagements');
        Schema::dropIfExists('dps_screening_results');
        Schema::dropIfExists('dps_screening_runs');
        Schema::dropIfExists('dps_list_entries');
        Schema::dropIfExists('dps_sanction_lists');
        Schema::dropIfExists('bahrain_vat_returns');
    }
};
