<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('provider_id');
            $table->string('external_user_id');
            $table->string('access_token_hash')->nullable();
            $table->string('id_token_hash')->nullable();
            $table->timestamp('session_started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id', 'sso_sess_usr_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('provider_id', 'sso_sess_prov_fk')->references('id')->on('sso_providers')->cascadeOnDelete();
        });

        Schema::create('user_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 100)->index();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Composite index for dashboard queries
            $table->index(['organization_id', 'event_type', 'created_at']);
        });

        Schema::create('user_module_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module_definitions')->cascadeOnDelete();

            // Override type
            $table->string('override_type', 20); // grant, revoke, restrict

            // Same fields as role_module_permissions (NULL = inherit from role)
            $table->boolean('can_view')->nullable();
            $table->boolean('can_create')->nullable();
            $table->boolean('can_edit')->nullable();
            $table->boolean('can_delete')->nullable();
            $table->boolean('can_export')->nullable();
            $table->boolean('can_import')->nullable();
            $table->boolean('can_approve')->nullable();
            $table->string('data_scope', 30)->nullable();
            $table->decimal('max_amount_limit', 15, 2)->nullable();
            $table->json('custom_permissions')->nullable();

            $table->text('reason')->nullable(); // Why this override was set
            $table->date('expires_at')->nullable(); // Temporary access
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'module_id']);
        });

        Schema::create('user_onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('onboarding_templates')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('onboarding_steps')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'template_id', 'step_id'], 'uop_user_template_step_unique');
            $table->index(['organization_id', 'user_id', 'template_id'], 'uop_org_user_template_idx');
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->unique();
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->string('device_type', 20)->nullable(); // desktop, mobile, tablet
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('location')->nullable(); // City, Country from IP
            $table->timestamp('login_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('logout_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('logout_reason', 50)->nullable(); // manual, expired, forced

            $table->index(['user_id', 'is_active']);
            $table->index(['session_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('resource_type', 100); // invoice, payment, customer, etc.
            $table->string('resource_id', 100)->nullable();
            $table->json('data'); // Event payload
            $table->unsignedInteger('webhooks_triggered')->default(0);
            $table->timestamp('created_at');

            $table->index(['organization_id', 'event_type']);
            $table->index(['organization_id', 'resource_type', 'resource_id']);
            $table->index('created_at');
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('secret', 64)->nullable(); // For HMAC signature
            $table->json('events'); // Array of event types to subscribe to
            $table->json('headers')->nullable(); // Custom headers to include
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('retry_count')->default(3);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->string('content_type', 50)->default('application/json');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending, success, failed
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->unsignedInteger('duration_ms')->nullable(); // Response time
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
            $table->index(['webhook_id', 'event_type']);
            $table->index('created_at');
            $table->index('next_retry_at');

                $table->index(['status', 'created_at'], 'webhook_deliveries_status_created_at_index');
        });

        Schema::create('webhook_dlq_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('webhook_id');
            $table->string('event_type');
            $table->json('payload');
            $table->unsignedTinyInteger('failure_count')->default(1);
            $table->timestamp('first_failed_at');
            $table->timestamp('last_failed_at');
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->enum('status', ['pending', 'retrying', 'dead', 'replayed'])->default('pending');
            $table->timestamp('replayed_at')->nullable();
            $table->unsignedBigInteger('replayed_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('webhook_id', 'wh_dlq_wh_fk')->references('id')->on('webhooks')->cascadeOnDelete();
            $table->foreign('replayed_by', 'wh_dlq_usr_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('workflow_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->name('wel_org_fk');
            $table->foreignId('approval_request_id')->constrained('approval_requests')->name('wel_request_fk');
            $table->foreignId('workflow_escalation_rule_id')->constrained('workflow_escalation_rules')->name('wel_rule_fk');
            $table->string('escalation_type', 50);
            $table->dateTime('triggered_at');
            $table->foreignId('escalated_to_user_id')->nullable()->constrained('users')->name('wel_escalated_to_fk');
            $table->string('action_taken', 100)->nullable();
            $table->timestamps();

            $table->index(['approval_request_id'], 'wel_request_idx');
        });

        Schema::create('workflow_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->name('wer_workflow_fk');
            $table->unsignedSmallInteger('step_number')->nullable();
            $table->enum('escalation_type', [
                'reminder',
                'escalate_to_manager',
                'escalate_to_admin',
                'auto_approve',
                'auto_reject',
            ])->default('reminder');
            $table->unsignedSmallInteger('trigger_after_hours');
            $table->foreignId('escalate_to_user_id')->nullable()->constrained('users')->name('wer_escalate_to_fk');
            $table->string('escalate_to_role', 100)->nullable();
            $table->text('notification_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['approval_workflow_id'], 'wer_workflow_idx');
        });

        Schema::create('workflow_substitution_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('wsr_org_fk');
            $table->foreignId('approver_id')->constrained('users')->name('wsr_approver_fk');
            $table->foreignId('substitute_id')->constrained('users')->name('wsr_substitute_fk');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['approver_id', 'is_active'], 'wsr_approver_active_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_substitution_rules');
        Schema::dropIfExists('workflow_escalation_rules');
        Schema::dropIfExists('workflow_escalation_logs');
        Schema::dropIfExists('webhook_dlq_entries');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('user_onboarding_progress');
        Schema::dropIfExists('user_module_overrides');
        Schema::dropIfExists('user_events');
        Schema::dropIfExists('sso_sessions');
    }
};
