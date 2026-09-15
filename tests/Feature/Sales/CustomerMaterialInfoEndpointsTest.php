<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\CustomerMaterialInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the customer material info endpoints: the list and its filters,
 * create, show, update, delete and the cross-reference lookup. Keeps contacts
 * and products inside the caller's organization and the contact's tax number
 * out of the responses.
 */
class CustomerMaterialInfoEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Contact $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.customer-material-infos.view',
            'sales.customer-material-infos.create',
            'sales.customer-material-infos.edit',
            'sales.customer-material-infos.delete',
        ]);

        $this->otherOrg = Organization::factory()->create();
        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => '300000000000003',
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_the_list_filters_and_shows_the_contact_without_its_tax_number(): void
    {
        $bolt = $this->info(['customer_material_number' => 'BOLT-7', 'created_at' => now()->subDay()]);
        $other = Product::factory()->create(['organization_id' => $this->organization->id]);
        $nut = $this->info(['product_id' => $other->id, 'customer_material_number' => 'NUT-1', 'is_active' => false, 'created_at' => now()]);
        $foreignContact = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $this->info([
            'organization_id' => $this->otherOrg->id,
            'contact_id' => $foreignContact->id,
            'product_id' => Product::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $ids = fn (string $query) => array_column($this->apiGet('/sales/customer-material-infos'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$nut->id, $bolt->id], $ids(''));
        $this->assertSame([$bolt->id], $ids('?active_only=1'));
        $this->assertSame([$bolt->id], $ids('?search=BOLT'));
        $this->assertSame([$nut->id], $ids("?product_id={$other->id}"));

        $listed = $this->apiGet('/sales/customer-material-infos');
        $this->assertSame($this->customer->company_name, $listed->json('data.0.contact.company_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.contact'));
        $this->assertSame($other->id, $listed->json('data.0.product.id'));
    }

    public function test_an_info_is_created_shown_updated_and_deleted(): void
    {
        $created = $this->apiPost('/sales/customer-material-infos', [
            'contact_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'customer_material_number' => 'CM-100',
        ]);
        $created->assertStatus(201)
            ->assertJsonPath('message', 'Customer material info created.')
            ->assertJsonPath('data.organization_id', $this->organization->id);
        $this->assertArrayNotHasKey('tax_number', $created->json('data.contact'));
        $id = $created->json('data.id');

        $shown = $this->apiGet("/sales/customer-material-infos/{$id}")->assertOk();
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.contact'));

        $updated = $this->apiPut("/sales/customer-material-infos/{$id}", ['delivery_lead_time_days' => 4]);
        $updated->assertOk()
            ->assertJsonPath('message', 'Customer material info updated.')
            ->assertJsonPath('data.delivery_lead_time_days', 4);
        $this->assertArrayNotHasKey('tax_number', $updated->json('data.contact'));

        $this->apiDelete("/sales/customer-material-infos/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Customer material info deleted.');
        $this->assertNull(CustomerMaterialInfo::find($id));
    }

    public function test_another_organizations_contact_product_and_info_are_refused(): void
    {
        $response = $this->apiPost('/sales/customer-material-infos', [
            'contact_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            'product_id' => Product::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('contact_id', $response->json('errors') ?? []);
        $this->assertArrayHasKey('product_id', $response->json('errors') ?? []);

        $foreignContact = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreign = $this->info([
            'organization_id' => $this->otherOrg->id,
            'contact_id' => $foreignContact->id,
            'product_id' => Product::factory()->create(['organization_id' => $this->otherOrg->id])->id,
        ]);
        $this->apiGet("/sales/customer-material-infos/{$foreign->id}")->assertNotFound();
        $this->apiDelete("/sales/customer-material-infos/{$foreign->id}")->assertNotFound();
    }

    public function test_the_lookup_finds_the_active_cross_reference(): void
    {
        $this->info(['customer_material_number' => 'CM-9']);

        $this->apiGet("/sales/customer-material-infos/lookup?contact_id={$this->customer->id}&customer_material_number=CM-9")
            ->assertOk()
            ->assertJsonPath('data.product.id', $this->product->id);

        $this->apiGet("/sales/customer-material-infos/lookup?contact_id={$this->customer->id}&customer_material_number=NONE")
            ->assertNotFound()
            ->assertJsonPath('error.message', 'No customer material info found for the given criteria.');

        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $refused = $this->apiGet("/sales/customer-material-infos/lookup?contact_id={$foreign->id}");
        $refused->assertStatus(422);
        $this->assertArrayHasKey('contact_id', $refused->json('errors') ?? []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function info(array $attributes = []): CustomerMaterialInfo
    {
        $info = new CustomerMaterialInfo();
        $info->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'customer_material_number' => 'CM-1',
            'is_active' => true,
        ], $attributes))->save();

        return $info;
    }
}
