<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the maintenance identifiers unique within an organization.
 *
 * A counter order, a counter plan, a fault code, a notification, a service
 * order and a task list each carried a number or a code unique across
 * every organization.
 *
 * Two organizations numbering their own documents reach the same value, and
 * the second was refused - which also told it the value exists somewhere it
 * cannot see. The index now carries organization_id, so the value is theirs
 * alone within their organization and free everywhere else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counter_based_orders', function (Blueprint $table) {
            $table->dropUnique('counter_based_orders_order_number_unique');
            $table->unique(['organization_id', 'order_number'], 'counter_based_orders_org_number_unq');
        });

        Schema::table('counter_based_plans', function (Blueprint $table) {
            $table->dropUnique('counter_based_plans_plan_number_unique');
            $table->unique(['organization_id', 'plan_number'], 'counter_based_plans_org_number_unq');
        });

        Schema::table('maintenance_fault_codes', function (Blueprint $table) {
            $table->dropUnique('maintenance_fault_codes_code_unique');
            $table->unique(['organization_id', 'code'], 'maint_fault_codes_org_code_unq');
        });

        Schema::table('maintenance_notifications', function (Blueprint $table) {
            $table->dropUnique('maintenance_notifications_notification_number_unique');
            $table->unique(['organization_id', 'notification_number'], 'maint_notifications_org_number_unq');
        });

        Schema::table('maintenance_service_orders', function (Blueprint $table) {
            $table->dropUnique('maintenance_service_orders_service_order_number_unique');
            $table->unique(['organization_id', 'service_order_number'], 'maint_service_orders_org_number_unq');
        });

        Schema::table('maintenance_task_lists', function (Blueprint $table) {
            $table->dropUnique('maintenance_task_lists_task_list_number_unique');
            $table->unique(['organization_id', 'task_list_number'], 'maint_task_lists_org_number_unq');
        });

    }

    public function down(): void
    {
        Schema::table('counter_based_orders', function (Blueprint $table) {
            $table->dropUnique('counter_based_orders_org_number_unq');
            $table->unique('order_number', 'counter_based_orders_order_number_unique');
        });

        Schema::table('counter_based_plans', function (Blueprint $table) {
            $table->dropUnique('counter_based_plans_org_number_unq');
            $table->unique('plan_number', 'counter_based_plans_plan_number_unique');
        });

        Schema::table('maintenance_fault_codes', function (Blueprint $table) {
            $table->dropUnique('maint_fault_codes_org_code_unq');
            $table->unique('code', 'maintenance_fault_codes_code_unique');
        });

        Schema::table('maintenance_notifications', function (Blueprint $table) {
            $table->dropUnique('maint_notifications_org_number_unq');
            $table->unique('notification_number', 'maintenance_notifications_notification_number_unique');
        });

        Schema::table('maintenance_service_orders', function (Blueprint $table) {
            $table->dropUnique('maint_service_orders_org_number_unq');
            $table->unique('service_order_number', 'maintenance_service_orders_service_order_number_unique');
        });

        Schema::table('maintenance_task_lists', function (Blueprint $table) {
            $table->dropUnique('maint_task_lists_org_number_unq');
            $table->unique('task_list_number', 'maintenance_task_lists_task_list_number_unique');
        });

    }
};
