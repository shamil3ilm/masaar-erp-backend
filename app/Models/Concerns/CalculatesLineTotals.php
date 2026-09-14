<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\TaxMath;

/**
 * Recalculates a document line's discount, subtotal, tax and total whenever
 * it is saved, so what is stored always follows from quantity, price,
 * discount and rate whatever the caller passed for the amounts.
 *
 * A line whose table carries the India GST split overrides splitsGst(): its
 * CGST, SGST and IGST amounts are then charged at their own rates and replace
 * the tax.
 */
trait CalculatesLineTotals
{
    public static function bootCalculatesLineTotals(): void
    {
        static::saving(function (self $line): void {
            $line->calculateTotals();
        });
    }

    public function calculateTotals(): void
    {
        $amounts = TaxMath::line(
            (string) $this->quantity,
            (string) $this->unit_price,
            (string) ($this->tax_rate ?? '0'),
            $this->discount_type,
            $this->discount_value === null ? null : (string) $this->discount_value,
        );

        $this->discount_amount = $amounts['discount'];
        $this->subtotal = $amounts['subtotal'];
        $this->tax_amount = $amounts['tax'];

        $gst = $this->splitsGst()
            ? TaxMath::gst(
                $amounts['subtotal'],
                (string) ($this->cgst_rate ?? '0'),
                (string) ($this->sgst_rate ?? '0'),
                (string) ($this->igst_rate ?? '0'),
            )
            : null;

        if ($gst !== null) {
            $this->cgst_amount = $gst['cgst'];
            $this->sgst_amount = $gst['sgst'];
            $this->igst_amount = $gst['igst'];
            $this->tax_amount = $gst['tax'];
        }

        $this->total = bcadd($amounts['subtotal'], (string) $this->tax_amount, TaxMath::SCALE);
    }

    /** Whether this line's table has the CGST, SGST and IGST columns. */
    protected function splitsGst(): bool
    {
        return false;
    }
}
