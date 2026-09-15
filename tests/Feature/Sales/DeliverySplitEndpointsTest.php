<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\DeliverySplitRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the delivery split rule endpoints: the list and its filters, editing a
 * rule and applying the rules for a customer, and keeps rules and customers
 * inside the caller's organization.
 */
class DeliverySplitEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.delivery-split-rules.view',
            'sales.delivery-split-rules.create',
            'sales.delivery-split-rules.edit',
            'sales.delivery-split-rules.delete',
        ]);

        $this->otherOrg = Organization::factory()->create();
        $this->customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_the_list_shows_the_organizations_rules_latest_first_and_filters(): void
    {
        $older = $this->rule(['split_criteria' => 'warehouse', 'created_at' => now()->subDay()]);
        $newer = $this->rule(['split_criteria' => 'weight', 'is_active' => false, 'created_at' => now()]);
        $this->rule(['organization_id' => $this->otherOrg->id]);

        $ids = fn (string $query) => array_column($this->apiGet('/sales/delivery-split-rules'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$newer->id, $older->id], $ids(''));
        $this->assertSame([$older->id], $ids('?active_only=1'));
        $this->assertSame([$newer->id], $ids('?split_criteria=weight'));
    }

    public function test_a_rule_is_created_updated_and_deleted(): void
    {
        $created = $this->apiPost('/sales/delivery-split-rules', [
            'rule_name' => 'By warehouse',
            'split_criteria' => 'warehouse',
            'applies_to' => 'all_customers',
        ]);
        $created->assertStatus(201)
            ->assertJsonPath('message', 'Delivery split rule created.')
            ->assertJsonPath('data.organization_id', $this->organization->id);
        $id = $created->json('data.id');

        $this->apiPut("/sales/delivery-split-rules/{$id}", ['rule_name' => 'By route', 'split_criteria' => 'route'])
            ->assertOk()
            ->assertJsonPath('message', 'Delivery split rule updated.')
            ->assertJsonPath('data.rule_name', 'By route');

        $this->apiGet("/sales/delivery-split-rules/{$id}")->assertOk()->assertJsonPath('data.split_criteria', 'route');

        $this->apiDelete("/sales/delivery-split-rules/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Delivery split rule deleted.');
        $this->assertNull(DeliverySplitRule::find($id));
    }

    public function test_another_organizations_rule_is_not_found(): void
    {
        $foreign = $this->rule(['organization_id' => $this->otherOrg->id]);

        $this->apiGet("/sales/delivery-split-rules/{$foreign->id}")->assertNotFound();
        $this->apiPut("/sales/delivery-split-rules/{$foreign->id}", ['rule_name' => 'x'])->assertNotFound();
        $this->apiDelete("/sales/delivery-split-rules/{$foreign->id}")->assertNotFound();
    }

    public function test_applying_returns_the_active_rules_for_the_customer(): void
    {
        $all = $this->rule(['applies_to' => 'all_customers', 'minimum_delivery_quantity_pct' => 25]);
        $specific = $this->rule(['applies_to' => 'specific_customer', 'applies_to_id' => $this->customer->id]);
        $this->rule(['applies_to' => 'specific_customer', 'applies_to_id' => $this->customer->id + 1000]);
        $this->rule(['applies_to' => 'all_customers', 'is_active' => false]);
        $this->rule(['organization_id' => $this->otherOrg->id, 'applies_to' => 'all_customers']);

        $response = $this->apiPost('/sales/delivery-split-rules/apply', [
            'source_type' => 'sales_order',
            'source_id' => 7,
            'customer_id' => $this->customer->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Delivery split rules applied.')
            ->assertJsonPath('data.split_required', true)
            ->assertJsonPath('data.source_id', 7);
        $this->assertEqualsCanonicalizing([$all->id, $specific->id], array_column($response->json('data.applicable_rules'), 'rule_id'));
    }

    public function test_applying_without_rules_says_no_split_is_required(): void
    {
        $this->apiPost('/sales/delivery-split-rules/apply', [
            'source_type' => 'shipment',
            'source_id' => 3,
            'customer_id' => $this->customer->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.split_required', false)
            ->assertJsonPath('data.applicable_rules', [])
            ->assertJsonPath('data.message', 'No applicable delivery split rules found.');
    }

    public function test_applying_refuses_another_organizations_customer(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $response = $this->apiPost('/sales/delivery-split-rules/apply', [
            'source_type' => 'sales_order',
            'source_id' => 1,
            'customer_id' => $foreign->id,
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('customer_id', $response->json('errors') ?? []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(array $attributes = []): DeliverySplitRule
    {
        $rule = new DeliverySplitRule();
        $rule->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'rule_name' => 'Rule',
            'split_criteria' => 'warehouse',
            'applies_to' => 'all_customers',
            'allow_partial_delivery' => true,
            'minimum_delivery_quantity_pct' => 0,
            'is_active' => true,
        ], $attributes))->save();

        return $rule;
    }
}
