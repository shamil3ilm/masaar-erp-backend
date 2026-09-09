<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Formula;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormulaTest extends TestCase
{
    public function test_it_adds_and_subtracts(): void
    {
        $this->assertSame(7.0, Formula::evaluate('3 + 4'));
        $this->assertSame(-1.0, Formula::evaluate('3 - 4'));
    }

    public function test_multiplication_binds_tighter(): void
    {
        $this->assertSame(14.0, Formula::evaluate('2 + 3 * 4'));
        $this->assertSame(20.0, Formula::evaluate('(2 + 3) * 4'));
    }

    public function test_it_reads_decimals(): void
    {
        $this->assertSame(1500.0, Formula::evaluate('5000 * 0.3'));
    }

    public function test_it_negates(): void
    {
        $this->assertSame(-5.0, Formula::evaluate('-5'));
        $this->assertSame(3.0, Formula::evaluate('8 + -5'));
        $this->assertSame(-8.0, Formula::evaluate('-(3 + 5)'));
    }

    public function test_an_unsubstituted_placeholder_raises(): void
    {
        // The old evaluator returned 0 here, paying nothing for the component.
        $this->expectException(InvalidArgumentException::class);

        Formula::evaluate('{basic} * 0.1');
    }

    public function test_dividing_by_zero_raises(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Formula::evaluate('100 / 0');
    }

    public function test_unmatched_brackets_raise(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Formula::evaluate('(2 + 3');
    }

    public function test_an_incomplete_expression_raises(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Formula::evaluate('2 +');
    }

    public function test_it_will_not_run_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Formula::evaluate('phpinfo()');
    }
}
