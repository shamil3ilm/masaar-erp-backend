<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Line and document tax arithmetic, shared by every document that stores
 * amounts from quantity, price, discount and tax rate.
 *
 * All figures are bcmath strings at the document's scale. Documents stored at
 * four decimals use scale 4; sales credit notes and sales returns are stored
 * at two and use scale 2.
 *
 * Tax is rounded half away from zero at that scale, by round(): 0.125 is
 * 0.13, and -0.125 on a credit note is -0.13. bcmath only truncates, and
 * truncating tax understates what is owed. Everything that charges tax - a
 * line, the India GST split, a document's category bases - goes through
 * tax(), so one rule decides every tax figure the module stores.
 *
 * A line discount comes off before tax, so it reduces that line's taxable
 * amount. A document discount is an allowance over the whole document: it is
 * shared across the tax rates the lines carry, in proportion to the net
 * charged at each, and each rate is then taxed on its reduced base. So once a
 * document discount applies, the document's tax is not the sum of its lines'
 * tax - it is what the reduced bases bear, the way EN 16931 and ZATCA
 * (BR-CO-14) read a document-level allowance.
 *
 * A discount never takes a base below zero, so no document turns its discount
 * into negative tax.
 *
 * Which rate applies is decided elsewhere (TaxCalculatorService); this class
 * only does the arithmetic, so a model's saving hook can use it as well as a
 * service.
 */
final class TaxMath
{
    public const SCALE = 4;

    /**
     * Digits kept past the scale while a percentage or a share is worked out,
     * so the figure that reaches round() is the exact one. Eight is more than
     * a four-decimal rate times an amount at the scale can need.
     */
    private const GUARD = 8;

    /**
     * $value rounded half away from zero at $scale.
     *
     * bcmath truncates towards zero, so adding half a unit of the last place
     * first turns that truncation into half-up; a negative figure takes that
     * half the other way, which rounds it away from zero as well.
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, self::isNegative($value) ? '-'.$half : $half, $scale);
    }

    /** $percent per cent of $amount, truncated at $scale. */
    public static function percentOf(string $amount, string $percent, int $scale = self::SCALE): string
    {
        return bcadd(self::exactPercentOf($amount, $percent, $scale), '0', $scale);
    }

    /**
     * Tax on a tax-exclusive amount, rounded half away from zero; nothing
     * when the rate is not positive.
     */
    public static function tax(string $taxable, string $rate, int $scale = self::SCALE): string
    {
        if (bccomp($rate, '0', $scale + self::GUARD) <= 0) {
            return Decimal::zero($scale);
        }

        return self::round(self::exactPercentOf($taxable, $rate, $scale), $scale);
    }

    /**
     * A line's amounts: the discount (percentage of the gross, or a fixed
     * amount as given; none for any other type), the subtotal after it, the
     * tax on that subtotal and the total.
     *
     * @return array{discount: string, subtotal: string, tax: string, total: string}
     */
    public static function line(
        string $quantity,
        string $unitPrice,
        string $taxRate,
        ?string $discountType = null,
        ?string $discountValue = null,
        int $scale = self::SCALE,
    ): array {
        $gross = bcmul($quantity, $unitPrice, $scale);
        $value = $discountValue ?? '0';

        $discount = match (true) {
            $discountType === 'percentage' && bccomp($value, '0', $scale + 2) > 0 => self::percentOf($gross, $value, $scale),
            $discountType === 'fixed' => $value,
            default => '0',
        };

        $subtotal = bcsub($gross, $discount, $scale);
        $tax = self::tax($subtotal, $taxRate, $scale);

        return [
            'discount' => $discount,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => bcadd($subtotal, $tax, $scale),
        ];
    }

    /**
     * India GST on a line's subtotal. An IGST rate makes it inter-state and
     * IGST is the whole tax; otherwise CGST and SGST are each charged at
     * their own rate and together make the tax. Null when the line carries
     * no GST rate, so the line's own tax rate stands.
     *
     * @return array{cgst: string, sgst: string, igst: string, tax: string}|null
     */
    public static function gst(
        string $subtotal,
        string $cgstRate,
        string $sgstRate,
        string $igstRate,
        int $scale = self::SCALE,
    ): ?array {
        $zero = Decimal::zero($scale);

        if (bccomp($igstRate, '0', $scale + 2) > 0) {
            $igst = self::tax($subtotal, $igstRate, $scale);

            return ['cgst' => $zero, 'sgst' => $zero, 'igst' => $igst, 'tax' => $igst];
        }

        if (bccomp($cgstRate, '0', $scale + 2) > 0 || bccomp($sgstRate, '0', $scale + 2) > 0) {
            $cgst = self::tax($subtotal, $cgstRate, $scale);
            $sgst = self::tax($subtotal, $sgstRate, $scale);

            return ['cgst' => $cgst, 'sgst' => $sgst, 'igst' => $zero, 'tax' => bcadd($cgst, $sgst, $scale)];
        }

        return null;
    }

