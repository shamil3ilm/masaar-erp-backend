<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds material_issue and material_return to the stock movement types. Goods
 * issues and work orders record the stock they consume and give back with
 * them (StockMovement::TYPE_MATERIAL_ISSUE and TYPE_MATERIAL_RETURN).
 *
 * SQLite changes a column by rebuilding the table, which drops the CHECK
 * constraints of the enum columns left out of the change, so direction is
 * declared again alongside.
 */
return new class extends Migration
{
    private const TYPES = [
        'purchase', 'sale', 'transfer_in', 'transfer_out', 'adjustment',
        'return_in', 'return_out', 'production_in', 'production_out', 'opening',
    ];

    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('movement_type', [...self::TYPES, 'material_issue', 'material_return'])->change();
            $table->enum('direction', ['in', 'out'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->enum('movement_type', self::TYPES)->change();
            $table->enum('direction', ['in', 'out'])->change();
        });
    }
};
