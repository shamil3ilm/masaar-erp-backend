<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 200);
            $table->string('type', 30)->default('road'); // road|air|sea|rail|courier|multimodal
            $table->string('status', 20)->default('active'); // active|inactive|suspended
            $table->string('scac_code', 10)->nullable(); // Standard Carrier Alpha Code (road/rail)
            $table->string('iata_code', 10)->nullable(); // IATA carrier code (air)
            $table->string('country_code', 5)->nullable();
            $table->string('currency_code', 5)->default('USD');
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->decimal('rating', 3, 2)->nullable(); // 0.00–5.00 computed from performance
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'carriers_org_status_idx');
            $table->index(['organization_id', 'type', 'status'], 'carriers_org_type_status_idx');
        });

        Schema::create('carrier_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month'); // 1–12
            $table->unsignedInteger('total_shipments')->default(0);
            $table->unsignedInteger('on_time_deliveries')->default(0);
            $table->unsignedInteger('late_deliveries')->default(0);
            $table->unsignedInteger('damaged_shipments')->default(0);
            $table->unsignedInteger('lost_shipments')->default(0);
            $table->decimal('avg_transit_days', 5, 2)->nullable();
            $table->decimal('cost_variance_pct', 6, 2)->nullable(); // actual vs agreed
            $table->decimal('on_time_pct', 5, 2)->nullable(); // computed
            $table->decimal('rating', 3, 2)->nullable(); // 0.00–5.00
            $table->timestamps();

            $table->unique(['organization_id', 'carrier_id', 'period_year', 'period_month'], 'tm_carrier_perf_org_carrier_period_uniq');
        });

        Schema::create('carrier_services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('mode', 30)->default('road'); // road|air|sea|rail|courier
            $table->unsignedSmallInteger('transit_days_min')->default(1);
            $table->unsignedSmallInteger('transit_days_max')->default(1);
            $table->boolean('is_tracking_available')->default(false);
            $table->string('tracking_url_template', 500)->nullable(); // {tracking_number} placeholder
            $table->boolean('handles_dangerous_goods')->default(false);
            $table->boolean('handles_refrigerated')->default(false);
            $table->boolean('handles_oversized')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'carrier_id', 'code'], 'carrier_services_org_carrier_code_uniq');
            $table->index(['organization_id', 'carrier_id', 'is_active'], 'carrier_services_org_carrier_active_idx');
        });

        Schema::create('freight_rate_tables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->foreignId('carrier_service_id')->nullable()->constrained('carrier_services')->nullOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('currency_code', 5)->default('USD');
            // basis: how the rate is applied
            $table->string('basis', 20)->default('weight'); // weight|volume|piece|pallet|shipment
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'freight_rate_tables_org_code_uniq');
            $table->index(['organization_id', 'carrier_id', 'is_active'], 'freight_rate_tables_org_carrier_active_idx');
            $table->index(['organization_id', 'valid_from', 'valid_to'], 'freight_rate_tables_org_validity_idx');
        });

        Schema::create('freight_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->string('agreement_number', 50);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('currency_code', 5)->default('USD');
            $table->string('status', 20)->default('draft'); // draft|active|expired|terminated
            $table->foreignId('rate_table_id')->nullable()->constrained('freight_rate_tables')->nullOnDelete();
            $table->decimal('annual_volume_commitment', 14, 4)->nullable(); // kg
            $table->decimal('annual_spend_commitment', 14, 4)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'agreement_number'], 'freight_agreements_org_num_uniq');
            $table->index(['organization_id', 'carrier_id', 'status'], 'freight_agreements_org_carrier_status_idx');
            $table->index(['organization_id', 'valid_from', 'valid_to'], 'freight_agreements_org_validity_idx');
        });

        Schema::create('freight_rate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_table_id')->constrained('freight_rate_tables')->cascadeOnDelete();
            $table->string('origin_zone', 100)->nullable();   // null = any
            $table->string('destination_zone', 100)->nullable();
            $table->decimal('weight_from', 10, 3)->default(0);
            $table->decimal('weight_to', 10, 3)->nullable(); // null = unlimited
            $table->decimal('volume_from', 10, 4)->default(0);
            $table->decimal('volume_to', 10, 4)->nullable();
            $table->decimal('base_rate', 14, 4)->default(0);    // fixed charge
            $table->decimal('per_unit_rate', 14, 6)->default(0); // per kg, per cbm, etc.
            $table->decimal('min_charge', 14, 4)->default(0);
            $table->decimal('max_charge', 14, 4)->nullable();
            $table->timestamps();

            $table->index(['rate_table_id', 'origin_zone', 'destination_zone'], 'freight_rate_lines_table_zones_idx');
        });

        Schema::create('freight_surcharges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_table_id')->nullable()->constrained('freight_rate_tables')->nullOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('type', 30)->default('fuel');
            // type: fuel|toll|insurance|dg_handling|remote_area|oversize|residential|peak|other
            $table->string('calculation_method', 20)->default('pct');
            // calculation_method: flat|pct|per_kg|per_cbm|per_piece
            $table->decimal('value', 14, 6);
            $table->string('currency_code', 5)->default('USD');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'type', 'is_active'], 'freight_surcharges_org_type_active_idx');
            $table->index(['organization_id', 'carrier_id', 'is_active'], 'freight_surcharges_org_carrier_active_idx');
        });

        Schema::create('freight_tender_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('tender_number', 50);
            $table->string('title', 200);
            $table->string('origin_country', 5)->nullable();
            $table->string('origin_zone', 100)->nullable();
            $table->string('destination_country', 5)->nullable();
            $table->string('destination_zone', 100)->nullable();
            $table->string('transport_mode', 30)->default('road');
            $table->decimal('total_weight', 14, 3)->default(0);
            $table->decimal('total_volume', 14, 4)->default(0);
            $table->unsignedInteger('shipment_count')->default(1);
            $table->boolean('has_dangerous_goods')->default(false);
            $table->boolean('requires_refrigeration')->default(false);
            $table->date('required_by_date')->nullable();
            $table->dateTime('bid_deadline')->nullable();
            $table->string('status', 20)->default('draft');
            // status: draft|open|evaluating|awarded|cancelled
            $table->foreignId('awarded_carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->foreignId('awarded_bid_id')->nullable(); // set after award
            $table->dateTime('awarded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'tender_number'], 'freight_tender_requests_org_num_uniq');
            $table->index(['organization_id', 'status', 'bid_deadline'], 'freight_tender_requests_org_status_deadline_idx');
        });

        Schema::create('freight_tender_bids', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tender_request_id')->constrained('freight_tender_requests')->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->cascadeOnDelete();
            $table->decimal('total_price', 14, 4);
            $table->string('currency_code', 5)->default('USD');
            $table->unsignedSmallInteger('transit_days');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('submitted');
            // status: submitted|under_review|awarded|rejected|withdrawn
            $table->dateTime('submitted_at');
            $table->dateTime('evaluated_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('breakdown')->nullable(); // itemized cost breakdown
            $table->timestamps();

            $table->unique(['tender_request_id', 'carrier_id'], 'freight_tender_bids_request_carrier_uniq');
            $table->index(['tender_request_id', 'status'], 'freight_tender_bids_request_status_idx');
        });

        Schema::create('freight_tender_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_request_id')->constrained('freight_tender_requests')->cascadeOnDelete();
            $table->string('description', 200);
            $table->decimal('weight', 14, 3)->default(0);
            $table->decimal('volume', 14, 4)->default(0);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit_of_measure', 20)->default('pcs');
            $table->string('cargo_type', 50)->nullable(); // general|dg|refrigerated|hazmat|bulk
            $table->boolean('is_dangerous_goods')->default(false);
            $table->string('un_number', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('load_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('plan_number', 50);
            $table->string('status', 20)->default('open');
            // status: open|building|finalized|dispatched|closed|cancelled
            $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->foreignId('carrier_service_id')->nullable()->constrained('carrier_services')->nullOnDelete();
            $table->string('vehicle_type', 50)->nullable(); // truck|van|container_20ft|container_40ft|etc
            $table->string('vehicle_plate', 30)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_contact', 50)->nullable();
            $table->decimal('max_weight', 14, 3)->nullable(); // kg
            $table->decimal('max_volume', 14, 4)->nullable(); // cbm
            $table->decimal('current_weight', 14, 3)->default(0);
            $table->decimal('current_volume', 14, 4)->default(0);
            $table->decimal('utilization_weight_pct', 5, 2)->default(0); // computed
            $table->decimal('utilization_volume_pct', 5, 2)->default(0); // computed
            $table->dateTime('planned_departure')->nullable();
            $table->dateTime('actual_departure')->nullable();
            $table->string('origin_location', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'plan_number'], 'load_plans_org_num_uniq');
            $table->index(['organization_id', 'status', 'planned_departure'], 'load_plans_org_status_departure_idx');
            $table->index(['organization_id', 'carrier_id', 'status'], 'load_plans_org_carrier_status_idx');
        });

        Schema::create('transportation_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('order_number', 50);
            $table->string('type', 20)->default('outbound'); // outbound|inbound|internal
            $table->string('status', 20)->default('draft');
            // status: draft|planned|tendered|carrier_assigned|in_transit|delivered|cancelled
            $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->foreignId('carrier_service_id')->nullable()->constrained('carrier_services')->nullOnDelete();
            $table->foreignId('load_plan_id')->nullable()->constrained('load_plans')->nullOnDelete();
            $table->foreignId('tender_request_id')->nullable()->constrained('freight_tender_requests')->nullOnDelete();
            $table->string('origin_address', 500)->nullable();
            $table->string('origin_country', 5)->nullable();
            $table->string('destination_address', 500)->nullable();
            $table->string('destination_country', 5)->nullable();
            $table->dateTime('planned_departure')->nullable();
            $table->dateTime('planned_arrival')->nullable();
            $table->dateTime('actual_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->decimal('total_weight', 14, 3)->default(0);
            $table->decimal('total_volume', 14, 4)->default(0);
            $table->decimal('freight_cost', 14, 4)->default(0);
            $table->string('currency_code', 5)->default('USD');
            $table->string('tracking_number', 100)->nullable();
            $table->boolean('has_dangerous_goods')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'order_number'], 'transportation_orders_org_num_uniq');
            $table->index(['organization_id', 'status', 'planned_departure'], 'transportation_orders_org_status_departure_idx');
            $table->index(['organization_id', 'carrier_id', 'status'], 'transportation_orders_org_carrier_status_idx');
            $table->index(['organization_id', 'load_plan_id'], 'transportation_orders_org_load_plan_idx');
        });

        Schema::create('load_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_plan_id')->constrained('load_plans')->cascadeOnDelete();
            $table->foreignId('transportation_order_id')->constrained('transportation_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('loading_sequence')->default(0);
            $table->timestamps();

            $table->unique(['load_plan_id', 'transportation_order_id'], 'load_plan_items_plan_order_uniq');
        });

        Schema::create('transportation_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transportation_order_id')->constrained('transportation_orders')->cascadeOnDelete();
            $table->string('reference_type', 30)->nullable();
            // reference_type: sales_order|purchase_order|stock_transfer|shipment|other
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 50)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description', 200);
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit_of_measure', 20)->default('pcs');
            $table->decimal('weight', 14, 3)->default(0);
            $table->decimal('volume', 14, 4)->default(0);
            $table->boolean('is_dangerous_goods')->default(false);
            $table->string('un_number', 10)->nullable();
            $table->timestamps();

            $table->index(['transportation_order_id'], 'transportation_order_items_order_idx');
            $table->index(['reference_type', 'reference_id'], 'transportation_order_items_ref_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('transportation_order_items');
        Schema::dropIfExists('load_plan_items');
        Schema::dropIfExists('transportation_orders');
        Schema::dropIfExists('load_plans');
        Schema::dropIfExists('freight_tender_items');
        Schema::dropIfExists('freight_tender_bids');
        Schema::dropIfExists('freight_tender_requests');
        Schema::dropIfExists('freight_surcharges');
        Schema::dropIfExists('freight_rate_lines');
        Schema::dropIfExists('freight_agreements');
        Schema::dropIfExists('freight_rate_tables');
        Schema::dropIfExists('carrier_services');
        Schema::dropIfExists('carrier_performance');
        Schema::dropIfExists('carriers');
    }
};
