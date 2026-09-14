<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Support\TaxMath;

class TaxService
{
    /**
     * Calculate tax for a line item.
     */
    public function calculateLineTax(
        string $amount,
        string $taxRate,
        bool $isTaxInclusive = false,
        int $decimals = 4
    ): TaxCalculation {
        if ($isTaxInclusive) {
            return $this->extractTaxFromInclusive($amount, $taxRate, $decimals);
        }

        return $this->calculateTaxOnExclusive($amount, $taxRate, $decimals);
    }

    /**
     * Calculate tax on tax-exclusive amount.
     */
    public function calculateTaxOnExclusive(string $amount, string $taxRate, int $decimals = 4): TaxCalculation
    {
        $taxAmount = TaxMath::percentOf($amount, $taxRate, $decimals);
        $totalAmount = bcadd($amount, $taxAmount, $decimals);

        return new TaxCalculation(
            taxableAmount: $amount,
            taxAmount: $taxAmount,
            totalAmount: $totalAmount,
            taxRate: $taxRate,
            isTaxInclusive: false
        );
    }

    /**
     * Extract tax from tax-inclusive amount.
     */
    public function extractTaxFromInclusive(string $amount, string $taxRate, int $decimals = 4): TaxCalculation
    {
        // Formula: taxable = inclusive / (1 + rate/100)
        $divisor = bcadd('1', bcdiv($taxRate, '100', 10), 10);
        $taxableAmount = bcdiv($amount, $divisor, $decimals);
        $taxAmount = bcsub($amount, $taxableAmount, $decimals);

        return new TaxCalculation(
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
            totalAmount: $amount,
            taxRate: $taxRate,
            isTaxInclusive: true
        );
    }

}

// Data classes for tax calculations

class TaxCalculation
{
    public function __construct(
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly string $totalAmount,
        public readonly string $taxRate,
        public readonly bool $isTaxInclusive
    ) {}
}
