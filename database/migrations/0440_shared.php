<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('event', 50);
            $table->string('auditable_type', 255);
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->index('archived_at');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });

        Schema::create('calibration_equipment', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('equipment_code', 30);
            $table->string('name');
            $table->string('manufacturer', 100)->nullable();
            $table->string('model_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->string('category', 50)->nullable()
                ->comment('thermometer/pressure_gauge/scale/caliper/multimeter/other');
            $table->string('location', 100)->nullable();
            $table->foreignId('responsible_person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'equipment_code'], 'cal_equip_org_code_idx');
            $table->index(['organization_id', 'is_active'], 'cal_equip_org_active_idx');
        });

        Schema::create('capacity_slots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('work_center_id')->constrained('work_centers', 'id', 'cap_slot_wc_fk');
            $table->date('slot_date');
            $table->time('slot_start');
            $table->time('slot_end');
            $table->unsignedSmallInteger('available_minutes');
            $table->unsignedSmallInteger('allocated_minutes')->default(0);
            $table->decimal('utilization_pct', 5, 2)->storedAs(
                'CASE WHEN available_minutes = 0 THEN 0 ELSE ROUND(allocated_minutes / available_minutes * 100, 2) END'
            );
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('competency_frameworks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('applicable_to', ['all', 'department', 'designation', 'position'])->default('all');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('competency_framework_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competency_framework_id');
            $table->foreign('competency_framework_id', 'cf_skill_cf_fk')->references('id')->on('competency_frameworks')->onDelete('cascade');
            $table->unsignedBigInteger('skill_id');
            $table->foreign('skill_id', 'cf_skill_skill_fk')->references('id')->on('skills')->onDelete('cascade');
            $table->unsignedTinyInteger('required_level');
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('daily_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->time('work_start');
            $table->time('work_end');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->decimal('planned_hours', 4, 2);
            $table->enum('day_type', ['normal', 'reduced', 'off', 'public_holiday'])->default('normal');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('financial_close_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('financial_close_task_id');
            $table->unsignedBigInteger('depends_on_task_id');
            $table->timestamps();

            $table->foreign('financial_close_task_id', 'fk_fctdep_task')
                ->references('id')->on('financial_close_tasks')->onDelete('cascade');
            $table->foreign('depends_on_task_id', 'fk_fctdep_depends')
                ->references('id')->on('financial_close_tasks')->onDelete('cascade');

            $table->unique(
                ['financial_close_task_id', 'depends_on_task_id'],
                'uq_fctdep_task_dep'
            );
        });

        Schema::create('financial_idempotency_keys', function (Blueprint $table) {
            $table->id();

            // Scoped key: the caller-supplied string (e.g. "invoice:42:send")
            $table->string('key', 255);

            // Logical operation name (e.g. "invoice.send", "payroll.generate")
            $table->string('operation', 100);

            // Tenant scope — prevents cross-org key collisions
            $table->unsignedBigInteger('organization_id');

            // Processing state
            $table->enum('status', ['processing', 'completed', 'failed'])
                ->default('processing');

            // SHA-256 of the request payload for integrity checking (optional)
            $table->string('request_hash', 64)->nullable();

            // Cached result returned to duplicate callers
            $table->json('response_payload')->nullable();

            // When this key expires and may be reused
            $table->timestamp('expires_at')->index();

            $table->timestamps();

            // Serialisation point: DB enforces uniqueness, not application code
            $table->unique(['key', 'organization_id', 'operation'], 'fin_idempotency_scope_unique');

            $table->index('organization_id');
        });

        Schema::create('location_equipment', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('floc_id');
            $table->string('equipment_number');
            $table->string('description');
            $table->string('category')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('installed_at')->nullable();
            $table->date('removed_at')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('floc_id', 'floc_eq_floc_fk')->references('id')->on('functional_locations')->cascadeOnDelete();
            $table->foreign('product_id', 'floc_eq_prod_fk')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('hr_budget_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id', 'hr_budget_dept_fk')->references('id')->on('departments')->onDelete('set null');
            $table->string('plan_name');
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'hr_budget_appr_fk')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('total_headcount')->default(0);
            $table->decimal('total_salary_budget', 18, 4)->default(0);
            $table->decimal('total_benefits_budget', 18, 4)->default(0);
            $table->char('currency', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint');
            $table->text('response')->nullable();
            $table->smallInteger('status_code');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->index('expires_at');
        });

        Schema::create('invoice_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->string('status', 30)->default('paid');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('total', 20, 4)->default(0);
            $table->decimal('amount_paid', 20, 4)->default(0);
            $table->decimal('amount_due', 20, 4)->default(0);
            $table->string('currency_code', 10)->default('SAR');
            $table->json('snapshot')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'invoice_date']);
            $table->index('archived_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('journal_entry_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('entry_number', 50)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('description')->nullable();
            $table->date('entry_date');
            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);
            $table->string('status', 20)->default('posted');
            $table->string('currency_code', 10)->default('SAR');
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
            $table->index(['organization_id', 'entry_date']);
            $table->index('archived_at');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_archives');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('invoice_archives');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('hr_budget_plans');
        Schema::dropIfExists('location_equipment');
        Schema::dropIfExists('financial_idempotency_keys');
        Schema::dropIfExists('financial_close_task_dependencies');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('daily_work_schedules');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('competency_framework_skills');
        Schema::dropIfExists('competency_frameworks');
        Schema::dropIfExists('capacity_slots');
        Schema::dropIfExists('calibration_equipment');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('audit_log_archives');
    }
};
