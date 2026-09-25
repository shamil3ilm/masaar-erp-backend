<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the purchase identifiers unique within an organization.
 *
 * A service entry sheet number and a service purchase order number were
 * unique across every organization, while the generator that issues them
 * counts per organization.
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
        Schema::table('service_entry_sheets', function (Blueprint $table) {
            $table->dropUnique('service_entry_sheets_ses_number_unique');
            $table->unique(['organization_id', 'ses_number'], 'service_entry_sheets_org_number_unq');
        });

        Schema::table('service_purchase_orders', function (Blueprint $table) {
            $table->dropUnique('service_purchase_orders_po_number_unique');
            $table->unique(['organization_id', 'po_number'], 'service_purchase_orders_org_number_unq');
        });

    }

    public function down(): void
    {
        Schema::table('service_entry_sheets', function (Blueprint $table) {
            $table->dropUnique('service_entry_sheets_org_number_unq');
            $table->unique('ses_number', 'service_entry_sheets_ses_number_unique');
        });

        Schema::table('service_purchase_orders', function (Blueprint $table) {
            $table->dropUnique('service_purchase_orders_org_number_unq');
            $table->unique('po_number', 'service_purchase_orders_po_number_unique');
        });

    }
};
