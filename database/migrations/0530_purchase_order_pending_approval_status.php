<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds pending_approval to the purchase order statuses. An order that needs
 * approval waits in PurchaseOrder::STATUS_PENDING_APPROVAL until it is
 * approved or rejected.
 *
 * SQLite changes a column by rebuilding the table, which drops the CHECK
 * constraints of the enum columns left out of the change, so discount_type is
 * declared again alongside.
 */
return new class extends Migration
{
    private const STATUSES = [
        'draft', 'sent', 'confirmed', 'partially_received', 'received', 'billed', 'cancelled',
    ];

    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('status', [...self::STATUSES, 'pending_approval'])->default('draft')->change();
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('status', self::STATUSES)->default('draft')->change();
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable()->change();
        });
    }
};
