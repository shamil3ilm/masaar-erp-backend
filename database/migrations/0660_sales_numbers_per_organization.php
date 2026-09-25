<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the sales identifiers unique within an organization.
 *
 * A cash sale, a commission payment, a customer account group, a delivery,
 * a free goods condition, a material account group and a pick each carried
 * a number or a code unique across every organization.
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
        Schema::table('cash_sales', function (Blueprint $table) {
            $table->dropUnique('cash_sales_cash_sale_number_unique');
            $table->unique(['organization_id', 'cash_sale_number'], 'cash_sales_org_number_unq');
        });

        Schema::table('commission_payments', function (Blueprint $table) {
            $table->dropUnique('commission_payments_payment_reference_unique');
            $table->unique(['organization_id', 'payment_reference'], 'commission_payments_org_reference_unq');
        });

        Schema::table('customer_account_groups', function (Blueprint $table) {
            $table->dropUnique('customer_account_groups_group_code_unique');
            $table->unique(['organization_id', 'group_code'], 'customer_account_groups_org_code_unq');
        });

        Schema::table('delivery_documents', function (Blueprint $table) {
            $table->dropUnique('delivery_documents_delivery_number_unique');
            $table->unique(['organization_id', 'delivery_number'], 'delivery_documents_org_number_unq');
        });

        Schema::table('free_goods_conditions', function (Blueprint $table) {
            $table->dropUnique('free_goods_conditions_condition_number_unique');
            $table->unique(['organization_id', 'condition_number'], 'free_goods_conditions_org_number_unq');
        });

        Schema::table('material_account_groups', function (Blueprint $table) {
            $table->dropUnique('material_account_groups_group_code_unique');
            $table->unique(['organization_id', 'group_code'], 'material_account_groups_org_code_unq');
        });

        Schema::table('pick_documents', function (Blueprint $table) {
            $table->dropUnique('pick_documents_pick_number_unique');
            $table->unique(['organization_id', 'pick_number'], 'pick_documents_org_number_unq');
        });

    }

    public function down(): void
    {
        Schema::table('cash_sales', function (Blueprint $table) {
            $table->dropUnique('cash_sales_org_number_unq');
            $table->unique('cash_sale_number', 'cash_sales_cash_sale_number_unique');
        });

        Schema::table('commission_payments', function (Blueprint $table) {
            $table->dropUnique('commission_payments_org_reference_unq');
            $table->unique('payment_reference', 'commission_payments_payment_reference_unique');
        });

        Schema::table('customer_account_groups', function (Blueprint $table) {
            $table->dropUnique('customer_account_groups_org_code_unq');
            $table->unique('group_code', 'customer_account_groups_group_code_unique');
        });

        Schema::table('delivery_documents', function (Blueprint $table) {
            $table->dropUnique('delivery_documents_org_number_unq');
            $table->unique('delivery_number', 'delivery_documents_delivery_number_unique');
        });

        Schema::table('free_goods_conditions', function (Blueprint $table) {
            $table->dropUnique('free_goods_conditions_org_number_unq');
            $table->unique('condition_number', 'free_goods_conditions_condition_number_unique');
        });

        Schema::table('material_account_groups', function (Blueprint $table) {
            $table->dropUnique('material_account_groups_org_code_unq');
            $table->unique('group_code', 'material_account_groups_group_code_unique');
        });

        Schema::table('pick_documents', function (Blueprint $table) {
            $table->dropUnique('pick_documents_org_number_unq');
            $table->unique('pick_number', 'pick_documents_pick_number_unique');
        });

    }
};
