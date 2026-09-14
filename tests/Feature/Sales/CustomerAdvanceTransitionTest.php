<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\AdvancePayment;
use App\Models\Sales\AdvancePaymentApplication;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Services\Sales\CustomerAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Applying and refunding a customer advance read the advance and the invoice
 * under their locks, so a balance is never spent twice and an invoice keeps
 * a payment recorded on it meanwhile.
 */
class CustomerAdvanceTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private CustomerAdvanceService $service;
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.customer-advances.view', 'sales.customer-advances.apply']);
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
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $this->service = app(CustomerAdvanceService::class);
    }

    public function test_a_stale_advance_is_not_applied_beyond_its_balance(): void
    {
        $advance = $this->advance(300);
        $invoice = $this->invoice(1000);
        $staleAdvance = AdvancePayment::findOrFail($advance->id);
        $staleInvoice = Invoice::findOrFail($invoice->id);

        $this->service->applyToInvoice($advance, $invoice, 200, $this->user->id);

        $this->assertRejected(fn () => $this->service->applyToInvoice($staleAdvance, $staleInvoice, 200, $this->user->id));

        $fresh = $advance->fresh();
        $this->assertSame(0, bccomp((string) $fresh->available_amount, '100', 4));
        $this->assertSame(1, AdvancePaymentApplication::where('advance_payment_id', $advance->id)->count());
        $this->assertSame(0, bccomp((string) $invoice->fresh()->amount_paid, '200', 4));
    }

    public function test_applying_keeps_a_payment_recorded_on_the_invoice_meanwhile(): void
    {
        $advance = $this->advance(300);
        $invoice = $this->invoice(1000);
        $stale = Invoice::findOrFail($invoice->id);

        Invoice::findOrFail($invoice->id)->recordPayment(400);
        $this->service->applyToInvoice($advance, $stale, 200, $this->user->id);

        $fresh = $invoice->fresh();
        $this->assertSame(0, bccomp((string) $fresh->amount_paid, '600', 4), "amount_paid is {$fresh->amount_paid}");
        $this->assertSame(0, bccomp((string) $fresh->amount_due, '400', 4), "amount_due is {$fresh->amount_due}");
        $this->assertSame(Invoice::STATUS_PARTIAL, $fresh->status);
    }

    public function test_refund_returns_only_the_balance_left_after_a_concurrent_application(): void
    {
        $advance = $this->advance(300);
        $stale = AdvancePayment::findOrFail($advance->id);

        $this->service->applyToInvoice($advance, $this->invoice(1000), 200, $this->user->id);
        $this->service->refund($stale);

        $refund = JournalEntry::where('description', 'like', 'Advance refund%')->sole();
        $this->assertSame(0, bccomp((string) $refund->lines()->sum('debit'), '100', 4));
        $this->assertSame(AdvancePayment::STATUS_REFUNDED, $advance->fresh()->status);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (ApiException) {
            return;
        }

        $this->fail('The transition was expected to be rejected.');
    }

    private function advance(float $amount): AdvancePayment
    {
        return AdvancePayment::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'payment_date' => now(),
            'currency_code' => 'SAR',
            'amount' => $amount,
            'base_amount' => $amount,
            'applied_amount' => 0,
            'available_amount' => $amount,
            'status' => AdvancePayment::STATUS_RECEIVED,
            'received_by' => $this->user->id,
        ]);
    }

    private function invoice(float $total): Invoice
    {
        return Invoice::factory()->sent()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'total' => $total,
            'amount_due' => $total,
            'currency_code' => 'SAR',
            'due_date' => now()->addDays(30),
        ]);
    }
}
