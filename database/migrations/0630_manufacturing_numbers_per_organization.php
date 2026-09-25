<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the manufacturing identifiers unique within an organization.
 *
 * An audit plan, an 8D, a CAPA, a complaint, a production confirmation, a
 * scheduling run, a shop floor paper, a staging request, a supplier NCR
 * and a usage decision each carried a number unique across every
 * organization.
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
        Schema::table('audit_plans', function (Blueprint $table) {
            $table->dropUnique('audit_plans_plan_number_unique');
            $table->unique(['organization_id', 'plan_number'], 'audit_plans_org_plan_number_unq');
        });

        Schema::table('capa_8d', function (Blueprint $table) {
            $table->dropUnique('capa_8d_capa_number_unique');
            $table->unique(['organization_id', 'capa_number'], 'capa_8d_org_capa_number_unq');
        });

        Schema::table('capa_records', function (Blueprint $table) {
            $table->dropUnique('capa_records_capa_number_unique');
            $table->unique(['organization_id', 'capa_number'], 'capa_records_org_capa_number_unq');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique('complaints_complaint_number_unique');
            $table->unique(['organization_id', 'complaint_number'], 'complaints_org_number_unq');
        });

        Schema::table('production_confirmations', function (Blueprint $table) {
            $table->dropUnique('production_confirmations_confirmation_number_unique');
            $table->unique(['organization_id', 'confirmation_number'], 'prod_confirmations_org_number_unq');
        });

        Schema::table('scheduling_runs', function (Blueprint $table) {
            $table->dropUnique('scheduling_runs_run_number_unique');
            $table->unique(['organization_id', 'run_number'], 'scheduling_runs_org_number_unq');
        });

        Schema::table('shop_floor_papers', function (Blueprint $table) {
            $table->dropUnique('shop_floor_papers_paper_number_unique');
            $table->unique(['organization_id', 'paper_number'], 'shop_floor_papers_org_number_unq');
        });

        Schema::table('staging_requests', function (Blueprint $table) {
            $table->dropUnique('staging_requests_request_number_unique');
            $table->unique(['organization_id', 'request_number'], 'staging_requests_org_number_unq');
        });

        Schema::table('supplier_ncr_records', function (Blueprint $table) {
            $table->dropUnique('supplier_ncr_records_ncr_number_unique');
            $table->unique(['organization_id', 'ncr_number'], 'supplier_ncr_records_org_number_unq');
        });

        Schema::table('usage_decisions', function (Blueprint $table) {
            $table->dropUnique('usage_decisions_decision_number_unique');
            $table->unique(['organization_id', 'decision_number'], 'usage_decisions_org_number_unq');
        });

    }

    public function down(): void
    {
        Schema::table('audit_plans', function (Blueprint $table) {
            $table->dropUnique('audit_plans_org_plan_number_unq');
            $table->unique('plan_number', 'audit_plans_plan_number_unique');
        });

        Schema::table('capa_8d', function (Blueprint $table) {
            $table->dropUnique('capa_8d_org_capa_number_unq');
            $table->unique('capa_number', 'capa_8d_capa_number_unique');
        });

        Schema::table('capa_records', function (Blueprint $table) {
            $table->dropUnique('capa_records_org_capa_number_unq');
            $table->unique('capa_number', 'capa_records_capa_number_unique');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique('complaints_org_number_unq');
            $table->unique('complaint_number', 'complaints_complaint_number_unique');
        });

        Schema::table('production_confirmations', function (Blueprint $table) {
            $table->dropUnique('prod_confirmations_org_number_unq');
            $table->unique('confirmation_number', 'production_confirmations_confirmation_number_unique');
        });

        Schema::table('scheduling_runs', function (Blueprint $table) {
            $table->dropUnique('scheduling_runs_org_number_unq');
            $table->unique('run_number', 'scheduling_runs_run_number_unique');
        });

        Schema::table('shop_floor_papers', function (Blueprint $table) {
            $table->dropUnique('shop_floor_papers_org_number_unq');
            $table->unique('paper_number', 'shop_floor_papers_paper_number_unique');
        });

        Schema::table('staging_requests', function (Blueprint $table) {
            $table->dropUnique('staging_requests_org_number_unq');
            $table->unique('request_number', 'staging_requests_request_number_unique');
        });

        Schema::table('supplier_ncr_records', function (Blueprint $table) {
            $table->dropUnique('supplier_ncr_records_org_number_unq');
            $table->unique('ncr_number', 'supplier_ncr_records_ncr_number_unique');
        });

        Schema::table('usage_decisions', function (Blueprint $table) {
            $table->dropUnique('usage_decisions_org_number_unq');
            $table->unique('decision_number', 'usage_decisions_decision_number_unique');
        });

    }
};
