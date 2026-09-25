<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps a scheduled automation rule to one pending entry.
 *
 * A rule has one next run, and the booking code held that by reading whether a
 * pending entry existed before writing one. Two processes arriving together —
 * a sweep finishing a run beside an operator switching the rule back on — both
 * read no entry and both wrote one, so the rule ran its next occurrence twice.
 *
 * pending_rule_id carries the rule id while the entry is pending and null
 * otherwise, so a unique index on it holds one pending entry per rule in the
 * database rather than in whichever process reads first. Null repeats freely
 * on MySQL and SQLite alike, which a partial index does not.
 *
 * Entries already double-booked cannot be told apart by their next run, so the
 * rule keeps the one it booked first and the later duplicates go: they are the
 * second bookings this index exists to prevent, and leaving them would let the
 * rule run its next occurrence twice once more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('pending_rule_id')->nullable()->after('rule_id');
        });

        $firstBooked = DB::table('automation_schedules')
            ->where('status', 'pending')
            ->groupBy('rule_id')
            ->selectRaw('MIN(id) as id')
            ->pluck('id')
            ->all();

        if ($firstBooked !== []) {
            DB::table('automation_schedules')
                ->whereIn('id', $firstBooked)
                ->update(['pending_rule_id' => DB::raw('rule_id')]);

            DB::table('automation_schedules')
                ->where('status', 'pending')
                ->whereNotIn('id', $firstBooked)
                ->delete();
        }

        Schema::table('automation_schedules', function (Blueprint $table) {
            $table->unique('pending_rule_id', 'automation_schedules_pending_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::table('automation_schedules', function (Blueprint $table) {
            $table->dropUnique('automation_schedules_pending_rule_unique');
        });

        Schema::table('automation_schedules', function (Blueprint $table) {
            $table->dropColumn('pending_rule_id');
        });
    }
};
