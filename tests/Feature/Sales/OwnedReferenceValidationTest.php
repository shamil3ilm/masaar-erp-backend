<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\BankAccount;
use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\BulkSaleBatch;
use App\Models\Sales\Contact;
use App\Models\Sales\DeliveryMode;
use App\Models\Sales\PriceList;
use App\Models\Sales\PricingConditionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Sales endpoints refuse an id that belongs to another organization.
 *
 * Each case sends the same request twice: once with a row of a second
 * organization, which the field must reject, and once with the caller's own
 * row, which the field must accept. The second call only asserts that the
 * field itself passed validation, so a case stays about the rule rather than
 * about whatever the endpoint does afterwards.
 */
class OwnedReferenceValidationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.atp.check',
            'sales.bulk-sales.view',
            'sales.bulk-sales.manage',
            'sales.delivery-modes.manage',
            'sales.pricing.view',
            'sales.pricing.manage',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_availability_is_not_checked_for_another_organizations_product_or_warehouse(): void
    {
        $url = '/api/v1/sales/atp/check';

        $this->postJson($url, [
            'product_id' => $this->product($this->otherOrg)->id,
            'warehouse_id' => $this->warehouse($this->otherOrg)->id,
            'quantity' => 5,
            'requested_date' => '2026-03-01',
        ], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'warehouse_id']);

        $this->postJson($url, [
            'product_id' => $this->product()->id,
            'warehouse_id' => $this->warehouse()->id,
            'quantity' => 5,
            'requested_date' => '2026-03-01',
        ], $this->authHeaders())
            ->assertJsonMissingValidationErrors(['product_id', 'warehouse_id']);
    }

    public function test_an_order_is_not_checked_against_another_organizations_contact_or_lines(): void
    {
        $url = '/api/v1/sales/atp/check-order';

        $this->postJson($url, $this->orderPayload(
            $this->contact($this->otherOrg)->id,
            $this->product($this->otherOrg)->id,
            $this->warehouse($this->otherOrg)->id,
        ), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'lines.0.product_id', 'lines.0.warehouse_id']);

        $this->postJson($url, $this->orderPayload(
            $this->contact()->id,
            $this->product()->id,
            $this->warehouse()->id,
        ), $this->authHeaders())
            ->assertJsonMissingValidationErrors(['contact_id', 'lines.0.product_id', 'lines.0.warehouse_id']);
    }

    public function test_a_bulk_sale_is_not_created_against_another_organizations_rows(): void
    {
        $url = '/api/v1/sales/bulk/bulk-sales';

        $this->postJson($url, $this->bulkSalePayload(
            $this->branch($this->otherOrg)->id,
            $this->bankAccount($this->otherOrg)->id,
            $this->contact($this->otherOrg)->id,
            $this->product($this->otherOrg)->id,
        ), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'branch_id', 'bank_account_id', 'items.0.customer_id', 'items.0.product_id',
            ]);

        $this->postJson($url, $this->bulkSalePayload(
            $this->branch->id,
            $this->bankAccount()->id,
            $this->contact()->id,
            $this->product()->id,
        ), $this->authHeaders())
            ->assertJsonMissingValidationErrors([
                'branch_id', 'bank_account_id', 'items.0.customer_id', 'items.0.product_id',
            ]);
    }

    public function test_a_bulk_sale_is_not_updated_against_another_organizations_rows(): void
    {
        $batch = BulkSaleBatch::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'draft',
        ]);

        $url = "/api/v1/sales/bulk/bulk-sales/{$batch->id}";

        $this->putJson($url, $this->bulkSaleUpdatePayload(
            $this->bankAccount($this->otherOrg)->id,
            $this->contact($this->otherOrg)->id,
            $this->product($this->otherOrg)->id,
        ), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_account_id', 'items.0.customer_id', 'items.0.product_id']);

        $this->putJson($url, $this->bulkSaleUpdatePayload(
            $this->bankAccount()->id,
            $this->contact()->id,
            $this->product()->id,
        ), $this->authHeaders())
            ->assertJsonMissingValidationErrors(['bank_account_id', 'items.0.customer_id', 'items.0.product_id']);
    }

    public function test_shipping_is_not_costed_on_another_organizations_delivery_mode(): void
    {
        $url = '/api/v1/sales/payment-delivery/delivery-modes/calculate-shipping';

        $this->postJson($url, ['delivery_mode_id' => $this->deliveryMode($this->otherOrg)->id], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_mode_id');

        $this->postJson($url, ['delivery_mode_id' => $this->deliveryMode()->id], $this->authHeaders())
            ->assertJsonMissingValidationErrors('delivery_mode_id');
    }

    public function test_a_condition_record_is_not_created_against_another_organizations_rows(): void
    {
        $url = '/api/v1/sales/pricing-conditions/condition-records';

        $this->postJson($url, $this->conditionRecordPayload(
            $this->conditionType($this->otherOrg)->id,
            $this->contact($this->otherOrg)->id,
            $this->product($this->otherOrg)->id,
            $this->priceList($this->otherOrg)->id,
        ), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition_type_id', 'customer_id', 'product_id', 'price_list_id']);

        $this->postJson($url, $this->conditionRecordPayload(
            $this->conditionType()->id,
            $this->contact()->id,
            $this->product()->id,
            $this->priceList()->id,
        ), $this->authHeaders())
            ->assertJsonMissingValidationErrors(['condition_type_id', 'customer_id', 'product_id', 'price_list_id']);
    }

    public function test_a_price_is_not_resolved_for_another_organizations_product_or_customer(): void
    {
        $url = '/api/v1/sales/pricing-conditions/resolve';

        $this->postJson($url, [
            'product_id' => $this->product($this->otherOrg)->id,
            'customer_id' => $this->contact($this->otherOrg)->id,
            'quantity' => 1,
            'currency' => 'SAR',
        ], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'customer_id']);

        $this->postJson($url, [
            'product_id' => $this->product()->id,
            'customer_id' => $this->contact()->id,
            'quantity' => 1,
            'currency' => 'SAR',
        ], $this->authHeaders())
            ->assertJsonMissingValidationErrors(['product_id', 'customer_id']);
    }

    /** @return array<string, mixed> */
    private function orderPayload(int $contactId, int $productId, int $warehouseId): array
    {
        return [
            'contact_id' => $contactId,
            'lines' => [[
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 2,
                'requested_date' => '2026-03-01',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function bulkSalePayload(int $branchId, int $bankAccountId, int $customerId, int $productId): array
    {
        return array_merge(
            ['branch_id' => $branchId],
            $this->bulkSaleUpdatePayload($bankAccountId, $customerId, $productId),
        );
    }

    /** @return array<string, mixed> */
    private function bulkSaleUpdatePayload(int $bankAccountId, int $customerId, int $productId): array
    {
        return [
            'sale_date' => '2026-03-01',
            'bank_account_id' => $bankAccountId,
            'items' => [[
                'customer_id' => $customerId,
                'product_id' => $productId,
                'description' => 'Annual licence',
                'quantity' => 1,
                'unit_price' => 250,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function conditionRecordPayload(int $typeId, int $customerId, int $productId, int $priceListId): array
    {
        return [
            'condition_type_id' => $typeId,
            'key_combination' => 'customer_material',
            'customer_id' => $customerId,
            'product_id' => $productId,
            'price_list_id' => $priceListId,
            'rate' => 12.5,
            'currency_code' => 'SAR',
        ];
    }

    private function product(?Organization $organization = null): Product
    {
        return Product::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function warehouse(?Organization $organization = null): Warehouse
    {
        return Warehouse::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function contact(?Organization $organization = null): Contact
    {
        return Contact::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function branch(Organization $organization): Branch
    {
        return Branch::factory()->create(['organization_id' => $organization->id]);
    }

    private function bankAccount(?Organization $organization = null): BankAccount
    {
        return BankAccount::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function deliveryMode(?Organization $organization = null): DeliveryMode
    {
        return DeliveryMode::factory()->create(['organization_id' => $this->idOf($organization)]);
    }

    private function priceList(?Organization $organization = null): PriceList
    {
        return PriceList::create([
            'organization_id' => $this->idOf($organization),
            'name' => 'Retail',
            'code' => 'PL'.fake()->unique()->numerify('####'),
            'type' => 'selling',
            'currency_code' => 'SAR',
        ]);
    }

    private function conditionType(?Organization $organization = null): PricingConditionType
    {
        return PricingConditionType::create([
            'organization_id' => $this->idOf($organization),
            'code' => 'PR'.fake()->unique()->numerify('####'),
            'name' => 'Base price',
            'condition_class' => 'price',
            'calculation_type' => 'fixed',
        ]);
    }

    private function idOf(?Organization $organization): int
    {
        return ($organization ?? $this->organization)->id;
    }
}
