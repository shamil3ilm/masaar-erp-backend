<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Line and document tax arithmetic, shared by every document that stores
 * amounts from quantity, price, discount and tax rate.
 *
 * All figures are bcmath strings and every step truncates at the document's
 * scale. A percentage is divided by 100 two decimals finer than that scale,
 * which is exact for the four-decimal rates the rate columns hold. Documents
 * stored at four decimals use scale 4; sales credit notes and sales returns
 * are stored at two and use scale 2.
 *
 * A line discount comes off before tax, so it reduces the taxable amount. A
 * document discount comes off after tax and leaves the VAT as the lines
 * charged it.
 *
 * Which rate applies is decided elsewhere (TaxCalculatorService); this class
 * only does the arithmetic, so a model's saving hook can use it as well as a
 * service.
 */
final class TaxMath
{
    public const SCALE = 4;

    /** $percent per cent of $amount. */
    public static function percentOf(string $amount, string $percent, int $scale = self::SCALE): string
    {
        return bcmul($amount, bcdiv($percent, '100', $scale + 2), $scale);
    }

    /** Tax on a tax-exclusive amount; nothing when the rate is not positive. */
    public static function tax(string $taxable, string $rate, int $scale = self::SCALE): string
    {
        if (bccomp($rate, '0', $scale + 2) <= 0) {
            return bcadd('0', '0', $scale);
        }

        return self::percentOf($taxable, $rate, $scale);
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
        $zero = bcadd('0', '0', $scale);

        if (bccomp($igstRate, '0', $scale + 2) > 0) {
            $igst = self::percentOf($subtotal, $igstRate, $scale);

            return ['cgst' => $zero, 'sgst' => $zero, 'igst' => $igst, 'tax' => $igst];
        }

        if (bccomp($cgstRate, '0', $scale + 2) > 0 || bccomp($sgstRate, '0', $scale + 2) > 0) {
            $cgst = self::percentOf($subtotal, $cgstRate, $scale);
            $sgst = self::percentOf($subtotal, $sgstRate, $scale);

            return ['cgst' => $cgst, 'sgst' => $sgst, 'igst' => $zero, 'tax' => bcadd($cgst, $sgst, $scale)];
        }

        return null;
    }

    /**
     * A document's discount and total from the sums of its lines. Only a
     * positive discount value applies.
     *
     * @return array{discount: string, total: string}
     */
    public static function document(
        string $subtotal,
        string $tax,
        ?string $discountType = null,
        ?string $discountValue = null,
        int $scale = self::SCALE,
    ): array {
        $value = $discountValue ?? '0';
        $hasDiscount = bccomp($value, '0', $scale + 2) > 0;

        $discount = match (true) {
            $hasDiscount && $discountType === 'percentage' => self::percentOf($subtotal, $value, $scale),
            $hasDiscount && $discountType === 'fixed' => $value,
            default => '0',
        };

        return [
            'discount' => $discount,
            'total' => bcsub(bcadd($subtotal, $tax, $scale), $discount, $scale),
        ];
    }
}
