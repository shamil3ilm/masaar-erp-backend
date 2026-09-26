<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads a number of any of the shapes the database and callers use as a
 * bcmath decimal string.
 *
 * A decimal column arrives as a string on one driver and as a float on
 * another, and a request body decodes its numbers as floats. A float large or
 * small enough is written as an exponent - 1.0E+14, 1.2E-7 - which bcmath
 * refuses outright, so a figure that never went through here failed the
 * arithmetic rather than rounding badly in it.
 *
 * Every shape truncates at the scale, so the same number gives the same
 * answer whichever shape it arrived in, and the arithmetic keeps the module's
 * convention of truncating rather than rounding.
 */
final class Decimal
{
    /**
     * How much finer than the scale a float is written before it is
     * truncated. A float carries about seventeen significant digits, so a few
     * spare places are enough to hold what it knows and let the truncation,
     * rather than the formatting, decide the last digit.
     */
    private const GUARD = 6;

    /** $value as a decimal string at $scale, truncated, never rounded. */
    public static function at(float|int|string|null $value, int $scale): string
    {
        if ($value === null) {
            return self::zero($scale);
        }

        if (is_int($value)) {
            return bcadd((string) $value, '0', $scale);
        }

        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return bcadd($value, '0', $scale);
        }

        // Written out in full at a finer scale first: casting the float to a
        // string is what produces the exponent bcmath will not take.
        return bcadd(number_format((float) $value, $scale + self::GUARD, '.', ''), '0', $scale);
    }

    /** Zero at $scale, for starting a sum. */
    public static function zero(int $scale): string
    {
        return bcadd('0', '0', $scale);
    }
}
