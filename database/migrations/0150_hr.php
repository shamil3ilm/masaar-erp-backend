<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_cycles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->date('self_review_deadline')->nullable();
            $table->date('manager_review_deadline')->nullable();
            $table->enum('status', [
                'draft',
                'active',
                'self_review',
                'manager_review',
                'calibration',
                'completed',
                'cancelled',
            ])->default('draft');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('appraisal_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->tinyInteger('rating_scale')->default(5);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('appraisal_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_template_id')->constrained('appraisal_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('appraisal_template_id');
        });

        Schema::create('appraisal_template_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appraisal_template_section_id');
            $table->foreign('appraisal_template_section_id', 'apprsl_tmpl_questions_section_fk')
                ->references('id')->on('appraisal_template_sections')->cascadeOnDelete();
            $table->text('question');
            $table->enum('question_type', ['rating', 'text', 'yes_no', 'multiselect'])->default('rating');
            $table->boolean('is_required')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('appraisal_template_section_id');
        });

        Schema::create('benefit_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->enum('category', ['allowance', 'insurance', 'other'])->default('allowance');
            $table->enum('calculation_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('default_amount', 15, 4)->default(0);
            $table->decimal('percentage_basis', 5, 2)->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('eligibility_rules')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'category', 'is_active'], 'ben_typ_org_cat_active_idx');
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 200);
            $table->string('phone', 50)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('resume_path', 500)->nullable();
            $table->decimal('total_experience_years', 4, 1)->default(0);
            $table->string('current_company', 200)->nullable();
            $table->string('current_title', 200)->nullable();
            $table->enum('source', ['job_board', 'referral', 'linkedin', 'direct', 'agency', 'other'])->default('direct');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
        });

        Schema::create('compensation_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('review_name', 100);
            $table->date('review_date');
            $table->date('effective_date');
            $table->decimal('budget_amount', 15, 4)->default(0);
            $table->decimal('allocated_amount', 15, 4)->default(0);
            $table->enum('status', ['draft', 'in_progress', 'approved', 'applied'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'cr_org_status_idx');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('level')->default(1); // For hierarchy
            $table->decimal('min_salary', 15, 4)->nullable();
            $table->decimal('max_salary', 15, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('employee_exits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete()->name('ee_employee_fk');
            $table->enum('exit_type', ['resignation', 'termination', 'retirement', 'contract_end', 'death'])->default('resignation');
            $table->date('resignation_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->default(30);
            $table->boolean('notice_period_waived')->default(false);
            $table->text('exit_reason')->nullable();
            $table->enum('status', ['initiated', 'notice_period', 'clearance_in_progress', 'clearance_complete', 'settled', 'closed'])->default('initiated');
            $table->decimal('final_settlement_amount', 18, 4)->nullable();
            $table->date('settlement_date')->nullable();
            $table->decimal('eosb_amount', 18, 4)->nullable();
            $table->decimal('leave_encashment_amount', 18, 4)->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete()->name('ee_initiated_by_fk');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->name('ee_approved_by_fk');
            $table->datetime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id'], 'ee_org_employee_idx');
            $table->index(['organization_id', 'status'], 'ee_org_status_idx');
            $table->index(['last_working_date'], 'ee_last_working_date_idx');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Employee identification
            $table->string('employee_number', 50)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('display_name', 200)->nullable();

            // Personal info
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('blood_group', 5)->nullable();

            // Contact
            $table->string('email', 200)->nullable();
            $table->string('personal_email', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();

            // Address
            $table->string('address_line_1', 200)->nullable();
            $table->string('address_line_2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 2)->nullable();

            // Employment
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('termination_reason', 500)->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern', 'probation'])->default('full_time');
            $table->enum('employment_status', ['active', 'on_notice', 'terminated', 'resigned', 'absconded'])->default('active');

            // Work schedule
            $table->string('work_schedule', 50)->nullable(); // Reference to work schedule
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->json('work_days')->nullable(); // ['monday', 'tuesday', ...]

            // Documents
            // Aadhaar, Emirates ID, etc.
            // Encrypted by the model. Ciphertext is 200 characters at its shortest,
            // so the column is sized for the ciphertext, not the value.
            $table->text('national_id')->nullable();
            $table->text('passport_number')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('visa_number', 50)->nullable();
            $table->date('visa_expiry')->nullable();
            $table->string('work_permit_number', 50)->nullable();
            $table->date('work_permit_expiry')->nullable();

            // Tax info (varies by country)
            $table->string('tax_number', 50)->nullable(); // PAN (India), TIN, etc.
            $table->string('social_security_number', 50)->nullable(); // PF number, GOSI, etc.
            $table->json('tax_declarations')->nullable(); // For India HRA, 80C, etc.

            // Salary info
            $table->string('currency_code', 3)->default('SAR');
            $table->string('payment_mode', 20)->default('bank_transfer');
            $table->string('bank_name', 100)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('bank_ifsc_code', 20)->nullable(); // IFSC for India
            $table->text('bank_iban')->nullable(); // IBAN for GCC

            // Other
            $table->text('notes')->nullable();
            $table->string('profile_photo_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'employee_number']);
            $table->index(['organization_id', 'department_id']);
            $table->index(['organization_id', 'employment_status']);
            $table->index(['organization_id', 'is_active']);

            $table->date('rehire_date')->nullable();
            $table->date('previous_termination_date')->nullable();
            $table->unsignedTinyInteger('rehire_count')->default(0);
        });

        Schema::create('compensation_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('compensation_reviews')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('current_salary', 15, 4);
            $table->decimal('proposed_salary', 15, 4)->nullable();
            $table->decimal('increase_amount', 15, 4)->nullable();
            $table->decimal('increase_percentage', 5, 2)->nullable();
            $table->enum('adjustment_type', ['merit', 'promotion', 'market_adjustment', 'equity'])->default('merit');
            $table->text('justification')->nullable();
            $table->enum('status', ['pending', 'recommended', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->index(['review_id', 'employee_id'], 'cri_review_emp_idx');
        });

        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('benefit_type_id')->constrained('benefit_types')->cascadeOnDelete();
            $table->decimal('amount', 15, 4)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->string('policy_number', 100)->nullable();
            $table->string('provider_name', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status'], 'emp_ben_emp_status_idx');
            $table->index(['organization_id', 'benefit_type_id'], 'emp_ben_org_type_idx');
        });

        Schema::create('benefit_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_benefit_id')->constrained('employee_benefits')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('change_type', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['employee_benefit_id'], 'ben_chg_benefit_idx');
            $table->index(['employee_id', 'changed_at'], 'ben_chg_emp_date_idx');
        });

        Schema::create('employee_dependents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('relationship', ['spouse', 'child', 'parent', 'sibling', 'other']);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->char('nationality', 2)->nullable();
            $table->enum('id_type', ['national_id', 'passport', 'birth_certificate'])->nullable();
            $table->string('id_number', 100)->nullable();
            $table->date('id_expiry_date')->nullable();
            $table->boolean('is_beneficiary')->default(false);
            $table->boolean('is_sponsored')->default(false);
            $table->string('visa_number', 50)->nullable();
            $table->date('visa_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'employee_id']);
            $table->index(['organization_id', 'is_beneficiary']);
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50); // passport, id_card, contract, certificate, etc.
            $table->string('document_name', 200);
            $table->string('document_number', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'document_type']);
            $table->index('expiry_date');
        });

        Schema::create('employee_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('company_name', 200);
            $table->string('designation', 100)->nullable();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->text('responsibilities')->nullable();
            $table->string('reason_for_leaving', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('loan_number', 50);
            $table->enum('loan_type', ['loan', 'advance', 'salary_advance'])->default('loan');
            $table->decimal('principal_amount', 15, 4);
            $table->decimal('interest_rate', 5, 4)->default(0); // Annual %
            $table->date('disbursement_date');
            $table->date('repayment_start_date');
            $table->unsignedSmallInteger('tenure_months');
            $table->decimal('emi_amount', 15, 4);
            $table->decimal('total_repaid', 15, 4)->default(0);
            $table->decimal('balance', 15, 4);
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'loan_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('employee_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('qualification_type', 50); // degree, diploma, certification, etc.
            $table->string('qualification_name', 200);
            $table->string('institution', 200)->nullable();
            $table->string('specialization', 200)->nullable();
            $table->year('year_of_passing')->nullable();
            $table->string('grade', 50)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('eosb_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('country_code', 10);
            $table->enum('calculation_method', ['saudi', 'uae', 'qatar', 'kuwait', 'bahrain', 'oman', 'india']);
            $table->unsignedSmallInteger('min_service_months')->default(12);
            $table->decimal('first_period_days_per_year', 5, 2)->default(15.00);
            $table->unsignedSmallInteger('first_period_years')->default(5);
            $table->decimal('subsequent_days_per_year', 5, 2)->default(30.00);
            $table->boolean('prorate_partial_year')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'country_code'], 'eosb_pol_org_country_idx');
        });

        Schema::create('eosb_calculations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('eosb_policies')->nullOnDelete();
            $table->date('calculation_date');
            $table->decimal('service_years', 8, 2);
            $table->decimal('last_basic_salary', 15, 2);
            $table->decimal('last_total_salary', 15, 2);
            $table->decimal('gratuity_amount', 15, 2);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'eosb_calc_org_status_idx');
            $table->index('employee_id', 'eosb_calc_emp_idx');
        });

        Schema::create('eosb_provisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('eosb_policy_id')->constrained('eosb_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('days_earned', 8, 4)->default(0);
            $table->decimal('daily_rate', 15, 4)->default(0);
            $table->decimal('provision_amount', 15, 4)->default(0);
            $table->decimal('cumulative_amount', 15, 4)->default(0);
            $table->decimal('basic_salary_used', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'period_year', 'period_month'], 'eosb_prov_emp_period_uniq');
            $table->index(['organization_id', 'period_year', 'period_month'], 'eosb_prov_org_period_idx');
        });

        Schema::create('eosb_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('eosb_policy_id')->constrained('eosb_policies')->cascadeOnDelete();
            $table->date('termination_date');
            $table->decimal('years_of_service', 8, 4);
            $table->decimal('total_days_earned', 8, 4);
            $table->decimal('daily_rate', 15, 4);
            $table->decimal('gross_amount', 15, 4);
            $table->decimal('deductions', 15, 4)->default(0);
            $table->decimal('net_amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('payment_date')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'eosb_set_org_status_idx');
            $table->index('employee_id', 'eosb_set_emp_idx');
        });

        Schema::create('exit_clearance_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('eci_org_fk');
            $table->foreignId('employee_exit_id')->constrained('employee_exits')->cascadeOnDelete()->name('eci_exit_fk');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->name('eci_dept_fk');
            $table->string('clearance_item', 100);
            $table->foreignId('responsible_person_id')->nullable()->constrained('users')->nullOnDelete()->name('eci_responsible_fk');
            $table->enum('status', ['pending', 'cleared', 'waived'])->default('pending');
            $table->datetime('cleared_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['employee_exit_id'], 'eci_exit_idx');
        });

        Schema::create('gosi_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('country_code', 10)->default('SA');
            $table->string('name', 100)->nullable();
            $table->decimal('employee_contribution_pct', 6, 2)->default(0);
            $table->decimal('employer_contribution_pct', 6, 2)->default(0);
            $table->decimal('hazard_pct', 6, 2)->default(0);
            $table->decimal('salary_ceiling', 15, 2)->nullable();
            $table->decimal('salary_floor', 15, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'gosi_conf_org_active_idx');
            $table->index(['organization_id', 'country_code'], 'gosi_conf_org_country_idx');
        });

        Schema::create('gosi_contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_salary', 15, 2)->default(0);
            $table->decimal('contributable_salary', 15, 2)->default(0);
            $table->decimal('employee_contribution', 15, 2)->default(0);
            $table->decimal('employer_contribution', 15, 2)->default(0);
            $table->decimal('hazard_contribution', 15, 2)->default(0);
            $table->decimal('total_contribution', 15, 2)->default(0);
            $table->string('gosi_id', 50)->nullable();
            $table->enum('status', ['draft', 'submitted', 'paid'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'employee_id', 'period_year', 'period_month'], 'gosi_contrib_emp_period_uniq');
            $table->index(['organization_id', 'period_year', 'period_month'], 'gosi_contrib_org_period_idx');
            $table->index(['organization_id', 'status'], 'gosi_contrib_org_status_idx');
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->date('holiday_date');
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_restricted')->default(false); // Only for certain religions/groups
            $table->string('applicable_to', 50)->nullable(); // all, specific_department, etc.
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'holiday_date']);
        });

        Schema::create('hr_onboardings', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('template_type', 50)->default('standard')
                ->comment('standard | probation | rehire | transfer_in');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->date('started_date');
            $table->date('target_completion_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('hr_onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('hr_onboardings')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('hr')
                ->comment('hr | it | manager | employee | legal | finance');
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'done', 'skipped'])->default('pending');
            $table->boolean('is_required')->default(true);
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['onboarding_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
            $table->string('location', 200)->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->unsignedInteger('vacancies')->default(1);
            $table->unsignedInteger('filled_count')->default(0);
            $table->enum('status', ['draft', 'open', 'on_hold', 'closed', 'cancelled'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->enum('status', [
                'applied',
                'screening',
                'shortlisted',
                'interview_scheduled',
                'interviewed',
                'offer_extended',
                'offer_accepted',
                'offer_declined',
                'hired',
                'rejected',
                'withdrawn',
            ])->default('applied');
            $table->text('cover_letter')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_posting_id', 'candidate_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['job_posting_id', 'status']);
        });

        Schema::create('interview_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->enum('interview_type', ['phone', 'video', 'in_person', 'technical', 'panel'])->default('in_person');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->string('location', 300)->nullable();
            $table->string('meeting_link', 500)->nullable();
            $table->json('interviewers')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->text('feedback')->nullable();
            $table->tinyInteger('rating')->unsigned()->nullable()->comment('1-5');
            $table->enum('recommendation', ['strong_yes', 'yes', 'neutral', 'no', 'strong_no'])->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'job_application_id']);
        });

        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->decimal('offered_salary', 12, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('joining_date')->nullable();
            $table->date('offer_valid_until')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'declined', 'expired', 'withdrawn'])->default('draft');
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'job_application_id']);
        });

        Schema::create('key_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('title');
            $table->enum('criticality', ['critical', 'high', 'medium'])->default('high');
            $table->foreignId('current_holder_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('target_fill_date')->nullable();
            $table->unsignedSmallInteger('min_successors')->default(2);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'criticality', 'is_active'], 'key_pos_org_crit_active_idx');
            $table->index(['organization_id', 'department_id'], 'key_pos_org_dept_idx');
        });

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('policy_year_type', 20)->default('calendar'); // calendar, fiscal, anniversary
            $table->date('year_start_date')->nullable(); // For fiscal/custom year
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('require_approval')->default(true);
            $table->unsignedTinyInteger('min_notice_days')->default(0); // Days before leave start
            $table->boolean('allow_half_day')->default(true);
            $table->boolean('allow_hourly')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->decimal('annual_quota', 8, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_encashable')->default(false);
            $table->decimal('max_encashable_days', 8, 2)->nullable();
            $table->boolean('carry_forward')->default(false);
            $table->decimal('max_carry_forward_days', 8, 2)->nullable();
            $table->unsignedSmallInteger('min_days_notice')->default(0);
            $table->decimal('max_consecutive_days', 8, 2)->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedSmallInteger('attachment_required_after_days')->default(0);
            $table->boolean('half_day_allowed')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->string('applicable_gender', 20)->default('all');
            $table->string('applicable_marital_status', 20)->default('all');
            $table->unsignedSmallInteger('applicable_after_months')->default(0);
            $table->string('accrual_type', 20)->default('annual'); // annual, monthly, quarterly
            $table->boolean('prorate_on_joining')->default(true);
            $table->boolean('prorate_on_exit')->default(true);
            $table->string('color', 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening_balance', 8, 2)->default(0);
            $table->decimal('accrued', 8, 2)->default(0);
            $table->decimal('taken', 8, 2)->default(0);
            $table->decimal('adjustment', 8, 2)->default(0);
            $table->decimal('encashed', 8, 2)->default(0);
            $table->decimal('lapsed', 8, 2)->default(0);
            $table->decimal('closing_balance', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
            $table->index(['organization_id', 'year']);
        });

        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained('leave_balances')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('accrual_date');
            $table->string('accrual_type', 30); // monthly, yearly, adjustment, carryforward, opening
            $table->decimal('days', 6, 2);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['leave_balance_id', 'accrual_date']);
        });

        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->foreignId('leave_balance_id')->constrained('leave_balances')->cascadeOnDelete();
            $table->string('adjustment_type', 30); // add, deduct, set, carryforward, encashment
            $table->decimal('days', 6, 2);
            $table->decimal('balance_before', 6, 2);
            $table->decimal('balance_after', 6, 2);
            $table->text('reason');
            $table->date('effective_date');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'employee_id']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('total_days', 5, 2);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_type', 20)->nullable(); // first_half, second_half
            $table->text('reason');
            $table->string('contact_during_leave')->nullable();
            $table->string('address_during_leave')->nullable();
            $table->string('status', 20)->default('pending'); // draft, pending, approved, rejected, cancelled
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['from_date', 'to_date']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_adjustments');
        Schema::dropIfExists('leave_accruals');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('key_positions');
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('interview_schedules');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('hr_onboarding_tasks');
        Schema::dropIfExists('hr_onboardings');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('gosi_contributions');
        Schema::dropIfExists('gosi_configurations');
        Schema::dropIfExists('exit_clearance_items');
        Schema::dropIfExists('eosb_settlements');
        Schema::dropIfExists('eosb_provisions');
        Schema::dropIfExists('eosb_calculations');
        Schema::dropIfExists('eosb_policies');
        Schema::dropIfExists('employee_qualifications');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('employee_experiences');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_dependents');
        Schema::dropIfExists('benefit_changes');
        Schema::dropIfExists('employee_benefits');
        Schema::dropIfExists('compensation_review_items');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('employee_exits');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('compensation_reviews');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('benefit_types');
        Schema::dropIfExists('appraisal_template_questions');
        Schema::dropIfExists('appraisal_template_sections');
        Schema::dropIfExists('appraisal_templates');
        Schema::dropIfExists('appraisal_cycles');
    }
};
