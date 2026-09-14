<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Core\Organization;
use App\Models\Sales\AdvancePayment;
use App\Models\Sales\AdvancePaymentApplication;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Services\Sales\CustomerAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * An advance refers only to the caller's own contacts, bank accounts and
 * invoices, and only a draft advance is deleted, checked on the locked row.
 */
class CustomerAdvanceScopingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.customer-advances.view',
            'sales.customer-advances.create',
            'sales.customer-advances.apply',
            'sales.customer-advances.delete',
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
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_contact_of_another_organization_is_refused(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/customer-advances', $this->payload(['contact_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, AdvancePayment::count());
    }

    public function test_a_bank_account_of_another_organization_is_refused(): void
    {
        $foreign = BankAccount::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/customer-advances', $this->payload(['bank_account_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, AdvancePayment::withoutGlobalScopes()->count());
    }

    public function test_applying_to_an_invoice_of_another_organization_is_refused(): void
    {
        $advance = $this->advance(AdvancePayment::STATUS_RECEIVED);
        $foreign = Invoice::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        $this->apiPost("/sales/customer-advances/{$advance->uuid}/apply", [
            'invoice_id' => $foreign->id,
            'amount' => 10,
        ])->assertStatus(422);

        $this->assertSame(0, AdvancePaymentApplication::count());
    }

    public function test_a_draft_advance_is_deleted_and_a_received_one_is_not(): void
    {
        $draft = $this->advance(AdvancePayment::STATUS_DRAFT);
        $received = $this->advance(AdvancePayment::STATUS_RECEIVED);

        $this->apiDelete("/sales/customer-advances/{$draft->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Advance payment deleted.');
        $this->assertSoftDeleted('advance_payments', ['id' => $draft->id]);

        $this->apiDelete("/sales/customer-advances/{$received->uuid}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Only draft advances can be deleted.');
        $this->assertNotNull(AdvancePayment::find($received->id));
    }

    public function test_an_advance_received_meanwhile_is_not_deleted_through_a_stale_draft(): void
    {
        $advance = $this->advance(AdvancePayment::STATUS_DRAFT);
        $stale = AdvancePayment::findOrFail($advance->id);

        AdvancePayment::whereKey($advance->id)->update(['status' => AdvancePayment::STATUS_RECEIVED]);

        try {
            app(CustomerAdvanceService::class)->delete($stale);
            $this->fail('A received advance must not be deleted.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertNotNull(AdvancePayment::find($advance->id));
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'contact_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'currency_code' => 'SAR',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function advance(string $status): AdvancePayment
    {
        return AdvancePayment::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'status' => $status,
            'amount' => 100,
            'available_amount' => 100,
            'received_by' => $this->user->id,
        ]);
    }
}
