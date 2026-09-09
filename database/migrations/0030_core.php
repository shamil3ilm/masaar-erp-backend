<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');

            $table->string('bp_number', 30)->unique();         // auto-generated BP-XXXXX
            $table->string('bp_category', 10)->default('ORG'); // ORG | PERSON
            $table->string('name');
            $table->string('name2')->nullable();               // legal name / trade name
            $table->string('search_term', 100)->nullable();

            // Contact info
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('commercial_reg', 50)->nullable();

            // Address
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->nullable();          // ISO alpha-2

            // Linked CRM contact and Purchase supplier
            $table->unsignedBigInteger('contact_id')->nullable(); // -> contacts
            $table->unsignedBigInteger('supplier_id')->nullable(); // -> suppliers

            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'bp_number']);
            $table->index(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'supplier_id']);
        });

        Schema::create('business_partner_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_partner_id');
            $table->string('role_code', 20);                   // FLCU00 | FLVN00 | BUP001 | BUP002
            $table->string('role_name');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_partner_id', 'role_code']);
            $table->foreign('business_partner_id')->references('id')->on('business_partners')->cascadeOnDelete();
        });

        Schema::create('class_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('ca_org_fk');
            $table->foreignId('classification_class_id')->constrained('classification_classes')->name('ca_class_fk');
            $table->string('object_type', 50);
            $table->unsignedBigInteger('object_id');
            $table->dateTime('assigned_at');
            $table->timestamps();

            $table->unique(['classification_class_id', 'object_type', 'object_id'], 'ca_class_obj_unq');
            $table->index(['object_type', 'object_id'], 'ca_obj_idx');
        });

        Schema::create('class_characteristic_values', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('ccv_org_fk');
            $table->foreignId('class_characteristic_id')->constrained('class_characteristics')->name('ccv_char_fk');
            $table->string('object_type', 50);
            $table->unsignedBigInteger('object_id');
            $table->string('text_value', 500)->nullable();
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->date('date_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->timestamps();

            $table->unique(['class_characteristic_id', 'object_type', 'object_id'], 'ccv_char_obj_unq');
            $table->index(['object_type', 'object_id'], 'ccv_obj_idx');
        });

        Schema::create('class_characteristics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('cchar_org_fk');
            $table->foreignId('classification_class_id')->constrained('classification_classes')->name('cchar_class_fk');
            $table->string('characteristic_code', 30);
            $table->string('characteristic_name', 100);
            $table->enum('data_type', ['text', 'numeric', 'date', 'boolean', 'list'])->default('text');
            $table->string('unit_of_measure', 20)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->decimal('min_value', 18, 4)->nullable();
            $table->decimal('max_value', 18, 4)->nullable();
            $table->json('allowed_values')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['classification_class_id', 'characteristic_code'], 'cchar_class_code_unq');
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // total_sales, revenue_chart, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // kpi, chart, table, list, custom
            $table->string('type'); // number, currency, percentage, line_chart, bar_chart, pie_chart, table, list
            $table->json('default_config')->nullable();
            $table->json('available_sizes')->nullable(); // ["1x1", "2x1", "2x2", "4x2"]
            $table->string('data_source')->nullable(); // Service method or endpoint
            $table->string('permission')->nullable(); // Required permission
            $table->string('module')->nullable(); // sales, inventory, accounting
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('edi_message_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->name('ems_org_fk');
            $table->foreignId('edi_message_id')->constrained('edi_messages')->name('ems_message_fk');
            $table->string('segment_id', 10);
            $table->unsignedSmallInteger('segment_sequence');
            $table->json('segment_data');
            $table->timestamps();

            $table->index(['edi_message_id'], 'ems_message_idx');
        });

        Schema::create('edi_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('em_org_fk');
            $table->foreignId('edi_partner_id')->constrained('edi_partners')->name('em_partner_fk');
            $table->string('message_type', 50);
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->enum('status', [
                'received',
                'processing',
                'processed',
                'failed',
                'sent',
                'acknowledged',
            ])->default('received');
            $table->string('control_number', 50)->nullable();
            $table->string('functional_acknowledgment', 50)->nullable();
            $table->longText('raw_content')->nullable();
            $table->json('parsed_content')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'direction', 'status'], 'em_org_dir_status_idx');
            $table->index(['edi_partner_id'], 'em_partner_idx');
            $table->index(['message_type', 'direction'], 'em_type_dir_idx');
        });

        Schema::create('failed_jobs_monitor', function (Blueprint $table) {
            $table->id();
            $table->string('job_class', 500);
            $table->string('queue', 255)->default('default');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at');
            $table->timestamps();

            $table->index('failed_at', 'idx_failed_at');
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // en, ar, hi, ur, etc.
            $table->string('name'); // English, Arabic, Hindi
            $table->string('native_name'); // English, العربية, हिन्दी
            $table->string('direction', 3)->default('ltr'); // ltr, rtl
            $table->string('locale'); // en_US, ar_SA, hi_IN
            $table->string('flag_icon')->nullable(); // emoji or icon code
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('module_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique(); // sales, purchase, inventory, accounting, hr, crm, manufacturing, etc.
            $table->string('group', 50); // core, finance, operations, hr, marketing, advanced
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('sub_modules')->nullable(); // ['invoices', 'quotations', 'payments']
            $table->json('required_modules')->nullable(); // Dependencies: inventory requires accounting
            $table->string('min_subscription_tier', 20)->default('free'); // free, starter, professional, enterprise
            $table->boolean('is_core')->default(false); // Core modules can't be disabled
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('module_readiness_checks', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('check_key', 100);
            $table->string('check_name', 200);
            $table->text('description')->nullable();
            $table->enum('severity', ['error', 'warning', 'info'])->default('error');
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('order')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['module', 'check_key'], 'mrc_module_check_unique');
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();

            // Location & Tax Configuration
            $table->string('country_code', 2); // SA, AE, QA, OM, BH, KW, IN
            $table->string('tax_scheme', 10)->default('VAT'); // VAT, GST, NONE
            // TRN for GCC, GSTIN for India.
            // Encrypted by the model. Ciphertext is 200 characters at its shortest,
            // so the column is sized for the ciphertext, not the value.
            $table->text('tax_number')->nullable();
            $table->string('base_currency', 3)->default('SAR');

            // Fiscal Year
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1); // 1-12
            $table->unsignedTinyInteger('fiscal_year_start_day')->default(1); // 1-31

            // Contact Information
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Settings (JSON for flexibility)
            $table->json('settings')->nullable();

            // Logo
            $table->string('logo_url')->nullable();

            // Status
            $table->string('status', 30)->default('active'); // active, suspended, inactive
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('country_code');
            $table->index('tax_scheme');
            $table->index('is_active');

            $table->string('subscription_tier', 20)->default('standard');
            $table->timestamp('subscription_expires_at')->nullable();
        });

        Schema::create('api_call_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('service', 100);           // 'MasaarClient', 'ZatcaClient', etc.
            $table->string('method', 10);             // GET, POST, etc.
            $table->string('url', 2048);
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('status', 20)->default('success'); // success, error, timeout
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'service', 'created_at']);
            $table->index('status');
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('approvable_type', 100)->nullable(); // App\Models\Sales\Invoice, etc.
            $table->decimal('min_amount', 15, 4)->nullable();
            $table->decimal('max_amount', 15, 4)->nullable();
            $table->json('conditions')->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'approvable_type', 'is_active'], 'approval_wf_org_approvable_active_idx');
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('approver_type', 30); // user, role, department_head, reporting_manager, custom
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->json('approver_custom')->nullable();
            $table->string('action_type', 30)->nullable();
            $table->json('condition')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('requires_all')->default(false);
            $table->unsignedInteger('min_approvers')->default(1);
            $table->unsignedInteger('timeout_hours')->nullable();
            $table->boolean('can_skip')->default(false);
            $table->boolean('can_delegate')->default(false);
            $table->timestamps();

            $table->index(['approval_workflow_id', 'sequence']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 20); // Branch code (unique within org)

            // Address
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 2)->nullable();

            // Contact
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();

            // Tax Number (for branches with separate tax registration)
            $table->string('tax_number', 50)->nullable();

            $table->string('compliance_status', 20)->default('pending'); // pending, active, suspended

            // Status
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['organization_id', 'code']);
            $table->index('is_active');
            $table->index('is_default');

            $table->string('zatca_branch_id')->nullable();
            $table->string('zatca_onboarding_status')->nullable();
            $table->dateTime('zatca_certificate_expires_at')->nullable();
        });

        Schema::create('classification_classes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('class_code', 30);
            $table->string('class_name', 100);
            $table->string('object_type', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'class_code', 'object_type'], 'cc_org_code_type_unq');
            $table->index(['organization_id', 'object_type'], 'cc_org_type_idx');
        });

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50); // invoice, customer, product, employee, etc.
            $table->string('field_name', 50); // Internal name (snake_case)
            $table->string('field_label'); // Display label
            $table->string('field_type', 30); // text, number, decimal, date, datetime, boolean, select, multiselect, textarea, file, url, email, phone
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // For select/multiselect: [{value: 'x', label: 'X'}]
            $table->json('validation')->nullable(); // {required: true, min: 0, max: 100, pattern: '', etc.}
            $table->string('default_value')->nullable();
            $table->string('placeholder')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('field_group')->nullable(); // Group fields together in UI
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('show_in_list')->default(false); // Show in list/table view
            $table->boolean('show_in_form')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'entity_type', 'field_name'], 'cf_defs_org_entity_field_unique');
            $table->index(['organization_id', 'entity_type', 'is_active'], 'cf_defs_org_entity_active_idx');
        });

        Schema::create('custom_field_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50);
            $table->string('name');
            $table->string('slug', 50);
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_collapsible')->default(true);
            $table->boolean('is_collapsed_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'entity_type', 'slug']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->morphs('entity'); // The entity this value belongs to
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 20, 6)->nullable();
            $table->date('value_date')->nullable();
            $table->datetime('value_datetime')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable(); // For multiselect, file references, etc.
            $table->timestamps();

            $table->unique(['field_definition_id', 'entity_type', 'entity_id'], 'custom_field_value_unique');
        });

        Schema::create('edi_partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('partner_code', 50);
            $table->string('partner_name', 100);
            $table->enum('partner_type', ['vendor', 'customer', 'bank', 'carrier', 'other'])->default('vendor');
            $table->enum('edi_standard', ['edifact', 'x12', 'ubl', 'idoc', 'custom'])->default('edifact');
            $table->boolean('is_active')->default(true);
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->name('ep_contact_fk');
            $table->string('interchange_id', 50)->nullable();
            $table->string('interchange_qualifier', 10)->nullable();
            $table->boolean('test_mode')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'partner_code'], 'ep_org_code_unq');
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique(); // invoice_created, payment_reminder, etc.
            $table->string('name', 100);
            $table->string('subject', 255);
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->string('from_name', 100)->nullable();
            $table->string('reply_to', 100)->nullable();
            $table->string('cc', 255)->nullable();
            $table->string('bcc', 255)->nullable();
            $table->json('variables')->nullable(); // Available placeholder variables
            $table->string('language', 5)->default('en');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System templates cannot be deleted
            $table->timestamps();

            $table->unique(['organization_id', 'code', 'language']);
        });

        Schema::create('gdpr_processing_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('activity_name');
            $table->text('purpose');
            $table->enum('legal_basis', ['consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests']);
            $table->json('data_categories');
            $table->json('recipient_categories')->nullable();
            $table->unsignedInteger('retention_period_days')->nullable();
            $table->boolean('third_country_transfers')->default(false);
            $table->boolean('dpia_required')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('import_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('entity_type', 50);
            $table->json('column_mapping');
            $table->json('options')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'entity_type']);
        });

        // Two things number documents. NumberGeneratorService keeps a plain
        // counter per sequence_key; the NumberSequence model keeps a formatted
        // sequence per organisation, branch and document type, and Sales calls
        // it to number quotations and sales orders. The model's columns were
        // never created, so both of those writes failed. Both sets are here
        // until one generator replaces the other.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('sequence_key', 100)->nullable()->unique();
            $table->unsignedBigInteger('current_value')->default(0);

            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50)->nullable();
            $table->string('prefix', 20)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->unsignedBigInteger('current_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->boolean('include_year')->default(true);
            $table->boolean('include_month')->default(false);
            $table->boolean('reset_yearly')->default(true);
            $table->boolean('reset_monthly')->default(false);
            $table->unsignedSmallInteger('last_reset_year')->nullable();
            $table->unsignedTinyInteger('last_reset_month')->nullable();

            $table->timestamps();

            $table->index('organization_id');
            $table->unique(['organization_id', 'branch_id', 'type'], 'number_sequences_org_branch_type');
        });

        Schema::create('onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('module', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'module', 'is_active'], 'ont_org_module_active_idx');
        });

        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('onboarding_templates')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('step_type', ['action', 'info', 'video', 'link'])->default('action');
            $table->string('action_key', 100)->nullable();
            $table->string('help_url', 500)->nullable();
            $table->boolean('is_required')->default(true);
            $table->tinyInteger('order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['template_id', 'order'], 'ons_template_order_idx');
        });

        Schema::create('organization_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Logos
            $table->string('logo_url')->nullable();
            $table->string('logo_dark_url')->nullable(); // For dark mode
            $table->string('favicon_url')->nullable();
            $table->string('login_background_url')->nullable();

            // Colors
            $table->string('primary_color', 20)->default('#3498db');
            $table->string('secondary_color', 20)->default('#2ecc71');
            $table->string('accent_color', 20)->default('#9b59b6');
            $table->string('danger_color', 20)->default('#e74c3c');
            $table->string('warning_color', 20)->default('#f39c12');
            $table->string('success_color', 20)->default('#27ae60');
            $table->string('info_color', 20)->default('#3498db');
            $table->string('text_color', 20)->default('#333333');
            $table->string('background_color', 20)->default('#f8f9fa');
            $table->string('sidebar_color', 20)->default('#2c3e50');
            $table->string('header_color', 20)->default('#ffffff');

            // Typography
            $table->string('font_family')->default('Inter');
            $table->string('font_family_arabic')->default('Cairo'); // For RTL
            $table->integer('base_font_size')->default(14);

            // Theme
            $table->string('theme')->default('light'); // light, dark, auto
            $table->boolean('enable_dark_mode')->default(true);

            // Custom CSS
            $table->text('custom_css')->nullable();

            // Email branding
            $table->string('email_header_color', 20)->nullable();
            $table->string('email_footer_text')->nullable();

            // Document branding
            $table->string('document_watermark')->nullable();
            $table->string('document_footer_text')->nullable();

            $table->timestamps();

            $table->unique('organization_id');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Human-readable name
            $table->string('slug')->unique(); // e.g., 'sales.invoices.create'
            $table->string('module', 50); // e.g., 'sales', 'accounting', 'inventory'
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('module');
        });

        Schema::create('print_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('printer_type'); // laser, inkjet, thermal_80, thermal_58, sunmi_v2, sunmi_v2_pro
            $table->string('default_paper_size')->default('a4');
            $table->json('paper_sizes')->nullable(); // Available paper sizes for this config
            $table->json('thermal_settings')->nullable(); // Width, DPI, cut mode
            $table->json('margin_settings')->nullable(); // top, right, bottom, left
            $table->json('font_settings')->nullable(); // family, size, line_height
            $table->boolean('auto_cut')->default(true);
            $table->boolean('open_drawer')->default(false);
            $table->integer('copies')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'branch_id']);
        });

        Schema::create('print_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->index(); // invoice_a4, invoice_thermal_80, quote_a5, etc.
            $table->string('document_type'); // invoice, quotation, purchase_order, credit_note, delivery_note, payment_receipt
            $table->string('paper_size'); // a3, a4, a5, thermal_80, thermal_58, letter, legal
            $table->string('orientation')->default('portrait'); // portrait, landscape
            $table->text('template_content')->nullable(); // Custom HTML/Blade content
            $table->string('template_file')->nullable(); // Reference to blade file
            $table->json('settings')->nullable(); // Font size, margins, colors, etc.
            $table->json('sections')->nullable(); // Which sections to show/hide
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_qr_code')->default(true);
            $table->boolean('show_signature')->default(false);
            $table->boolean('show_watermark')->default(false);
            $table->string('watermark_text')->nullable();
            $table->string('primary_color')->default('#2c3e50');
            $table->string('secondary_color')->default('#3498db');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'document_type', 'paper_size']);
        });

        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('policy_name', 100);
            $table->integer('retention_years');
            $table->enum('jurisdiction', ['saudi_arabia', 'uae', 'india', 'global'])->default('global');
            $table->enum('action_on_expiry', ['archive', 'delete', 'notify_only'])->default('notify_only');
            $table->boolean('legal_hold_override')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'document_type', 'jurisdiction'], 'rp_org_type_jurisdiction_unique');
        });

        Schema::create('retention_schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->timestamp('run_at');
            $table->integer('documents_evaluated')->default(0);
            $table->integer('documents_archived')->default(0);
            $table->integer('documents_deleted')->default(0);
            $table->integer('documents_skipped_legal_hold')->default(0);
            $table->text('run_log')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false); // System roles can't be deleted
            $table->timestamps();

            // Slug unique within organization (null org = global)
            $table->unique(['organization_id', 'slug']);
            $table->index('is_system');
        });

        Schema::create('role_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();
            $table->string('menu_label');
            $table->string('menu_icon')->nullable();
            $table->string('route_name')->nullable();
            $table->string('parent_menu')->nullable(); // For nested menus
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_pinned')->default(false); // Pinned to sidebar/favorites
            $table->timestamps();

            $table->index(['role_id', 'position']);
        });

        Schema::create('role_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();

            // CRUD + special permissions
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_import')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_print')->default(false);

            // Data scope
            $table->string('data_scope', 30)->default('own'); // own, branch, department, organization, all
            // own = only records they created
            // branch = records in their branch
            // department = records in their department
            // organization = all records in org
            // all = no restrictions (super admin)

            // Financial limits
            $table->decimal('max_amount_limit', 15, 2)->nullable(); // Max transaction amount
            $table->decimal('max_discount_percent', 5, 2)->nullable(); // Max discount they can give

            // Custom permissions for this module
            $table->json('custom_permissions')->nullable(); // Module-specific extras

            $table->timestamps();

            $table->unique(['role_id', 'module_id']);
            $table->index(['role_id']);
            $table->index(['module_id']);
        });

        Schema::create('sso_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('provider_name');
            $table->enum('protocol', ['oauth2', 'saml2', 'oidc']);
            $table->string('client_id')->nullable();
            $table->text('client_secret_encrypted')->nullable();
            $table->string('authorization_endpoint')->nullable();
            $table->string('token_endpoint')->nullable();
            $table->string('userinfo_endpoint')->nullable();
            $table->string('saml_entity_id')->nullable();
            $table->string('saml_sso_url')->nullable();
            $table->text('saml_certificate')->nullable();
            $table->json('attribute_mapping')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('tenant_rate_limit_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->unsignedSmallInteger('requests_per_minute')->default(60);
            $table->unsignedSmallInteger('requests_per_hour')->default(1000);
            $table->unsignedInteger('requests_per_day')->default(10000);
            $table->unsignedSmallInteger('burst_limit')->default(100);
            $table->unsignedSmallInteger('api_key_limit')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->json('custom_limits')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('tracked_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_class', 255);
            $table->string('job_key', 100)->nullable()->comment('Business key for idempotency, e.g. payroll_period_id');
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending|running|succeeded|failed|replayed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['job_class', 'status']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index('job_key');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_jobs');
        Schema::dropIfExists('tenant_rate_limit_configs');
        Schema::dropIfExists('sso_providers');
        Schema::dropIfExists('role_module_permissions');
        Schema::dropIfExists('role_menu_items');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('retention_schedule_runs');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('print_templates');
        Schema::dropIfExists('print_configurations');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('organization_branding');
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('onboarding_templates');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('import_templates');
        Schema::dropIfExists('gdpr_processing_activities');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('edi_partners');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_groups');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('classification_classes');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('api_call_logs');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('module_readiness_checks');
        Schema::dropIfExists('module_definitions');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('failed_jobs_monitor');
        Schema::dropIfExists('edi_messages');
        Schema::dropIfExists('edi_message_segments');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('class_characteristics');
        Schema::dropIfExists('class_characteristic_values');
        Schema::dropIfExists('class_assignments');
        Schema::dropIfExists('business_partner_roles');
        Schema::dropIfExists('business_partners');
    }
};
