<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationLine;
use App\Models\Sales\SalesOrder;
use App\Services\Sales\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the quotation list, update, delete, send, review and conversion to a
 * sales order, and checks that each status change is made against the locked row.
 */
class QuotationEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.quotations.view',
            'sales.quotations.create',
            'sales.quotations.edit',
            'sales.quotations.delete',
            'sales.quotations.send',
            'sales.quotations.convert',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);
    }

    public function test_the_list_filters_by_customer_status_and_date(): void
    {
        $match = $this->quotation(Quotation::STATUS_DRAFT, ['quotation_date' => '2025-01-10']);
        $this->quotation(Quotation::STATUS_SENT, ['quotation_date' => '2025-01-12']);
        $this->quotation(Quotation::STATUS_DRAFT, ['quotation_date' => '2025-03-01']);

        $other = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->quotation(Quotation::STATUS_DRAFT, ['quotation_date' => '2025-01-11', 'customer_id' => $other->id]);

        $response = $this->apiGet("/sales/quotations?customer_id={$this->customer->id}&status=draft&from_date=2025-01-01&to_date=2025-01-31");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_declined_quotation_is_not_updated(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_DECLINED);

        $this->apiPut("/sales/quotations/{$quotation->id}", ['notes' => 'changed'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Quotation cannot be updated in its current status.');
    }

    public function test_updating_leaves_fields_sent_as_null_unchanged(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_DRAFT, ['notes' => 'keep me']);

        $this->apiPut("/sales/quotations/{$quotation->id}", ['notes' => null, 'reference' => 'R-1'])
            ->assertOk()
            ->assertJsonPath('message', 'Quotation updated successfully.');

        $fresh = $quotation->fresh();
        $this->assertSame('keep me', $fresh->notes);
        $this->assertSame('R-1', $fresh->reference);
    }

    public function test_only_a_draft_quotation_is_deleted(): void
    {
        $draft = $this->quotation(Quotation::STATUS_DRAFT);
        $sent = $this->quotation(Quotation::STATUS_SENT);

        $this->apiDelete("/sales/quotations/{$sent->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft quotations can be deleted.');

        $this->apiDelete("/sales/quotations/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Quotation deleted successfully.');

        $this->assertNull(Quotation::find($draft->id));
        $this->assertNotNull(Quotation::find($sent->id));
    }

    public function test_an_accepted_quotation_is_not_sent(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_ACCEPTED);

        $this->apiPost("/sales/quotations/{$quotation->id}/send")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Quotation cannot be sent in its current status.');
    }

    public function test_a_converted_quotation_is_not_reviewed(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_CONVERTED);

        $this->apiPost("/sales/quotations/{$quotation->id}/review", ['action' => 'decline'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Quotation cannot be reviewed in its current status.');
    }

    public function test_declining_a_sent_quotation(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_SENT);

        $this->apiPost("/sales/quotations/{$quotation->id}/review", ['action' => 'decline'])
            ->assertOk()
            ->assertJsonPath('message', 'Quotation declined successfully.')
            ->assertJsonPath('data.status', Quotation::STATUS_DECLINED);
    }

    public function test_a_draft_quotation_is_not_converted(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_DRAFT);

        $this->apiPost("/sales/quotations/{$quotation->id}/convert", ['convert_to' => 'sales_order'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only accepted quotations can be converted.');
    }

    public function test_converting_to_a_sales_order_copies_the_lines_and_marks_the_quotation_converted(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_ACCEPTED, ['quotation_number' => 'QT-9001']);
        $this->line($quotation, 'First', 2, 100);
        $this->line($quotation, 'Second', 1, 50);

        $response = $this->apiPost("/sales/quotations/{$quotation->id}/convert", ['convert_to' => 'sales_order']);

        $response->assertOk()
            ->assertJsonPath('message', 'Quotation converted successfully.')
            ->assertJsonPath('data.type', 'sales_order');

        $order = SalesOrder::findOrFail($response->json('data.id'));
        $this->assertSame($order->order_number, $response->json('data.number'));
        $this->assertSame($quotation->id, $order->quotation_id);
        $this->assertSame('QT-9001', $order->reference);
        $this->assertSame(SalesOrder::STATUS_DRAFT, $order->status);
        $this->assertSame(2, $order->lines()->count());
        $this->assertSame(Quotation::STATUS_CONVERTED, $quotation->fresh()->status);
    }

    public function test_a_quotation_converted_meanwhile_is_not_converted_again_through_a_stale_copy(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_ACCEPTED);
        $this->line($quotation, 'Only', 1, 10);
        $stale = Quotation::findOrFail($quotation->id);
        $service = app(QuotationService::class);

        $service->convert($quotation, 'sales_order');

        try {
            $service->convert($stale, 'sales_order');
            $this->fail('A converted quotation must not be converted again.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(1, SalesOrder::count());
    }

    public function test_a_quotation_declined_meanwhile_is_not_sent_through_a_stale_draft(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_DRAFT);
        $stale = Quotation::findOrFail($quotation->id);
        $service = app(QuotationService::class);

        $service->review($quotation, 'decline');

        try {
            $service->send($stale);
            $this->fail('A declined quotation must not be sent.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(Quotation::STATUS_DECLINED, $quotation->fresh()->status);
    }

    public function test_a_quotation_accepted_meanwhile_is_not_updated_or_deleted_through_a_stale_draft(): void
    {
        $quotation = $this->quotation(Quotation::STATUS_DRAFT, ['notes' => 'as accepted']);
        $stale = Quotation::findOrFail($quotation->id);
        $service = app(QuotationService::class);

        $service->review($quotation, 'accept');

        try {
            $service->update($stale, ['notes' => 'rewritten']);
            $this->fail('An accepted quotation must not be updated.');
        } catch (\InvalidArgumentException) {
        }

        try {
            $service->delete($stale);
            $this->fail('An accepted quotation must not be deleted.');
        } catch (\InvalidArgumentException) {
        }

        $fresh = $quotation->fresh();
        $this->assertSame('as accepted', $fresh->notes);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $fresh->status);
    }

    /** @param  array<string, mixed>  $attributes */
    private function quotation(string $status, array $attributes = []): Quotation
    {
        return Quotation::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'currency_code' => 'SAR',
            'status' => $status,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    private function line(Quotation $quotation, string $description, float $quantity, float $price): QuotationLine
    {
        return QuotationLine::factory()->create([
            'quotation_id' => $quotation->id,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $price,
        ]);
    }
}
