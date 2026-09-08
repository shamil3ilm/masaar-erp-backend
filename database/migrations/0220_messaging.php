<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject')->nullable();
            $table->string('type', 20)->default('direct'); // direct, group
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'last_message_at']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('type', 20)->default('text'); // text, file, image
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50); // invoice_sent, payment_received, order_confirmed
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('category', 50); // transactional, promotional, reminder, notification

            // Content
            $table->string('subject')->nullable(); // For email
            $table->text('body'); // Supports {{variable}} placeholders
            $table->text('html_body')->nullable(); // Rich HTML for email
            $table->json('variables')->nullable(); // Available placeholders
            $table->json('attachments_config')->nullable(); // Auto-attach invoice PDF, etc.

            // Language
            $table->string('language', 5)->default('en');
            $table->foreignId('parent_template_id')->nullable()->constrained('message_templates')->nullOnDelete();

            $table->boolean('is_system')->default(false); // System template, cannot be deleted
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code', 'channel_type', 'language'], 'msg_tpl_org_code_channel_lang_unique');
        });

        Schema::create('channel_template_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('message_templates')->cascadeOnDelete();
            $table->string('channel_type', 30);
            $table->string('provider_template_id')->nullable(); // WhatsApp business template ID
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'channel_type']);
        });

        Schema::create('messaging_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('name');
            $table->string('provider', 50); // smtp, sendgrid, twilio, vonage, firebase, whatsapp_business
            // Encrypted by the model. Ciphertext is 200 characters at its shortest,
            // so the column is sized for the ciphertext, not the value.
            $table->text('credentials');
            $table->json('settings')->nullable(); // Rate limits, sender defaults, etc.
            $table->string('sender_name')->nullable();
            $table->string('sender_address')->nullable(); // Email, phone number
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'channel_type', 'is_default'], 'msg_channels_org_type_default_unique');
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('messaging_automations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // Trigger
            $table->string('trigger_event', 100); // invoice.created, payment.received, order.shipped, customer.birthday, invoice.overdue
            $table->string('trigger_entity', 50)->nullable(); // invoice, payment, order, customer

            // Timing
            $table->string('timing', 20)->default('immediate'); // immediate, delayed, scheduled
            $table->unsignedInteger('delay_minutes')->default(0); // For delayed triggers
            $table->string('delay_unit', 10)->nullable(); // minutes, hours, days

            // Conditions
            $table->json('conditions')->nullable(); // Filter conditions (amount > 500, status = overdue, etc.)

            // Action
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->foreignId('template_id')->constrained('message_templates')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('messaging_channels')->nullOnDelete();

            // Recipients
            $table->string('recipient_type', 30)->default('contact'); // contact, user, custom, role
            $table->json('recipient_config')->nullable(); // CC, BCC, additional recipients

            // Rate limiting
            $table->unsignedInteger('max_sends_per_contact')->nullable(); // Per day/week/month
            $table->string('rate_limit_period', 10)->nullable(); // day, week, month

            // Status
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'trigger_event', 'is_active'], 'msg_auto_org_trigger_active_idx');
        });

        Schema::create('message_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('messaging_automations')->nullOnDelete();
            $table->string('recipient_type', 30); // contact, user, custom
            $table->unsignedBigInteger('recipient_id')->nullable(); // polymorphic reference
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('name')->nullable();
            $table->string('status', 20)->default('pending'); // pending, sent, failed, unsubscribed
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['organization_id', 'recipient_type', 'recipient_id'], 'mcr_org_recipient_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('message_campaign_recipients');
        Schema::dropIfExists('messaging_automations');
        Schema::dropIfExists('messaging_channels');
        Schema::dropIfExists('channel_template_approvals');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
