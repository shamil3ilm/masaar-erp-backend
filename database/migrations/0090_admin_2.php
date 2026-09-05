<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('system_announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('organization_admin_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('platform_admins')->cascadeOnDelete();
            $table->text('note');
            $table->string('note_type', 30)->default('general'); // general, warning, support, billing
            $table->boolean('is_internal')->default(true); // Visible only to admins
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('organization_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('status_from', 30)->nullable();
            $table->string('status_to', 30);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('platform_admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('platform_admins')->cascadeOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['organization_id']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ticket_number', 30)->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('category', 50); // technical, billing, feature_request, bug_report, general
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->string('status', 30)->default('open'); // open, in_progress, waiting_response, resolved, closed
            $table->json('tags')->nullable();
            $table->string('source', 30)->default('web'); // web, email, api, phone
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('satisfaction_rating', 2, 1)->nullable(); // 1-5
            $table->text('satisfaction_feedback')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['assigned_admin_id', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->text('message');
            $table->boolean('is_internal_note')->default(false); // Only visible to admins
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('dim_organization', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'dim_org_org_id_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->string('org_name');
            $table->string('org_type', 50)->nullable();
            $table->char('country_code', 3);
            $table->char('currency_code', 3);
            $table->tinyInteger('fiscal_year_start_month')->default(1);
            $table->timestamps();
        });

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);           // GET, POST, PUT, DELETE, PATCH
            $table->string('route_name')->nullable(); // Named route (e.g. sales.invoices.store)
            $table->string('module')->nullable();    // Resolved module (sales, hr, inventory...)
            $table->string('action')->nullable();    // store, index, show, update, destroy
            $table->string('entity_type')->nullable(); // Invoice, Employee, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedSmallInteger('response_status'); // HTTP status code
            $table->unsignedInteger('duration_ms')->nullable(); // Request duration
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->json('request_summary')->nullable(); // non-sensitive subset of request params
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'module', 'created_at']);
            $table->index(['route_name', 'created_at']);
        });

        Schema::create('user_cluster_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('cluster_name');         // high_value, inactive, power_user, etc.
            $table->string('algorithm')->default('rule_based'); // rule_based, kmeans (future)
            $table->unsignedTinyInteger('confidence')->default(100); // 0-100
            $table->json('dimensions');             // dimension values used for assignment
            $table->timestamp('assigned_at');
            $table->timestamp('expires_at')->nullable(); // re-evaluate after this date
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'cluster_name']);
            $table->index(['organization_id', 'cluster_name']);
        });

        Schema::create('user_feature_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module');               // sales, hr, inventory, etc.
            $table->string('feature');              // invoices, employees, stock_transfer, etc.
            $table->date('usage_date');
            $table->unsignedInteger('access_count')->default(0);
            $table->unsignedInteger('create_count')->default(0);
            $table->unsignedInteger('update_count')->default(0);
            $table->unsignedInteger('delete_count')->default(0);
            $table->unsignedInteger('total_duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'module', 'feature', 'usage_date']);
            $table->index(['organization_id', 'module', 'usage_date']);
        });

        Schema::create('user_sessions_extended', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('session_token_hash', 64); // hashed JWT jti claim
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable(); // computed on logout
            $table->unsignedInteger('request_count')->default(0);
            $table->json('modules_accessed')->nullable(); // set of module names visited
            $table->string('end_reason')->nullable(); // logout, timeout, token_blacklisted
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'started_at']);
            $table->index(['organization_id', 'started_at']);
        });

        Schema::create('automation_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->json('variables')->nullable(); // Available variables
            $table->string('category', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type', 50); // event, schedule, manual
            $table->string('trigger_event')->nullable(); // invoice.created, payment.received, etc.
            $table->string('trigger_schedule')->nullable(); // Cron expression for scheduled
            $table->string('entity_type', 50); // invoice, customer, expense, etc.
            $table->json('conditions'); // Array of condition groups (AND/OR)
            $table->json('actions'); // Array of actions to execute
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('stop_on_match')->default(false); // Stop processing other rules
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'entity_type', 'is_active']);
            $table->index(['organization_id', 'trigger_event', 'is_active']);
        });

        Schema::create('automation_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->morphs('entity'); // The entity that triggered the rule
            $table->string('status', 20); // success, failed, skipped
            $table->json('conditions_matched')->nullable();
            $table->json('actions_executed')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->timestamps();

            $table->index(['rule_id', 'created_at']);
        });

        Schema::create('automation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->timestamp('scheduled_for');
            $table->timestamp('executed_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending, running, completed, failed
            $table->timestamps();

            $table->index(['scheduled_for', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('automation_schedules');
        Schema::dropIfExists('automation_rule_logs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('automation_email_templates');
        Schema::dropIfExists('user_sessions_extended');
        Schema::dropIfExists('user_feature_usage');
        Schema::dropIfExists('user_cluster_assignments');
        Schema::dropIfExists('user_activity_logs');
        Schema::dropIfExists('dim_organization');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('platform_admin_activities');
        Schema::dropIfExists('organization_status_history');
        Schema::dropIfExists('organization_admin_notes');
        Schema::dropIfExists('announcement_reads');
    }
};
