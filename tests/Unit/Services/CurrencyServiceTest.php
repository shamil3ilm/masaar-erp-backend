<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Core\CurrencyService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * What happens when there is no rate.
 *
 * getDefaultRate used to end in `return 1.0`, so a pair it had no rate for
 * converted at parity: the amount was copied across and called converted.
 */
class CurrencyServiceTest extends TestCase
{
    private CurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CurrencyService;
    }

    public function test_it_reads_a_known_rate(): void
    {
        $this->assertSame(3.75, $this->rate('USD', 'SAR'));
    }

    public function test_a_direct_rate_wins_over_the_inverse(): void
    {
        // SAR -> USD is listed as 0.267, and that is used rather than 1/3.75.
        // The two disagree: converting to USD and back multiplies by 1.00125.
        $this->assertSame(0.267, $this->rate('SAR', 'USD'));
    }

    public function test_it_inverts_when_only_one_direction_is_listed(): void
    {
        // GBP -> USD is not listed; USD -> GBP is.
        $this->assertEqualsWithDelta(1 / 0.79, $this->rate('GBP', 'USD'), 0.0001);
    }

    public function test_it_crosses_through_usd(): void
    {
        // AED -> USD -> GBP, neither of which is listed directly.
        $expected = (1 / 3.67) * 0.79;

        $this->assertEqualsWithDelta($expected, $this->rate('AED', 'GBP'), 0.0001);
    }

    public function test_an_unknown_pair_raises(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rate('USD', 'JPY');
    }

    private function rate(string $from, string $to): float
    {
        $method = new \ReflectionMethod($this->service, 'getDefaultRate');
        $method->setAccessible(true);

        return $method->invoke($this->service, $from, $to);
    }
}
