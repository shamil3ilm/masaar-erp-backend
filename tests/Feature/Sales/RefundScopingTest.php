<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Refund;
use App\Models\Sales\SalesReturn;
use App\Models\User;
use App\Services\Sales\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A refund refers only to the caller's own contacts, bank accounts and sales
 * returns, keeps its approval and posting fields for the server to set, shows
 * its contact without the tax number, and changes status against the locked row.
 */
class RefundScopingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.refunds.view',
            'sales.refunds.create',
            'sales.refunds.approve',
            'sales.refunds.process',
            'sales.refunds.cancel',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'tax_number' => '300000000000003',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_refund_is_recorded_as_pending(): void
    {
        $response = $this->apiPost('/sales/refunds', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.status', Refund::STATUS_PENDING)
            ->assertJsonPath('data.created_by', $this->user->id)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_a_contact_of_another_organization_is_refused(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/refunds', $this->payload(['contact_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, Refund::withoutGlobalScopes()->count());
    }

    public function test_a_bank_account_of_another_organization_is_refused(): void
    {
        $foreign = BankAccount::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/refunds', $this->payload(['bank_account_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, Refund::withoutGlobalScopes()->count());
    }

    public function test_a_sales_return_of_another_organization_is_refused(): void
    {
        $foreign = SalesReturn::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $this->apiPost('/sales/refunds', $this->payload(['sales_return_id' => $foreign->id]))
            ->assertStatus(422);

        $this->assertSame(0, Refund::withoutGlobalScopes()->count());
    }

    public function test_approval_processing_and_posting_fields_are_not_taken_from_the_request(): void
    {
        $journal = JournalEntry::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/refunds', $this->payload([
            'journal_entry_id' => $journal->id,
            'approved_by' => $this->user->id,
            'approved_at' => now()->toDateTimeString(),
            'processed_by' => $this->user->id,
            'processed_at' => now()->toDateTimeString(),
        ]))->assertStatus(201);

        $refund = Refund::sole();
        $this->assertNull($refund->journal_entry_id);
        $this->assertNull($refund->approved_by);
        $this->assertNull($refund->approved_at);
        $this->assertNull($refund->processed_by);
        $this->assertNull($refund->processed_at);
    }

    public function test_a_refund_of_another_organization_is_not_found(): void
    {
        $foreign = Refund::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'contact_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'status' => Refund::STATUS_PENDING,
            'created_by' => User::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $this->apiGet("/sales/refunds/{$foreign->id}")->assertNotFound();
        $this->apiPost("/sales/refunds/{$foreign->id}/approve")->assertNotFound();
        $this->apiPost("/sales/refunds/{$foreign->id}/process")->assertNotFound();
        $this->apiPost("/sales/refunds/{$foreign->id}/cancel")->assertNotFound();

        $this->assertSame(Refund::STATUS_PENDING, Refund::withoutGlobalScopes()->find($foreign->id)->status);
    }

    public function test_the_list_and_the_refund_show_the_contact_without_its_tax_number(): void
    {
        $refund = $this->refund(Refund::STATUS_PENDING);

        $shown = $this->apiGet("/sales/refunds/{$refund->id}")->assertOk();
        $this->assertSame($this->customer->contact_name, $shown->json('data.contact.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.contact'));

        $listed = $this->apiGet('/sales/refunds')->assertOk();
        $this->assertSame($this->customer->company_name, $listed->json('data.0.contact.company_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.contact'));
    }

    public function test_a_refund_processed_meanwhile_is_not_cancelled_through_a_stale_copy(): void
    {
        $refund = $this->refund(Refund::STATUS_APPROVED);
        $stale = Refund::findOrFail($refund->id);
        $service = app(RefundService::class);

        $service->process($refund, $this->user->id);

        try {
            $service->cancel($stale);
            $this->fail('A processed refund must not be cancelled.');
        } catch (ApiException) {
        }

        $this->assertSame(Refund::STATUS_PROCESSED, $refund->fresh()->status);
    }

    public function test_a_refund_cancelled_meanwhile_is_not_approved_through_a_stale_copy(): void
    {
        $refund = $this->refund(Refund::STATUS_PENDING);
        $stale = Refund::findOrFail($refund->id);
        $service = app(RefundService::class);

        $service->cancel($refund);

        try {
            $service->approve($stale, $this->user->id);
            $this->fail('A cancelled refund must not be approved.');
        } catch (ApiException) {
        }

        $this->assertSame(Refund::STATUS_CANCELLED, $refund->fresh()->status);
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'refund_number' => 'RFD-000001',
            'refund_type' => 'customer_refund',
            'refundable_type' => Invoice::class,
            'refundable_id' => 1,
            'contact_id' => $this->customer->id,
            'amount' => 50,
            'currency_code' => 'SAR',
            'refund_method' => 'cash',
            'refund_date' => now()->toDateString(),
        ], $overrides);
    }

    private function refund(string $status): Refund
    {
        return Refund::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'refund_method' => Refund::METHOD_CASH,
            'currency_code' => 'SAR',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }
}
