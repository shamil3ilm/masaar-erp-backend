<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\RebateMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the rebate master list, show, create and update, and refuses another
 * organization's customer or accounts.
 */
class RebateEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;
    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['sales.rebates.view', 'sales.rebates.manage']);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'tax_number' => '300000000000003',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_the_list_and_a_rebate_show_the_customer_without_its_tax_number(): void
    {
        $rebate = $this->rebate();

        $listed = $this->apiGet('/sales/rebates')->assertOk();
        $this->assertSame($rebate->id, $listed->json('data.0.id'));
        $this->assertSame($this->customer->contact_name, $listed->json('data.0.customer.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.customer'));

        $shown = $this->apiGet("/sales/rebates/{$rebate->id}")->assertOk();
        $this->assertSame($this->customer->company_name, $shown->json('data.customer.company_name'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.customer'));
        $this->assertSame([], $shown->json('data.accruals'));
    }

    public function test_the_list_filters_by_status_and_contact(): void
    {
        $match = $this->rebate();
        $this->rebate(['status' => RebateMaster::STATUS_INACTIVE]);
        $other = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $this->rebate(['contact_id' => $other->id]);

        $response = $this->apiGet("/sales/rebates?status=active&contact_id={$this->customer->id}");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_rebate_is_created_active_and_updated(): void
    {
        $created = $this->apiPost('/sales/rebates', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('message', 'Rebate master created')
            ->assertJsonPath('data.status', RebateMaster::STATUS_ACTIVE)
            ->assertJsonPath('data.organization_id', $this->organization->id);

        $id = $created->json('data.id');

        $this->apiPut("/sales/rebates/{$id}", ['name' => 'Renamed', 'status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('message', 'Rebate master updated')
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.status', RebateMaster::STATUS_INACTIVE);
    }

    public function test_a_customer_of_another_organization_is_refused(): void
    {
        $foreign = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);

        $this->apiPost('/sales/rebates', $this->payload(['contact_id' => $foreign->id]))->assertStatus(422);

        $this->assertSame(0, RebateMaster::withoutGlobalScopes()->count());
    }

    public function test_an_account_of_another_organization_is_refused(): void
    {
        $foreign = Account::factory()->create([
            'organization_id' => $this->otherOrg->id,
            'code' => '9999',
            'name' => 'Foreign accrual',
            'currency_code' => null,
        ]);

        $this->apiPost('/sales/rebates', $this->payload(['accrual_account_id' => $foreign->id]))->assertStatus(422);
        $this->assertSame(0, RebateMaster::withoutGlobalScopes()->count());

        $rebate = $this->rebate();

        $this->apiPut("/sales/rebates/{$rebate->id}", ['expense_account_id' => $foreign->id])->assertStatus(422);
        $this->assertNull($rebate->fresh()->expense_account_id);
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Volume rebate',
            'contact_id' => $this->customer->id,
            'rebate_type' => 'percentage',
            'calculation_base' => 'invoice_value',
            'rebate_rate' => 2,
            'accrual_method' => 'periodic',
            'valid_from' => '2025-01-01',
        ], $overrides);
    }

    /** @param  array<string, mixed>  $attributes */
    private function rebate(array $attributes = []): RebateMaster
    {
        return RebateMaster::create(array_merge($this->payload(), [
            'organization_id' => $this->organization->id,
            'status' => RebateMaster::STATUS_ACTIVE,
        ], $attributes));
    }
}
