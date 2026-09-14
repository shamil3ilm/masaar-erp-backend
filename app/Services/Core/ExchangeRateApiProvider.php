<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\ExchangeRateProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ExchangeRate-API v6.
 */
final class ExchangeRateApiProvider implements ExchangeRateProvider
{
    private const URL = 'https://v6.exchangerate-api.com/v6/latest/';

    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function latest(string $from, string $to): ?float
    {
        if ($this->apiKey === '') {
            return null;
        }

        try {
            // The key travels as a bearer token, which keeps it out of the URL
            // and so out of access logs.
            $response = Http::withToken($this->apiKey)
                ->timeout(5)
                ->get(self::URL . rawurlencode($from));
        } catch (ConnectionException $e) {
            Log::warning('Exchange rate provider unreachable', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful() || $response->json('result') !== 'success') {
            return null;
        }

        $rate = $response->json("conversion_rates.{$to}");

        return is_numeric($rate) && (float) $rate > 0 ? (float) $rate : null;
    }
}
