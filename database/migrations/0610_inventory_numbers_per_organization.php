<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the inventory identifiers unique within an organization.
 *
 * A transfer order number, a goods issue number, a valuation type code and
 * a storage type code were unique across every organization.
 *
 * Two organizations numbering their own documents reach the same value, and
 * the second was refused - which also told it the value exists somewhere it
 * cannot see. The index now carries organization_id, so the value is theirs
 * alone within their organization and free everywhere else.
 *
 * The last two already held to one organization through the valuation
 * category and the warehouse they hang off; naming the column says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ewm_transfer_orders', function (Blueprint $table) {
            $table->dropUnique('ewm_transfer_orders_to_number_unique');
            $table->unique(['organization_id', 'to_number'], 'ewm_transfer_orders_org_number_unq');
        });

        Schema::table('goods_issues', function (Blueprint $table) {
            $table->dropUnique('goods_issues_gi_number_unique');
            $table->unique(['organization_id', 'gi_number'], 'goods_issues_org_gi_number_unq');
        });

        Schema::table('inventory_valuation_types', function (Blueprint $table) {
            $table->dropUnique('inventory_valuation_types_valuation_category_id_type_code_unique');
            $table->unique(['organization_id', 'valuation_category_id', 'type_code'], 'inv_val_types_org_category_code_unq');
        });

        Schema::table('storage_types', function (Blueprint $table) {
            $table->dropUnique('st_warehouse_code_unq');
            $table->unique(['organization_id', 'warehouse_id', 'storage_type_code'], 'st_org_warehouse_code_unq');
        });

    }

    public function down(): void
    {
        Schema::table('ewm_transfer_orders', function (Blueprint $table) {
            $table->dropUnique('ewm_transfer_orders_org_number_unq');
            $table->unique('to_number', 'ewm_transfer_orders_to_number_unique');
        });

        Schema::table('goods_issues', function (Blueprint $table) {
            $table->dropUnique('goods_issues_org_gi_number_unq');
            $table->unique('gi_number', 'goods_issues_gi_number_unique');
        });

        Schema::table('inventory_valuation_types', function (Blueprint $table) {
            $table->dropUnique('inv_val_types_org_category_code_unq');
            $table->unique(['valuation_category_id', 'type_code'], 'inventory_valuation_types_valuation_category_id_type_code_unique');
        });

        Schema::table('storage_types', function (Blueprint $table) {
            $table->dropUnique('st_org_warehouse_code_unq');
            $table->unique(['warehouse_id', 'storage_type_code'], 'st_warehouse_code_unq');
        });

    }
};
