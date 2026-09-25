<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the budget identifiers unique within an organization.
 *
 * A budget transfer number was unique across every organization, so the
 * second tenant to raise BT-2026-1 was refused.
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
        Schema::table('budget_transfers', function (Blueprint $table) {
            $table->dropUnique('budget_transfers_transfer_number_unique');
            $table->unique(['organization_id', 'transfer_number'], 'budget_transfers_org_number_unq');
        });

    }

    public function down(): void
    {
        Schema::table('budget_transfers', function (Blueprint $table) {
            $table->dropUnique('budget_transfers_org_number_unq');
            $table->unique('transfer_number', 'budget_transfers_transfer_number_unique');
        });

    }
};
