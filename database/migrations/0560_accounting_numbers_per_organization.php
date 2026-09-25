<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the accounting identifiers unique within an organization.
 *
 * A confirmation, a supplement, a reconciliation run, a forward contract,
 * an intercompany session and an XBRL taxonomy each carried a number or a
 * namespace unique across every organization.
 *
 * Two organizations numbering their own documents reach the same value, and
 * the second was refused - which also told it the value exists somewhere it
 * cannot see. The index now carries organization_id, so the value is theirs
 * alone within their organization and free everywhere else.
 *
 * Two organizations filing against the same published taxonomy hold the
 * same namespace, which the global index refused outright.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_confirmations', function (Blueprint $table) {
            $table->dropUnique('activity_confirmations_confirmation_number_unique');
            $table->unique(['organization_id', 'confirmation_number'], 'act_conf_org_number_unq');
        });

        Schema::table('cost_center_budget_supplements', function (Blueprint $table) {
            $table->dropUnique('cost_center_budget_supplements_supplement_number_unique');
            $table->unique(['organization_id', 'supplement_number'], 'ccbs_org_supplement_number_unq');
        });

        Schema::table('cost_reconciliation_runs', function (Blueprint $table) {
            $table->dropUnique('cost_reconciliation_runs_run_number_unique');
            $table->unique(['organization_id', 'run_number'], 'cost_recon_runs_org_number_unq');
        });

        Schema::table('fx_forwards', function (Blueprint $table) {
            $table->dropUnique('fx_forwards_contract_number_unique');
            $table->unique(['organization_id', 'contract_number'], 'fx_forwards_org_contract_number_unq');
        });

        Schema::table('intercompany_reconciliation_sessions', function (Blueprint $table) {
            $table->dropUnique('intercompany_reconciliation_sessions_session_number_unique');
            $table->unique(['organization_id', 'session_number'], 'icr_sessions_org_number_unq');
        });

        Schema::table('xbrl_taxonomies', function (Blueprint $table) {
            $table->dropUnique('xbrl_taxonomies_namespace_unique');
            $table->unique(['organization_id', 'namespace'], 'xbrl_taxonomies_org_namespace_unq');
        });

    }

    public function down(): void
    {
        Schema::table('activity_confirmations', function (Blueprint $table) {
            $table->dropUnique('act_conf_org_number_unq');
            $table->unique('confirmation_number', 'activity_confirmations_confirmation_number_unique');
        });

        Schema::table('cost_center_budget_supplements', function (Blueprint $table) {
            $table->dropUnique('ccbs_org_supplement_number_unq');
            $table->unique('supplement_number', 'cost_center_budget_supplements_supplement_number_unique');
        });

        Schema::table('cost_reconciliation_runs', function (Blueprint $table) {
            $table->dropUnique('cost_recon_runs_org_number_unq');
            $table->unique('run_number', 'cost_reconciliation_runs_run_number_unique');
        });

        Schema::table('fx_forwards', function (Blueprint $table) {
            $table->dropUnique('fx_forwards_org_contract_number_unq');
            $table->unique('contract_number', 'fx_forwards_contract_number_unique');
        });

        Schema::table('intercompany_reconciliation_sessions', function (Blueprint $table) {
            $table->dropUnique('icr_sessions_org_number_unq');
            $table->unique('session_number', 'intercompany_reconciliation_sessions_session_number_unique');
        });

        Schema::table('xbrl_taxonomies', function (Blueprint $table) {
            $table->dropUnique('xbrl_taxonomies_org_namespace_unq');
            $table->unique('namespace', 'xbrl_taxonomies_namespace_unique');
        });

    }
};
