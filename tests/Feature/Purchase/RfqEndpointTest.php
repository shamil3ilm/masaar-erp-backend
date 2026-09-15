<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\RfqHeader;
use App\Models\Purchase\RfqItem;
use App\Models\Purchase\RfqQuote;
use App\Models\Purchase\RfqVendor;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * RFQ endpoints: ids a request names must be the caller's organization's or
 * the RFQ's own, and an awarded RFQ becomes one purchase order.
 */
class RfqEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/rfq';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.rfq.view', 'purchase.rfq.create', 'purchase.rfq.edit', 'purchase.rfq.send',
            'purchase.rfq.quote', 'purchase.rfq.award', 'purchase.rfq.convert',
        ]);

        $this->supplier = Contact::factory()->supplier()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_lists_only_this_organizations_rfqs(): void
    {
        $this->rfq($this->organization);
        $this->rfq($this->organization);
        $this->rfq(Organization::factory()->create());

        $this->apiGet($this->baseUrl)->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_store_refuses_another_organizations_branch_product_and_unit(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost($this->baseUrl, [
            'title' => 'Steel',
            'branch_id' => Branch::factory()->create(['organization_id' => $other->id])->id,
            'items' => [[
                'description' => 'Steel bar',
                'quantity' => 5,
                'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
                'unit_id' => UnitOfMeasure::factory()->create(['organization_id' => $other->id])->id,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['branch_id', 'items.0.product_id', 'items.0.unit_id']);

        $this->assertSame(0, RfqHeader::withoutGlobalScopes()->count());
    }

    public function test_update_refuses_a_sent_rfq(): void
    {
        $rfq = $this->rfq($this->organization, RfqHeader::STATUS_SENT);

        $this->apiPut("{$this->baseUrl}/{$rfq->id}", ['title' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft RFQs can be updated.');
    }

    public function test_sending_refuses_another_organizations_vendor(): void
    {
        $rfq = $this->rfq($this->organization);
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/send-to-vendors", ['vendor_ids' => [$foreignSupplier->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vendor_ids.0');

        $this->assertSame(0, RfqVendor::count());
        $this->assertSame(RfqHeader::STATUS_DRAFT, $rfq->fresh()->status);
    }

    public function test_recording_a_quote_refuses_an_item_of_another_rfq(): void
    {
        $rfq = $this->rfq($this->organization, RfqHeader::STATUS_SENT);
        $invitation = $this->invite($rfq);
        $otherItem = $this->rfq($this->organization)->items()->first();

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/record-quote", $this->quotePayload($invitation, $otherItem))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.rfq_item_id');

        $this->assertSame(0, RfqQuote::count());
    }

    public function test_recording_a_quote_refuses_an_invitation_of_another_rfq(): void
    {
        $rfq = $this->rfq($this->organization, RfqHeader::STATUS_SENT);
        $otherInvitation = $this->invite($this->rfq($this->organization, RfqHeader::STATUS_SENT));

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/record-quote", $this->quotePayload($otherInvitation, $rfq->items()->first()))
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Vendor invitation does not belong to this RFQ.');

        $this->assertSame(0, RfqQuote::count());
    }

    public function test_recording_a_quote_totals_its_lines(): void
    {
        $rfq = $this->rfq($this->organization, RfqHeader::STATUS_SENT);
        $invitation = $this->invite($rfq);

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/record-quote", $this->quotePayload($invitation, $rfq->items()->first()))
            ->assertCreated()
            ->assertJsonPath('data.total_amount', 250)
            ->assertJsonPath('data.contact_id', $this->supplier->id);

        $this->assertSame('responded', $invitation->fresh()->status);
    }

    public function test_awarding_a_quote_rejects_the_others(): void
    {
        $rfq = $this->rfq($this->organization, RfqHeader::STATUS_SENT);
        $winner = $this->quote($rfq, $this->invite($rfq));
        $loser = $this->quote($rfq, $this->invite($rfq, Contact::factory()->supplier()->create(['organization_id' => $this->organization->id])));

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/award", ['quote_id' => $winner->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'awarded');

        $this->assertSame('rejected', $loser->fresh()->status);
        $this->assertSame(RfqHeader::STATUS_AWARDED, $rfq->fresh()->status);
    }

    public function test_awarding_a_quote_of_another_rfq_is_refused(): void
    {
        $rfq = $this->rfq($this->organization, RfqHeader::STATUS_SENT);
        $other = $this->rfq($this->organization, RfqHeader::STATUS_SENT);
        $foreignQuote = $this->quote($other, $this->invite($other));

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/award", ['quote_id' => $foreignQuote->id])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Quote does not belong to this RFQ.');

        $this->assertSame('received', $foreignQuote->fresh()->status);
    }

    public function test_an_awarded_rfq_converts_to_one_purchase_order(): void
    {
        $rfq = $this->rfq($this->organization, RfqHeader::STATUS_SENT);
        $quote = $this->quote($rfq, $this->invite($rfq));

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/award", ['quote_id' => $quote->id])->assertOk();

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/convert-to-po", ['quote_id' => $quote->id])
            ->assertCreated()
            ->assertJsonPath('data.supplier_id', $this->supplier->id);

        $this->apiPost("{$this->baseUrl}/{$rfq->id}/convert-to-po", ['quote_id' => $quote->id])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'This RFQ has already been converted to a purchase order.');

        $this->assertSame(1, PurchaseOrder::count());
        $this->assertSame(RfqHeader::STATUS_CLOSED, $rfq->fresh()->status);
    }

    private function rfq(Organization $organization, string $status = RfqHeader::STATUS_DRAFT): RfqHeader
    {
        $rfq = RfqHeader::create([
            'organization_id' => $organization->id,
            'rfq_number' => 'RFQ-'.fake()->unique()->numerify('#####'),
            'title' => 'Steel',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);

        RfqItem::create(['rfq_id' => $rfq->id, 'description' => 'Steel bar', 'quantity' => 10]);

        return $rfq;
    }

    private function invite(RfqHeader $rfq, ?Contact $supplier = null): RfqVendor
    {
        return RfqVendor::create([
            'rfq_id' => $rfq->id,
            'contact_id' => ($supplier ?? $this->supplier)->id,
            'status' => 'invited',
            'sent_at' => now(),
        ]);
    }

    private function quote(RfqHeader $rfq, RfqVendor $invitation): RfqQuote
    {
        $quote = RfqQuote::create([
            'rfq_id' => $rfq->id,
            'rfq_vendor_id' => $invitation->id,
            'contact_id' => $invitation->contact_id,
            'currency_code' => 'SAR',
            'total_amount' => 100,
            'status' => 'received',
        ]);

        $quote->lines()->create([
            'rfq_item_id' => $rfq->items()->firstOrFail()->id,
            'unit_price' => 10,
            'quantity' => 10,
            'line_total' => 100,
        ]);

        return $quote;
    }

    /**
     * @return array<string, mixed>
     */
    private function quotePayload(RfqVendor $invitation, RfqItem $item): array
    {
        return [
            'rfq_vendor_id' => $invitation->id,
            'currency_code' => 'SAR',
            'lines' => [[
                'rfq_item_id' => $item->id,
                'unit_price' => 25,
                'quantity' => 10,
                'line_total' => 250,
            ]],
        ];
    }
}
