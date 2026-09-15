<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\VendorConsignmentSettlement;
use App\Models\Purchase\VendorConsignmentStock;
use App\Models\Purchase\VendorConsignmentWithdrawal;
use App\Models\Sales\Contact;
use App\Services\Purchase\VendorConsignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Vendor consignment endpoints: ids a request names must be the caller's
 * organization's, vendor and bill tax numbers leave masked, and a settlement
 * is billed once.
 */
class VendorConsignmentEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/vendor-consignment';
    private Contact $supplier;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.consignment.view', 'purchase.consignment.receive',
            'purchase.consignment.withdraw', 'purchase.consignment.settle',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_stock_index_lists_only_this_organizations_stock_with_masked_vendors(): void
    {
        $this->stock($this->organization, $this->supplier, $this->product, $this->warehouse);
        $other = Organization::factory()->create();
        $this->stock(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
            Warehouse::factory()->create(['organization_id' => $other->id]),
        );

        $response = $this->apiGet("{$this->baseUrl}/stocks")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.vendor.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_stock_show_of_another_organizations_stock_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $stock = $this->stock(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
            Warehouse::factory()->create(['organization_id' => $other->id]),
        );

        $this->apiGet("{$this->baseUrl}/stocks/{$stock->id}")->assertNotFound();
    }

    public function test_receiving_refuses_another_organizations_order_and_unit_and_another_warehouses_location(): void
    {
        $other = Organization::factory()->create();
        $otherWarehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiPost("{$this->baseUrl}/receive", $this->receiptPayload(5, [
            'warehouse_location_id' => WarehouseLocation::factory()->create(['warehouse_id' => $otherWarehouse->id])->id,
            'purchase_order_id' => PurchaseOrder::factory()->create([
                'organization_id' => $other->id,
                'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            ])->id,
            'unit_id' => UnitOfMeasure::factory()->create(['organization_id' => $other->id])->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['warehouse_location_id', 'purchase_order_id', 'unit_id']);

        $this->assertSame(0, VendorConsignmentStock::withoutGlobalScopes()->count());
    }

    public function test_receiving_adds_to_the_stock_on_hand(): void
    {
        $this->apiPost("{$this->baseUrl}/receive", $this->receiptPayload(5))->assertCreated();
        $this->apiPost("{$this->baseUrl}/receive", $this->receiptPayload(2.5))->assertCreated();

        $stock = VendorConsignmentStock::withoutGlobalScopes()->sole();

        $this->assertSame('7.5000', $stock->quantity_on_hand);
    }

    public function test_withdrawing_refuses_another_organizations_stock(): void
    {
        $other = Organization::factory()->create();
        $foreignStock = $this->stock(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
            Warehouse::factory()->create(['organization_id' => $other->id]),
        );

        $this->apiPost("{$this->baseUrl}/withdraw", [
            'vendor_consignment_stock_id' => $foreignStock->id,
            'withdrawal_date' => now()->toDateString(),
            'quantity_withdrawn' => 1,
            'withdrawal_type' => 'production',
        ])->assertStatus(422)->assertJsonValidationErrors('vendor_consignment_stock_id');

        $this->assertSame('10.0000', $foreignStock->fresh()->quantity_on_hand);
    }

    public function test_a_settlement_values_the_withdrawals_of_its_period(): void
    {
        $stock = $this->stock($this->organization, $this->supplier, $this->product, $this->warehouse);
        $this->withdrawal($stock, 2, '2026-03-05');
        $this->withdrawal($stock, 3, '2026-03-20');
        $this->withdrawal($stock, 4, '2026-04-02');

        $this->apiPost("{$this->baseUrl}/settlements", [
            'vendor_id' => $this->supplier->id,
            'period_from' => '2026-03-01',
            'period_to' => '2026-03-31',
        ])->assertCreated()
            ->assertJsonPath('data.total_quantity', '5.0000')
            ->assertJsonPath('data.total_value', '62.5000')
            ->assertJsonPath('data.vendor.tax_number', '***********0003');
    }

    public function test_settlements_list_masks_the_bill_supplier_tax_number(): void
    {
        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'supplier_tax_number' => self::TAX_NUMBER,
        ]);
        $this->settlement(VendorConsignmentSettlement::STATUS_SUBMITTED, $bill);

        $response = $this->apiGet("{$this->baseUrl}/settlements")
            ->assertOk()
            ->assertJsonPath('data.0.bill.supplier_tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_submitting_a_stale_copy_of_a_submitted_settlement_bills_once(): void
    {
        $settlement = $this->settlement(VendorConsignmentSettlement::STATUS_DRAFT);
        $staleCopy = VendorConsignmentSettlement::withoutGlobalScopes()->findOrFail($settlement->id);

        $this->apiPost("{$this->baseUrl}/settlements/{$settlement->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', VendorConsignmentSettlement::STATUS_SUBMITTED);

        try {
            app(VendorConsignmentService::class)->submitSettlement($staleCopy);
            $this->fail('A submitted settlement was billed a second time.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only draft settlements can be submitted.', $e->getMessage());
        }

        $this->assertSame(1, Bill::withoutGlobalScopes()->count());
    }

    public function test_submitting_another_organizations_settlement_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $settlement = VendorConsignmentSettlement::create([
            'organization_id' => $other->id,
            'vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'settlement_period_from' => '2026-03-01',
            'settlement_period_to' => '2026-03-31',
            'total_quantity' => 1,
            'total_value' => 10,
            'currency_code' => 'SAR',
            'status' => VendorConsignmentSettlement::STATUS_DRAFT,
        ]);

        $this->apiPost("{$this->baseUrl}/settlements/{$settlement->id}/submit")->assertNotFound();

        $this->assertSame(0, Bill::withoutGlobalScopes()->count());
    }

    private function stock(Organization $organization, Contact $supplier, Product $product, Warehouse $warehouse): VendorConsignmentStock
    {
        return VendorConsignmentStock::create([
            'organization_id' => $organization->id,
            'vendor_id' => $supplier->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'vendor_price' => 12.5,
            'currency_code' => 'SAR',
            'last_movement_at' => now(),
        ]);
    }

    private function withdrawal(VendorConsignmentStock $stock, float $quantity, string $date): VendorConsignmentWithdrawal
    {
        return VendorConsignmentWithdrawal::create([
            'organization_id' => $stock->organization_id,
            'vendor_consignment_stock_id' => $stock->id,
            'withdrawal_date' => $date,
            'quantity_withdrawn' => $quantity,
            'withdrawal_type' => 'production',
        ]);
    }

    private function settlement(string $status, ?Bill $bill = null): VendorConsignmentSettlement
    {
        return VendorConsignmentSettlement::create([
            'organization_id' => $this->organization->id,
            'vendor_id' => $this->supplier->id,
            'settlement_period_from' => '2026-03-01',
            'settlement_period_to' => '2026-03-31',
            'total_quantity' => 5,
            'total_value' => 62.5,
            'currency_code' => 'SAR',
            'status' => $status,
            'bill_id' => $bill?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receiptPayload(float $quantity, array $overrides = []): array
    {
        return array_merge([
            'vendor_id' => $this->supplier->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'quantity_received' => $quantity,
            'vendor_price' => 12.5,
            'currency_code' => 'SAR',
        ], $overrides);
    }
}
