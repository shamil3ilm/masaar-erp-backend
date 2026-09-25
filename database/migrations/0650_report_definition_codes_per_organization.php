<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the reports identifiers unique within an organization.
 *
 * A report code was unique across every organization, so one tenant naming
 * a report SALES-SUMMARY took the name from all the others.
 *
 * Two organizations numbering their own documents reach the same value, and
 * the second was refused - which also told it the value exists somewhere it
 * cannot see. The index now carries organization_id, so the value is theirs
 * alone within their organization and free everywhere else.
 *
 * A system report carries no organization, so the index no longer holds
 * two system reports apart by code; they are seeded, not written by a
 * tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_definitions', function (Blueprint $table) {
            $table->dropUnique('report_definitions_code_unique');
            $table->unique(['organization_id', 'code'], 'report_definitions_org_code_unq');
        });

    }

    public function down(): void
    {
        Schema::table('report_definitions', function (Blueprint $table) {
            $table->dropUnique('report_definitions_org_code_unq');
            $table->unique('code', 'report_definitions_code_unique');
        });

    }
};
