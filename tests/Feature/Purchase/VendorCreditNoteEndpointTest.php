<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\VendorCreditNote;
use App\Models\Sales\Contact;
use App\Services\Purchase\VendorCreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Vendor credit note endpoints: supplier data leaves masked, another
 * organization's bill is refused, and draft edits and voiding are checked on
 * the locked note.
 */
class VendorCreditNoteEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/vendor-credit-notes';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.vendor-credit-notes.view', 'purchase.vendor-credit-notes.create',
            'purchase.vendor-credit-notes.apply',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
    }

    public function test_store_creates_a_draft_with_its_lines_and_totals(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'vendor_id' => $this->supplier->id,
            'issue_date' => now()->toDateString(),
            'credit_date' => now()->toDateString(),
            'lines' => [['description' => 'Returned goods', 'quantity' => 2, 'unit_price' => 50, 'tax_rate' => 15]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', VendorCreditNote::STATUS_DRAFT)
            ->assertJsonPath('data.vendor_id', $this->supplier->id)
            ->assertJsonCount(1, 'data.lines');
        $this->assertEqualsWithDelta(115.0, (float) $response->json('data.total_amount'), 0.0001);
    }

    public function test_store_refuses_another_organizations_vendor(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost($this->baseUrl, [
            'vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'issue_date' => now()->toDateString(),
            'credit_date' => now()->toDateString(),
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('vendor_id');
    }

    public function test_show_masks_the_vendors_tax_number(): void
    {
        $note = $this->note(VendorCreditNote::STATUS_DRAFT, 100);

        $response = $this->apiGet("{$this->baseUrl}/{$note->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.credit_note_number', $note->credit_note_number)
            ->assertJsonPath('data.vendor.id', $this->supplier->id)
            ->assertJsonPath('data.vendor.tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_index_masks_the_vendors_tax_number(): void
    {
        $this->note(VendorCreditNote::STATUS_DRAFT, 100);

        $response = $this->apiGet($this->baseUrl);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.vendor.tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_apply_refuses_another_organizations_bill(): void
    {
        $other = Organization::factory()->create();
        $foreignBill = Bill::factory()->approved()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);

        $this->apiPost("{$this->baseUrl}/{$this->note(VendorCreditNote::STATUS_POSTED, 100)->id}/apply", [
            'bill_id' => $foreignBill->id,
            'amount' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('bill_id');
    }

    public function test_apply_returns_the_bill_with_its_supplier_tax_number_masked(): void
    {
        $note = $this->note(VendorCreditNote::STATUS_POSTED, 100);
        $bill = $this->bill(500);

        $response = $this->apiPost("{$this->baseUrl}/{$note->id}/apply", [
            'bill_id' => $bill->id,
            'amount' => 40,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.credit_note.id', $note->id)
            ->assertJsonPath('data.bill.id', $bill->id)
            ->assertJsonPath('data.bill.supplier_tax_number', '***********0003');
        $this->assertEqualsWithDelta(40.0, (float) $response->json('data.credit_note.applied_amount'), 0.0001);
        $this->assertEqualsWithDelta(460.0, (float) $response->json('data.bill.amount_due'), 0.0001);
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_a_stale_note_applied_meanwhile_is_not_voided(): void
    {
        $this->actingAs($this->user, 'api');
        $note = $this->note(VendorCreditNote::STATUS_POSTED, 100);
        $stale = VendorCreditNote::findOrFail($note->id);

        VendorCreditNote::whereKey($note->id)->update([
            'status' => VendorCreditNote::STATUS_APPLIED,
            'applied_amount' => 100,
        ]);

        $this->assertRejected(fn () => app(VendorCreditNoteService::class)->void($stale));
        $this->assertSame(VendorCreditNote::STATUS_APPLIED, $note->fresh()->status);
    }

    public function test_a_stale_draft_posted_meanwhile_is_not_updated_or_deleted(): void
    {
        $this->actingAs($this->user, 'api');
        $note = $this->note(VendorCreditNote::STATUS_DRAFT, 100);
        $stale = VendorCreditNote::findOrFail($note->id);

        VendorCreditNote::whereKey($note->id)->update(['status' => VendorCreditNote::STATUS_POSTED]);

        $service = app(VendorCreditNoteService::class);
        $this->assertRejected(fn () => $service->update($stale, ['notes' => 'changed']));
        $this->assertRejected(fn () => $service->delete($stale));

        $note->refresh();
        $this->assertNull($note->notes);
        $this->assertNull($note->deleted_at);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException) {
            return;
        }

        $this->fail('The change was expected to be rejected.');
    }

    private function note(string $status, float $total): VendorCreditNote
    {
        return VendorCreditNote::create([
            'organization_id' => $this->organization->id,
            'credit_note_number' => 'VCN-'.fake()->unique()->numerify('#####'),
            'vendor_id' => $this->supplier->id,
            'issue_date' => now()->toDateString(),
            'credit_date' => now()->toDateString(),
            'status' => $status,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total_amount' => $total,
            'applied_amount' => 0,
        ]);
    }

    private function bill(float $total): Bill
    {
        return Bill::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'supplier_tax_number' => self::TAX_NUMBER,
            'total' => $total,
            'base_total' => $total,
            'amount_paid' => 0,
            'amount_due' => $total,
            'currency_code' => 'SAR',
        ]);
    }
}
