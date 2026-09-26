<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsPostings;
use Tests\Traits\TestHelpers;

/**
 * The payables ageing over bills the test writes: the bucket each due date
 * falls in, which bills are owed, and the total the summary adds up to.
 */
class PayableAgingReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    private const TODAY = '2026-06-15';

    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY.' 09:30:00');

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.reports.view']);

        $this->supplier = Contact::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_due_date_falls_in_the_bucket_its_age_puts_it_in(): void
    {
        $this->bill('2026-06-20', '1.0000');    // not yet due
        $this->bill('2026-06-15', '2.0000');    // due today
        $this->bill('2026-06-14', '4.0000');    // 1 day
        $this->bill('2026-05-16', '8.0000');    // 30 days
        $this->bill('2026-05-15', '16.0000');   // 31 days
        $this->bill('2026-04-16', '32.0000');   // 60 days
        $this->bill('2026-04-15', '64.0000');   // 61 days
        $this->bill('2026-03-17', '128.0000');  // 90 days
        $this->bill('2026-03-16', '256.0000');  // 91 days

        $summary = $this->payableAging()['summary'];

        $this->assertSame('3.0000', $this->money($summary['current']));
        $this->assertSame('12.0000', $this->money($summary['1_30_days']));
        $this->assertSame('48.0000', $this->money($summary['31_60_days']));
        $this->assertSame('192.0000', $this->money($summary['61_90_days']));
        $this->assertSame('256.0000', $this->money($summary['over_90_days']));
        $this->assertSame('511.0000', $this->money($summary['total']));
    }

    public function test_only_an_approved_and_unsettled_bill_is_owed(): void
    {
        $this->bill('2026-05-15', '10.0000', Bill::STATUS_APPROVED);
        $this->bill('2026-05-15', '20.0000', Bill::STATUS_PARTIAL);
        $this->bill('2026-05-15', '40.0000', Bill::STATUS_OVERDUE);

        // A bill still in draft or awaiting approval is not yet a commitment,
        // and a voided one never was.
        $this->bill('2026-05-15', '100.0000', Bill::STATUS_DRAFT);
        $this->bill('2026-05-15', '200.0000', Bill::STATUS_PENDING);
        $this->bill('2026-05-15', '400.0000', Bill::STATUS_VOIDED);
        $this->bill('2026-05-15', '800.0000', Bill::STATUS_PAID);
        $this->bill('2026-05-15', '1600.0000', Bill::STATUS_APPROVED, ['amount_due' => '0.0000']);

        $this->assertSame('70.0000', $this->money($this->payableAging()['summary']['total']));
    }

    public function test_another_organizations_bills_are_not_owed_here(): void
    {
        $other = Organization::factory()->create();

        $this->bill('2026-05-15', '25.0000');
        Bill::factory()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->create(['organization_id' => $other->id])->id,
            'bill_date' => '2026-04-15',
            'due_date' => '2026-05-15',
            'status' => Bill::STATUS_APPROVED,
            'total' => '9000.0000',
            'amount_due' => '9000.0000',
        ]);

        $report = $this->payableAging();

        $this->assertSame('25.0000', $this->money($report['summary']['total']));
        $this->assertCount(1, $report['details']);
    }

    public function test_nothing_owed_reports_zeroes_rather_than_nulls(): void
    {
        $report = $this->payableAging();

        $this->assertSame([], $report['details']);
        foreach (['current', '1_30_days', '31_60_days', '61_90_days', 'over_90_days', 'total'] as $bucket) {
            $this->assertSame('0.0000', $this->money($report['summary'][$bucket]), $bucket);
        }
    }

    public function test_a_bill_in_a_foreign_currency_is_aged_in_the_base_currency(): void
    {
        $this->bill('2026-05-15', '100.0000', Bill::STATUS_APPROVED, [
            'currency_code' => 'USD',
            'exchange_rate' => '3.75000000',
        ]);
        $this->bill('2026-05-15', '40.0000');

        $this->assertSame('415.0000', $this->money($this->payableAging()['summary']['total']));
    }

    private function bill(
        string $dueDate,
        string $amount,
        string $status = Bill::STATUS_APPROVED,
        array $attributes = []
    ): Bill {
        return Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'bill_date' => '2026-01-05',
            'due_date' => $dueDate,
            'status' => $status,
            'currency_code' => 'SAR',
            'exchange_rate' => '1.00000000',
            'subtotal' => $amount,
            'tax_amount' => '0.0000',
            'total' => $amount,
            'base_total' => $amount,
            'amount_paid' => '0.0000',
            'amount_due' => $amount,
            ...$attributes,
        ]);
    }

    private function payableAging(): array
    {
        return $this->apiGet('/reports/financial/payable-aging')->assertOk()->json('data');
    }
}
