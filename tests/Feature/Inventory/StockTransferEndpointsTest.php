<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the stock transfer list and pending list, and keeps line variants
 * inside the caller's organization.
 */
class StockTransferEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $source;
    private Warehouse $destination;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.stock-transfers.view',
            'inventory.stock-transfers.create',
        ]);

        $this->source = $this->warehouse('WH-A');
        $this->destination = $this->warehouse('WH-B');
        $this->product = $this->stockedProduct();
    }

    public function test_the_list_filters_by_status_and_source_warehouse(): void
    {
        $match = $this->transfer(StockTransfer::STATUS_DRAFT, $this->source, $this->destination);
        $this->transfer(StockTransfer::STATUS_IN_TRANSIT, $this->source, $this->destination);
        $this->transfer(StockTransfer::STATUS_DRAFT, $this->destination, $this->source);

        $response = $this->apiGet("/inventory/stock-transfers?status=draft&from_warehouse_id={$this->source->id}");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_the_pending_list_holds_draft_and_in_transit_transfers(): void
    {
        $draft = $this->transfer(StockTransfer::STATUS_DRAFT, $this->source, $this->destination);
        $inTransit = $this->transfer(StockTransfer::STATUS_IN_TRANSIT, $this->source, $this->destination);
        $this->transfer(StockTransfer::STATUS_RECEIVED, $this->source, $this->destination);
        $this->transfer(StockTransfer::STATUS_CANCELLED, $this->source, $this->destination);

        $response = $this->apiGet('/inventory/stock-transfers/pending');

        $response->assertOk();
        $this->assertEqualsCanonicalizing([$draft->id, $inTransit->id], array_column($response->json('data'), 'id'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_a_line_may_name_a_variant_of_the_organization(): void
    {
        $response = $this->apiPost('/inventory/stock-transfers', $this->payload($this->variantOf($this->product)->id));

        $response->assertCreated();
    }

    public function test_a_variant_of_another_organization_is_refused(): void
    {
        $response = $this->apiPost('/inventory/stock-transfers', $this->payload($this->variantOf($this->foreignProduct())->id));

        $response->assertStatus(422)->assertJsonValidationErrors(['lines.0.variant_id']);
        $this->assertSame(0, StockTransfer::count());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $variantId): array
    {
        return [
            'from_warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->destination->id,
            'transfer_date' => now()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'variant_id' => $variantId, 'quantity' => 2]],
        ];
    }

    private function transfer(string $status, Warehouse $from, Warehouse $to): StockTransfer
    {
        return StockTransfer::create([
            'organization_id' => $this->organization->id,
            'transfer_number' => 'TRF-'.fake()->unique()->numerify('#####'),
            'transfer_date' => now()->toDateString(),
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }
}
