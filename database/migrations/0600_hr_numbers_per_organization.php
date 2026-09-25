<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the HR identifiers unique within an organization.
 *
 * A manager's team view, a personnel action number and a travel expense
 * report number were unique across every organization.
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
        Schema::table('manager_team_views', function (Blueprint $table) {
            $table->dropUnique('mtv_manager_employee_unq');
            $table->unique(['organization_id', 'manager_id', 'employee_id'], 'mtv_org_manager_employee_unq');
        });

        Schema::table('personnel_actions', function (Blueprint $table) {
            $table->dropUnique('personnel_actions_action_number_unique');
            $table->unique(['organization_id', 'action_number'], 'personnel_actions_org_number_unq');
        });

        Schema::table('travel_expense_reports', function (Blueprint $table) {
            $table->dropUnique('travel_expense_reports_report_number_unique');
            $table->unique(['organization_id', 'report_number'], 'travel_exp_reports_org_number_unq');
        });

    }

    public function down(): void
    {
        Schema::table('manager_team_views', function (Blueprint $table) {
            $table->dropUnique('mtv_org_manager_employee_unq');
            $table->unique(['manager_id', 'employee_id'], 'mtv_manager_employee_unq');
        });

        Schema::table('personnel_actions', function (Blueprint $table) {
            $table->dropUnique('personnel_actions_org_number_unq');
            $table->unique('action_number', 'personnel_actions_action_number_unique');
        });

        Schema::table('travel_expense_reports', function (Blueprint $table) {
            $table->dropUnique('travel_exp_reports_org_number_unq');
            $table->unique('report_number', 'travel_expense_reports_report_number_unique');
        });

    }
};
