<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the transportation identifiers unique within an organization.
 *
 * A carrier code was unique across every organization, so the first tenant
 * to add DHL stopped every other tenant adding it.
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
        Schema::table('carriers', function (Blueprint $table) {
            $table->dropUnique('carriers_code_unique');
            $table->unique(['organization_id', 'code'], 'carriers_org_code_unq');
        });

    }

    public function down(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->dropUnique('carriers_org_code_unq');
            $table->unique('code', 'carriers_code_unique');
        });

    }
};
