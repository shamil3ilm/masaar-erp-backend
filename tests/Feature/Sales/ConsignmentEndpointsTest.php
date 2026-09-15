<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\ConsignmentOrder;
use App\Models\Sales\ConsignmentStock;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the consignment endpoints: the list, creating an order with lines, the
 * fill-up workflow that moves goods into consignment stock, cancellation,
 * stock levels and the statement. Keeps contacts, branches, products, units
 * and warehouses inside the caller's organization and the contact's tax number
 * out of the responses.
 */
class ConsignmentEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Contact $customer;
    private Product $product;
    private UnitOfMeasure $unit;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.consignment.view', 'sales.consignment.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => '300000000000003',
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->unit = UnitOfMeasure::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_an_order_is_created_and_listed_without_the_contacts_tax_number(): void
    {
        $created = $this->apiPost('/sales/consignment', $this->payload());

        $created->assertStatus(201)
            ->assertJsonPath('message', 'Consignment order created.')
            ->assertJsonPath('data.status', ConsignmentOrder::STATUS_DRAFT)
            ->assertJsonPath('data.order_type', ConsignmentOrder::TYPE_FILLUP)
            ->assertJsonPath('data.lines.0.line_total', '100.0000');
        $this->assertArrayNotHasKey('tax_number', $created->json('data.contact'));

        $this->order(['order_type' => ConsignmentOrder::TYPE_PICKUP]);
        $this->order(['organization_id' => $this->otherOrg->id, 'contact_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id]);

        $listed = $this->apiGet('/sales/consignment')->assertOk();
        $this->assertCount(2, $listed->json('data'));
        $this->assertSame($this->customer->company_name, $listed->json('data.0.contact.company_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.contact'));
        $this->assertSame(
            [$created->json('data.id')],
            array_column($this->apiGet('/sales/consignment?order_type=fillup')->json('data'), 'id')
        );
    }

    public function test_another_organizations_references_are_refused(): void
    {
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/sales/consignment', $this->payload([
            'contact_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'branch_id' => Branch::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'lines' => [[
                'product_id' => $foreignProduct->id,
                'quantity' => 1,
                'unit_id' => UnitOfMeasure::factory()->create(['organization_id' => $this->otherOrg->id])->id,
                'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            ]],
        ]));

        $response->assertStatus(422);
        foreach (['contact_id', 'branch_id', 'lines.0.product_id', 'lines.0.unit_id', 'lines.0.warehouse_id'] as $field) {
            $this->assertArrayHasKey($field, $response->json('errors') ?? []);
        }
        $this->assertSame(0, ConsignmentOrder::count());
    }

    public function test_a_fill_up_is_confirmed_shipped_and_completed_into_consignment_stock(): void
    {
        $id = $this->apiPost('/sales/consignment', $this->payload())->json('data.id');

        $this->apiPost("/sales/consignment/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('message', 'Consignment order confirmed.')
            ->assertJsonPath('data.status', ConsignmentOrder::STATUS_CONFIRMED);

        $this->apiPost("/sales/consignment/{$id}/ship")
            ->assertOk()
            ->assertJsonPath('data.status', ConsignmentOrder::STATUS_SHIPPED);

        $this->assertSame('10.0000', ConsignmentStock::firstOrFail()->on_hand_quantity);

        $completed = $this->apiPost("/sales/consignment/{$id}/complete")->assertOk();
        $completed->assertJsonPath('data.status', ConsignmentOrder::STATUS_COMPLETED);
        $this->assertArrayNotHasKey('tax_number', $completed->json('data.contact'));
        $this->assertSame('10.0000', ConsignmentStock::firstOrFail()->on_hand_quantity);

        $this->apiPost("/sales/consignment/{$id}/ship")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_ARGUMENT');

        $shown = $this->apiGet("/sales/consignment/{$id}")->assertOk();
        $this->assertSame($this->product->id, $shown->json('data.lines.0.product.id'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.contact'));
    }

    public function test_a_draft_is_cancelled_and_another_organizations_order_is_not_found(): void
    {
        $draft = $this->order();

        $this->apiPost("/sales/consignment/{$draft->id}/cancel")
            ->assertOk()
            ->assertJsonPath('message', 'Consignment order cancelled.')
            ->assertJsonPath('data.status', ConsignmentOrder::STATUS_CANCELLED);

        $foreign = $this->order([
            'organization_id' => $this->otherOrg->id,
            'contact_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);
        $this->apiGet("/sales/consignment/{$foreign->id}")->assertNotFound();
        $this->apiPost("/sales/consignment/{$foreign->id}/confirm")->assertNotFound();
    }

    public function test_stock_levels_and_the_statement_are_the_organizations_own(): void
    {
        $stock = ConsignmentStock::create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'on_hand_quantity' => 7,
        ]);

        $levels = $this->apiGet("/sales/consignment/stock?contact_id={$this->customer->id}")->assertOk();
        $this->assertSame([$stock->id], array_column($levels->json('data'), 'id'));
        $this->assertSame($this->product->id, $levels->json('data.0.product.id'));

        $this->apiGet("/sales/consignment/statement/{$this->customer->id}")
            ->assertOk()
            ->assertJsonPath('data.contact_id', $this->customer->id)
            ->assertJsonPath('data.totals.total_items', 1);

        $foreignContact = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $refused = $this->apiGet("/sales/consignment/stock?contact_id={$foreignContact->id}");
        $refused->assertStatus(422);
        $this->assertArrayHasKey('contact_id', $refused->json('errors') ?? []);

        $this->apiGet("/sales/consignment/statement/{$foreignContact->id}")->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'order_type' => 'fillup',
            'contact_id' => $this->customer->id,
            'order_date' => '2026-09-01',
            'branch_id' => $this->branch->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => 10,
                'unit_id' => $this->unit->id,
                'unit_price' => 10,
                'warehouse_id' => $this->warehouse->id,
            ]],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): ConsignmentOrder
    {
        $order = new ConsignmentOrder();
        $order->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'order_number' => 'CON-'.uniqid(),
            'order_type' => ConsignmentOrder::TYPE_FILLUP,
            'contact_id' => $this->customer->id,
            'status' => ConsignmentOrder::STATUS_DRAFT,
            'order_date' => '2026-09-01',
            'created_by' => $this->user->id,
        ], $attributes))->save();

        return $order;
    }
}
