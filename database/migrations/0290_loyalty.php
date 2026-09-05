<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs')->cascadeOnDelete();
            $table->foreignId('customer_tier_id')->nullable()->constrained('customer_tiers')->nullOnDelete();
            $table->string('membership_number', 30)->nullable();

            // Points
            $table->unsignedBigInteger('total_earned_points')->default(0);
            $table->unsignedBigInteger('total_redeemed_points')->default(0);
            $table->unsignedBigInteger('total_expired_points')->default(0);
            $table->unsignedBigInteger('available_points')->default(0);
            $table->unsignedBigInteger('pending_points')->default(0); // Earned but not yet available

            // Spending
            $table->decimal('total_spending', 15, 2)->default(0);
            $table->decimal('spending_this_period', 15, 2)->default(0);

            // Dates
            $table->date('enrolled_at');
            $table->date('tier_qualified_at')->nullable();
            $table->date('tier_expires_at')->nullable();
            $table->date('last_activity_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id', 'loyalty_program_id'], 'cust_loyalty_org_contact_program_unique');
            $table->index(['membership_number']);
        });

        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loyalty_account_id')->constrained('customer_loyalty_accounts')->cascadeOnDelete();
            $table->string('transaction_type', 30); // earn, redeem, expire, adjust, bonus, refund_reversal
            $table->integer('points'); // Positive for earn, negative for redeem/expire
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('description');

            // Source reference
            $table->string('source_type', 100)->nullable(); // Invoice, Order, manual, etc.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('source_amount', 15, 2)->nullable(); // Order/invoice amount

            // Earn multiplier applied
            $table->decimal('earn_multiplier', 5, 2)->default(1.00);

            // Expiry
            $table->date('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['loyalty_account_id', 'transaction_type']);
            $table->index(['loyalty_account_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['expires_at', 'is_expired']);
        });

        Schema::create('maintenance_order_cost_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->foreignId('cost_element_id')->nullable()->constrained('cost_elements')->nullOnDelete();
            $table->string('cost_type', 30)
                ->comment('labor/material/external/overhead');
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('total_cost', 18, 4);
            $table->string('currency_code', 3);
            $table->date('posting_date');
            $table->foreignId('vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['maintenance_order_id', 'cost_type'], 'mo_cost_line_order_type_idx');
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('complaint_number', 50)->unique();
            $table->enum('complaint_source', ['customer', 'internal', 'regulatory', 'supplier'])->default('customer');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id', 'complaint_contact_fk')->references('id')->on('contacts')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed', 'withdrawn'])->default('open');
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->foreign('assigned_to_id', 'complaint_assignee_fk')->references('id')->on('users')->nullOnDelete();
            $table->date('received_date');
            $table->date('target_resolution_date')->nullable();
            $table->date('actual_resolution_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('complaint_communications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('complaint_id');
            $table->foreign('complaint_id', 'complaint_comm_fk')->references('id')->on('complaints')->cascadeOnDelete();
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->enum('channel', ['email', 'phone', 'letter', 'portal', 'in_person'])->default('email');
            $table->text('content');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'complaint_comm_user_fk')->references('id')->on('users');
            $table->timestamp('communicated_at');
            $table->timestamps();
        });

        Schema::create('complaint_resolutions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('complaint_id');
            $table->foreign('complaint_id', 'complaint_res_fk')->references('id')->on('complaints')->cascadeOnDelete();
            $table->enum('resolution_type', ['replacement', 'refund', 'credit', 'repair', 'explanation', 'apology', 'other'])->default('other');
            $table->text('resolution_description');
            $table->boolean('customer_accepted')->default(false);
            $table->date('resolution_date');
            $table->unsignedBigInteger('resolved_by_id');
            $table->foreign('resolved_by_id', 'complaint_res_user_fk')->references('id')->on('users');
            $table->timestamps();
        });

        Schema::create('supplier_quality_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id');
            $table->foreign('supplier_id', 'sq_rating_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete();
            $table->date('rating_period_start');
            $table->date('rating_period_end');
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->decimal('delivery_score', 5, 2)->nullable();
            $table->decimal('price_score', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->enum('classification', ['preferred', 'approved', 'conditional', 'disqualified'])->default('approved');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('evaluated_by_id')->nullable();
            $table->foreign('evaluated_by_id', 'sq_rating_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contact_messaging_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('marketing_enabled')->default(true);
            $table->boolean('transactional_enabled')->default(true);
            $table->boolean('reminder_enabled')->default(true);
            $table->string('preferred_channel', 30)->default('email');
            $table->string('preferred_language', 5)->default('en');
            $table->string('timezone')->nullable();
            $table->json('quiet_hours')->nullable(); // {"start": "22:00", "end": "08:00"}
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id']);
        });

        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_id')->nullable()->constrained('messaging_automations')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('messaging_channels')->nullOnDelete();

            // Channel info
            $table->string('channel_type', 30);
            $table->string('sender', 255)->nullable();
            $table->string('recipient', 255);
            $table->string('recipient_name')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Content
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('html_body')->nullable();

            // Context
            $table->string('entity_type', 100)->nullable(); // invoice, payment, order
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('category', 50); // transactional, promotional, reminder

            // Status
            $table->string('status', 20)->default('queued'); // queued, sending, sent, delivered, failed, bounced, opened, clicked
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();

            // Cost tracking
            $table->decimal('cost', 10, 4)->nullable(); // SMS/WhatsApp message cost
            $table->string('cost_currency', 3)->nullable();

            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'channel_type', 'created_at']);
            $table->index(['organization_id', 'status']);
            $table->index(['contact_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'next_retry_at']);
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('provider', 50)->nullable();
            $table->string('recipient')->nullable(); // email address or phone number
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, delivered, failed, bounced
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'channel_type']);
            $table->index('outbound_message_id');
            $table->index('sent_at');
        });

        Schema::create('message_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('provider', 50)->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('queued'); // queued, processing, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->index('outbound_message_id');
        });

        Schema::create('outbound_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('outbound_messages')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size');
            $table->boolean('is_inline')->default(false); // Inline image in HTML email
            $table->timestamps();

            $table->index(['message_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_message_attachments');
        Schema::dropIfExists('message_queues');
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('contact_messaging_preferences');
        Schema::dropIfExists('supplier_quality_ratings');
        Schema::dropIfExists('complaint_resolutions');
        Schema::dropIfExists('complaint_communications');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('maintenance_order_cost_lines');
        Schema::dropIfExists('points_transactions');
        Schema::dropIfExists('customer_loyalty_accounts');
    }
};
