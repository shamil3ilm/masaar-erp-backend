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

    public function test_a_float_and_its_string_read_the_same(): void
    {
        // A decimal column arrives as a string on one driver and a float on
        // another. Reading the same number two ways has to give one answer,
        // or a figure differs between the database the tests run on and the
        // one that holds the books.
        foreach (['1.00005', '2.99999', '-1.00005', '0.12345'] as $number) {
            $this->assertSame(
                Decimal::at($number, 4),
                Decimal::at((float) $number, 4),
                "{$number} read as a string and as a float must agree",
            );
        }
    }

    public function test_it_truncates_rather_than_rounds(): void
    {
        $this->assertSame('1.0000', Decimal::at('1.00005', 4));
        $this->assertSame('1.0000', Decimal::at(1.00005, 4));
        $this->assertSame('2.9999', Decimal::at(2.99999, 4));
        $this->assertSame('-1.0000', Decimal::at(-1.00005, 4));
    }

    public function test_zero_starts_a_sum_at_the_scale(): void
    {
        $this->assertSame('0.0000', Decimal::zero(4));
        $this->assertSame('0.00000000', Decimal::zero(8));
    }
}
