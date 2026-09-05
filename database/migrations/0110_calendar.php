<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Personal calendar
            $table->string('name');
            $table->string('color', 7)->default('#3B82F6');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('personal'); // personal, team, organization, resource
            $table->boolean('is_default')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->string('timezone', 50)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('event_type', 30)->default('event'); // event, meeting, task, reminder, holiday
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('timezone', 50)->nullable();
            $table->string('status', 20)->default('confirmed'); // tentative, confirmed, cancelled
            $table->string('visibility', 20)->default('default'); // default, public, private
            $table->string('color', 7)->nullable();
            $table->nullableMorphs('related'); // Link to: customer, lead, invoice, employee, etc.
            $table->json('attendees')->nullable(); // Quick attendee list (JSON)
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('recurring_event_id')->nullable(); // Parent recurring event
            $table->timestamps();
            $table->softDeletes();

            $table->index(['calendar_id', 'start_at', 'end_at']);
            $table->index(['organization_id', 'start_at']);
            // related_type/related_id index already created by morphs()

            $table->foreign('recurring_event_id', 'cal_event_recurring_parent_fk')
                ->references('id')->on('calendar_events')->nullOnDelete();
        });

        Schema::create('calendar_event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('role', 20)->default('attendee'); // organizer, attendee, optional
            $table->string('status', 20)->default('pending'); // pending, accepted, declined, tentative
            $table->text('comment')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('calendar_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->string('method', 20)->default('notification'); // notification, email, sms
            $table->unsignedInteger('reminder_minutes')->default(15);
            $table->unsignedInteger('minutes_before')->nullable(); // legacy alias
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('calendar_recurring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->string('frequency', 20); // daily, weekly, monthly, yearly
            $table->unsignedInteger('interval')->default(1);
            $table->json('days_of_week')->nullable(); // [MO, TU, WE] for weekly
            $table->json('days_of_month')->nullable(); // [1, 15] for monthly
            $table->json('months_of_year')->nullable(); // [1, 6, 12] for yearly
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->json('exception_dates')->nullable(); // Dates to skip
            $table->timestamps();
        });

        Schema::create('calendar_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending'); // pending, in_progress, completed, cancelled
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->dateTime('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'due_date']);
            $table->index('assigned_to');
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('remind_at');
            $table->string('frequency', 20)->nullable(); // once, daily, weekly, monthly
            $table->morphs('remindable'); // Link to any entity
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'remind_at', 'is_sent']);
            // remindable_type/remindable_id index already created by morphs()
        });

        Schema::create('user_segments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions');
            $table->string('color', 7)->default('#6366f1');
            $table->boolean('is_dynamic')->default(true);
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_event')->nullable();
            $table->json('conditions')->nullable();
            $table->foreignId('target_segment_id')->nullable()->references('id')->on('user_segments')->nullOnDelete();
            $table->json('actions');
            $table->string('status')->default('draft');
            $table->string('schedule_type')->default('immediate');
            $table->unsignedInteger('delay_minutes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('max_sends_per_user')->default(1);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('channel', 20)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['campaign_id', 'user_id']);
            $table->index(['organization_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_sends');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('user_segments');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('calendar_tasks');
        Schema::dropIfExists('calendar_recurring_rules');
        Schema::dropIfExists('calendar_event_reminders');
        Schema::dropIfExists('calendar_event_attendees');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendars');
    }
};
