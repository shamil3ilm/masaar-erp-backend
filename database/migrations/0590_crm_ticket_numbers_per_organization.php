<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the CRM identifiers unique within an organization.
 *
 * A service ticket number was unique across every organization, while the
 * generator that issues it counts per organization, so two tenants reach
 * TKT-2026-00001 on the same day.
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
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropUnique('service_tickets_ticket_number_unique');
            $table->unique(['organization_id', 'ticket_number'], 'service_tickets_org_number_unq');
        });

    }

    public function down(): void
    {
        Schema::table('service_tickets', function (Blueprint $table) {
            $table->dropUnique('service_tickets_org_number_unq');
            $table->unique('ticket_number', 'service_tickets_ticket_number_unique');
        });

    }
};
