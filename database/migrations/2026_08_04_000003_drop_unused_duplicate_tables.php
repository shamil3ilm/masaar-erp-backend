<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops tables that nothing reads or writes.
 *
 * Each duplicated a capability the application already has elsewhere:
 *   - parked invoices  -> ParkedDocument (accounting/parked-documents)
 *   - tolerance keys and rules, and their check results
 *                      -> PaymentToleranceService
 *   - procurement match results
 *                      -> ThreeWayMatchResult, written by GoodsReceiptService
 *   - customer credit limits
 *                      -> CreditLimit, used by CreditManagementService and InvoiceService
 *   - credit limit histories
 *                      -> CreditLimit records changes through HasAuditTrail
 *
 * The models were removed alongside this migration. The original create
 * migrations remain in history if the schema is ever wanted again.
 */
return new class extends Migration
{
    private const DROPPED = [
        'mm_tolerance_check_results',
        'mm_tolerance_keys',
        'mm_invoice_tolerance_rules',
        'mm_invoice_blocks',
        'mm_parked_invoices',
        'procurement_match_results',
        'credit_limit_histories',
        'customer_credit_limits',
    ];

    public function up(): void
    {
        foreach (self::DROPPED as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Not reversible: recreating empty tables no model targets would restore
     * nothing useful. Roll back to the create migrations instead.
     */
    public function down(): void
    {
        //
    }
};
