<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a subsidiary organization to its parent.
 *
 * Fields that name another company - the receiver of an inter-company asset
 * transfer, a consolidation group's entities, the two sides of an
 * intercompany sales order - accepted any organization in the table, so one
 * tenant could post into another tenant's books. The column gives those
 * checks a group to stay inside: the root of a group is
 * parent_organization_id when the organization has a parent and its own id
 * otherwise, and two organizations share a group when their roots match.
 *
 * Null on delete rather than cascade: a deleted parent leaves its
 * subsidiaries standing as groups of their own instead of deleting the
 * tenants underneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('parent_organization_id')->nullable()->after('slug')
                ->constrained('organizations')->nullOnDelete();
            $table->index('parent_organization_id', 'organizations_parent_index');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['parent_organization_id']);
            $table->dropIndex('organizations_parent_index');
            $table->dropColumn('parent_organization_id');
        });
    }
};
