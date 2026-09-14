<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * A source of current exchange rates.
 */
interface ExchangeRateProvider
{
    /**
     * Units of $to for one unit of $from, or null when the provider has no
     * usable answer (not configured, unreachable, refused, or pair unknown).
     */
    public function latest(string $from, string $to): ?float;
}
