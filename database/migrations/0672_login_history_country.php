<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where a login came from, so "a login from a new country" can mean it.
 *
 * The geographic fraud rule named new_country_login compared the country
 * against a fixed high-risk list and never looked at where the user had logged
 * in before - so a user whose logins all came from one country was not flagged
 * when one suddenly came from another, and a user whose own country is on the
 * list was flagged every day.
 *
 * It could not do better: nothing stored a country per user. The country
 * arrives in a request header, is passed to the fraud job, and was never
 * persisted. login_attempts holds email, IP and whether it succeeded - it
 * drives lockout, not history - and login_history, which has the user and the
 * status and the indexes for exactly this question, was never written by
 * anything at all, so the endpoint that reads it returned nothing.
 *
 * Two characters, nullable: a request behind a proxy that does not set the
 * header has no country to record, and a login with no country is not evidence
 * of a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('ip_address');

            // The fraud check asks for one user's distinct countries, and the
            // activity log asks for one user's recent logins. Both are served
            // by the existing user_id/attempted_at index; this one answers
            // "has this user ever been seen from here" directly.
            $table->index(['user_id', 'country_code'], 'login_history_user_country_index');
        });
    }

    public function down(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            $table->dropIndex('login_history_user_country_index');
            $table->dropColumn('country_code');
        });
    }
};
