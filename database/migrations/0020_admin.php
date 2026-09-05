<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->json('permissions'); // List of permission slugs
            $table->boolean('is_system')->default(false); // Cannot be deleted
            $table->timestamps();
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role', 30)->default('admin'); // super_admin, admin, support, finance, viewer
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_2fa_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            $table->json('permissions')->nullable(); // Override role-based permissions
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role', 'is_active']);
        });

        Schema::create('admin_ip_whitelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('platform_admins')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_id', 'ip_address']);
            $table->index(['ip_address', 'is_active']);
        });

        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->cascadeOnDelete();
            $table->string('type', 100); // new_organization, subscription_expiring, system_alert, support_ticket
            $table->string('title');
            $table->text('message');
            $table->string('severity', 20)->default('info'); // info, warning, error, critical
            $table->json('data')->nullable();
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'is_read']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('rollout_type', 30)->default('all'); // all, percentage, specific, subscription_plan
            $table->unsignedTinyInteger('rollout_percentage')->nullable();
            $table->json('specific_organization_ids')->nullable();
            $table->json('specific_subscription_plans')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'rollout_type']);
        });

        Schema::create('platform_admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('platform_admins')->cascadeOnDelete();
            $table->string('session_token', 64)->unique();
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->string('device_type', 30)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at');
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index(['admin_id', 'is_revoked']);
            $table->index(['session_token']);
        });

        Schema::create('platform_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('module', 50); // organizations, users, billing, support, system
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string'); // string, integer, boolean, json, encrypted
            $table->string('group', 50)->default('general'); // general, email, security, billing, integrations
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be fetched by frontend
            $table->foreignId('updated_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['group']);
        });

        Schema::create('system_announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('type', 30)->default('info'); // info, warning, maintenance, feature, critical
            $table->string('target_audience', 30)->default('all'); // all, organizations, admins, specific
            $table->json('target_organization_ids')->nullable(); // For specific targeting
            $table->json('target_subscription_plans')->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->boolean('show_banner')->default(false);
            $table->string('banner_color', 7)->nullable();
            $table->string('action_url')->nullable();
            $table->string('action_text')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('draft'); // draft, scheduled, published, archived
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('dim_time', function (Blueprint $table) {
            $table->id();
            $table->date('full_date')->unique();
            $table->tinyInteger('day_of_week');
            $table->string('day_name', 10);
            $table->tinyInteger('day_of_month');
            $table->tinyInteger('week_of_year');
            $table->tinyInteger('month_number');
            $table->string('month_name', 10);
            $table->tinyInteger('quarter');
            $table->smallInteger('year');
            $table->smallInteger('fiscal_year');
            $table->tinyInteger('fiscal_period');
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->timestamps();

            $table->index(['year', 'month_number'], 'dim_time_year_month_idx');
            $table->index(['fiscal_year', 'fiscal_period'], 'dim_time_fiscal_idx');
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type', 20); // percentage, fixed_amount
            $table->decimal('discount_value', 15, 2);
            $table->decimal('max_discount_amount', 15, 2)->nullable(); // Cap for percentage discounts
            $table->decimal('min_order_amount', 15, 2)->nullable();
            $table->string('applies_to', 30)->default('all'); // all, specific_plans, addons
            $table->json('applicable_plan_ids')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_org')->default(1);
            $table->unsignedInteger('times_used')->default(0);
            $table->date('starts_at');
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('platform_admins')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->string('addon_type', 30); // users, storage, api_calls, feature, module
            $table->decimal('price', 15, 2);
            $table->string('pricing_model', 20); // flat, per_unit, tiered
            $table->string('billing_cycle', 20); // monthly, yearly, one_time
            $table->unsignedInteger('unit_quantity')->nullable(); // e.g., 5 users, 10GB storage
            $table->string('unit_label')->nullable(); // "users", "GB", etc.
            $table->json('compatible_plans')->nullable(); // Plan IDs this addon works with
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['addon_type', 'is_active']);
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->string('tier', 20); // free, starter, professional, enterprise
            $table->string('billing_cycle', 20); // monthly, yearly, one_time
            $table->decimal('base_price', 15, 2);
            $table->string('currency_code', 3)->default('USD');

            // Limits
            $table->unsignedInteger('max_users')->nullable(); // NULL = unlimited
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedBigInteger('storage_limit_mb')->nullable();
            $table->unsignedInteger('max_invoices_per_month')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_customers')->nullable();
            $table->unsignedInteger('max_employees')->nullable();
            $table->unsignedInteger('api_calls_per_month')->nullable();

            // Features
            $table->json('included_modules'); // ['sales', 'purchase', 'inventory', 'accounting', 'hr', 'crm', 'manufacturing']
            $table->json('features')->nullable(); // Additional feature flags

            // Trial
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('trial_requires_card')->default(false);

            // Settings
            $table->boolean('is_public')->default(true); // Visible on pricing page
            $table->boolean('is_popular')->default(false); // Highlight as popular
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tier', 'is_active']);
        });

        Schema::create('metered_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('metric_type', 50);
            $table->unsignedBigInteger('from_quantity');
            $table->unsignedBigInteger('to_quantity')->nullable(); // NULL = unlimited
            $table->decimal('price_per_unit', 15, 6);
            $table->string('unit_label', 50);
            $table->timestamps();

            $table->index(['plan_id', 'metric_type']);
        });

        Schema::create('budget_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('transfer_number')->unique();                // BT-2026-00001
            $table->unsignedBigInteger('from_budget_id');
            $table->unsignedBigInteger('from_budget_line_id');
            $table->unsignedBigInteger('to_budget_id');
            $table->unsignedBigInteger('to_budget_line_id');
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');                 // draft|submitted|approved|rejected|posted
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['from_budget_line_id']);
            $table->index(['to_budget_line_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('budget_transfers');
        Schema::dropIfExists('metered_pricing_tiers');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('dim_time');
        Schema::dropIfExists('system_announcements');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('platform_permissions');
        Schema::dropIfExists('platform_admin_sessions');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('admin_ip_whitelist');
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('platform_admin_roles');
    }
};
