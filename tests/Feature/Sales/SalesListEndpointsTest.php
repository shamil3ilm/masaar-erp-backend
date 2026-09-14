<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\BackdatedTransaction;
use App\Models\Sales\BulkSaleBatch;
use App\Models\Sales\Contact;
use App\Models\Sales\DeliveryMode;
use App\Models\Sales\PricingConditionType;
use App\Models\Sales\ProductBundle;
use App\Models\Sales\QuickSaleTemplate;
use App\Models\Sales\SeasonalCampaign;
use App\Models\Sales\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the filters and ordering of the backdated transaction, bulk sale,
 * quick sale template, bundle, campaign, shipment and condition type lists,
 * and keeps the contact's tax number out of the lists that embed a contact.
 */
class SalesListEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.backdated-transactions.view',
            'sales.bulk-sales.view',
            'sales.quick-sale-templates.view',
            'sales.offers.view',
            'sales.shipments.view',
            'sales.pricing.view',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'tax_number' => '300000000000003',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_backdated_transactions_filter_by_type_and_creator(): void
    {
        $match = BackdatedTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'transaction_type' => 'invoice',
            'created_by' => $this->user->id,
        ]);
        BackdatedTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'transaction_type' => 'payment',
            'created_by' => $this->user->id,
        ]);

        $response = $this->apiGet("/sales/bulk/backdated-transactions?transaction_type=invoice&created_by={$this->user->id}");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_bulk_sale_batches_filter_by_status_and_date(): void
    {
        $match = $this->batch(BulkSaleBatch::STATUS_DRAFT, '2025-01-10');
        $this->batch(BulkSaleBatch::STATUS_DRAFT, '2025-03-10');
        $this->batch('completed', '2025-01-12');

        $response = $this->apiGet('/sales/bulk/bulk-sales?status=draft&from_date=2025-01-01&to_date=2025-01-31');

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_quick_sale_templates_filter_by_active_and_show_the_customer_without_its_tax_number(): void
    {
        $active = QuickSaleTemplate::factory()->create([
            'organization_id' => $this->organization->id,
            'default_customer_id' => $this->customer->id,
            'is_active' => true,
        ]);
        $inactive = QuickSaleTemplate::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => false,
        ]);

        $this->assertSame(
            [$inactive->id],
            array_column($this->apiGet('/sales/bulk/quick-sale-templates?is_active=0')->assertOk()->json('data'), 'id')
        );

        $response = $this->apiGet('/sales/bulk/quick-sale-templates?is_active=1')->assertOk();
        $this->assertSame([$active->id], array_column($response->json('data'), 'id'));
        $this->assertSame($this->customer->contact_name, $response->json('data.0.default_customer.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $response->json('data.0.default_customer'));
    }

    public function test_bundles_are_listed_in_display_order_for_the_organization(): void
    {
        $second = ProductBundle::factory()->create(['organization_id' => $this->organization->id, 'display_order' => 2]);
        $first = ProductBundle::factory()->create(['organization_id' => $this->organization->id, 'display_order' => 1]);
        ProductBundle::factory()->create(['organization_id' => $this->otherOrg->id, 'display_order' => 0]);

        $response = $this->apiGet('/sales/offers/bundles');

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    public function test_campaigns_are_listed_latest_start_first_for_the_organization(): void
    {
        $earlier = SeasonalCampaign::factory()->create(['organization_id' => $this->organization->id, 'starts_at' => now()->addDays(2)]);
        $later = SeasonalCampaign::factory()->create(['organization_id' => $this->organization->id, 'starts_at' => now()->addDays(5)]);
        SeasonalCampaign::factory()->create(['organization_id' => $this->otherOrg->id, 'starts_at' => now()->addDays(9)]);

        $response = $this->apiGet('/sales/offers/campaigns');

        $response->assertOk();
        $this->assertSame([$later->id, $earlier->id], array_column($response->json('data'), 'id'));
    }

    public function test_shipments_show_the_contact_without_its_tax_number(): void
    {
        $mode = DeliveryMode::factory()->create(['organization_id' => $this->organization->id]);
        $shipment = Shipment::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'delivery_mode_id' => $mode->id,
            'contact_id' => $this->customer->id,
        ]);

        $response = $this->apiGet('/sales/payment-delivery/shipments');

        $response->assertOk();
        $this->assertSame([$shipment->id], array_column($response->json('data'), 'id'));
        $this->assertSame($mode->id, $response->json('data.0.delivery_mode.id'));
        $this->assertSame($this->customer->company_name, $response->json('data.0.contact.company_name'));
        $this->assertArrayNotHasKey('tax_number', $response->json('data.0.contact'));
    }

    public function test_condition_types_filter_by_class(): void
    {
        $discount = PricingConditionType::create([
            'organization_id' => $this->organization->id,
            'code' => 'K007',
            'name' => 'Customer discount',
            'condition_class' => 'discount',
            'calculation_type' => 'percentage',
            'step' => 20,
            'counter' => 0,
        ]);
        PricingConditionType::create([
            'organization_id' => $this->organization->id,
            'code' => 'PR00',
            'name' => 'Base price',
            'condition_class' => 'price',
            'calculation_type' => 'fixed',
            'step' => 10,
            'counter' => 0,
        ]);

        $response = $this->apiGet('/sales/pricing-conditions/condition-types?condition_class=discount');

        $response->assertOk();
        $this->assertSame([$discount->id], array_column($response->json('data'), 'id'));
    }

    private function batch(string $status, string $saleDate): BulkSaleBatch
    {
        return BulkSaleBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => $status,
            'sale_date' => $saleDate,
            'created_by' => $this->user->id,
        ]);
    }
}
