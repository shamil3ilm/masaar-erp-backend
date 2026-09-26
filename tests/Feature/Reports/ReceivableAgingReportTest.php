<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsPostings;
use Tests\Traits\TestHelpers;

/**
 * The receivables ageing over invoices the test writes: which bucket each due
 * date falls in, the bucket boundaries either side, the invoices that count,
 * and the total the summary adds up to.
 */
class ReceivableAgingReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    private const TODAY = '2026-06-15';

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::TODAY.' 09:30:00');

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['accounting.reports.view']);

        $this->customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_a_due_date_falls_in_the_bucket_its_age_puts_it_in(): void
    {
        // The day either side of every boundary, so a bucket cannot quietly
        // take one day too many or one too few.
        $this->invoice('2026-06-20', '1.0000');    // not yet due
        $this->invoice('2026-06-15', '2.0000');    // due today
        $this->invoice('2026-06-14', '4.0000');    // 1 day
        $this->invoice('2026-05-16', '8.0000');    // 30 days
        $this->invoice('2026-05-15', '16.0000');   // 31 days
        $this->invoice('2026-04-16', '32.0000');   // 60 days
        $this->invoice('2026-04-15', '64.0000');   // 61 days
        $this->invoice('2026-03-17', '128.0000');  // 90 days
        $this->invoice('2026-03-16', '256.0000');  // 91 days

        $summary = $this->receivableAging()['summary'];

        $this->assertSame('3.0000', $this->money($summary['current']));
        $this->assertSame('12.0000', $this->money($summary['1_30_days']));
        $this->assertSame('48.0000', $this->money($summary['31_60_days']));
        $this->assertSame('192.0000', $this->money($summary['61_90_days']));
        $this->assertSame('256.0000', $this->money($summary['over_90_days']));
        $this->assertSame('511.0000', $this->money($summary['total']));
    }

    public function test_a_detail_row_is_filed_in_the_same_bucket_as_the_summary(): void
    {
        $this->invoice('2026-05-15', '16.0000');
        $this->invoice('2026-06-20', '1.0000');

        $report = $this->receivableAging();
        $rows = collect($report['details'])->keyBy('aging_bucket');

        $this->assertSame(31, $rows['31_60']['days_overdue']);
        $this->assertSame(0, $rows['current']['days_overdue']);
        $this->assertSame('16.0000', $this->money($report['summary']['31_60_days']));
        $this->assertSame('1.0000', $this->money($report['summary']['current']));
    }

    public function test_only_an_unsettled_invoice_is_chased(): void
    {
        $this->invoice('2026-05-15', '10.0000', Invoice::STATUS_SENT);
        $this->invoice('2026-05-15', '20.0000', Invoice::STATUS_PARTIAL);
        $this->invoice('2026-05-15', '40.0000', Invoice::STATUS_OVERDUE);

        // A draft is not a claim on anyone, and a voided invoice never was.
        $this->invoice('2026-05-15', '100.0000', Invoice::STATUS_DRAFT);
        $this->invoice('2026-05-15', '200.0000', Invoice::STATUS_VOIDED);
        $this->invoice('2026-05-15', '400.0000', Invoice::STATUS_PAID);
        // Settled in full, so nothing is outstanding whatever the status says.
        $this->invoice('2026-05-15', '800.0000', Invoice::STATUS_SENT, ['amount_due' => '0.0000']);

        $this->assertSame('70.0000', $this->money($this->receivableAging()['summary']['total']));
    }

    public function test_another_organizations_invoices_are_not_chased_here(): void
    {
        $other = Organization::factory()->create();
        $theirCustomer = Contact::factory()->create(['organization_id' => $other->id]);

        $this->invoice('2026-05-15', '25.0000');
        Invoice::factory()->create([
            'organization_id' => $other->id,
            'customer_id' => $theirCustomer->id,
            'invoice_date' => '2026-04-15',
            'due_date' => '2026-05-15',
            'status' => Invoice::STATUS_SENT,
            'total' => '9000.0000',
            'amount_due' => '9000.0000',
        ]);

        $report = $this->receivableAging();

        $this->assertSame('25.0000', $this->money($report['summary']['total']));
        $this->assertCount(1, $report['details']);
    }

    public function test_nothing_outstanding_reports_zeroes_rather_than_nulls(): void
    {
        $report = $this->receivableAging();

        $this->assertSame([], $report['details']);
        foreach (['current', '1_30_days', '31_60_days', '61_90_days', 'over_90_days', 'total'] as $bucket) {
            $this->assertSame('0.0000', $this->money($report['summary'][$bucket]), $bucket);
        }
    }

    public function test_an_invoice_in_a_foreign_currency_is_aged_in_the_base_currency(): void
    {
        // 100 USD at 3.75 owes 375 SAR. A summary that adds the document
        // figures together reports a total in no currency at all, and one the
        // receivable balance on the balance sheet cannot be reconciled to.
        $this->invoice('2026-05-15', '100.0000', Invoice::STATUS_SENT, [
            'currency_code' => 'USD',
            'exchange_rate' => '3.75000000',
        ]);
        $this->invoice('2026-05-15', '40.0000');

        $report = $this->receivableAging();

        $this->assertSame('415.0000', $this->money($report['summary']['31_60_days']));
        $this->assertSame('415.0000', $this->money($report['summary']['total']));
    }

    private function invoice(
        string $dueDate,
        string $amount,
        string $status = Invoice::STATUS_SENT,
        array $attributes = []
    ): Invoice {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-01-05',
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

    private function receivableAging(): array
    {
        return $this->apiGet('/reports/financial/receivable-aging')->assertOk()->json('data');
    }
}