    /**
     * A document's totals from the net its lines charge at each tax rate.
     *
     * The discount comes off the taxable amount: it is shared across the
     * rates in proportion to their nets, and each rate's tax is charged on
     * what is left of its own net. Only a positive discount value applies,
     * and a discount above the subtotal is held at the subtotal so no base
     * and no tax goes negative.
     *
     * @param  list<array{rate: string, net: string}>  $bases  one row per tax rate
     * @return array{subtotal: string, discount: string, taxable: string, tax: string, total: string}
     */
    public static function document(
        array $bases,
        ?string $discountType = null,
        ?string $discountValue = null,
        int $scale = self::SCALE,
    ): array {
        $subtotal = self::sumOfNets($bases, $scale);
        $discount = self::documentDiscount($subtotal, $discountType, $discountValue, $scale);
        $tax = Decimal::zero($scale);

        foreach (self::apportion($bases, $discount, $scale) as $index => $share) {
            $base = bcsub($bases[$index]['net'], $share, $scale);
            $tax = bcadd($tax, self::tax($base, $bases[$index]['rate'], $scale), $scale);
        }

        $taxable = bcsub($subtotal, $discount, $scale);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'taxable' => $taxable,
            'tax' => $tax,
            'total' => bcadd($taxable, $tax, $scale),
        ];
    }

    /**
     * Each rate's share of $discount, in proportion to its net. The last
     * share takes what the rounded ones leave, so the shares add up to the
     * discount exactly however the proportions fall.
     *
     * @param  list<array{rate: string, net: string}>  $bases
     * @return list<string>
     */
    public static function apportion(array $bases, string $discount, int $scale = self::SCALE): array
    {
        $total = self::sumOfNets($bases, $scale);
        $zero = Decimal::zero($scale);

        if (bccomp($discount, '0', $scale) <= 0 || bccomp($total, '0', $scale) <= 0) {
            return array_fill(0, count($bases), $zero);
        }

        $shares = [];
        $assigned = $zero;
        $last = count($bases) - 1;
        $working = $scale + self::GUARD;

        foreach ($bases as $index => $base) {
            if ($index === $last) {
                $shares[] = bcsub($discount, $assigned, $scale);

                continue;
            }

            $share = self::round(bcdiv(bcmul($discount, $base['net'], $working), $total, $working), $scale);
            $shares[] = $share;
            $assigned = bcadd($assigned, $share, $scale);
        }

        return $shares;
    }

    /**
     * The document's allowance: a percentage of the subtotal or a fixed
     * amount, never more than the subtotal and never less than nothing.
     */
    private static function documentDiscount(
        string $subtotal,
        ?string $discountType,
        ?string $discountValue,
        int $scale,
    ): string {
        $value = $discountValue ?? '0';
        $hasDiscount = bccomp($value, '0', $scale + 2) > 0;

        $discount = match (true) {
            $hasDiscount && $discountType === 'percentage' => self::percentOf($subtotal, $value, $scale),
            $hasDiscount && $discountType === 'fixed' => bcadd($value, '0', $scale),
            default => Decimal::zero($scale),
        };

        $ceiling = bccomp($subtotal, '0', $scale) > 0 ? $subtotal : Decimal::zero($scale);

        return bccomp($discount, $ceiling, $scale) > 0 ? $ceiling : $discount;
    }

    /**
     * @param  list<array{rate: string, net: string}>  $bases
     */
    private static function sumOfNets(array $bases, int $scale): string
    {
        $total = Decimal::zero($scale);

        foreach ($bases as $base) {
            $total = bcadd($total, $base['net'], $scale);
        }

        return $total;
    }

    /**
     * $percent per cent of $amount, kept GUARD digits past the scale so the
     * figure round() rounds, or percentOf() truncates, is the exact one for
     * the four-decimal rates the rate columns hold.
     */
    private static function exactPercentOf(string $amount, string $percent, int $scale): string
    {
        $working = $scale + self::GUARD;

        return bcdiv(bcmul($amount, $percent, $working), '100', $working);
    }

    /** Whether $value is below zero: a minus sign and a digit that is not zero. */
    private static function isNegative(string $value): bool
    {
        return str_starts_with($value, '-') && preg_match('/[1-9]/', $value) === 1;
    }
}
