<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\ExchangeRateProvider;
use App\Models\Accounting\Currency;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CurrencyService
{
    public function __construct(
        private readonly ExchangeRateProvider $rates,
    ) {}

    /**
     * Supported currencies with their details.
     */
    public const CURRENCIES = [
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼', 'decimals' => 2, 'region' => 'GCC'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'decimals' => 2, 'region' => 'GCC'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => '﷼', 'decimals' => 2, 'region' => 'GCC'],
        'OMR' => ['name' => 'Omani Rial', 'symbol' => '﷼', 'decimals' => 3, 'region' => 'GCC'],
        'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => '.د.ب', 'decimals' => 3, 'region' => 'GCC'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'decimals' => 3, 'region' => 'GCC'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimals' => 2, 'region' => 'ASIA'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'region' => 'GLOBAL'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2, 'region' => 'GLOBAL'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'decimals' => 2, 'region' => 'GLOBAL'],
    ];

    /**
     * Get all supported currencies.
     */
    public function getSupportedCurrencies(): array
    {
        return self::CURRENCIES;
    }

    /**
     * Get currency details.
     */
    public function getCurrency(string $code): ?array
    {
        return self::CURRENCIES[strtoupper($code)] ?? null;
    }

    /**
     * Get decimal places for currency.
     */
    public function getDecimals(string $code): int
    {
        return self::CURRENCIES[strtoupper($code)]['decimals'] ?? 2;
    }

    /**
     * Format amount in currency.
     */
    public function format(float $amount, string $currencyCode, bool $showSymbol = true): string
    {
        $currency = $this->getCurrency($currencyCode);
        $decimals = $currency['decimals'] ?? 2;

        $formatted = number_format($amount, $decimals);

        if ($showSymbol && $currency) {
            return $currency['symbol'].' '.$formatted;
        }

        return $formatted;
    }

    /**
     * Get exchange rate between two currencies.
     */
    public function getExchangeRate(
        string $fromCurrency,
        string $toCurrency,
        ?Carbon $date = null
    ): float {
        if (strtoupper($fromCurrency) === strtoupper($toCurrency)) {
            return 1.0;
        }

        $date = $date ?? now();

        // Check database first
        $rate = $this->getDatabaseRate($fromCurrency, $toCurrency, $date);

        if ($rate !== null) {
            return $rate;
        }

        // Fall back to cached rates or fetch new ones
        return $this->getCachedOrFetchRate($fromCurrency, $toCurrency, $date);
    }

    /**
     * Get rate from database.
     */
    protected function getDatabaseRate(string $from, string $to, Carbon $date): ?float
    {
        $rate = ExchangeRate::where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to))
            ->where('rate_date', '<=', $date->format('Y-m-d'))
            ->orderBy('rate_date', 'desc')
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Get cached rate or fetch from API.
     */
    protected function getCachedOrFetchRate(string $from, string $to, Carbon $date): float
    {
        $cacheKey = "exchange_rate_{$from}_{$to}_{$date->format('Y-m-d')}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($from, $to) {
            return $this->fetchLiveRate($from, $to);
        });
    }

    /**
     * The provider's current rate, stored for later lookups, or the built-in
     * rate when the provider has none.
     */
    protected function fetchLiveRate(string $from, string $to): float
    {
        $rate = $this->rates->latest($from, $to);

        if ($rate === null) {
            return $this->getDefaultRate($from, $to);
        }

        $this->storeRate($from, $to, $rate);

        return $rate;
    }

    /**
     * Get default exchange rates for common pairs.
     */
    protected function getDefaultRate(string $from, string $to): float
    {
        // Converting at a rate nobody chose is worth a line in the log. These
        // are a snapshot, and an invoice converted with one is wrong by
        // however far the market has moved since.
        Log::warning('Converting currency at a built-in rate, not a live one.', [
            'from' => $from,
            'to' => $to,
        ]);

        // Default rates as of a baseline (these should be updated regularly)
        $defaultRates = [
            'USD' => [
                'SAR' => 3.75,
                'AED' => 3.67,
                'QAR' => 3.64,
                'OMR' => 0.385,
                'BHD' => 0.376,
                'KWD' => 0.308,
                'INR' => 83.0,
                'EUR' => 0.92,
                'GBP' => 0.79,
            ],
            'SAR' => [
                'USD' => 0.267,
                'AED' => 0.98,
                'INR' => 22.13,
            ],
        ];

        // Direct rate
        if (isset($defaultRates[$from][$to])) {
            return $defaultRates[$from][$to];
        }

        // Inverse rate
        if (isset($defaultRates[$to][$from])) {
            return 1 / $defaultRates[$to][$from];
        }

        // Cross rate via USD
        if ($from !== 'USD' && $to !== 'USD') {
            $fromToUsd = $this->getDefaultRate($from, 'USD');
            $usdToTo = $this->getDefaultRate('USD', $to);

            return $fromToUsd * $usdToTo;
        }

        // Not 1.0. A pair with no rate is not a pair at parity, and returning
        // one copies the amount across and calls it converted.
        throw new InvalidArgumentException(
            "No exchange rate is available for {$from} to {$to}."
        );
    }

    /**
     * Store exchange rate in database.
     */
    public function storeRate(string $from, string $to, float $rate, ?Carbon $date = null): ExchangeRate
    {
        $date = $date ?? now();

        return ExchangeRate::updateOrCreate(
            [
                'organization_id' => auth()->user()?->organization_id,
                'from_currency' => strtoupper($from),
                'to_currency' => strtoupper($to),
                'rate_date' => $date->format('Y-m-d'),
            ],
            [
                'rate' => $rate,
            ]
        );
    }

    /**
     * Convert amount between currencies.
     */
    public function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        ?Carbon $date = null,
        ?float $customRate = null
    ): array {
        $rate = $customRate ?? $this->getExchangeRate($fromCurrency, $toCurrency, $date);
        $fromDecimals = $this->getDecimals($fromCurrency);
        $toDecimals = $this->getDecimals($toCurrency);

        $convertedAmount = bcmul((string) $amount, (string) $rate, $toDecimals + 2);
        $convertedAmount = round((float) $convertedAmount, $toDecimals);

        return [
            'original_amount' => round($amount, $fromDecimals),
            'original_currency' => $fromCurrency,
            'converted_amount' => $convertedAmount,
            'converted_currency' => $toCurrency,
            'exchange_rate' => $rate,
            'rate_date' => ($date ?? now())->format('Y-m-d'),
        ];
    }

    /**
     * Convert amount to base currency.
     */
    public function convertToBase(float $amount, string $fromCurrency, ?float $rate = null): array
    {
        $baseCurrency = $this->getBaseCurrency();

        return $this->convert($amount, $fromCurrency, $baseCurrency, null, $rate);
    }

    /**
     * Get organization's base currency.
     */
    public function getBaseCurrency(): string
    {
        if (auth()->check() && auth()->user()->organization) {
            return auth()->user()->organization->base_currency ?? 'SAR';
        }

        return config('erp.default_currency', 'SAR');
    }

    /**
     * Calculate exchange gain/loss.
     */
    public function calculateExchangeGainLoss(
        float $originalAmount,
        string $originalCurrency,
        float $originalRate,
        float $currentRate
    ): array {
        $baseCurrency = $this->getBaseCurrency();

        $originalBaseAmount = bcmul((string) $originalAmount, (string) $originalRate, 4);
        $currentBaseAmount = bcmul((string) $originalAmount, (string) $currentRate, 4);

        $difference = bcsub($currentBaseAmount, $originalBaseAmount, 4);

        return [
            'original_base_amount' => (float) $originalBaseAmount,
            'current_base_amount' => (float) $currentBaseAmount,
            'gain_loss' => (float) $difference,
            'is_gain' => (float) $difference > 0,
            'is_loss' => (float) $difference < 0,
            'base_currency' => $baseCurrency,
            'original_rate' => $originalRate,
            'current_rate' => $currentRate,
        ];
    }

    /**
     * Round amount according to currency rules.
     */
    public function round(float $amount, string $currencyCode): float
    {
        $decimals = $this->getDecimals($currencyCode);

        return round($amount, $decimals);
    }

    /**
     * Get rate history for a currency pair.
     */
    public function getRateHistory(
        string $from,
        string $to,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        return ExchangeRate::where('from_currency', strtoupper($from))
            ->where('to_currency', strtoupper($to))
            ->whereBetween('rate_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('rate_date')
            ->get()
            ->map(fn ($rate) => [
                'date' => $rate->rate_date,
                'rate' => (float) $rate->rate,
            ])
            ->toArray();
    }
}
