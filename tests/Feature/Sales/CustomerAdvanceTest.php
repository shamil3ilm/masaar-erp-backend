<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Sales\AdvancePayment;
use App\Models\Sales\AdvancePaymentApplication;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A customer advance is recorded, then applied against an invoice.
 */
class CustomerAdvanceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.customer-advances.view',
            'sales.customer-advances.create',
            'sales.customer-advances.apply',
        ]);
        $this->setUpOpenFiscalPeriod();

        foreach ([
            ['1020', 'Cash at Bank', Account::TYPE_ASSET, 'bank'],
            ['1200', 'Accounts Receivable', Account::TYPE_ASSET, 'receivable'],
            ['2100', 'Customer Advances', Account::TYPE_LIABILITY, 'other_liability'],
        ] as [$code, $name, $type, $subType]) {
            Account::factory()->create([
                'organization_id' => $this->organization->id,
                'account_type' => $type,
                'sub_type' => $subType,
                'code' => $code,
                'name' => $name,
                'is_system' => true,
                'currency_code' => 'SAR',
            ]);
        }

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => 'customer',
        ]);
    }

    public function test_an_advance_is_recorded_and_applied_to_an_invoice(): void
    {
        $advance = $this->receive(300);

        $this->assertSame(AdvancePayment::TYPE_CUSTOMER, $advance->payment_type);
        $this->assertSame($this->customer->getDisplayName(), $advance->contact_name);
        $this->assertEquals(300, $advance->base_amount);

        $invoice = $this->invoice(1000);

        $this->apiPost("/sales/customer-advances/{$advance->uuid}/apply", [
            'invoice_id' => $invoice->id,
            'amount' => 200,
        ])->assertStatus(201);

        $application = AdvancePaymentApplication::sole();
        $this->assertSame($invoice->getMorphClass(), $application->applied_to_type);
        $this->assertSame($invoice->id, $application->applied_to_id);
        $this->assertSame($this->user->id, $application->applied_by);
        $this->assertEquals(200, $application->applied_amount);

        $advance->refresh();
        $this->assertEquals(100, $advance->available_amount);
        $this->assertSame(AdvancePayment::STATUS_PARTIALLY_APPLIED, $advance->status);
        $this->assertEquals(800, $invoice->fresh()->amount_due);

        $this->apiGet("/sales/customer-advances/{$advance->uuid}")
            ->assertOk()
            ->assertJsonPath('data.applications.0.applied_to.id', $invoice->id);
    }

    public function test_more_than_the_invoice_owes_is_refused(): void
    {
        $advance = $this->receive(2000);
        $invoice = $this->invoice(1000);

        $response = $this->apiPost("/sales/customer-advances/{$advance->uuid}/apply", [
            'invoice_id' => $invoice->id,
            'amount' => 1500,
        ]);

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertLessThan(500, $response->status());
        $this->assertSame(0, AdvancePaymentApplication::count());
        $this->assertEquals(2000, $advance->fresh()->available_amount);
    }

    private function receive(float $amount): AdvancePayment
    {
        $this->apiPost('/sales/customer-advances', [
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'currency_code' => 'SAR',
            'payment_method' => 'cash',
        ])->assertStatus(201);

        return AdvancePayment::latest('id')->firstOrFail();
    }

    private function invoice(float $total): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'total' => $total,
            'amount_paid' => 0,
            'amount_due' => $total,
        ]);
    }
}
