<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Decimal;
use App\Support\TaxMath;

/**
 * The totals a document stores from its lines, for every document that has a
 * lines() relation of lines using CalculatesLineTotals.
 *
 * The lines are read and summed here rather than through a SQL SUM(): the sum
 * of a decimal column comes back as a float on SQLite and as a string on
 * MySQL, and the float is the one that quietly loses the last decimals of a
 * large document. Added here, every figure stays a bcmath string.
 *
 * The lines are also grouped by the rate each was taxed at, because the
 * document discount is an allowance over the whole document: TaxMath shares
 * it across those rates and taxes each reduced base, so the document's tax
 * follows the discount instead of the lines' own tax.
 */
trait CalculatesDocumentTotals
{
    /**
     * This document's subtotal, discount, taxable amount, tax and total.
     *
     * @return array{subtotal: string, discount: string, taxable: string, tax: string, total: string}
     */
    protected function documentTotals(int $scale = TaxMath::SCALE): array
    {
        return TaxMath::document(
            $this->netByTaxRate($scale),
            $this->discount_type,
            $this->discount_value === null ? null : Decimal::at($this->discount_value, $scale),
            $scale,
        );
    }

    /**
     * The net this document's lines charge at each rate they were taxed at,
     * in the order the rates first appear.
     *
     * @return list<array{rate: string, net: string}>
     */
    protected function netByTaxRate(int $scale = TaxMath::SCALE): array
    {
        $nets = [];

        foreach ($this->lines()->get() as $line) {
            // Always a decimal string at this scale, so never an array key
            // PHP would turn back into an integer.
            $rate = $line->appliedTaxRate($scale);

            $nets[$rate] = bcadd(
                $nets[$rate] ?? Decimal::zero($scale),
                Decimal::at($line->subtotal, $scale),
                $scale,
            );
        }

        return array_map(
            static fn (string $rate, string $net): array => ['rate' => $rate, 'net' => $net],
            array_keys($nets),
            array_values($nets),
        );
    }
}
