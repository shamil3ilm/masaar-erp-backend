<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recurring profile run that fails, or an occurrence that is skipped,
 * creates no document, so its log entry has no created_type or created_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_profile_logs', function (Blueprint $table) {
            $table->string('created_type', 100)->nullable()->change();
            $table->unsignedBigInteger('created_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_profile_logs', function (Blueprint $table) {
            $table->string('created_type', 100)->nullable(false)->change();
            $table->unsignedBigInteger('created_id')->nullable(false)->change();
        });
    }
};
