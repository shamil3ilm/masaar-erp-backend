<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Activity type
            $table->enum('activity_type', [
                'call',
                'email',
                'meeting',
                'task',
                'note',
                'follow_up',
            ]);

            // Subject and description
            $table->string('subject', 200);
            $table->text('description')->nullable();

            // Related to (polymorphic)
            $table->string('related_type', 50)->nullable(); // lead, opportunity, contact
            $table->unsignedBigInteger('related_id')->nullable();

            // Timing
            $table->datetime('start_datetime')->nullable();
            $table->datetime('end_datetime')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_all_day')->default(false);

            // Status
            $table->enum('status', [
                'planned',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('planned');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->datetime('completed_at')->nullable();

            // For calls
            $table->enum('call_direction', ['inbound', 'outbound'])->nullable();
            $table->enum('call_result', ['connected', 'no_answer', 'busy', 'voicemail', 'wrong_number'])->nullable();

            // For meetings
            $table->string('location', 200)->nullable();
            $table->string('meeting_link', 500)->nullable();

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->json('attendees')->nullable(); // Array of user IDs or contact IDs

            // Reminder
            $table->datetime('reminder_datetime')->nullable();
            $table->boolean('reminder_sent')->default(false);

            $table->text('outcome')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'activity_type']);
            $table->index(['organization_id', 'status']);
            $table->index(['related_type', 'related_id']);
            $table->index(['organization_id', 'assigned_to', 'status']);
            $table->index(['organization_id', 'start_datetime']);
        });

        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('probability')->default(0); // 0-100%
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('color', 7)->nullable();
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('priority'); // low, medium, high, critical
            $table->unsignedInteger('first_response_hours');
            $table->unsignedInteger('resolution_hours');
            $table->boolean('business_hours_only')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'priority', 'is_active']);
        });

        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('territories')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->enum('territory_type', [
                'global', 'region', 'country', 'state', 'city', 'postal_zone', 'custom',
            ])->default('custom');
            $table->string('country_code', 3)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->json('postal_codes')->nullable()->comment('Array of postal code patterns');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('territory_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->enum('entity_type', ['lead', 'opportunity', 'contact'])->default('lead');
            $table->enum('match_field', ['country', 'state', 'postal_code', 'city', 'custom'])->default('country');
            $table->string('match_value');
            $table->tinyInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'entity_type']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('territory_routing_rules');
        Schema::dropIfExists('territories');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('lead_sources');
        Schema::dropIfExists('crm_activities');
    }
};
