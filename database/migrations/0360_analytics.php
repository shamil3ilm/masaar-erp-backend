<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'dim_product_prod_id_fk')
                ->references('id')->on('products')->onDelete('set null');
            $table->string('product_code', 50);
            $table->string('product_name');
            $table->string('category_name', 100);
            $table->string('subcategory_name', 100)->nullable();
            $table->string('unit_of_measure', 20);
            $table->string('product_type', 30);
            $table->boolean('is_active')->default(true);
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index('organization_id', 'dim_product_org_id_idx');
        });

        Schema::create('fact_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('dim_product_id');
            $table->foreign('dim_product_id', 'fact_inv_prod_id_fk')
                ->references('id')->on('dim_product')->onDelete('restrict');
            $table->unsignedBigInteger('dim_warehouse_id');
            $table->foreign('dim_warehouse_id', 'fact_inv_wh_id_fk')
                ->references('id')->on('dim_warehouse')->onDelete('restrict');
            $table->unsignedBigInteger('dim_time_id');
            $table->foreign('dim_time_id', 'fact_inv_time_id_fk')
                ->references('id')->on('dim_time')->onDelete('restrict');
            $table->string('movement_type', 30);
            $table->decimal('quantity_in', 18, 4)->default(0);
            $table->decimal('quantity_out', 18, 4)->default(0);
            $table->decimal('quantity_balance', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->char('currency_code', 3);
            $table->string('reference_type', 50)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'dim_time_id'], 'fact_inv_org_time_idx');
            $table->index(['dim_product_id', 'dim_warehouse_id', 'dim_time_id'], 'fact_inv_prod_wh_time_idx');
        });

        Schema::create('fact_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('dim_product_id');
            $table->foreign('dim_product_id', 'fact_purch_prod_id_fk')
                ->references('id')->on('dim_product')->onDelete('restrict');
            $table->unsignedBigInteger('dim_vendor_id');
            $table->foreign('dim_vendor_id', 'fact_purch_vendor_id_fk')
                ->references('id')->on('dim_vendor')->onDelete('restrict');
            $table->unsignedBigInteger('dim_time_id');
            $table->foreign('dim_time_id', 'fact_purch_time_id_fk')
                ->references('id')->on('dim_time')->onDelete('restrict');
            $table->unsignedBigInteger('dim_warehouse_id')->nullable();
            $table->foreign('dim_warehouse_id', 'fact_purch_wh_id_fk')
                ->references('id')->on('dim_warehouse')->onDelete('set null');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('net_amount', 18, 4);
            $table->decimal('tax_amount', 18, 4);
            $table->decimal('gross_amount', 18, 4);
            $table->char('currency_code', 3);
            $table->timestamps();

            $table->index(['organization_id', 'dim_time_id'], 'fact_purch_org_time_idx');
            $table->index(['dim_vendor_id', 'dim_time_id'], 'fact_purch_vendor_time_idx');
            $table->index(['dim_product_id', 'dim_time_id'], 'fact_purch_prod_time_idx');
        });

        Schema::create('fact_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('dim_product_id');
            $table->foreign('dim_product_id', 'fact_sales_prod_id_fk')
                ->references('id')->on('dim_product')->onDelete('restrict');
            $table->unsignedBigInteger('dim_customer_id');
            $table->foreign('dim_customer_id', 'fact_sales_cust_id_fk')
                ->references('id')->on('dim_customer')->onDelete('restrict');
            $table->unsignedBigInteger('dim_time_id');
            $table->foreign('dim_time_id', 'fact_sales_time_id_fk')
                ->references('id')->on('dim_time')->onDelete('restrict');
            $table->unsignedBigInteger('dim_warehouse_id')->nullable();
            $table->foreign('dim_warehouse_id', 'fact_sales_wh_id_fk')
                ->references('id')->on('dim_warehouse')->onDelete('set null');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('invoice_line_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('net_amount', 18, 4);
            $table->decimal('tax_amount', 18, 4);
            $table->decimal('gross_amount', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('cost_amount', 18, 4)->default(0);
            $table->decimal('gross_margin', 18, 4)->default(0);
            $table->char('currency_code', 3);
            $table->timestamps();

            $table->index(['organization_id', 'dim_time_id'], 'fact_sales_org_time_idx');
            $table->index(['dim_customer_id', 'dim_time_id'], 'fact_sales_cust_time_idx');
            $table->index(['dim_product_id', 'dim_time_id'], 'fact_sales_prod_time_idx');
        });

        Schema::create('ecommerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('ecommerce_orders')->cascadeOnDelete();
            $table->string('external_product_id')->nullable();
            $table->string('external_variant_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('fulfilled_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('ecommerce_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('ecommerce_channels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('external_product_id');
            $table->string('external_variant_id')->nullable();
            $table->string('external_sku')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'external_product_id', 'external_variant_id'], 'ecom_product_mapping_unique');
            $table->index(['channel_id', 'product_id']);
        });

        Schema::create('equipment_spare_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('recommended_stock_qty', 15, 4)->default(0);
            $table->decimal('current_stock_qty', 15, 4)->default(0);
            $table->boolean('is_critical')->default(false);
            $table->decimal('lead_time_days', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['equipment_id', 'product_id'], 'esp_equip_product_unique');
        });

        Schema::create('maintenance_order_parts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description');
            $table->decimal('quantity_required', 10, 4)->default(1.0000);
            $table->decimal('quantity_used', 10, 4)->default(0.0000);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->timestamps();

            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('maintenance_order_id');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_parts');
        Schema::dropIfExists('equipment_spare_parts');
        Schema::dropIfExists('ecommerce_product_mappings');
        Schema::dropIfExists('ecommerce_order_items');
        Schema::dropIfExists('fact_sales');
        Schema::dropIfExists('fact_purchases');
        Schema::dropIfExists('fact_inventory_movements');
        Schema::dropIfExists('dim_product');
    }
};
