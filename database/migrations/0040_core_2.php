<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('language_code', 10);
            $table->string('group'); // validation, messages, labels, invoice, etc.
            $table->string('key'); // invoice.title, button.save, etc.
            $table->text('value');
            $table->timestamps();

            $table->unique(['organization_id', 'language_code', 'group', 'key'], 'trans_unique');
            $table->index(['language_code', 'group']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            // Add UUID after id
            $table->uuid('uuid')->unique();

            // Organization relationship (nullable for super admins)
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            // Employee link (for HR module)
            $table->foreignId('employee_id')->nullable();

            // Additional contact info
            $table->string('phone', 20)->nullable();

            // Preferences
            $table->string('preferred_language', 5)->default('en');
            $table->string('timezone', 50)->default('Asia/Riyadh');

            // Two-factor authentication
            $table->boolean('two_factor_enabled')->default(false);
            // Encrypted by the model: a 32-character secret is 256 characters
            // of ciphertext, one more than a default string column holds.
            $table->text('two_factor_secret')->nullable();

            // Status flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_super_admin')->default(false);

            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Soft delete
            $table->softDeletes();

            // Indexes
            $table->index('organization_id');
            $table->index('is_active');
            $table->index('is_super_admin');

            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('deactivation_reason', 255)->nullable();
            $table->timestamp('roles_updated_at')->nullable();

            $table->json('module_access')->nullable();

            $table->string('email_verification_code', 255)->nullable();
            $table->timestamp('email_verification_code_sent_at')->nullable();

            // Hashed recovery codes stored as a JSON array, shown plaintext only once on 2FA enable
            $table->json('two_factor_recovery_codes')->nullable();

            // Timestamp of when the user last completed 2FA setup confirmation
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->string('registration_source', 30)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('referral_code', 50)->nullable();
            $table->string('registration_device_type', 20)->nullable();
            $table->string('registration_ip', 45)->nullable();
            $table->unsignedBigInteger('invited_by_user_id')->nullable();

            $table->foreign('invited_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            //
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // Subject (the entity the activity is about)
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');

            // Causer (who/what caused this activity - could be user, system, API)
            $table->string('causer_type', 100)->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();

            // Activity details
            $table->string('event', 50); // created, updated, deleted, sent, approved, etc.
            $table->string('description', 500)->nullable();

            // Change tracking
            $table->json('properties')->nullable(); // Additional properties
            $table->json('old_values')->nullable(); // Values before change
            $table->json('new_values')->nullable(); // Values after change

            // Metadata
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('source', 30)->default('web'); // web, api, system, import

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('event');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            // What happened
            $table->string('action', 50); // created, updated, deleted, viewed, exported, imported, approved, rejected, etc.
            $table->string('entity_type', 100); // Invoice, Customer, Product, etc.
            $table->string('entity_id', 100)->nullable();
            $table->string('entity_name')->nullable(); // Human-readable identifier

            // Details
            $table->string('description'); // Human-readable description
            $table->json('old_values')->nullable(); // Previous state
            $table->json('new_values')->nullable(); // New state
            $table->json('changed_fields')->nullable(); // List of changed field names
            $table->json('metadata')->nullable(); // Additional context

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_url')->nullable();
            $table->string('session_id', 100)->nullable();

            // Categorization
            $table->string('module', 50)->nullable(); // sales, inventory, hr, etc.
            $table->string('severity', 20)->default('info'); // info, warning, error, critical
            $table->boolean('is_system')->default(false); // System-generated vs user action

            $table->timestamp('created_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'user_id', 'created_at']);
            $table->index(['organization_id', 'entity_type', 'entity_id']);
            $table->index(['organization_id', 'action']);
            $table->index(['organization_id', 'module']);

            $table->unsignedBigInteger('impersonated_by_id')->nullable();
            $table->char('impersonation_session_id', 36)->nullable();

            $table->foreign('impersonated_by_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('impersonation_session_id');
            $table->index('impersonated_by_id');
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            $table->nullableMorphs('approvable');
            $table->foreignId('current_step_id')->nullable()->constrained('approval_workflow_steps')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 15, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('approval_workflow_steps')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('delegated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('action_at')->nullable();
            $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();

            $table->index(['approval_request_id', 'workflow_step_id']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Polymorphic relation
            $table->string('attachable_type', 100);
            $table->unsignedBigInteger('attachable_id');

            // File details
            $table->string('file_name', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size'); // bytes
            $table->string('disk', 50)->default('local'); // local, s3, etc.
            $table->string('path', 500);

            // Metadata
            $table->string('category', 50)->nullable(); // receipt, contract, image, etc.
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable(); // Image dimensions, PDF pages, etc.

            // Security
            $table->boolean('is_public')->default(false);
            $table->string('visibility', 20)->default('private'); // private, organization, public
            $table->timestamp('expires_at')->nullable();

            // Audit
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['organization_id', 'category']);
        });

        Schema::create('attachment_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20); // view, download, share
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['attachment_id', 'action']);
        });

        Schema::create('bulk_operation_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('operation_type');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->json('payload');
            $table->json('result_summary')->nullable();
            $table->json('error_log')->nullable();
            $table->unsignedBigInteger('initiated_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('initiated_by', 'bulk_op_usr_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('change_freeze_periods', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('reason')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->enum('scope', ['all', 'module'])->default('all');
            $table->json('affected_modules')->nullable();
            $table->json('bypass_roles')->nullable();
            $table->string('bypass_permission', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'starts_at', 'ends_at'], 'change_freeze_org_time_idx');
        });

        Schema::create('change_transport_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'ctr_org_id_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->string('request_number', 20);
            $table->string('description', 200);
            $table->string('request_type', 20)
                ->comment('workbench/customizing/transport_of_copies');
            $table->string('category', 30)
                ->comment('feature/bugfix/configuration/data_migration');
            $table->string('target_environment', 20)
                ->comment('quality/production/staging');
            $table->string('status', 20)->default('open')
                ->comment('open/released/imported/failed');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by', 'ctr_created_by_fk')
                ->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('released_by')->nullable();
            $table->foreign('released_by', 'ctr_released_by_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->dateTime('released_at')->nullable();
            $table->dateTime('imported_at')->nullable();
            $table->text('import_log')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('request_number', 'ctr_request_number_idx');
            $table->index(['status', 'target_environment'], 'ctr_status_env_idx');
            $table->index(['created_by', 'status'], 'ctr_created_status_idx');
        });

        Schema::create('change_transport_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_transport_request_id');
            $table->foreign('change_transport_request_id', 'ctl_request_id_fk')
                ->references('id')->on('change_transport_requests')->onDelete('cascade');
            $table->string('action', 50)
                ->comment('created/object_added/released/import_started/imported/failed/rollback');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->foreign('performed_by', 'ctl_performed_by_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->string('environment', 20)->nullable();
            $table->text('message')->nullable();
            $table->dateTime('created_at');

            $table->index('change_transport_request_id', 'ctl_req_id_idx');
        });

        Schema::create('change_transport_object_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_transport_request_id');
            $table->foreign('change_transport_request_id', 'ctoa_request_id_fk')
                ->references('id')->on('change_transport_requests')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'ctoa_user_id_fk')
                ->references('id')->on('users')->onDelete('cascade');
            $table->dateTime('assigned_at');
            $table->timestamps();
        });

        Schema::create('change_transport_objects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('change_transport_request_id');
            $table->foreign('change_transport_request_id', 'cto_request_id_fk')
                ->references('id')->on('change_transport_requests')->onDelete('cascade');
            $table->string('object_type', 50)
                ->comment('migration/config/route/permission/setting');
            $table->string('object_name', 200);
            $table->string('object_key', 200)->nullable();
            $table->string('change_type', 20)
                ->comment('create/modify/delete');
            $table->json('payload')->nullable();
            $table->string('checksums', 64)->nullable();
            $table->timestamps();

            $table->index('change_transport_request_id', 'cto_req_id_idx');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Commentable entity
            $table->string('commentable_type', 100);
            $table->unsignedBigInteger('commentable_id');

            // Parent comment for threading
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();

            $table->text('content');
            $table->boolean('is_internal')->default(false); // Internal note vs customer-visible
            $table->boolean('is_pinned')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id']);
        });

        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->default('Default');
            $table->string('type')->default('main'); // main, sales, inventory, finance
            $table->json('widgets'); // Widget configuration
            $table->json('layout'); // Grid layout positions
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false); // Shared with org
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'type']);
        });

        Schema::create('document_download_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('document_type', 20); // 'invoice', 'bill', 'receipt', 'payslip'
            $table->unsignedBigInteger('document_id');
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('first_accessed_at')->nullable();
            $table->dateTime('access_expires_at')->nullable();
            $table->smallInteger('access_count')->default(0);
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index('token');
            $table->index(['document_type', 'document_id']);
            $table->index(['organization_id', 'expires_at']);
        });

        Schema::create('document_legal_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->string('hold_reason', 500);
            $table->foreignId('held_by')->constrained('users');
            $table->date('hold_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'dlh_doc_idx');
            $table->index(['organization_id', 'is_active'], 'dlh_org_active_idx');
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_code', 50)->nullable();
            $table->string('emailable_type', 100)->nullable(); // Invoice, Quotation, etc.
            $table->unsignedBigInteger('emailable_id')->nullable();
            $table->string('to_email', 255);
            $table->string('to_name', 255)->nullable();
            $table->string('subject', 255);
            $table->text('body_preview')->nullable(); // First 500 chars
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, failed, bounced
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('message_id', 255)->nullable(); // External email provider message ID
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['emailable_type', 'emailable_id']);
        });

        Schema::create('entity_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 100);
            $table->string('entity_id', 100);
            $table->string('entity_name')->nullable();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'entity_type', 'entity_id']);
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50); // customers, invoices, products, etc.
            $table->string('format', 10)->default('xlsx'); // xlsx, csv, pdf
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->json('filters')->nullable(); // Applied filters for export
            $table->json('columns')->nullable(); // Selected columns to export
            $table->json('options')->nullable(); // Export options
            $table->unsignedInteger('total_records')->default(0);
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // When the file will be deleted
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'entity_type']);
            $table->index('expires_at');
        });

        Schema::create('feature_adoption_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->timestamp('first_used_at');
            $table->timestamp('last_used_at');
            $table->unsignedInteger('usage_count')->default(1);

            $table->unique(['organization_id', 'user_id', 'feature_key'], 'fae_org_user_feature_unique');
            $table->index(['organization_id', 'feature_key'], 'fae_org_feature_idx');
        });

        // Per-organisation feature flags. The platform's own feature_flags
        // table (0020_admin) is a different thing: a global flag with a
        // rollout percentage. This is the tenant's own on/off switch, and its
        // two companion tables below key on the same organisation and flag.
        Schema::create('organization_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('flag_key', 100);
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'flag_key'], 'off_org_flag_unique');
        });

        Schema::create('feature_flag_rollout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('flag_key', 100);
            $table->enum('action', [
                'enabled',
                'disabled',
                'target_added',
                'target_removed',
                'rollout_percentage_set',
            ]);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'flag_key'], 'ffrl_org_flag_idx');
        });

        Schema::create('feature_flag_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('flag_key', 100);
            $table->enum('target_type', ['user', 'branch', 'role', 'percentage']);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'flag_key', 'target_type', 'target_id'],
                'fft_org_flag_type_target_unique'
            );
            $table->index(['organization_id', 'flag_key'], 'fft_org_flag_idx');
        });

        Schema::create('gdpr_data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->enum('request_type', ['access', 'erasure', 'portability', 'rectification', 'restriction', 'objection']);
            $table->string('requester_name');
            $table->string('requester_email');
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->enum('status', ['received', 'verifying', 'processing', 'completed', 'rejected'])->default('received');
            $table->timestamp('received_at');
            $table->timestamp('deadline_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('data_exported_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('requester_id', 'gdpr_req_usr_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50); // customers, suppliers, products, employees, etc.
            $table->string('file_name');
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, cancelled
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->json('column_mapping')->nullable(); // Maps file columns to entity fields
            $table->json('options')->nullable(); // Import options (update_existing, skip_errors, etc.)
            $table->json('errors')->nullable(); // Array of row-level errors
            $table->json('summary')->nullable(); // Import summary stats
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'entity_type']);
            $table->index('created_at');
        });

        Schema::create('ip_allowlist_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('rule_name');
            $table->string('ip_address')->nullable();
            $table->string('ip_range_start')->nullable();
            $table->string('ip_range_end')->nullable();
            $table->string('cidr_notation')->nullable();
            $table->enum('rule_type', ['allow', 'deny'])->default('allow');
            $table->enum('applies_to', ['all', 'api', 'admin', 'specific_role'])->default('all');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('role_id', 'ip_allow_role_fk')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('created_by', 'ip_allow_usr_fk')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('job_monitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->foreign('organization_id', 'jm_org_id_fk')
                ->references('id')->on('organizations')->onDelete('set null');
            $table->string('job_class', 200);
            $table->string('job_name', 200);
            $table->string('queue_name', 50)->default('default');
            $table->string('status', 20)->default('queued')
                ->comment('queued/running/completed/failed/retrying');
            $table->json('payload')->nullable();
            $table->text('output')->nullable();
            $table->text('error_message')->nullable();
            $table->tinyInteger('attempts')->default(0);
            $table->tinyInteger('max_attempts')->default(3);
            $table->tinyInteger('progress_percentage')->default(0);
            $table->string('progress_message', 200)->nullable();
            $table->dateTime('queued_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->integer('run_duration_seconds')->nullable();
            $table->string('triggered_by', 50)->default('manual')
                ->comment('manual/scheduled/event/system');
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->foreign('triggered_by_user_id', 'jm_triggered_by_user_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['status', 'queued_at'], 'jm_status_queued_idx');
            $table->index(['job_class', 'status'], 'jm_class_status_idx');
            $table->index(['organization_id', 'status'], 'jm_org_status_idx');
            $table->index(['queue_name', 'status'], 'jm_queue_status_idx');
        });

        Schema::create('job_monitor_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_monitor_id');
            $table->foreign('job_monitor_id', 'jml_monitor_id_fk')
                ->references('id')->on('job_monitors')->onDelete('cascade');
            $table->string('level', 10)->comment('info/warning/error/debug');
            $table->text('message');
            $table->json('context')->nullable();
            $table->dateTime('created_at');

            $table->index(['job_monitor_id', 'level'], 'jml_monitor_level_idx');
        });

        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->string('status', 20); // success, failed, blocked, 2fa_required
            $table->string('failure_reason')->nullable();
            $table->timestamp('attempted_at');

            $table->index(['user_id', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
            $table->index(['email', 'attempted_at']);
        });

        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
        });

        Schema::create('module_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();
            $table->string('action', 50); // view, create, edit, delete, approve, export, import
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->boolean('was_allowed')->default(true);
            $table->string('denial_reason')->nullable(); // Why access was denied
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at');

            $table->index(['organization_id', 'accessed_at']);
            $table->index(['user_id', 'accessed_at']);
            $table->index(['module_id', 'was_allowed']);
        });

        Schema::create('module_readiness_results', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50);
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('run_at');
            $table->enum('overall_status', ['pass', 'fail', 'warning'])->default('pass');
            $table->json('results');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'module'], 'mrr_org_module_idx');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('notification_type', 100);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('database_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'notification_type']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 100); // invoice.created, payment.received, stock.low, etc.
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('action_url')->nullable(); // URL to navigate to
            $table->string('action_text', 50)->nullable(); // Button text
            $table->string('notifiable_type')->nullable(); // Related model class
            $table->unsignedBigInteger('notifiable_id')->nullable(); // Related model ID
            $table->json('data')->nullable(); // Additional data
            $table->string('channel', 20)->default('database'); // database, email, sms, push
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['organization_id', 'type']);
            $table->index(['notifiable_type', 'notifiable_id']);

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index');
        });

        Schema::create('organization_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->date('enabled_at')->nullable();
            $table->date('disabled_at')->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('config')->nullable(); // Module-specific config overrides
            $table->timestamps();

            $table->unique(['organization_id', 'module_id']);
        });

        Schema::create('organization_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('module_code', 50);
            $table->boolean('is_enabled')->default(true);
            $table->json('enabled_features')->nullable(); // Specific features within module
            $table->json('settings')->nullable(); // Module-specific settings
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'module_code']);
            $table->index(['organization_id', 'is_enabled']);
        });

        Schema::create('recurring_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('profile_type', 30); // invoice, bill, journal_entry, expense

            // Source document reference (the template)
            $table->string('source_type', 100); // Invoice, Bill, JournalEntry
            $table->unsignedBigInteger('source_id');

            // Scheduling
            $table->string('frequency', 20); // daily, weekly, monthly, quarterly, yearly, custom
            $table->unsignedSmallInteger('interval')->default(1); // every X frequency
            $table->json('schedule_config')->nullable(); // Day of week, day of month, etc.

            // Dates
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = no end
            $table->date('next_run_date')->nullable();
            $table->date('last_run_date')->nullable();

            // Limits
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->unsignedInteger('occurrences_count')->default(0);

            // Options
            $table->boolean('auto_send')->default(false); // Auto-send/post after creation
            $table->boolean('send_reminder')->default(false);
            $table->unsignedSmallInteger('reminder_days_before')->default(3);
            $table->string('status', 20)->default('active'); // active, paused, completed, expired

            // Notification
            $table->boolean('notify_on_creation')->default(true);
            $table->string('notify_email', 255)->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'next_run_date']);
        });

        Schema::create('recurring_profile_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_profile_id')->constrained()->cascadeOnDelete();
            $table->string('created_type', 100); // The type of document created
            $table->unsignedBigInteger('created_id'); // The ID of created document
            $table->date('scheduled_date');
            $table->date('created_date');
            $table->string('status', 20)->default('success'); // success, failed, skipped
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['recurring_profile_id', 'status']);
        });

        Schema::create('sensitive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('model_type', 100);
            $table->unsignedBigInteger('model_id');
            $table->string('action', 20)->default('read');
            $table->string('sensitive_fields', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id'], 'sal_model_idx');
            $table->index(['user_id', 'created_at'], 'sal_user_date_idx');
            $table->index(['organization_id', 'created_at'], 'sal_org_date_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_access_logs');
        Schema::dropIfExists('recurring_profile_logs');
        Schema::dropIfExists('recurring_profiles');
        Schema::dropIfExists('organization_modules');
        Schema::dropIfExists('organization_module_access');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('module_readiness_results');
        Schema::dropIfExists('module_access_logs');
        Schema::dropIfExists('mentions');
        Schema::dropIfExists('login_history');
        Schema::dropIfExists('job_monitor_logs');
        Schema::dropIfExists('job_monitors');
        Schema::dropIfExists('ip_allowlist_rules');
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('gdpr_data_subject_requests');
        Schema::dropIfExists('feature_flag_targets');
        Schema::dropIfExists('organization_feature_flags');
        Schema::dropIfExists('feature_flag_rollout_logs');
        Schema::dropIfExists('feature_adoption_events');
        Schema::dropIfExists('export_jobs');
        Schema::dropIfExists('entity_views');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('document_legal_holds');
        Schema::dropIfExists('document_download_tokens');
        Schema::dropIfExists('dashboard_layouts');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('change_transport_objects');
        Schema::dropIfExists('change_transport_object_assignments');
        Schema::dropIfExists('change_transport_logs');
        Schema::dropIfExists('change_transport_requests');
        Schema::dropIfExists('change_freeze_periods');
        Schema::dropIfExists('bulk_operation_jobs');
        Schema::dropIfExists('attachment_access_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('users');
        Schema::dropIfExists('translations');
    }
};
