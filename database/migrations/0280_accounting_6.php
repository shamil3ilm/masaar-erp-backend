<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_exposures', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('open_invoices', 15, 4)->default(0);
            $table->decimal('open_orders', 15, 4)->default(0);
            $table->decimal('total_exposure', 15, 4)->default(0);
            $table->decimal('credit_limit', 15, 4)->default(0);
            $table->decimal('available_credit', 15, 4)->default(0);
            $table->decimal('utilization_pct', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id', 'snapshot_date'], 'credit_exp_org_contact_date_uniq');
            $table->index(['organization_id', 'snapshot_date'], 'credit_exposures_org_date_idx');
            $table->index(['contact_id', 'snapshot_date'], 'credit_exposures_contact_date_idx');
        });

        Schema::create('credit_holds', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamp('held_at');
            $table->timestamp('released_at')->nullable();
            $table->string('hold_reason', 500);
            $table->string('release_reason', 500)->nullable();
            $table->foreignId('held_by')->constrained('users');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'contact_id'], 'credit_holds_org_contact_idx');
            $table->index(['organization_id', 'released_at'], 'credit_holds_org_released_idx');
        });

        Schema::create('credit_limits', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('credit_limit', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->smallInteger('payment_terms_days')->unsigned()->default(30);
            $table->enum('risk_class', ['low', 'medium', 'high', 'blocked'])->default('medium');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'contact_id'], 'credit_limits_org_contact_unique');
            $table->index(['organization_id', 'risk_class'], 'credit_limits_org_risk_idx');
        });

        Schema::create('currency_revaluation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revaluation_id')->constrained('currency_revaluations')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->string('account_type', 30); // receivable, payable, bank, asset, liability
            $table->decimal('foreign_currency_balance', 18, 4); // Balance in foreign currency
            $table->decimal('old_base_amount', 18, 4); // Balance at old rate
            $table->decimal('new_base_amount', 18, 4); // Balance at new rate
            $table->decimal('gain_loss_amount', 18, 4); // Difference
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete(); // For AR/AP
            $table->timestamps();

            $table->index(['revaluation_id']);
            $table->index(['account_id']);
        });

        Schema::create('dunning_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('blocked_until')->nullable();
            $table->string('reason', 500);
            $table->foreignId('blocked_by')->constrained('users');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'contact_id'], 'dunning_blocks_org_contact_idx');
            $table->index(['organization_id', 'released_at'], 'dunning_blocks_org_released_idx');
        });

        Schema::create('dunning_notices', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('dunning_run_id')->constrained('dunning_runs')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('dunning_level_id')->constrained('dunning_levels');
            $table->decimal('total_overdue', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('notice_date');
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'blocked'])->default('pending');
            $table->string('blocking_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['dunning_run_id', 'status'], 'dunning_notices_run_status_idx');
            $table->index(['contact_id', 'status'], 'dunning_notices_contact_status_idx');
        });

        Schema::create('dunning_notice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dunning_notice_id')->constrained('dunning_notices')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('original_amount', 15, 4);
            $table->decimal('outstanding_amount', 15, 4);
            $table->smallInteger('days_overdue')->unsigned();
            $table->timestamps();

            $table->index(['dunning_notice_id'], 'dunning_notice_items_notice_idx');
            $table->index(['invoice_id'], 'dunning_notice_items_invoice_idx');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();

            $table->text('description')->nullable();

            // Amounts in transaction currency
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);

            // Amounts in base currency (for reporting)
            $table->decimal('base_debit', 18, 4)->default(0);
            $table->decimal('base_credit', 18, 4)->default(0);

            // Optional dimensions
            $table->foreignId('cost_center_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable(); // Customer/Supplier

            // Line ordering
            $table->unsignedSmallInteger('line_order')->default(0);

            $table->timestamps();

            $table->index(['journal_entry_id', 'line_order']);
            $table->index(['account_id']);

            $table->foreign('contact_id', 'jel_contact_fk')
                ->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('cost_center_id', 'jel_cost_center_fk')
                ->references('id')->on('cost_centers')->nullOnDelete();


            $table->unsignedBigInteger('ledger_id')
                ->nullable()

                ->comment('NULL = leading ledger; non-null = parallel ledger (IFRS/tax/mgmt)');

            $table->index(['ledger_id']);


            $table->string('segment_id', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->foreignId('profit_center_id')->nullable()->constrained('profit_centers')->nullOnDelete();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('loan_number', 30);
            $table->string('loan_type', 30); // employee_loan, inter_company, intra_company, bank_loan
            $table->string('loan_category', 50)->nullable(); // personal, salary_advance, housing, vehicle, education

            // Borrower
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete(); // For inter-company
            $table->string('borrower_name')->nullable();

            // Lender
            $table->string('lender_type', 30); // organization, bank, other
            $table->string('lender_name')->nullable();
            $table->foreignId('lender_contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Loan details
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->default(0); // Annual rate
            $table->string('interest_type', 20)->default('simple'); // simple, compound, flat
            $table->decimal('total_interest', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('outstanding_amount', 15, 2);
            $table->string('currency_code', 3)->default('SAR');

            // Terms
            $table->date('disbursement_date');
            $table->date('first_payment_date');
            $table->date('maturity_date');
            $table->unsignedInteger('tenure_months');
            $table->string('payment_frequency', 20)->default('monthly'); // weekly, bi-weekly, monthly
            $table->decimal('emi_amount', 15, 2); // Equated Monthly Installment
            $table->unsignedInteger('total_installments');
            $table->unsignedInteger('paid_installments')->default(0);

            // Status
            $table->string('status', 20)->default('pending'); // pending, approved, active, completed, defaulted, written_off
            $table->string('approval_status', 20)->default('pending'); // pending, approved, rejected

            // Accounting
            $table->foreignId('loan_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('interest_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            // For employee loans - payroll deduction
            $table->boolean('deduct_from_payroll')->default(false);
            $table->decimal('monthly_deduction', 15, 2)->nullable();

            $table->text('purpose')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->json('documents')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['loan_type', 'status']);
        });

        Schema::create('inter_company_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_number', 30);
            $table->string('transfer_type', 30); // fund_transfer, loan, investment

            // From
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('from_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();

            // To
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('to_organization_id')->nullable(); // For inter-company

            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('transfer_date');
            $table->string('reference')->nullable();
            $table->text('purpose')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, completed, cancelled

            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'transfer_date']);
            $table->index(['from_branch_id', 'to_branch_id']);
        });

        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('outstanding_balance', 15, 2);
            $table->string('status', 20)->default('pending'); // pending, paid, partial, overdue
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['loan_id', 'due_date', 'status']);
        });

        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('loan_schedules')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2);
            $table->string('payment_method', 30); // cash, bank_transfer, payroll_deduction
            $table->string('reference')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('payroll_id')->nullable(); // If deducted from payroll
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'payment_date']);

            $table->foreign('payroll_id', 'loan_payment_payroll_period_fk')
                ->references('id')->on('payroll_periods')->nullOnDelete();
        });

        Schema::create('aml_cdd_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('cdd_level');            // standard, enhanced, simplified
            $table->string('status');               // pending, completed, expired, failed
            $table->json('verification_data')->nullable();
            $table->date('verified_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'contact_id']);
        });

        Schema::create('aml_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');        // 0-100 composite risk score
            $table->string('risk_level');                // low (0-30), medium (31-60), high (61-80), critical (81-100)
            $table->json('score_breakdown');             // per-dimension contributions
            $table->boolean('sanctions_hit')->default(false);
            $table->boolean('pep_hit')->default(false);
            $table->string('sanctions_details')->nullable();
            $table->timestamp('last_screened_at')->nullable();
            $table->timestamp('score_updated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'risk_level']);
        });

        Schema::create('aml_screening_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('list_type');            // ofac, eu, un, pep, local
            $table->boolean('is_match')->default(false);
            $table->json('match_details')->nullable();
            $table->string('data_hash');            // hash of contact fields screened — skip if unchanged
            $table->timestamp('screened_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['contact_id', 'list_type']);
            $table->index(['organization_id', 'is_match']);
        });

        Schema::create('aml_suspicious_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('report_type');          // SAR, CTR (currency transaction report), STR
            $table->string('status')->default('draft'); // draft, filed, closed
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('contact_name')->nullable(); // denormalized for reports
            $table->json('related_transaction_ids')->nullable();
            $table->text('description');
            $table->string('activity_type');        // structuring, smurfing, layering, unusual_pattern, sanctions_hit
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('activity_date_from')->nullable();
            $table->date('activity_date_to')->nullable();
            $table->text('narrative')->nullable();   // full narrative for filing
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('filed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('aml_transaction_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type');     // invoice, payment, journal_entry
            $table->unsignedBigInteger('transaction_id');
            $table->string('transaction_number')->nullable();
            $table->decimal('amount', 20, 4);
            $table->string('currency', 3);
            $table->string('flag_reason');          // large_cash, structuring, rapid_movement, threshold_breach, unusual_pattern
            $table->string('status')->default('flagged'); // flagged, cleared, escalated
            $table->unsignedInteger('aml_score')->default(0);
            $table->json('context');                // supporting data
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamp('transaction_date');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'status']);
            $table->index(['transaction_type', 'transaction_id']);
        });

        Schema::create('dim_customer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id', 'dim_customer_contact_id_fk')
                ->references('id')->on('contacts')->onDelete('set null');
            $table->string('customer_code', 50);
            $table->string('customer_name');
            $table->string('customer_group', 50)->nullable();
            $table->char('country_code', 3)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('currency_code', 3);
            $table->decimal('credit_limit', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index('organization_id', 'dim_customer_org_id_idx');
        });

        Schema::create('dim_vendor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id', 'dim_vendor_contact_id_fk')
                ->references('id')->on('contacts')->onDelete('set null');
            $table->string('vendor_code', 50);
            $table->string('vendor_name');
            $table->string('vendor_group', 50)->nullable();
            $table->char('country_code', 3)->nullable();
            $table->char('currency_code', 3);
            $table->string('payment_terms', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index('organization_id', 'dim_vendor_org_id_idx');
        });

        Schema::create('gdpr_consent_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('purpose');
            $table->boolean('consent_given')->default(false);
            $table->timestamp('given_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('consent_text')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('contact_id', 'gdpr_con_contact_fk')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('user_id', 'gdpr_con_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('portal_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('password_hash', 255);
            $table->boolean('is_active')->default(true);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->integer('login_count')->default(0);
            $table->string('password_reset_token', 100)->nullable();
            $table->dateTime('password_reset_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'email'], 'portal_users_org_email_unique');
            $table->index(['contact_id', 'is_active'], 'portal_users_contact_active_idx');
        });

        Schema::create('portal_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('activity_type', 50);
            $table->string('description', 200);
            $table->json('metadata')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('portal_document_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('document_type', 30); // invoice|quotation|order|credit_note|statement
            $table->unsignedBigInteger('document_id');
            $table->dateTime('accessed_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['portal_user_id', 'document_type'], 'portal_doc_acc_user_type_idx');
        });

        Schema::create('portal_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('session_token', 255);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('last_activity_at');
            $table->timestamps();

            $table->index('session_token', 'portal_sessions_token_idx');
            $table->index('portal_user_id', 'portal_sessions_user_idx');
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Lead info
            $table->string('lead_number', 50)->nullable();
            $table->string('title', 200)->nullable();
            $table->enum('lead_type', ['individual', 'company'])->default('company');

            // Company info
            $table->string('company_name', 200)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('website', 200)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();

            // Primary contact
            $table->string('contact_name', 200);
            $table->string('contact_title', 100)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();

            // Address
            $table->string('address_line_1', 200)->nullable();
            $table->string('address_line_2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 2)->nullable();

            // Source & Assignment
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_details', 200)->nullable(); // Campaign name, referrer, etc.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Status
            $table->enum('status', [
                'new',
                'contacted',
                'qualified',
                'unqualified',
                'converted',
                'lost',
            ])->default('new');
            $table->string('lost_reason', 500)->nullable();

            // Scoring
            $table->unsignedSmallInteger('lead_score')->default(0); // 0-100
            $table->enum('rating', ['hot', 'warm', 'cold'])->default('cold');

            // Potential
            $table->decimal('estimated_value', 15, 4)->nullable();
            $table->string('currency_code', 3)->default('SAR');

            // Converted to
            $table->foreignId('converted_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('converted_opportunity_id')->nullable();
            $table->datetime('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'lead_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'lead_source_id']);
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Opportunity info
            $table->string('opportunity_number', 50)->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Related to
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_name', 200)->nullable(); // Company name

            // Pipeline
            $table->foreignId('pipeline_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('probability')->default(0); // 0-100

            // Value
            $table->decimal('amount', 15, 4)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('expected_revenue', 15, 4)->nullable(); // amount * probability

            // Dates
            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();

            // Status
            $table->enum('status', [
                'open',
                'won',
                'lost',
                'suspended',
            ])->default('open');
            $table->string('lost_reason', 500)->nullable();
            $table->string('won_reason', 500)->nullable();

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained()->nullOnDelete();

            // Converted to
            $table->foreignId('quotation_id')->nullable();
            $table->foreignId('sales_order_id')->nullable();

            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->json('competitors')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'opportunity_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'pipeline_stage_id']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'expected_close_date']);

            $table->foreign('quotation_id', 'opp_quotation_fk')
                ->references('id')->on('quotations')->nullOnDelete();
            $table->foreign('sales_order_id', 'opp_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();
        });

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open'); // open, in_progress, pending_customer, resolved, closed, cancelled
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('type')->default('general'); // bug, feature_request, billing, technical, general
            $table->string('source')->default('manual'); // email, phone, portal, chat, manual
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('sla_policy_id')->nullable();
            $table->dateTime('first_response_due_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();
            $table->dateTime('first_response_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->tinyInteger('customer_rating')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('sla_policy_id')->references('id')->on('sla_policies')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'priority']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'contact_id']);
            $table->index('sla_breached');
            $table->index('resolution_due_at');
        });

        Schema::create('service_ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('service_tickets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['ticket_id', 'is_internal']);
        });

        Schema::create('customs_declarations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('declaration_number', 50);
            $table->string('declaration_type', 20); // import, export, transit, re_export, temporary_import, temporary_export
            $table->string('customs_regime', 30)->nullable(); // free_circulation, warehousing, inward_processing, outward_processing, transit

            // Source document
            $table->string('source_type', 100)->nullable(); // PurchaseOrder, Invoice, ImportShipment
            $table->unsignedBigInteger('source_id')->nullable();

            // Parties
            $table->foreignId('importer_exporter_id')->nullable()->constrained('contacts')->nullOnDelete(); // Importer/Exporter
            $table->foreignId('broker_id')->nullable()->constrained('contacts')->nullOnDelete(); // Customs broker
            $table->string('consignee_name')->nullable();
            $table->string('consignor_name')->nullable();

            // Customs office & port
            $table->string('customs_office', 100)->nullable();
            $table->string('port_of_entry', 100)->nullable();
            $table->string('port_of_exit', 100)->nullable();

            // Origin / Destination
            $table->string('country_of_origin', 3)->nullable();
            $table->string('country_of_destination', 3)->nullable();
            $table->string('country_of_consignment', 3)->nullable();

            // Trade terms
            $table->string('incoterm', 10)->nullable(); // EXW, FOB, CIF, DDP, etc.
            $table->string('transport_mode', 20)->nullable(); // sea, air, road, rail, multimodal, postal, pipeline
            $table->string('vessel_name')->nullable();
            $table->string('voyage_flight_number', 50)->nullable();

            // Values
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->decimal('fob_value', 18, 4)->default(0); // Free on Board
            $table->decimal('freight_value', 18, 4)->default(0);
            $table->decimal('insurance_value', 18, 4)->default(0);
            $table->decimal('cif_value', 18, 4)->default(0); // Cost, Insurance, Freight
            $table->decimal('assessable_value', 18, 4)->default(0); // Customs assessable value
            $table->decimal('total_duty', 18, 4)->default(0);
            $table->decimal('total_vat', 18, 4)->default(0);
            $table->decimal('total_excise', 18, 4)->default(0);
            $table->decimal('total_fees', 18, 4)->default(0); // Other customs fees
            $table->decimal('total_payable', 18, 4)->default(0);

            // Weights & packages
            $table->decimal('gross_weight_kg', 15, 4)->nullable();
            $table->decimal('net_weight_kg', 15, 4)->nullable();
            $table->unsignedInteger('total_packages')->nullable();
            $table->string('package_type', 30)->nullable(); // container, pallet, box, bulk

            // Bill of entry / shipping bill number
            $table->string('bill_of_entry_number', 50)->nullable(); // For imports
            $table->string('shipping_bill_number', 50)->nullable(); // For exports

            // Status
            $table->string('status', 20)->default('draft'); // draft, submitted, assessed, duty_paid, cleared, rejected, cancelled
            $table->date('declaration_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamp('duty_paid_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            // Journal & accounting
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'declaration_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'declaration_type']);
            $table->index(['source_type', 'source_id']);
            $table->index(['declaration_date']);
        });

        Schema::create('ecommerce_channels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 30); // shopify, woocommerce, magento, custom, marketplace
            $table->string('platform_name')->nullable(); // Noon, Amazon, etc.
            $table->string('store_url')->nullable();
            $table->json('credentials')->nullable(); // Encrypted API keys
            $table->json('settings')->nullable();
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('default_customer_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->boolean('sync_products')->default(true);
            $table->boolean('sync_orders')->default(true);
            $table->boolean('sync_inventory')->default(true);
            $table->boolean('auto_fulfill')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('status', 20)->default('active'); // active, paused, disconnected
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('ecommerce_channels')->cascadeOnDelete();
            $table->string('external_order_id');
            $table->string('order_number');
            $table->string('status', 30); // pending, processing, shipped, delivered, cancelled, refunded
            $table->string('financial_status', 30)->nullable(); // pending, paid, partially_paid, refunded
            $table->string('fulfillment_status', 30)->nullable(); // unfulfilled, partial, fulfilled

            // Customer
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Addresses
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();

            // Amounts
            $table->string('currency_code', 3);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            // Shipping
            $table->string('shipping_method')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();

            // Processing
            $table->foreignId('sales_order_id')->nullable(); // Linked ERP sales order
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->boolean('is_processed')->default(false);
            $table->timestamp('processed_at')->nullable();

            $table->json('raw_data')->nullable(); // Original order data
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at');
            $table->timestamps();

            $table->unique(['channel_id', 'external_order_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['channel_id', 'ordered_at']);

            $table->foreign('sales_order_id', 'ecomm_order_sales_order_fk')
                ->references('id')->on('sales_orders')->nullOnDelete();
        });

        Schema::create('ecommerce_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('ecommerce_channels')->cascadeOnDelete();
            $table->string('sync_type', 30); // products, orders, inventory, customers
            $table->string('direction', 10); // push, pull
            $table->string('status', 20); // started, completed, failed
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'created_at']);
        });

        Schema::create('invoice_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('qr_type', 30); // zatca, payment_link, custom
            $table->text('qr_data'); // Encoded data
            $table->string('qr_image_path')->nullable();
            $table->string('payment_link')->nullable();
            $table->decimal('payment_amount', 15, 2)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'qr_type']);
        });

        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->morphs('payable'); // invoice, ecommerce_order, subscription
            $table->string('external_payment_id')->nullable(); // Gateway transaction ID
            $table->string('status', 20); // pending, authorized, captured, failed, refunded
            $table->string('currency_code', 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('payment_method', 30)->nullable(); // card, mada, apple_pay
            $table->string('card_brand')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->json('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('payment_received_id')->nullable(); // Linked to payments_received
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('external_payment_id');

            $table->foreign('payment_received_id', 'online_pay_payment_received_fk')
                ->references('id')->on('payments_received')->nullOnDelete();
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency_code', 3)->default('SAR');
            $table->string('frequency', 20); // daily, weekly, monthly, quarterly, yearly
            $table->unsignedTinyInteger('frequency_interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_occurrence');
            $table->unsignedInteger('occurrences_count')->default(0);
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->boolean('auto_approve')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'next_occurrence']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fraud_rule_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');      // invoice, payment, login, contact
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_uuid')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable();
            $table->string('severity');
            $table->string('status')->default('open'); // open, reviewing, resolved, false_positive
            $table->unsignedInteger('fraud_score')->default(0);
            $table->json('evidence');           // context data captured at alert time
            $table->string('ip_address', 45)->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'status', 'severity']);
            $table->index(['entity_type', 'entity_id']);

            $table->foreign('contact_id', 'fraud_alert_contact_fk')
                ->references('id')->on('contacts')->nullOnDelete();
        });

        Schema::create('price_check_stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('station_code', 30);
            $table->string('location_description')->nullable(); // "Aisle 3", "Near entrance"

            // Hardware
            $table->string('device_type', 30)->default('kiosk'); // kiosk, handheld, mobile, tablet, pos
            $table->string('device_id')->nullable(); // Hardware identifier
            $table->string('scanner_type', 30)->default('laser'); // laser, camera, rfid, nfc

            // Supported scan types
            $table->boolean('scan_barcode')->default(true);
            $table->boolean('scan_qr')->default(true);
            $table->boolean('scan_rfid')->default(false);
            $table->boolean('scan_nfc')->default(false);
            $table->boolean('manual_entry')->default(true); // Type SKU/barcode manually

            // Display options
            $table->boolean('show_price')->default(true);
            $table->boolean('show_stock')->default(false);
            $table->boolean('show_promotions')->default(true);
            $table->boolean('show_alternatives')->default(false);
            $table->boolean('show_loyalty_points')->default(false);
            $table->boolean('show_product_image')->default(true);
            $table->boolean('show_description')->default(true);
            $table->boolean('show_location')->default(false); // Aisle/shelf location

            // Price list to use
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->boolean('use_customer_price')->default(false); // Scan loyalty card first

            // API key for this station
            $table->string('api_token', 64)->unique();

            $table->string('status', 20)->default('active'); // active, inactive, maintenance
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'station_code']);
            $table->index(['branch_id', 'status']);
            $table->index(['api_token']);
        });

        Schema::create('truck_appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('appointment_number', 20);
            $table->dateTime('scheduled_arrival');
            $table->dateTime('scheduled_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->dateTime('actual_departure')->nullable();
            $table->foreignId('dock_door_id')->nullable()->constrained('dock_doors')->nullOnDelete();
            $table->foreignId('yard_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->string('vehicle_plate', 20)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('appointment_type', 20)->default('delivery')
                ->comment('delivery/pickup/both');
            $table->string('reference_type', 30)->nullable()
                ->comment('purchase_order/sales_order/transfer');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 20)->default('scheduled')
                ->comment('scheduled/checked_in/docked/loading/departed/cancelled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status'], 'truck_appt_wh_status_idx');
            $table->index(['scheduled_arrival', 'status'], 'truck_appt_sched_status_idx');
            $table->index(['dock_door_id', 'status'], 'truck_appt_dock_status_idx');
            $table->index(['vendor_id', 'scheduled_arrival'], 'truck_appt_vendor_sched_idx');
            $table->unique(['warehouse_id', 'appointment_number'], 'truck_appt_wh_num_uq');
        });

        Schema::create('yard_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('truck_appointment_id')
                ->constrained('truck_appointments')->cascadeOnDelete();
            $table->foreignId('from_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->foreignId('to_zone_id')->nullable()->constrained('yard_zones')->nullOnDelete();
            $table->foreignId('from_dock_id')->nullable()->constrained('dock_doors')->nullOnDelete();
            $table->foreignId('to_dock_id')->nullable()->constrained('dock_doors')->nullOnDelete();
            $table->string('movement_type', 20)
                ->comment('arrival/move_to_dock/move_to_zone/departure');
            $table->dateTime('moved_at');
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('truck_appointment_id', 'yard_mov_appt_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('yard_movements');
        Schema::dropIfExists('truck_appointments');
        Schema::dropIfExists('price_check_stations');
        Schema::dropIfExists('fraud_alerts');
        Schema::dropIfExists('recurring_expenses');
        Schema::dropIfExists('online_payments');
        Schema::dropIfExists('invoice_qr_codes');
        Schema::dropIfExists('ecommerce_sync_logs');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('ecommerce_channels');
        Schema::dropIfExists('customs_declarations');
        Schema::dropIfExists('service_ticket_comments');
        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('portal_sessions');
        Schema::dropIfExists('portal_document_accesses');
        Schema::dropIfExists('portal_activity_logs');
        Schema::dropIfExists('portal_users');
        Schema::dropIfExists('gdpr_consent_records');
        Schema::dropIfExists('dim_vendor');
        Schema::dropIfExists('dim_customer');
        Schema::dropIfExists('aml_transaction_flags');
        Schema::dropIfExists('aml_suspicious_activities');
        Schema::dropIfExists('aml_screening_cache');
        Schema::dropIfExists('aml_risk_scores');
        Schema::dropIfExists('aml_cdd_records');
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loan_schedules');
        Schema::dropIfExists('inter_company_transfers');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('dunning_notice_items');
        Schema::dropIfExists('dunning_notices');
        Schema::dropIfExists('dunning_blocks');
        Schema::dropIfExists('currency_revaluation_items');
        Schema::dropIfExists('credit_limits');
        Schema::dropIfExists('credit_holds');
        Schema::dropIfExists('credit_exposures');
    }
};
