<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\CurrencyRevaluation;
use App\Models\Accounting\CurrencyRevaluationItem;
use App\Models\Sales\AdvancePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A money column reads back as a decimal string, at the scale it declares.
 *
 * The decimal: cast returns a string on every driver. A column without it is
 * handed over as the driver gives it: a string on MySQL, a float on SQLite. So
 * an uncast money column makes the suite measure a value production never
 * sees, and the difference is not cosmetic. Money is added with
 * bcadd((string) $model->amount, ...), and (string) on a float writes only
 * PHP's precision=14 significant digits: a whole 5000 arrives as "5000", and a
 * rate of 0.000001 as "1.0E-6", which bcmath refuses outright.
 *
 * What the cast cannot repair here is the storage. SQLite gives a decimal
 * column NUMERIC affinity and keeps the value as a REAL, and the cast reads
 * that double back through the same 14 digits. Figures wider than that are
 * exact on MySQL and rounded in this suite, so the values below stay inside a
 * double's printed reach.
 */
class DecimalCastTest extends TestCase
{
    use RefreshDatabase;

    /** A balance at the widest a double prints in full, and past what a float literal states. */
    private const BALANCE = '1234567890.1234';

    /** A rate a hyperinflated currency reaches, and the smallest the six-decimal column holds. */
    private const RATE = '0.000001';

    public function test_a_revaluation_amount_reads_back_as_the_decimal_it_was_written_with(): void
    {
        $item = $this->revaluationItem(self::BALANCE);

        $this->assertSame(self::BALANCE, $item->fresh()->gain_loss_amount);
    }

    public function test_a_round_revaluation_amount_still_carries_the_decimals_its_column_holds(): void
    {
        $item = $this->revaluationItem('5000');

        $this->assertSame('5000.0000', $item->fresh()->gain_loss_amount);
    }

    public function test_an_exchange_rate_below_a_millionth_is_a_figure_bcmath_can_multiply(): void
    {
        $payment = AdvancePayment::factory()->create(['exchange_rate' => self::RATE]);

        $this->assertSame(self::RATE, $payment->fresh()->exchange_rate);
        $this->assertSame('0.000100', bcmul((string) $payment->fresh()->exchange_rate, '100', 6));
    }

    private function revaluationItem(string $gainLoss): CurrencyRevaluationItem
    {
        return CurrencyRevaluationItem::factory()->create([
            'revaluation_id' => CurrencyRevaluation::factory()->state(['created_by' => User::factory()]),
            'account_id' => Account::factory()->state(['currency_code' => null]),
            'gain_loss_amount' => $gainLoss,
        ]);
    }
}
