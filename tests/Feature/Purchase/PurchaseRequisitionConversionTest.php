<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Sales\Contact;
use App\Services\Purchase\PurchaseRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Converting a requisition to purchase orders uses only the requisition
 * organization's vendors, however the lines came to name them.
 */
class PurchaseRequisitionConversionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.requisitions.view', 'purchase.requisitions.manage']);

        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->user, 'api');
    }

    public function test_conversion_orders_from_the_organizations_preferred_vendor(): void
    {
        $supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
        ]);

        $orders = app(PurchaseRequisitionService::class)->convertToPurchaseOrder($this->approvedRequisition($supplier));

        $this->assertCount(1, $orders);
        $this->assertSame($supplier->id, $orders[0]->supplier_id);
        $this->assertSame($supplier->getDisplayName(), $orders[0]->supplier_name);
        $this->assertSame('SAR', $orders[0]->currency_code);
    }

    public function test_conversion_refuses_a_preferred_vendor_of_another_organization(): void
    {
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => Organization::factory()->create()->id]);
        $requisition = $this->approvedRequisition($foreignSupplier);

        try {
            app(PurchaseRequisitionService::class)->convertToPurchaseOrder($requisition);
            $this->fail('A purchase order was created for another organization\'s vendor.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('A preferred vendor of this requisition was not found.', $e->getMessage());
        }

        $this->assertSame(0, PurchaseOrder::withoutGlobalScopes()->count());
        $this->assertSame(PurchaseRequisition::STATUS_APPROVED, $requisition->fresh()->status);
    }

    /**
     * An approved requisition whose line names the vendor directly, as a line
     * written before its vendor rule was scoped could.
     */
    private function approvedRequisition(Contact $vendor): PurchaseRequisition
    {
        $requisition = PurchaseRequisition::create([
            'organization_id' => $this->organization->id,
            'requisition_number' => 'PR-'.fake()->unique()->numerify('#####'),
            'requisition_date' => now()->toDateString(),
            'status' => PurchaseRequisition::STATUS_APPROVED,
            'requested_by' => $this->user->id,
        ]);

        $requisition->lines()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'estimated_unit_price' => 10,
            'preferred_vendor_id' => $vendor->id,
            'status' => 'open',
        ]);

        return $requisition;
    }
}
