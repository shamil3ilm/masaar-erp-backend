<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds in_inspection to the goods receipt statuses. A receipt whose products
 * need quality inspection waits in that status, holding its inspection lot,
 * until the lot is resolved and the receipt can be posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->enum('status', ['draft', 'in_inspection', 'posted', 'reversed'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft')->change();
        });
    }
};
