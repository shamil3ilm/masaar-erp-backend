<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the invoice list, its AG Grid form and the summary figures.
 */
class InvoiceListingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.invoices.view']);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);
    }

    public function test_the_start_and_end_date_aliases_bound_the_list(): void
    {
        $january = $this->invoice(Invoice::STATUS_DRAFT, '2025-01-10', 100, 0);
        $lateJanuary = $this->invoice(Invoice::STATUS_SENT, '2025-01-20', 200, 50);
        $this->invoice(Invoice::STATUS_SENT, '2025-03-01', 400, 0);

        $response = $this->apiGet('/sales/invoices?start_date=2025-01-01&end_date=2025-01-31');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$january->id, $lateJanuary->id],
            array_column($response->json('data'), 'id')
        );
        $this->assertSame($lateJanuary->id, $response->json('data.0.id'), 'The newest invoice comes first.');
        $this->assertSame($this->customer->id, $response->json('data.0.customer.id'));
    }

    public function test_an_ag_grid_request_returns_the_row_count_and_one_page_of_rows(): void
    {
        $this->invoice(Invoice::STATUS_DRAFT, '2025-01-10', 100, 0);
        $this->invoice(Invoice::STATUS_SENT, '2025-01-20', 200, 50);
        $this->invoice(Invoice::STATUS_SENT, '2025-03-01', 400, 0);

        $response = $this->apiGet('/sales/invoices?startRow=0&endRow=2&status=sent');

        $response->assertOk();
        $this->assertSame(2, $response->json('rowCount'));
        $this->assertCount(2, $response->json('rows'));
    }

    public function test_the_summary_bounds_its_totals_by_date_but_not_its_status_breakdown(): void
    {
        $this->invoice(Invoice::STATUS_DRAFT, '2025-01-10', 100, 0);
        $this->invoice(Invoice::STATUS_SENT, '2025-01-20', 200, 50);
        $this->invoice(Invoice::STATUS_SENT, '2025-03-01', 400, 0);

        $response = $this->apiGet('/sales/invoices/summary?from_date=2025-01-01&to_date=2025-01-31');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total_invoices'));
        $this->assertEquals(300, $response->json('data.total_amount'));
        $this->assertEquals(50, $response->json('data.total_paid'));
        $this->assertEquals(250, $response->json('data.total_outstanding'));
        $this->assertEquals(2, $response->json('data.by_status.sent.count'));
        $this->assertEquals(600, $response->json('data.by_status.sent.total'));
        $this->assertEquals(1, $response->json('data.by_status.draft.count'));
        $this->assertArrayHasKey('overdue_count', $response->json('data'));
        $this->assertArrayHasKey('overdue_amount', $response->json('data'));
    }

    public function test_the_summary_leaves_out_other_organizations(): void
    {
        $this->invoice(Invoice::STATUS_SENT, '2025-01-20', 200, 0);

        $other = $this->createOtherOrganizationInvoice();

        $response = $this->apiGet('/sales/invoices/summary');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total_invoices'));
        $this->assertEquals(200, $response->json('data.total_amount'));
        $this->assertEquals(1, $response->json('data.by_status.sent.count'));
        $this->assertNotNull($other->id);
    }

    private function invoice(string $status, string $date, float $total, float $paid): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'invoice_date' => $date,
            'due_date' => '2099-01-01',
            'total' => $total,
            'amount_paid' => $paid,
            'amount_due' => $total - $paid,
        ]);
    }

    private function createOtherOrganizationInvoice(): Invoice
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();

        return Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $otherOrg->id])->id,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => '2025-01-20',
            'total' => 9000,
            'amount_due' => 9000,
        ]);
    }
}
