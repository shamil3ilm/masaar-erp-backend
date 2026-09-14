<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use App\Models\Sales\DeliveryMode;
use App\Models\Sales\Invoice;
use App\Models\Sales\QuickSaleTemplate;
use App\Models\Tax\TaxCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Invoice lines, quick sale templates and shipments accept only ids of rows
 * that belong to the caller's organization.
 */
class OwnedReferenceRulesTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.invoices.create',
            'sales.invoices.edit',
            'sales.invoices.credit-note',
            'sales.quick-sale-templates.manage',
            'sales.shipments.create',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_an_invoice_line_refuses_another_organizations_tax_category_account_and_warehouse(): void
    {
        $response = $this->apiPost('/sales/invoices', $this->invoicePayload([
            'tax_category_id' => TaxCategory::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'account_id' => Account::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]));

        $response->assertStatus(422);
        $this->assertErrorsOn($response, ['lines.0.tax_category_id', 'lines.0.account_id', 'lines.0.warehouse_id']);
    }

    public function test_an_invoice_line_accepts_the_organizations_own_references_and_product_variant(): void
    {
        $product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => Product::TYPE_SERVICE,
            'track_inventory' => false,
        ]);

        $response = $this->apiPost('/sales/invoices', $this->invoicePayload([
            'product_id' => $product->id,
            'variant_id' => $this->variantOf($product),
            'tax_category_id' => TaxCategory::factory()->create(['organization_id' => $this->organization->id])->id,
            'account_id' => Account::factory()->create(['organization_id' => $this->organization->id])->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->organization->id])->id,
        ]));

        $response->assertStatus(201);
    }

    public function test_an_invoice_line_refuses_a_variant_of_another_organizations_product(): void
    {
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/sales/invoices', $this->invoicePayload([
            'variant_id' => $this->variantOf($foreignProduct),
        ]));

        $response->assertStatus(422);
        $this->assertErrorsOn($response, ['lines.0.variant_id']);
    }

    public function test_an_invoice_update_and_a_credit_note_refuse_another_organizations_references(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $updated = $this->apiPut("/sales/invoices/{$invoice->id}", [
            'lines' => [$this->line([
                'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->otherOrg->id])->id,
                'account_id' => Account::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            ])],
        ]);

        $updated->assertStatus(422);
        $this->assertErrorsOn($updated, ['lines.0.warehouse_id', 'lines.0.account_id']);

        $credited = $this->apiPost("/sales/invoices/{$invoice->id}/credit-note", [
            'lines' => [$this->line([
                'tax_category_id' => TaxCategory::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            ])],
        ]);

        $credited->assertStatus(422);
        $this->assertErrorsOn($credited, ['lines.0.tax_category_id']);
    }

    public function test_a_quick_sale_template_refuses_another_organizations_product_and_customer(): void
    {
        $foreign = [
            'default_items' => [[
                'product_id' => Product::factory()->create(['organization_id' => $this->otherOrg->id])->id,
                'description' => 'Item',
                'quantity' => 1,
                'unit_price' => 10,
            ]],
            'default_customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ];

        $created = $this->apiPost('/sales/bulk/quick-sale-templates', ['name' => 'Counter'] + $foreign);
        $created->assertStatus(422);
        $this->assertErrorsOn($created, ['default_items.0.product_id', 'default_customer_id']);

        $template = QuickSaleTemplate::factory()->create(['organization_id' => $this->organization->id]);

        $updated = $this->apiPut("/sales/bulk/quick-sale-templates/{$template->id}", $foreign);
        $updated->assertStatus(422);
        $this->assertErrorsOn($updated, ['default_items.0.product_id', 'default_customer_id']);
    }

    public function test_a_quick_sale_template_accepts_the_organizations_own_product_and_customer(): void
    {
        $response = $this->apiPost('/sales/bulk/quick-sale-templates', [
            'name' => 'Counter',
            'default_items' => [[
                'product_id' => Product::factory()->create(['organization_id' => $this->organization->id])->id,
                'description' => 'Item',
                'quantity' => 1,
                'unit_price' => 10,
            ]],
            'default_customer_id' => $this->customer->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_a_shipment_refuses_another_organizations_contact_delivery_mode_and_product(): void
    {
        $response = $this->apiPost('/sales/payment-delivery/shipments', [
            'delivery_mode_id' => DeliveryMode::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'contact_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'source_type' => 'invoice',
            'shipping_address' => ['line1' => 'Street 1'],
            'items' => [[
                'product_id' => Product::factory()->create(['organization_id' => $this->otherOrg->id])->id,
                'quantity' => 1,
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertErrorsOn($response, ['delivery_mode_id', 'contact_id', 'items.0.product_id']);
    }

    public function test_a_shipment_accepts_the_organizations_own_contact_delivery_mode_and_product(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->apiPost('/sales/payment-delivery/shipments', [
            'delivery_mode_id' => DeliveryMode::factory()->create(['organization_id' => $this->organization->id])->id,
            'contact_id' => $this->customer->id,
            'source_type' => 'invoice',
            'shipping_address' => ['line1' => 'Street 1'],
            'currency_code' => 'SAR',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertStatus(201);
        $this->assertSame($product->id, $response->json('data.items.0.product_id'));
    }

    /**
     * A variant row of the product. Inserted directly: the variant factory
     * writes columns the table does not have.
     */
    private function variantOf(Product $product): int
    {
        return DB::table('product_variants')->insertGetId([
            'product_id' => $product->id,
            'sku' => 'VAR-'.$product->id,
            'name' => 'Large',
            'attributes' => json_encode(['size' => 'L']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function invoicePayload(array $line): array
    {
        return [
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [$this->line($line)],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function line(array $overrides): array
    {
        return array_merge([
            'description' => 'Consulting',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 15,
        ], $overrides);
    }

    /**
     * @param  list<string>  $fields
     */
    private function assertErrorsOn(\Illuminate\Testing\TestResponse $response, array $fields): void
    {
        $errors = $response->json('errors') ?? [];

        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $errors, "No validation error on {$field}: ".json_encode($errors));
        }
    }
}
