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
 * Sales by customer over invoices the test writes: the figure per customer,
 * the period boundaries, the invoice statuses that count, and what the
 * summary is a summary of.
 */
class SalesByCustomerReportTest extends TestCase
{
    use BuildsPostings, RefreshDatabase, TestHelpers;

    private Contact $acme;

    private Contact $globex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.reports.view']);

        $this->acme = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name' => 'Acme',
        ]);
        $this->globex = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name' => 'Globex',
        ]);
    }

    public function test_each_customer_carries_what_they_were_billed_and_what_is_still_owed(): void
    {
        $this->sell($this->acme, '2026-03-05', '1000.0000', '400.0000');
        $this->sell($this->acme, '2026-03-20', '500.0000', '500.0000');
        $this->sell($this->globex, '2026-03-10', '300.0000', '0.0000');

        $report = $this->salesByCustomer('2026-03-01', '2026-03-31');
        $customers = collect($report['customers'])->keyBy('customer_name');

        $this->assertSame(1, $customers['Acme']['rank']);
        $this->assertSame(2, $customers['Acme']['invoice_count']);
        $this->assertSame('1500.0000', $this->money($customers['Acme']['total']));
        $this->assertSame('900.0000', $this->money($customers['Acme']['paid']));
        $this->assertSame('600.0000', $this->money($customers['Acme']['outstanding']));
        $this->assertSame('750.0000', $this->money($customers['Acme']['average_invoice']));

        $this->assertSame('300.0000', $this->money($customers['Globex']['total']));

        $this->assertSame('1800.0000', $this->money($report['summary']['total_sales']));
        $this->assertSame('900.0000', $this->money($report['summary']['total_paid']));
        $this->assertSame('900.0000', $this->money($report['summary']['total_outstanding']));
        $this->assertSame('50.0000', $this->money($report['summary']['collection_rate']));
    }

    public function test_the_period_holds_its_first_and_last_day_and_nothing_either_side(): void
    {
        $this->sell($this->acme, '2026-02-28', '1.0000', '0.0000');
        $this->sell($this->acme, '2026-03-01', '100.0000', '0.0000');
        $this->sell($this->acme, '2026-03-31', '20.0000', '0.0000');
        $this->sell($this->acme, '2026-04-01', '7.0000', '0.0000');

        $this->assertSame('120.0000', $this->money($this->salesByCustomer('2026-03-01', '2026-03-31')['summary']['total_sales']));
    }

    public function test_only_an_issued_invoice_counts_as_a_sale(): void
    {
        $this->sell($this->acme, '2026-03-05', '10.0000', '0.0000', Invoice::STATUS_SENT);
        $this->sell($this->acme, '2026-03-06', '20.0000', '0.0000', Invoice::STATUS_PARTIAL);
        $this->sell($this->acme, '2026-03-07', '40.0000', '0.0000', Invoice::STATUS_PAID);

        $this->sell($this->acme, '2026-03-08', '800.0000', '0.0000', Invoice::STATUS_DRAFT);
        $this->sell($this->acme, '2026-03-09', '1600.0000', '0.0000', Invoice::STATUS_VOIDED);
        // As in sales by product, an overdue invoice is left out although it
        // was issued. Pinned rather than judged.
        $this->sell($this->acme, '2026-03-10', '3200.0000', '0.0000', Invoice::STATUS_OVERDUE);

        $this->assertSame('70.0000', $this->money($this->salesByCustomer('2026-03-01', '2026-03-31')['summary']['total_sales']));
    }

    public function test_another_organizations_customers_are_not_in_the_ranking(): void
    {
        $other = Organization::factory()->create();
        $theirCustomer = Contact::factory()->create([
            'organization_id' => $other->id,
            'company_name' => 'Initech',
        ]);

        $this->sell($this->acme, '2026-03-05', '25.0000', '0.0000');
        Invoice::factory()->create([
            'organization_id' => $other->id,
            'customer_id' => $theirCustomer->id,
            'invoice_date' => '2026-03-05',
            'due_date' => '2026-04-05',
            'status' => Invoice::STATUS_SENT,
            'total' => '9000.0000',
            'amount_due' => '9000.0000',
        ]);

        $report = $this->salesByCustomer('2026-03-01', '2026-03-31');

        $this->assertSame(['Acme'], array_column($report['customers'], 'customer_name'));
        $this->assertSame('25.0000', $this->money($report['summary']['total_sales']));
    }

    public function test_a_period_with_no_sales_reports_zeroes_rather_than_nulls(): void
    {
        $report = $this->salesByCustomer('2026-03-01', '2026-03-31');

        $this->assertSame([], $report['customers']);
        $this->assertSame(0, $report['summary']['customer_count']);
        $this->assertSame(0, $report['summary']['total_invoices']);
        $this->assertSame('0.0000', $this->money($report['summary']['total_sales']));
        $this->assertSame('0.0000', $this->money($report['summary']['collection_rate']));
        $this->assertSame('0.0000', $this->money($report['summary']['average_per_customer']));
    }

    public function test_the_summary_totals_only_the_customers_the_ranking_shows(): void
    {
        // The ranking is capped by 'limit', and the summary adds up the rows
        // it holds rather than the period. Whether 'total_sales' is meant to
        // be the period's sales or the listed customers' is not settled here:
        // this pins that it is the listed customers'.
        $this->sell($this->acme, '2026-03-05', '1000.0000', '0.0000');
        $this->sell($this->globex, '2026-03-05', '10.0000', '0.0000');

        $report = $this->salesByCustomer('2026-03-01', '2026-03-31', '&limit=1');

        $this->assertCount(1, $report['customers']);
        $this->assertSame('1000.0000', $this->money($report['summary']['total_sales']));
    }

    private function sell(
        Contact $customer,
        string $date,
        string $total,
        string $paid,
        string $status = Invoice::STATUS_SENT
    ): void {
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->company_name,
            'invoice_date' => $date,
            'due_date' => $date,
            'status' => $status,
            'currency_code' => 'SAR',
            'exchange_rate' => '1.00000000',
            'subtotal' => $total,
            'tax_amount' => '0.0000',
            'total' => $total,
            'base_total' => $total,
            'amount_paid' => $paid,
            'amount_due' => bcsub($total, $paid, 4),
        ]);
    }

    private function salesByCustomer(string $start, string $end, string $extra = ''): array
    {
        return $this->apiGet("/reports/sales/by-customer?start_date={$start}&end_date={$end}{$extra}")
            ->assertOk()
            ->json('data');
    }
}
