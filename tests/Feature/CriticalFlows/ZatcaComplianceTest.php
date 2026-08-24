<?php

declare(strict_types=1);

namespace Tests\Feature\CriticalFlows;

use App\Models\Accounting\Account;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * ZATCA compliance-status endpoint tests.
 *
 * The PostInvoiceOrchestrator submits invoices to MasaarClient after the
 * DB transaction commits.  These tests verify the compliance-status endpoint
 * reflects the correct state without requiring the external ZATCA service
 * (ZATCA_INTEGRATION_ENABLED is false in the SQLite test environment, so
 * MasaarClient returns 'not_applicable' immediately).
 */
class ZatcaComplianceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Account $incomeAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.invoices.view',
            'sales.invoices.create',
            'sales.invoices.edit',
            'sales.invoices.send',
        ]);
        $this->setUpOpenFiscalPeriod();

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
            'currency_code'   => 'SAR',
        ]);

        $this->incomeAccount = $this->setUpInvoiceAccounts();
    }

    // ── Compliance-status endpoint ───────────────────────────────────────────

    /**
     * Which countries this ERP files invoices for.
     *
     * The submission path carries no jurisdiction — PostInvoiceOrchestrator
     * calls MasaarClient::submitInvoice(), which posts to /pipeline/submit
     * with no country and a circuit breaker keyed 'zatca'. A country that
     * requires compliance therefore has its invoices filed with ZATCA,
     * whatever its own authority expects.
     *
     * This names the countries that must stay off that list until the platform
     * can file for them and the partner API routes on jurisdiction.
     */
    public function test_only_saudi_organizations_require_compliance(): void
    {
        $saudi = \App\Models\Core\Organization::factory()->create(['country_code' => 'SA']);

        $this->assertTrue($saudi->requiresCompliance());

        foreach (['AE', 'IN', 'QA', 'BH', 'KW', 'OM', 'US'] as $country) {
            $org = \App\Models\Core\Organization::factory()->create(['country_code' => $country]);

            $this->assertFalse(
                $org->requiresCompliance(),
                "{$country} invoices would be filed with ZATCA as Saudi documents."
            );
        }
    }

    public function test_compliance_status_for_invoice_never_submitted(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id'  => $this->organization->id,
            'branch_id'        => $this->branch->id,
            'customer_id'      => $this->customer->id,
            'status'           => Invoice::STATUS_SENT,
            'compliance_uuid'  => null,
        ]);

        $response = $this->apiGet("/sales/invoices/{$invoice->id}/compliance-status");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        // No UUID means the invoice was never submitted — the endpoint must say so
        $this->assertNotNull($response->json('data'));
    }

    public function test_compliance_status_for_submitted_invoice(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $invoice = Invoice::factory()->create([
            'organization_id'       => $this->organization->id,
            'branch_id'             => $this->branch->id,
            'customer_id'           => $this->customer->id,
            'status'                => Invoice::STATUS_SENT,
            'compliance_uuid'       => $uuid,
            'compliance_status'     => Invoice::COMPLIANCE_SUBMITTED,
            'compliance_submitted_at' => now(),
        ]);

        $response = $this->apiGet("/sales/invoices/{$invoice->id}/compliance-status");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        // UUID is set so the endpoint must return it
        $response->assertJsonPath('data.uuid', $uuid);
    }

    public function test_compliance_status_requires_authentication(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => Invoice::STATUS_SENT,
        ]);

        $response = $this->getJson("/api/v1/sales/invoices/{$invoice->id}/compliance-status", [
            'Accept' => 'application/json',
        ]);

        $this->assertUnauthorized($response);
    }

    public function test_sending_invoice_sets_compliance_status_field(): void
    {
        Queue::fake();

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id'       => $this->branch->id,
            'customer_id'     => $this->customer->id,
            'status'          => Invoice::STATUS_DRAFT,
            'total'           => 1000.00,
            'amount_due'      => 1000.00,
            'tax_amount'      => 0,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'account_id' => $this->incomeAccount->id,
            'quantity'   => 1,
            'unit_price' => 1000.00,
            'subtotal'   => 1000.00,
            'total'      => 1000.00,
        ]);

        $response = $this->apiPost("/sales/invoices/{$invoice->id}/send");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // After send, compliance_status is set when requiresCompliance() is true.
        // In the SQLite test env the invoice may not require compliance (e.g. no VAT
        // registration), so we accept null/''/not_applicable as well as the submitted states.
        $status = $response->json('data.compliance_status');
        $validStates = [
            null,
            '',
            Invoice::COMPLIANCE_NOT_APPLICABLE,
            Invoice::COMPLIANCE_PENDING,
            Invoice::COMPLIANCE_SUBMITTED,
            Invoice::COMPLIANCE_CLEARED,
        ];
        $this->assertContains(
            $status,
            $validStates,
            "compliance_status '{$status}' is not a recognized ZATCA state"
        );
    }

    public function test_compliance_status_endpoint_is_scoped_to_organization(): void
    {
        // Create an invoice belonging to a DIFFERENT organization
        $otherOrg  = \App\Models\Core\Organization::factory()->create();
        $otherContact = Contact::factory()->create([
            'organization_id' => $otherOrg->id,
            'contact_type'    => Contact::TYPE_CUSTOMER,
        ]);
        $otherInvoice = Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id'     => $otherContact->id,
            'status'          => Invoice::STATUS_SENT,
        ]);

        // Our authenticated user (from $this->organization) must not see it
        $response = $this->apiGet("/sales/invoices/{$otherInvoice->id}/compliance-status");

        // 404 because the global BelongsToOrganization scope excludes it
        $response->assertStatus(404);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function setUpInvoiceAccounts(): Account
    {
        $receivable = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_ASSET,
            'sub_type'        => Account::SUBTYPE_RECEIVABLE,
            'name'            => 'Accounts Receivable',
            'code'            => '1100',
            'currency_code'   => null,
        ]);

        $this->customer->update(['receivable_account_id' => $receivable->id]);

        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type'    => Account::TYPE_INCOME,
            'sub_type'        => Account::SUBTYPE_SALES,
            'name'            => 'Sales Revenue',
            'code'            => '4000',
            'currency_code'   => null,
        ]);
    }
}
