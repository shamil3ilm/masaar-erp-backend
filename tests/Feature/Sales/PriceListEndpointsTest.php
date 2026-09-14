<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\PriceList;
use App\Models\Sales\PriceListAssignment;
use App\Models\Sales\PriceListItem;
use App\Models\Sales\PriceVolumeBreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the price list endpoints: the list and its filters, creating and editing
 * a list with its items, assignment to a contact, item import and price
 * resolution, and refuses another organization's products and contacts.
 */
class PriceListEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Product $product;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.price-lists.view', 'sales.price-lists.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
        $this->contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'currency_code' => 'SAR',
        ]);
    }

    public function test_the_list_shows_the_organizations_lists_latest_first_and_filters(): void
    {
        $older = $this->priceList(['code' => 'RETAIL', 'name' => 'Retail', 'created_at' => now()->subDay()]);
        $newer = $this->priceList(['code' => 'WHOLE', 'name' => 'Wholesale', 'currency_code' => 'USD', 'created_at' => now()]);
        $expired = $this->priceList([
            'code' => 'OLD', 'name' => 'Old', 'is_active' => false,
            'valid_from' => now()->subYear(), 'valid_until' => now()->subMonth(), 'created_at' => now()->subDays(2),
        ]);
        PriceList::create(['organization_id' => $this->otherOrg->id, 'name' => 'Foreign', 'code' => 'F', 'currency_code' => 'SAR']);

        $ids = fn (string $query) => array_column($this->apiGet('/sales/price-lists'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$newer->id, $older->id, $expired->id], $ids(''));
        $this->assertSame([$older->id, $expired->id], $ids('?currency_code=SAR'));
        $this->assertSame([$expired->id], $ids('?is_active=0'));
        $this->assertSame([$newer->id], $ids('?search=WHOLE'));
        $this->assertSame([$newer->id, $older->id], $ids('?valid_now=1'));
    }

    public function test_a_price_list_is_created_with_its_items(): void
    {
        $response = $this->apiPost('/sales/price-lists', [
            'name' => 'Retail',
            'code' => 'RETAIL',
            'currency_code' => 'SAR',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'items' => [
                ['product_id' => $this->product->id, 'unit_price' => 100, 'min_quantity' => 1, 'discount_pct' => 5],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Price list created.')
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.items.0.product_id', $this->product->id)
            ->assertJsonPath('data.items.0.discount_percent', '5.00')
            ->assertJsonPath('data.assignments', []);
        $this->assertSame('2026-12-31', PriceList::first()->valid_until->toDateString());
    }

    public function test_items_of_another_organizations_product_are_refused(): void
    {
        $foreign = Product::factory()->create(['organization_id' => $this->otherOrg->id]);
        $priceList = $this->priceList();

        $created = $this->apiPost('/sales/price-lists', [
            'name' => 'Retail', 'code' => 'RETAIL', 'currency_code' => 'SAR', 'valid_from' => '2026-01-01',
            'items' => [['product_id' => $foreign->id, 'unit_price' => 1]],
        ]);
        $created->assertStatus(422);
        $this->assertArrayHasKey('items.0.product_id', $created->json('errors') ?? []);

        $imported = $this->apiPost("/sales/price-lists/{$priceList->id}/import-items", [
            'items' => [['product_id' => $foreign->id, 'unit_price' => 1]],
        ]);
        $imported->assertStatus(422);
        $this->assertArrayHasKey('items.0.product_id', $imported->json('errors') ?? []);
    }

    public function test_a_price_list_shows_its_items_assignments_and_volume_breaks(): void
    {
        $priceList = $this->priceList();
        $item = $this->item($priceList, ['unit_price' => 50]);
        $assignment = $this->assign($priceList, PriceListAssignment::TYPE_ALL);
        $break = $this->volumeBreak($priceList, 10, 40);

        $this->apiGet("/sales/price-lists/{$priceList->id}")
            ->assertOk()
            ->assertJsonPath('data.price_list.id', $priceList->id)
            ->assertJsonPath('data.items.0.id', $item->id)
            ->assertJsonPath('data.assignments.0.id', $assignment->id)
            ->assertJsonPath('data.volume_breaks.0.id', $break->id);
    }

    public function test_a_price_list_is_renamed_its_items_replaced_and_deleted(): void
    {
        $priceList = $this->priceList();
        $this->item($priceList, ['unit_price' => 50]);

        $this->apiPut("/sales/price-lists/{$priceList->id}", [
            'name' => 'Renamed',
            'items' => [['product_id' => $this->product->id, 'unit_price' => 75]],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Price list updated.')
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame(['75.0000'], PriceListItem::where('price_list_id', $priceList->id)->pluck('unit_price')->all());

        $this->apiDelete("/sales/price-lists/{$priceList->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Price list deleted.');
        $this->assertNull(PriceList::find($priceList->id));
    }

    public function test_another_organizations_price_list_is_not_found(): void
    {
        $foreign = PriceList::create(['organization_id' => $this->otherOrg->id, 'name' => 'Foreign', 'code' => 'F', 'currency_code' => 'SAR']);

        $this->apiGet("/sales/price-lists/{$foreign->id}")->assertNotFound();
        $this->apiDelete("/sales/price-lists/{$foreign->id}")->assertNotFound();
    }

    public function test_assigning_a_contact_replaces_its_previous_assignment_and_refuses_a_foreign_contact(): void
    {
        $first = $this->priceList(['code' => 'A']);
        $second = $this->priceList(['code' => 'B']);
        $this->assign($first, PriceListAssignment::TYPE_CONTACT, $this->contact->id);

        $this->apiPost("/sales/price-lists/{$second->id}/assign-contact", ['contact_id' => $this->contact->id])
            ->assertOk()
            ->assertJsonPath('message', 'Price list assigned to contact.')
            ->assertJsonPath('data.price_list_id', $second->id)
            ->assertJsonPath('data.assignment_type', PriceListAssignment::TYPE_CONTACT);

        $this->assertSame([$second->id], PriceListAssignment::where('assignment_id', $this->contact->id)->pluck('price_list_id')->all());

        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $refused = $this->apiPost("/sales/price-lists/{$second->id}/assign-contact", ['contact_id' => $foreign->id]);
        $refused->assertStatus(422);
        $this->assertArrayHasKey('contact_id', $refused->json('errors') ?? []);
    }

    public function test_imported_items_are_upserted_by_product_and_minimum_quantity(): void
    {
        $priceList = $this->priceList();
        $this->item($priceList, ['unit_price' => 50, 'min_quantity' => 1]);

        $this->apiPost("/sales/price-lists/{$priceList->id}/import-items", [
            'items' => [
                ['product_id' => $this->product->id, 'unit_price' => 60, 'min_quantity' => 1],
                ['product_id' => $this->product->id, 'unit_price' => 55, 'min_quantity' => 10, 'discount_pct' => 2],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', '2 items imported.')
            ->assertJsonPath('data.imported', 2);

        $this->assertSame(
            ['60.0000', '55.0000'],
            PriceListItem::where('price_list_id', $priceList->id)->orderBy('min_quantity')->pluck('unit_price')->all()
        );
    }

    public function test_the_price_resolves_from_the_contacts_list_before_the_general_one(): void
    {
        $general = $this->priceList(['code' => 'ALL']);
        $this->item($general, ['unit_price' => 100, 'discount_percent' => 10]);
        $this->assign($general, PriceListAssignment::TYPE_ALL);

        $url = "/sales/price-lists/resolve-price?contact_id={$this->contact->id}&product_id={$this->product->id}&currency=SAR";

        $this->apiGet($url)
            ->assertOk()
            ->assertJsonPath('data.source', 'all')
            ->assertJsonPath('data.price_list_id', $general->id)
            ->assertJsonPath('data.effective_price', 90);

        $own = $this->priceList(['code' => 'VIP']);
        $this->item($own, ['unit_price' => 80]);
        $this->assign($own, PriceListAssignment::TYPE_CONTACT, $this->contact->id);

        $this->apiGet($url)
            ->assertOk()
            ->assertJsonPath('data.source', 'contact')
            ->assertJsonPath('data.effective_price', 80);

        $this->volumeBreak($own, 10, 70);

        $this->apiGet($url.'&quantity=10')
            ->assertOk()
            ->assertJsonPath('data.source', 'contact.volume_break')
            ->assertJsonPath('data.effective_price', 70);
    }

    public function test_resolving_without_a_list_is_not_found_and_a_foreign_contact_is_refused(): void
    {
        $this->apiGet("/sales/price-lists/resolve-price?contact_id={$this->contact->id}&product_id={$this->product->id}&currency=SAR")
            ->assertNotFound()
            ->assertJsonPath('error.message', 'No applicable price list found for the given parameters.');

        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $refused = $this->apiGet("/sales/price-lists/resolve-price?contact_id={$foreign->id}&product_id={$this->product->id}");
        $refused->assertStatus(422);
        $this->assertArrayHasKey('contact_id', $refused->json('errors') ?? []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function priceList(array $attributes = []): PriceList
    {
        $createdAt = Arr::pull($attributes, 'created_at');

        $priceList = PriceList::create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'Base',
            'code' => 'BASE',
            'currency_code' => 'SAR',
            'valid_from' => now()->subMonth(),
            'valid_until' => null,
            'is_active' => true,
        ], $attributes));

        if ($createdAt !== null) {
            $priceList->forceFill(['created_at' => $createdAt])->save();
        }

        return $priceList;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(PriceList $priceList, array $attributes): PriceListItem
    {
        return PriceListItem::create(array_merge([
            'price_list_id' => $priceList->id,
            'product_id' => $this->product->id,
            'min_quantity' => 1,
        ], $attributes));
    }

    private function assign(PriceList $priceList, string $type, ?int $assignmentId = null): PriceListAssignment
    {
        return PriceListAssignment::create([
            'price_list_id' => $priceList->id,
            'assignment_type' => $type,
            'assignment_id' => $assignmentId,
            'priority' => 0,
        ]);
    }

    private function volumeBreak(PriceList $priceList, int $minQty, int $unitPrice): PriceVolumeBreak
    {
        return PriceVolumeBreak::create([
            'price_list_id' => $priceList->id,
            'product_id' => $this->product->id,
            'min_qty' => $minQty,
            'unit_price' => $unitPrice,
            'discount_pct' => 0,
        ]);
    }
}
