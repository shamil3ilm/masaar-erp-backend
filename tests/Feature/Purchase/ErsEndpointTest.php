<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\ErsConfiguration;
use App\Models\Purchase\ErsRun;
use App\Models\Purchase\ErsRunItem;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Services\Purchase\GoodsReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\TestHelpers;

/**
 * Evaluated receipt settlement endpoints: vendor ids are the caller's
 * organization's, and vendor and bill tax numbers leave masked.
 */
class ErsEndpointTest extends TestCase
{
    use BuildsInventory, RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/ers';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.ers.view', 'purchase.ers.manage']);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
    }

    public function test_configs_mask_the_vendors_tax_number(): void
    {
        ErsConfiguration::create(['organization_id' => $this->organization->id, 'vendor_id' => $this->supplier->id]);

        $response = $this->apiGet("{$this->baseUrl}/configs");

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.vendor_id', $this->supplier->id)
            ->assertJsonPath('data.data.0.vendor.tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_saving_a_config_refuses_another_organizations_vendor(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost("{$this->baseUrl}/configs", [
            'vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'is_enabled' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('vendor_id');

        $this->assertSame(0, ErsConfiguration::withoutGlobalScopes()->count());
    }

    public function test_saving_a_config_masks_the_vendors_tax_number(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/configs", [
            'vendor_id' => $this->supplier->id,
            'auto_post' => false,
            'tolerance_percent' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.vendor_id', $this->supplier->id)
            ->assertJsonPath('data.vendor.tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_a_run_refuses_another_organizations_vendors(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost("{$this->baseUrl}/run", [
            'vendor_ids' => [Contact::factory()->supplier()->create(['organization_id' => $other->id])->id],
        ])->assertStatus(422)->assertJsonValidationErrors('vendor_ids.0');

        $this->assertSame(0, ErsRun::withoutGlobalScopes()->count());
    }

    public function test_runs_list_the_organizations_runs(): void
    {
        $this->ersRun($this->organization);
        $this->ersRun(Organization::factory()->create());

        $this->apiGet("{$this->baseUrl}/runs")->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_run_items_mask_the_vendor_and_bill_tax_numbers(): void
    {
        $run = $this->ersRun($this->organization);
        $bill = Bill::factory()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
            'supplier_tax_number' => self::TAX_NUMBER,
        ]);
        ErsRunItem::create([
            'organization_id' => $this->organization->id,
            'ers_run_id' => $run->id,
            'goods_receipt_id' => $this->goodsReceiptId(),
            'bill_id' => $bill->id,
            'vendor_id' => $this->supplier->id,
            'gross_amount' => 10,
            'status' => ErsRunItem::STATUS_PROCESSED,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/runs/{$run->id}/items");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vendor.tax_number', '***********0003')
            ->assertJsonPath('data.0.bill.supplier_tax_number', '***********0003')
            ->assertJsonPath('data.0.goods_receipt.id', ErsRunItem::sole()->goods_receipt_id);
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_another_organizations_run_is_not_found(): void
    {
        $run = $this->ersRun(Organization::factory()->create());

        $this->apiGet("{$this->baseUrl}/runs/{$run->id}/items")->assertNotFound();
    }

    private function ersRun(Organization $organization): ErsRun
    {
        return ErsRun::create([
            'organization_id' => $organization->id,
            'run_date' => today(),
            'status' => ErsRun::STATUS_COMPLETED,
        ]);
    }

    private function goodsReceiptId(): int
    {
        $this->actingAs($this->user, 'api');
        $product = $this->stockedProduct();
        $order = PurchaseOrder::factory()->confirmed()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $this->supplier->id,
        ]);

        return app(GoodsReceiptService::class)->createGr($order, [
            'warehouse_id' => $this->warehouse()->id,
            'lines' => [[
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'description' => $product->name,
                'quantity_ordered' => 1,
                'quantity_received' => 1,
                'unit_cost' => 10,
            ]],
        ])->id;
    }
}
