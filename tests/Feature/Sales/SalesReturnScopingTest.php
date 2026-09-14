<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Services\Sales\SalesReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A sales return refers only to the caller's own customers, invoices,
 * warehouses and products, shows its customer without the tax number, and
 * changes status against the locked row.
 */
class SalesReturnScopingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.returns.view',
            'sales.returns.create',
            'sales.returns.approve',
            'sales.returns.receive',
            'sales.returns.inspect',
            'sales.returns.resolve',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
            'tax_number' => '300000000000003',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_customer_of_another_organization_is_refused(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/returns', $this->payload(['customer_id' => $foreign->id]))->assertStatus(422);

        $this->assertSame(0, SalesReturn::withoutGlobalScopes()->count());
    }

    public function test_an_invoice_of_another_organization_is_refused(): void
    {
        $foreign = Invoice::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $this->apiPost('/sales/returns', $this->payload(['invoice_id' => $foreign->id]))->assertStatus(422);

        $this->assertSame(0, SalesReturn::withoutGlobalScopes()->count());
    }

    public function test_a_warehouse_of_another_organization_is_refused(): void
    {
        $foreign = Warehouse::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/returns', $this->payload(['warehouse_id' => $foreign->id]))->assertStatus(422);

        $this->assertSame(0, SalesReturn::withoutGlobalScopes()->count());
    }

    public function test_a_product_of_another_organization_is_refused(): void
    {
        $foreign = Product::factory()->create(['organization_id' => $this->otherOrg->id]);

        $payload = $this->payload();
        $payload['items'][0]['product_id'] = $foreign->id;

        $this->apiPost('/sales/returns', $payload)->assertStatus(422);

        $this->assertSame(0, SalesReturn::withoutGlobalScopes()->count());
    }

    public function test_a_return_of_another_organization_is_not_found(): void
    {
        $foreign = SalesReturn::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $this->apiGet("/sales/returns/{$foreign->id}")->assertNotFound();
        $this->apiPost("/sales/returns/{$foreign->id}/receive")->assertNotFound();
    }

    public function test_the_list_and_the_return_show_the_customer_without_its_tax_number(): void
    {
        $return = $this->salesReturn(SalesReturn::STATUS_PENDING);

        $shown = $this->apiGet("/sales/returns/{$return->id}")->assertOk();
        $this->assertSame($this->customer->contact_name, $shown->json('data.customer.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.customer'));
        $this->assertSame(1, count($shown->json('data.items')));

        $listed = $this->apiGet('/sales/returns')->assertOk();
        $this->assertSame($this->customer->company_name, $listed->json('data.0.customer.company_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.customer'));
    }

    public function test_receiving_without_items_receives_every_item_in_full(): void
    {
        $return = $this->salesReturn(SalesReturn::STATUS_APPROVED);

        $this->apiPost("/sales/returns/{$return->id}/receive")
            ->assertOk()
            ->assertJsonPath('message', 'Items received successfully.')
            ->assertJsonPath('data.status', SalesReturn::STATUS_RECEIVED);

        $item = $return->items()->sole();
        $this->assertEquals($item->quantity_returned, $item->quantity_received);
        $this->assertSame(SalesReturnItem::STATUS_RECEIVED, $item->item_status);
    }

    public function test_a_return_without_items_must_be_approved_before_it_is_received(): void
    {
        $return = SalesReturn::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => SalesReturn::STATUS_PENDING,
        ]);

        $this->apiPost("/sales/returns/{$return->id}/receive")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Sales return must be approved before receiving items.');

        $this->assertSame(SalesReturn::STATUS_PENDING, $return->fresh()->status);
    }

    public function test_a_return_inspected_meanwhile_is_not_inspected_again_through_a_stale_copy(): void
    {
        $return = $this->salesReturn(SalesReturn::STATUS_RECEIVED);
        $stale = SalesReturn::findOrFail($return->id);
        $service = app(SalesReturnService::class);

        $service->inspect($return, SalesReturn::INSPECTION_PASSED, 'first look');

        try {
            $service->inspect($stale, SalesReturn::INSPECTION_FAILED, 'second look');
            $this->fail('An inspected return must not be inspected again.');
        } catch (ApiException) {
        }

        $fresh = $return->fresh();
        $this->assertSame(SalesReturn::INSPECTION_PASSED, $fresh->inspection_status);
        $this->assertSame('first look', $fresh->inspection_notes);
    }

    public function test_a_return_rejected_meanwhile_is_not_approved_through_a_stale_copy(): void
    {
        $return = $this->salesReturn(SalesReturn::STATUS_PENDING);
        $stale = SalesReturn::findOrFail($return->id);
        $service = app(SalesReturnService::class);

        $service->reject($return, $this->user->id, 'not ours');

        try {
            $service->approve($stale, $this->user->id);
            $this->fail('A rejected return must not be approved.');
        } catch (ApiException) {
        }

        $this->assertSame(SalesReturn::STATUS_REJECTED, $return->fresh()->status);
    }

    public function test_resolving_sets_the_restock_flag_with_the_resolution(): void
    {
        $return = $this->salesReturn(SalesReturn::STATUS_INSPECTED);

        $this->apiPost("/sales/returns/{$return->id}/resolve", [
            'resolution_type' => SalesReturn::RESOLUTION_EXCHANGE,
            'restock_items' => false,
        ])->assertOk()->assertJsonPath('data.status', SalesReturn::STATUS_COMPLETED);

        $this->assertFalse((bool) $return->fresh()->restock_items);
    }

    public function test_a_refused_resolution_leaves_the_restock_flag_unchanged(): void
    {
        $return = $this->salesReturn(SalesReturn::STATUS_PENDING);

        $response = $this->apiPost("/sales/returns/{$return->id}/resolve", [
            'resolution_type' => SalesReturn::RESOLUTION_EXCHANGE,
            'restock_items' => false,
        ]);

        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertLessThan(500, $response->status());
        $this->assertTrue((bool) $return->fresh()->restock_items);
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'return_date' => now()->toDateString(),
            'return_type' => 'refund',
            'currency_code' => 'SAR',
            'items' => [
                ['description' => 'Returned item', 'quantity_returned' => 1, 'unit_price' => 10],
            ],
        ], $overrides);
    }

    private function salesReturn(string $status): SalesReturn
    {
        $return = SalesReturn::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => $status,
            'restock_items' => true,
        ]);

        SalesReturnItem::factory()->create([
            'sales_return_id' => $return->id,
            'quantity_returned' => 2,
        ]);

        return $return;
    }
}
