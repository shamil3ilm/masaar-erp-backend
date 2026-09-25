<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the boolean is_default on messaging channels with default_for_type,
 * which carries the channel type while the channel is its organization's
 * default for that type and null otherwise.
 *
 * The unique index on (organization_id, channel_type, is_default) treated false
 * as a value like any other, so an organization could hold only one non-default
 * channel per type and moving the default collided with the row it was moving
 * away from. A unique index on (organization_id, default_for_type) constrains
 * only the defaults, because null repeats freely on MySQL and SQLite alike, and
 * keeps one default per organization and channel type in the database rather
 * than in whichever request runs first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->dropUnique('msg_channels_org_type_default_unique');
        });

        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->string('default_for_type', 30)->nullable()->after('sender_address');
        });

        DB::table('messaging_channels')
            ->where('is_default', true)
            ->update(['default_for_type' => DB::raw('channel_type')]);

        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->unique(['organization_id', 'default_for_type'], 'msg_channels_org_default_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->dropUnique('msg_channels_org_default_unique');
        });

        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('sender_address');
        });

        DB::table('messaging_channels')
            ->whereNotNull('default_for_type')
            ->update(['is_default' => true]);

        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->dropColumn('default_for_type');
        });

        Schema::table('messaging_channels', function (Blueprint $table) {
            $table->unique(['organization_id', 'channel_type', 'is_default'], 'msg_channels_org_type_default_unique');
        });
    }
};
