<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\CpqConfigurableProduct;
use App\Models\Sales\CpqConfiguration;
use App\Models\Sales\CpqOption;
use App\Models\Sales\CpqOptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the CPQ endpoints: configurable products, their option groups, options
 * and rules, pricing a configuration and saving it. Keeps every child row, the
 * selected options and the linked contact inside the caller's organization,
 * and the contact's tax number out of the responses.
 */
class CpqEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Contact $contact;
    private CpqConfigurableProduct $laptop;
    private CpqOptionGroup $memory;
    private CpqOption $sixteenGb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.cpq.view', 'sales.cpq.manage']);

        $this->otherOrg = Organization::factory()->create();
        $this->contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => '300000000000003',
        ]);

        [$this->laptop, $this->memory, $this->sixteenGb] = $this->configurable($this->organization);
    }

    public function test_the_product_list_shows_the_organizations_active_products_with_their_options(): void
    {
        $inactive = $this->configurable($this->organization, ['is_active' => false])[0];
        $this->configurable($this->otherOrg);

        $active = $this->cpq('GET', 'sales.cpq.products')->assertOk();
        $this->assertSame([$this->laptop->id], array_column($active->json('data'), 'id'));
        $this->assertSame($this->sixteenGb->id, $active->json('data.0.option_groups.0.options.0.id'));

        $all = $this->cpq('GET', 'sales.cpq.products', [], ['active_only' => 0]);
        $this->assertEqualsCanonicalizing([$this->laptop->id, $inactive->id], array_column($all->json('data'), 'id'));
    }

    public function test_a_product_is_created_shown_and_updated_and_a_foreign_one_is_not_found(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->organization->id]);

        $created = $this->cpq('POST', 'sales.cpq.products.store', [], [
            'product_id' => $product->id, 'name' => 'Desktop', 'base_price' => 800, 'currency_code' => 'SAR',
        ]);
        $created->assertStatus(201)->assertJsonPath('data.product.id', $product->id);
        $id = $created->json('data.id');

        $this->cpq('PUT', 'sales.cpq.products.update', ['id' => $id], ['name' => 'Tower'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tower');

        $this->cpq('GET', 'sales.cpq.products.show', ['id' => $this->laptop->id])
            ->assertOk()
            ->assertJsonPath('data.option_groups.0.id', $this->memory->id);

        $foreign = $this->configurable($this->otherOrg)[0];
        $this->cpq('GET', 'sales.cpq.products.show', ['id' => $foreign->id])->assertNotFound();
        $this->cpq('PUT', 'sales.cpq.products.update', ['id' => $foreign->id], ['name' => 'X'])->assertNotFound();
    }

    public function test_a_product_of_another_organization_cannot_be_made_configurable(): void
    {
        $foreign = Product::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->cpq('POST', 'sales.cpq.products.store', [], [
            'product_id' => $foreign->id, 'name' => 'Desktop', 'base_price' => 800, 'currency_code' => 'SAR',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('product_id', $response->json('errors') ?? []);
    }

    public function test_option_groups_and_options_are_added_and_listed_in_order(): void
    {
        $group = $this->cpq('POST', 'sales.cpq.option-groups.store', ['productId' => $this->laptop->id], [
            'group_code' => 'SSD', 'name' => 'Storage', 'sort_order' => 0,
        ]);
        $group->assertStatus(201)->assertJsonPath('data.cpq_configurable_product_id', $this->laptop->id);

        $this->cpq('POST', 'sales.cpq.options.store', ['groupId' => $group->json('data.id')], [
            'option_code' => '1TB', 'name' => '1 TB', 'price_modifier_type' => 'fixed', 'price_modifier_value' => 50,
        ])->assertStatus(201)->assertJsonPath('data.cpq_option_group_id', $group->json('data.id'));

        $listed = $this->cpq('GET', 'sales.cpq.option-groups', ['productId' => $this->laptop->id])->assertOk();
        $this->assertSame([$group->json('data.id'), $this->memory->id], array_column($listed->json('data'), 'id'));
    }

    public function test_another_organizations_option_groups_and_rules_are_neither_read_nor_extended(): void
    {
        [$foreign, $foreignGroup] = $this->configurable($this->otherOrg);

        $this->cpq('GET', 'sales.cpq.option-groups', ['productId' => $foreign->id])->assertNotFound();
        $this->cpq('GET', 'sales.cpq.pricing-rules', ['productId' => $foreign->id])->assertNotFound();
        $this->cpq('GET', 'sales.cpq.constraint-rules', ['productId' => $foreign->id])->assertNotFound();

        $this->cpq('POST', 'sales.cpq.options.store', ['groupId' => $foreignGroup->id], [
            'option_code' => 'X', 'name' => 'X',
        ])->assertNotFound();
        $this->assertSame(1, CpqOption::where('cpq_option_group_id', $foreignGroup->id)->count());
    }

    public function test_a_pricing_rule_without_a_condition_is_added_and_listed_by_priority(): void
    {
        $this->cpq('POST', 'sales.cpq.pricing-rules.store', ['productId' => $this->laptop->id], [
            'rule_name' => 'Late', 'discount_type' => 'fixed', 'discount_value' => 10, 'priority' => 20,
        ])->assertStatus(201)->assertJsonPath('data.condition_json', []);

        $this->cpq('POST', 'sales.cpq.pricing-rules.store', ['productId' => $this->laptop->id], [
            'rule_name' => 'Early', 'discount_type' => 'percentage', 'discount_value' => 5, 'priority' => 10,
            'condition_json' => ['min_options' => 1],
        ])->assertStatus(201);

        $listed = $this->cpq('GET', 'sales.cpq.pricing-rules', ['productId' => $this->laptop->id])->assertOk();
        $this->assertSame(['Early', 'Late'], array_column($listed->json('data'), 'rule_name'));
    }

    public function test_a_constraint_rule_accepts_only_options_of_its_product(): void
    {
        $large = CpqOption::create(['cpq_option_group_id' => $this->memory->id, 'option_code' => '32G', 'name' => '32GB']);

        $this->cpq('POST', 'sales.cpq.constraint-rules.store', ['productId' => $this->laptop->id], [
            'rule_type' => 'excludes', 'if_option_id' => $this->sixteenGb->id, 'then_option_id' => $large->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.if_option.id', $this->sixteenGb->id)
            ->assertJsonPath('data.then_option.id', $large->id);

        $this->assertSame(
            $large->id,
            $this->cpq('GET', 'sales.cpq.constraint-rules', ['productId' => $this->laptop->id])->json('data.0.then_option.id')
        );

        $foreignOption = $this->configurable($this->otherOrg)[2];
        $refused = $this->cpq('POST', 'sales.cpq.constraint-rules.store', ['productId' => $this->laptop->id], [
            'rule_type' => 'requires', 'if_option_id' => $this->sixteenGb->id, 'then_option_id' => $foreignOption->id,
        ]);
        $refused->assertStatus(422);
        $this->assertArrayHasKey('then_option_id', $refused->json('errors') ?? []);
    }

    public function test_configuring_prices_the_selected_options_and_refuses_a_foreign_option(): void
    {
        $this->cpq('POST', 'sales.cpq.configure', [], [
            'product_id' => $this->laptop->id, 'selected_options' => [$this->sixteenGb->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('data.price_breakdown.total_price', 1100)
            ->assertJsonPath('data.product.id', $this->laptop->id);

        $foreignOption = $this->configurable($this->otherOrg)[2];
        $refused = $this->cpq('POST', 'sales.cpq.configure', [], [
            'product_id' => $this->laptop->id, 'selected_options' => [$foreignOption->id],
        ]);
        $refused->assertStatus(422);
        $this->assertArrayHasKey('selected_options.0', $refused->json('errors') ?? []);
    }

    public function test_a_saved_configuration_is_listed_and_shown_without_the_contacts_tax_number(): void
    {
        $saved = $this->cpq('POST', 'sales.cpq.configurations.store', [], $this->configurationPayload());
        $saved->assertStatus(201)
            ->assertJsonPath('data.total_price', '1100.0000')
            ->assertJsonPath('data.status', CpqConfiguration::STATUS_VALID)
            ->assertJsonPath('data.items.0.cpq_option_id', $this->sixteenGb->id);

        $listed = $this->cpq('GET', 'sales.cpq.configurations')->assertOk();
        $this->assertSame([$saved->json('data.id')], array_column($listed->json('data'), 'id'));
        $this->assertSame($this->contact->company_name, $listed->json('data.0.contact.company_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.contact'));

        $shown = $this->cpq('GET', 'sales.cpq.configurations.show', ['id' => $saved->json('data.id')])->assertOk();
        $this->assertSame($this->sixteenGb->id, $shown->json('data.items.0.option.id'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.contact'));
    }

    public function test_a_configuration_refuses_another_organizations_contact_and_options(): void
    {
        $foreignContact = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        [, $foreignGroup, $foreignOption] = $this->configurable($this->otherOrg);

        $response = $this->cpq('POST', 'sales.cpq.configurations.store', [], $this->configurationPayload([
            'contact_id' => $foreignContact->id,
            'selected_options' => [['option_id' => $foreignOption->id, 'option_group_id' => $foreignGroup->id]],
        ]));

        $response->assertStatus(422);
        foreach (['contact_id', 'selected_options.0.option_id', 'selected_options.0.option_group_id'] as $field) {
            $this->assertArrayHasKey($field, $response->json('errors') ?? []);
        }
        $this->assertSame(0, CpqConfiguration::count());
    }

    public function test_a_configuration_becomes_a_draft_quotation_once(): void
    {
        $saved = $this->cpq('POST', 'sales.cpq.configurations.store', [], $this->configurationPayload());
        $id = $saved->json('data.id');

        $converted = $this->cpq('POST', 'sales.cpq.configurations.convert', ['id' => $id], ['notes' => 'From CPQ']);

        $converted->assertStatus(201)
            ->assertJsonPath('data.customer_id', $this->contact->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.notes', 'From CPQ')
            ->assertJsonPath('data.lines.0.product_id', $this->laptop->product_id)
            ->assertJsonPath('data.lines.0.unit_price', '1100.0000');

        $config = CpqConfiguration::findOrFail($id);
        $this->assertSame(CpqConfiguration::STATUS_CONVERTED, $config->status);
        $this->assertSame($converted->json('data.id'), $config->quotation_id);

        $this->cpq('POST', 'sales.cpq.configurations.convert', ['id' => $id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_a_configuration_without_a_contact_is_not_converted(): void
    {
        $saved = $this->cpq('POST', 'sales.cpq.configurations.store', [], $this->configurationPayload(['contact_id' => null]));

        $this->cpq('POST', 'sales.cpq.configurations.convert', ['id' => $saved->json('data.id')])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CONTACT_REQUIRED');
    }

    public function test_another_organizations_configuration_is_not_found_and_a_foreign_salesperson_is_refused(): void
    {
        $saved = $this->cpq('POST', 'sales.cpq.configurations.store', [], $this->configurationPayload());

        $foreignUser = User::factory()->create(['organization_id' => $this->otherOrg->id]);
        $refused = $this->cpq('POST', 'sales.cpq.configurations.convert', ['id' => $saved->json('data.id')], [
            'salesperson_id' => $foreignUser->id,
        ]);
        $refused->assertStatus(422);
        $this->assertArrayHasKey('salesperson_id', $refused->json('errors') ?? []);

        $foreign = CpqConfiguration::withoutOrganizationScope()->findOrFail($saved->json('data.id'));
        $foreign->forceFill(['organization_id' => $this->otherOrg->id])->save();

        $this->cpq('GET', 'sales.cpq.configurations.show', ['id' => $foreign->id])->assertNotFound();
    }

    /**
     * A configurable product of the organization with one option group and one
     * fixed-price option.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: CpqConfigurableProduct, 1: CpqOptionGroup, 2: CpqOption}
     */
    private function configurable(Organization $organization, array $attributes = []): array
    {
        $product = CpqConfigurableProduct::withoutOrganizationScope()->getModel()->newInstance()->forceFill(array_merge([
            'organization_id' => $organization->id,
            'product_id' => Product::factory()->create(['organization_id' => $organization->id])->id,
            'name' => 'Laptop',
            'base_price' => 1000,
            'currency_code' => 'SAR',
            'is_active' => true,
        ], $attributes));
        $product->save();

        $group = CpqOptionGroup::create([
            'cpq_configurable_product_id' => $product->id, 'group_code' => 'RAM', 'name' => 'Memory', 'sort_order' => 1,
        ]);
        $option = CpqOption::create([
            'cpq_option_group_id' => $group->id, 'option_code' => '16G', 'name' => '16GB',
            'price_modifier_type' => 'fixed', 'price_modifier_value' => 100,
        ]);

        return [$product, $group, $option];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function configurationPayload(array $overrides = []): array
    {
        return array_merge([
            'cpq_configurable_product_id' => $this->laptop->id,
            'contact_id' => $this->contact->id,
            'selected_options' => [['option_id' => $this->sixteenGb->id, 'option_group_id' => $this->memory->id]],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $data
     */
    private function cpq(string $method, string $route, array $params = [], array $data = []): \Illuminate\Testing\TestResponse
    {
        $url = route($route, $params);

        if ($method === 'GET' && $data !== []) {
            $url .= '?'.http_build_query($data);
            $data = [];
        }

        return $this->json($method, $url, $data, $this->authHeaders());
    }
}
