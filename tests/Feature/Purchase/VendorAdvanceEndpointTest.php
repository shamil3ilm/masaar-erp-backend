<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\VendorAdvanceClearing;
use App\Models\Purchase\VendorAdvancePayment;
use App\Models\Purchase\VendorAdvanceRequest;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Vendor advance endpoints: ids a request names must be the caller's
 * organization's, and a bill's supplier tax number leaves masked.
 */
class VendorAdvanceEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/vendor-advances';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.vendor-advances.view', 'purchase.vendor-advances.create',
            'purchase.vendor-advances.pay', 'purchase.vendor-advances.clear',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_index_lists_only_this_organizations_requests(): void
    {
        $this->request($this->organization, $this->supplier);
        $this->request($this->organization, $this->supplier);
        $this->foreignRequest();

        $this->apiGet($this->baseUrl)->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_store_refuses_another_organizations_supplier_and_order(): void
    {
        $other = Organization::factory()->create();
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => $other->id]);
        $foreignOrder = PurchaseOrder::factory()->confirmed()->create([
            'organization_id' => $other->id,
            'supplier_id' => $foreignSupplier->id,
        ]);

        $this->apiPost($this->baseUrl, [
            'contact_id' => $foreignSupplier->id,
            'purchase_order_id' => $foreignOrder->id,
            'requested_amount' => 100,
            'currency_code' => 'SAR',
        ])->assertStatus(422)->assertJsonValidationErrors(['contact_id', 'purchase_order_id']);

        $this->assertSame(0, VendorAdvanceRequest::withoutGlobalScopes()->count());
    }

    public function test_recording_a_payment_refuses_another_organizations_bank_account(): void
    {
        $request = $this->request($this->organization, $this->supplier, VendorAdvanceRequest::STATUS_APPROVED);
        $foreignAccount = Account::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("{$this->baseUrl}/{$request->id}/payment", [
            'amount' => 100,
            'payment_method' => 'bank_transfer',
            'bank_account_id' => $foreignAccount->id,
        ])->assertStatus(422)->assertJsonValidationErrors('bank_account_id');

        $this->assertSame(0, VendorAdvancePayment::count());
    }

    public function test_clearing_another_organizations_advance_payment_is_not_found(): void
    {
        $foreignPayment = $this->payment($this->foreignRequest(), 500);

        $this->apiPost("{$this->baseUrl}/clear", [
            'advance_payment_id' => $foreignPayment->id,
            'bill_id' => $this->bill()->id,
            'amount' => 10,
        ])->assertNotFound()->assertJsonPath('error.message', 'Advance payment not found.');

        $this->assertSame(0, VendorAdvanceClearing::count());
    }

    public function test_clearing_refuses_another_organizations_bill(): void
    {
        $payment = $this->payment($this->request($this->organization, $this->supplier), 500);
        $other = Organization::factory()->create();
        $foreignBill = Bill::factory()->approved()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);

        $this->apiPost("{$this->baseUrl}/clear", [
            'advance_payment_id' => $payment->id,
            'bill_id' => $foreignBill->id,
            'amount' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('bill_id');
    }

    public function test_clearing_returns_the_bill_with_its_supplier_tax_number_masked(): void
    {
        $payment = $this->payment($this->request($this->organization, $this->supplier), 500);
        $bill = $this->bill();

        $response = $this->apiPost("{$this->baseUrl}/clear", [
            'advance_payment_id' => $payment->id,
            'bill_id' => $bill->id,
            'amount' => 100,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.bill_id', $bill->id)
            ->assertJsonPath('data.advance_payment.id', $payment->id)
            ->assertJsonPath('data.bill.supplier_tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_listing_clearings_masks_the_bills_supplier_tax_number(): void
    {
        $request = $this->request($this->organization, $this->supplier, VendorAdvanceRequest::STATUS_PAID);
        $payment = $this->payment($request, 500);
        $bill = $this->bill();
        VendorAdvanceClearing::create([
            'advance_payment_id' => $payment->id,
            'bill_id' => $bill->id,
            'cleared_amount' => 100,
            'clearing_date' => now()->toDateString(),
        ]);

        $response = $this->apiGet("{$this->baseUrl}/{$request->id}/clearings");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.bill_id', $bill->id)
            ->assertJsonPath('data.0.bill.supplier_tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_a_stale_request_paid_meanwhile_is_not_approved_again(): void
    {
        $this->actingAs($this->user, 'api');
        $request = $this->request($this->organization, $this->supplier, VendorAdvanceRequest::STATUS_DRAFT);
        $stale = VendorAdvanceRequest::findOrFail($request->id);

        VendorAdvanceRequest::whereKey($request->id)->update(['status' => VendorAdvanceRequest::STATUS_PAID]);

        try {
            app(\App\Services\Purchase\VendorAdvanceService::class)->approveRequest($stale);
            $this->fail('A request paid meanwhile was approved again.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(VendorAdvanceRequest::STATUS_PAID, $request->fresh()->status);
        }
    }

    private function request(Organization $organization, Contact $supplier, string $status = VendorAdvanceRequest::STATUS_PAID): VendorAdvanceRequest
    {
        return VendorAdvanceRequest::create([
            'organization_id' => $organization->id,
            'request_number' => 'VAR-'.fake()->unique()->numerify('#####'),
            'contact_id' => $supplier->id,
            'requested_amount' => 500,
            'currency_code' => 'SAR',
            'requested_by' => $this->user->id,
            'status' => $status,
        ]);
    }

    private function foreignRequest(): VendorAdvanceRequest
    {
        $other = Organization::factory()->create();

        return $this->request($other, Contact::factory()->supplier()->create(['organization_id' => $other->id]));
    }

    private function payment(VendorAdvanceRequest $request, float $amount): VendorAdvancePayment
    {
        return VendorAdvancePayment::create([
            'advance_request_id' => $request->id,
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
        ]);
    }

    private function bill(): Bill
    {
        return Bill::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'supplier_tax_number' => self::TAX_NUMBER,
            'total' => 800,
            'base_total' => 800,
            'amount_paid' => 0,
            'amount_due' => 800,
            'currency_code' => 'SAR',
        ]);
    }
}
