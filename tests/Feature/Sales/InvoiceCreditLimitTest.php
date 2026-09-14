<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Accounting\CreditLimit;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Creating an invoice checks the customer's credit limit against the invoice
 * total, tax included, which is the amount the customer will owe and the
 * amount the check at posting uses.
 */
class InvoiceCreditLimitTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        CreditLimit::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'credit_limit' => 1100,
            'currency_code' => 'SAR',
            'valid_from' => now()->subDay()->toDateString(),
            'payment_terms_days' => 30,
            'risk_class' => CreditLimit::RISK_LOW,
        ]);
    }

    public function test_tax_counts_towards_the_credit_limit(): void
    {
        try {
            $this->createInvoice(unitPrice: '1000', taxRate: '15');
            $this->fail('An invoice of 1150 was accepted against a credit limit of 1100.');
        } catch (ApiException $e) {
            $this->assertSame(ErrorCodes::SALES_INSUFFICIENT_CREDIT['code'], $e->getErrorCode());
        }

        $this->assertSame(0, Invoice::count());
    }

    public function test_an_invoice_within_the_limit_with_its_tax_is_created(): void
    {
        $invoice = $this->createInvoice(unitPrice: '900', taxRate: '15');

        $this->assertSame('1035.0000', $invoice->total);
    }

    private function createInvoice(string $unitPrice, string $taxRate): Invoice
    {
        return app(InvoiceService::class)->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'invoice_date' => now()->toDateString(),
        ], [
            ['description' => 'Consulting', 'quantity' => '1', 'unit_price' => $unitPrice, 'tax_rate' => $taxRate],
        ]);
    }
}
