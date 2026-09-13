<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys whose target is created later.
     *
     * Most close a cycle: a lead remembers the opportunity it became while the
     * opportunity remembers the lead, a return points at the refund that
     * settled it and the refund points back. One direction has to be added
     * after both tables exist. The rest point at a table from a later file.
     */
    public function up(): void
    {
        // waits on: opportunities
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('converted_opportunity_id', 'lead_converted_opp_fk')
                ->references('id')->on('opportunities')->nullOnDelete();
        });

        // waits on: exchange_orders, refunds
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreign('credit_note_id', 'sales_ret_credit_note_fk')
                ->references('id')->on('credit_notes')->nullOnDelete();
            $table->foreign('refund_id', 'sales_ret_refund_fk')
                ->references('id')->on('refunds')->nullOnDelete();
            $table->foreign('exchange_order_id', 'sales_ret_exchange_order_fk')
                ->references('id')->on('exchange_orders')->nullOnDelete();
        });

        // waits on: leave_tiers
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->foreign('leave_tier_id', 'leave_bal_tier_fk')
                ->references('id')->on('leave_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropForeign('leave_bal_tier_fk');
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropForeign('sales_ret_exchange_order_fk');
            $table->dropForeign('sales_ret_refund_fk');
            $table->dropForeign('sales_ret_credit_note_fk');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign('lead_converted_opp_fk');
        });
    }
};
