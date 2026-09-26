<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_it_reads_every_shape_a_column_arrives_in(): void
    {
        $this->assertSame('0.0000', Decimal::at(null, 4));
        $this->assertSame('12.0000', Decimal::at(12, 4));
        $this->assertSame('12.3400', Decimal::at('12.34', 4));
        $this->assertSame('12.3400', Decimal::at(12.34, 4));
        $this->assertSame('-12.3400', Decimal::at(-12.34, 4));
    }

    public function test_a_float_is_read_past_the_size_it_prints_in_full(): void
    {
        // (string) 1.0E+14 is an exponent, which bcmath refuses outright.
        $this->assertSame('100000000000000.0000', Decimal::at(1.0e14, 4));
        $this->assertSame('0.00000012', Decimal::at(1.2e-7, 8));
    }

    public function test_a_stored_figure_reads_the_same_as_a_string_or_a_float(): void
    {
        // The same column is handed over as a string on one driver and as a
        // float on another. Both have to give the figure that was stored, or
        // a total differs between the database the tests run on and the one
        // that holds the books.
        foreach (['823045260.0823', '12.3400', '-0.2345', '0.0001'] as $stored) {
            $this->assertSame(
                Decimal::at($stored, 4),
                Decimal::at((float) $stored, 4),
                "{$stored} must read the same as a string and as a float",
            );
        }
    }

    public function test_a_float_is_rounded_back_to_the_figure_it_stands_for(): void
    {
        // 823045260.0823 is held as 823045260.08229994. Truncating it would
        // take a ten-thousandth off every read, and off every sum of them.
        $this->assertSame('823045260.0823', Decimal::at(823045260.0823, 4));
        $this->assertSame('0.1000', Decimal::at(0.1, 4));
    }

    public function test_an_exact_figure_finer_than_the_scale_is_truncated(): void
    {
        $this->assertSame('1.0000', Decimal::at('1.00005', 4));
        $this->assertSame('2.9999', Decimal::at('2.99999', 4));
        $this->assertSame('-1.0000', Decimal::at('-1.00005', 4));
    }

    public function test_zero_starts_a_sum_at_the_scale(): void
    {
        $this->assertSame('0.0000', Decimal::zero(4));
        $this->assertSame('0.00000000', Decimal::zero(8));
    }
}
