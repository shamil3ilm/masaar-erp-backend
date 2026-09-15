<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\OutlineAgreement;
use App\Models\Purchase\OutlineAgreementItem;
use App\Models\Purchase\OutlineAgreementRelease;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Outline agreement endpoints: ids a request names must be the caller's
 * organization's or the agreement's own, the vendor's tax number leaves
 * masked, and releases add to the released totals as one change.
 */
class OutlineAgreementEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/outline-agreements';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.outline-agreements.view', 'purchase.outline-agreements.manage']);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
    }

    public function test_index_lists_only_this_organizations_agreements_with_masked_vendors(): void
    {
        $this->agreement($this->organization, $this->supplier);
        $this->agreement($this->organization, $this->supplier);
        $other = Organization::factory()->create();
        $this->agreement($other, Contact::factory()->supplier()->create(['organization_id' => $other->id]));

        $response = $this->apiGet($this->baseUrl)
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.vendor.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_store_refuses_another_organizations_vendor(): void
    {
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost($this->baseUrl, [
            'vendor_id' => $foreignSupplier->id,
            'agreement_number' => 'OA-1',
            'agreement_type' => 'value_contract',
            'valid_from' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('vendor_id');

        $this->assertSame(0, OutlineAgreement::withoutGlobalScopes()->count());
    }

    public function test_show_masks_the_vendors_tax_number(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier);

        $response = $this->apiGet("{$this->baseUrl}/{$agreement->id}")
            ->assertOk()
            ->assertJsonPath('data.vendor.id', $this->supplier->id)
            ->assertJsonPath('data.vendor.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_show_of_another_organizations_agreement_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $agreement = $this->agreement($other, Contact::factory()->supplier()->create(['organization_id' => $other->id]));

        $this->apiGet("{$this->baseUrl}/{$agreement->id}")->assertNotFound();
    }

    public function test_adding_an_item_refuses_another_organizations_product(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier);
        $foreignProduct = Product::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/items", ['product_id' => $foreignProduct->id, 'line_number' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');

        $this->assertSame(0, OutlineAgreementItem::withoutGlobalScopes()->count());
    }

    public function test_a_release_refuses_an_item_of_another_agreement_and_another_organizations_order(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier);
        $otherItem = $this->item($this->agreement($this->organization, $this->supplier));
        $other = Organization::factory()->create();
        $foreignOrder = PurchaseOrder::factory()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/releases", [
            'outline_agreement_item_id' => $otherItem->id,
            'purchase_order_id' => $foreignOrder->id,
            'release_date' => now()->toDateString(),
            'release_quantity' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors(['outline_agreement_item_id', 'purchase_order_id']);

        $this->assertSame(0, OutlineAgreementRelease::withoutGlobalScopes()->count());
        $this->assertSame('0.0000', $otherItem->fresh()->released_quantity);
    }

    public function test_a_release_adds_to_the_agreement_and_item_totals(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier);
        $item = $this->item($agreement);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/releases", [
            'outline_agreement_item_id' => $item->id,
            'release_date' => now()->toDateString(),
            'release_quantity' => 5.5,
            'release_value' => 110.25,
        ])->assertCreated()->assertJsonPath('data.outline_agreement_item_id', $item->id);

        $agreement->refresh();
        $item->refresh();

        $this->assertSame('5.5000', $agreement->released_quantity);
        $this->assertSame('110.2500', $agreement->released_value);
        $this->assertSame('5.5000', $item->released_quantity);
        $this->assertSame('110.2500', $item->released_value);
    }

    public function test_activating_a_cancelled_agreement_is_refused(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier, OutlineAgreement::STATUS_CANCELLED);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/activate")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft outline agreements can be activated.');

        $this->assertSame(OutlineAgreement::STATUS_CANCELLED, $agreement->fresh()->status);
    }

    public function test_a_draft_is_activated_and_then_cancelled(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', OutlineAgreement::STATUS_ACTIVE);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OutlineAgreement::STATUS_CANCELLED);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft or active outline agreements can be cancelled.');
    }

    private function agreement(Organization $organization, Contact $supplier, string $status = OutlineAgreement::STATUS_DRAFT): OutlineAgreement
    {
        return OutlineAgreement::create([
            'organization_id' => $organization->id,
            'vendor_id' => $supplier->id,
            'agreement_number' => 'OA-'.fake()->unique()->numerify('#####'),
            'agreement_type' => 'value_contract',
            'status' => $status,
            'valid_from' => now()->toDateString(),
            'released_quantity' => 0,
            'released_value' => 0,
        ]);
    }

    private function item(OutlineAgreement $agreement): OutlineAgreementItem
    {
        return OutlineAgreementItem::create([
            'organization_id' => $agreement->organization_id,
            'outline_agreement_id' => $agreement->id,
            'line_number' => 1,
            'released_quantity' => 0,
            'released_value' => 0,
        ]);
    }
}
