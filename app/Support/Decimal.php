<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads a number of any of the shapes the database and callers use as a
 * bcmath decimal string.
 *
 * A decimal column arrives as a string on one driver and as a float on
 * another, and a request body decodes its numbers as floats. A float large or
 * small enough casts to an exponent - 1.0E+14, 1.2E-7 - which bcmath refuses
 * outright, so a figure that never came through here failed the arithmetic
 * rather than merely losing a digit in it.
 *
 * An exact figure - a string or an integer - is truncated at the scale, the
 * way the rest of the module's arithmetic treats a figure finer than the
 * column it is going into.
 *
 * A float is rounded at the scale instead, because it is an approximation of
 * a number the column already holds there: 823045260.0823 is kept as a float
 * a shade below itself, so truncating would shave a ten-thousandth off it on
 * every read, and off every figure summed from it.
 */
final class Decimal
{
    /** $value as a decimal string at $scale. */
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

        // Written out rather than cast: casting is what produces the exponent
        // bcmath will not take.
        return number_format((float) $value, $scale, '.', '');
    }

    /** Zero at $scale, for starting a sum. */
    public static function zero(int $scale): string
    {
        return bcadd('0', '0', $scale);
    }
}
