<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TaxMath;
use PHPUnit\Framework\TestCase;

/**
 * The two rules TaxMath decides for every document in the module: tax rounds
 * half away from zero at the document's scale, and a document discount comes
 * off the taxable amount, shared across the tax rates the lines carry.
 */
class TaxMathTest extends TestCase
{
    public function test_tax_rounds_half_up_at_the_scale(): void
    {
        // 2.50 at 5% is exactly 0.125, the half-way case at two decimals.
        $this->assertSame('0.13', TaxMath::tax('2.50', '5', 2));
        $this->assertSame('0.12', TaxMath::tax('2.49', '5', 2));
        $this->assertSame('0.4177', TaxMath::tax('8.3535', '5', 4));
    }

    public function test_tax_on_a_negative_figure_rounds_away_from_zero(): void
    {
        // A credit note stores its figures as negatives, so half-up there has
        // to round away from zero or the note understates the VAT it returns.
        $this->assertSame('-0.13', TaxMath::tax('-2.50', '5', 2));
        $this->assertSame('-0.4177', TaxMath::tax('-8.3535', '5', 4));
        $this->assertSame('-0.13', TaxMath::round('-0.125', 2));
        $this->assertSame('0.13', TaxMath::round('0.125', 2));
    }

    public function test_rounding_leaves_a_figure_already_at_the_scale_alone(): void
    {
        $this->assertSame('0.4000', TaxMath::round('0.4', 4));
        $this->assertSame('0.00', TaxMath::round('0', 2));
        $this->assertSame('0.00', TaxMath::round('-0', 2));
        $this->assertSame('-1.50', TaxMath::round('-1.5', 2));
    }

    public function test_a_rate_that_is_not_positive_is_no_tax(): void
    {
        $this->assertSame('0.0000', TaxMath::tax('100', '0'));
        $this->assertSame('0.0000', TaxMath::tax('100', '-5'));
    }

    public function test_a_percentage_that_is_not_tax_truncates(): void
    {
        // A line or document discount is an allowance, not a charge: it keeps
        // bcmath truncation, so it is never rounded up against the customer.
        $this->assertSame('0.4176', TaxMath::percentOf('8.3535', '5'));
        $this->assertSame('19.8353', TaxMath::percentOf('198.3535', '10'));
    }

    public function test_a_single_rate_document_taxes_what_is_left_after_the_discount(): void
    {
        $totals = TaxMath::document([['rate' => '15.0000', 'net' => '1000.0000']], 'percentage', '10');

        $this->assertSame([
            'subtotal' => '1000.0000',
            'discount' => '100.0000',
            'taxable' => '900.0000',
            'tax' => '135.0000',
            'total' => '1035.0000',
        ], $totals);
    }

    public function test_a_mixed_rate_document_shares_the_discount_in_proportion_to_each_net(): void
    {
        $bases = [
            ['rate' => '15.0000', 'net' => '600.0000'],
            ['rate' => '5.0000', 'net' => '400.0000'],
        ];

        $this->assertSame(['60.0000', '40.0000'], TaxMath::apportion($bases, '100.0000'));

        // 540 at 15% is 81, 360 at 5% is 18. Taking the whole 100 off either
        // rate alone would give 76.50 or 102.00 instead.
        $this->assertSame('99.0000', TaxMath::document($bases, 'fixed', '100')['tax']);
    }

    public function test_the_shares_add_up_to_the_discount_when_the_proportions_do_not_divide(): void
    {
        $bases = [
            ['rate' => '5.0000', 'net' => '100.0000'],
            ['rate' => '10.0000', 'net' => '100.0000'],
            ['rate' => '15.0000', 'net' => '100.0000'],
        ];

        // A third of 10 each: the last rate takes the ten-thousandth the two
        // rounded shares leave, so the shares still sum to the discount.
        $shares = TaxMath::apportion($bases, '10.0000');
        $this->assertSame(['3.3333', '3.3333', '3.3334'], $shares);
        $this->assertSame('10.0000', bcadd(bcadd($shares[0], $shares[1], 4), $shares[2], 4));

        $totals = TaxMath::document($bases, 'fixed', '10');
        $this->assertSame('290.0000', $totals['taxable']);
        $this->assertSame('29.0000', $totals['tax']);
        $this->assertSame('319.0000', $totals['total']);
    }

    public function test_a_zero_rated_net_takes_its_share_of_the_discount_and_bears_no_tax(): void
    {
        $bases = [
            ['rate' => '15.0000', 'net' => '100.0000'],
            ['rate' => '0.0000', 'net' => '100.0000'],
        ];

        $totals = TaxMath::document($bases, 'fixed', '50');

        // Half the allowance belongs to the zero-rated net, so only 75 of the
        // standard-rated net is taxed - not 50 of it.
        $this->assertSame(['25.0000', '25.0000'], TaxMath::apportion($bases, '50.0000'));
        $this->assertSame('11.2500', $totals['tax']);
        $this->assertSame('161.2500', $totals['total']);
    }

    public function test_a_credit_notes_negative_net_is_taxed_away_from_zero(): void
    {
        $totals = TaxMath::document([['rate' => '5.0000', 'net' => '-2.50']], scale: 2);

        $this->assertSame('-0.13', $totals['tax']);
        $this->assertSame('-2.63', $totals['total']);
    }

    public function test_a_discount_above_the_subtotal_is_held_at_the_subtotal(): void
    {
        $bases = [
            ['rate' => '15.0000', 'net' => '100.0000'],
            ['rate' => '5.0000', 'net' => '100.0000'],
        ];

        // No base, and so no tax, goes negative however large the discount is.
        foreach ([['fixed', '1000'], ['percentage', '150']] as [$type, $value]) {
            $totals = TaxMath::document($bases, $type, $value);

            $this->assertSame('200.0000', $totals['discount'], "$type $value");
            $this->assertSame('0.0000', $totals['taxable'], "$type $value");
            $this->assertSame('0.0000', $totals['tax'], "$type $value");
            $this->assertSame('0.0000', $totals['total'], "$type $value");
        }
    }

    public function test_a_document_with_no_net_carries_no_discount(): void
    {
        $totals = TaxMath::document([], 'fixed', '50');

        $this->assertSame([
            'subtotal' => '0.0000',
            'discount' => '0.0000',
            'taxable' => '0.0000',
            'tax' => '0.0000',
            'total' => '0.0000',
        ], $totals);
    }

    public function test_a_document_without_a_discount_charges_each_rate_its_whole_net(): void
    {
        $totals = TaxMath::document([
            ['rate' => '15.0000', 'net' => '600.0000'],
            ['rate' => '5.0000', 'net' => '400.0000'],
        ]);

        $this->assertSame('0.0000', $totals['discount']);
        $this->assertSame('1000.0000', $totals['taxable']);
        $this->assertSame('110.0000', $totals['tax']);
        $this->assertSame('1110.0000', $totals['total']);
    }
}
