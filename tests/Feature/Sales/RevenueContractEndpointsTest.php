<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\RevenueContract;
use App\Services\Sales\RevenueRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the revenue contract list, show, create and draft update, shows the
 * customer without its tax number, refuses another organization's accounts
 * and checks the draft status on the locked contract.
 */
class RevenueContractEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.revenue_contracts.view',
            'sales.revenue_contracts.create',
            'sales.revenue_contracts.edit',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'tax_number' => '300000000000003',
        ]);
    }

    public function test_the_list_and_a_contract_show_the_customer_without_its_tax_number(): void
    {
        $contract = $this->contract(RevenueContract::STATUS_DRAFT);

        $listed = $this->apiGet('/sales/revenue-contracts')->assertOk();
        $this->assertSame($this->customer->contact_name, $listed->json('data.0.customer.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $listed->json('data.0.customer'));

        $shown = $this->apiGet("/sales/revenue-contracts/{$contract->id}")->assertOk();
        $this->assertSame($this->customer->company_name, $shown->json('data.customer.company_name'));
        $this->assertArrayNotHasKey('tax_number', $shown->json('data.customer'));
    }

    public function test_the_list_filters_by_status_and_searches_the_number(): void
    {
        $match = $this->contract(RevenueContract::STATUS_ACTIVE, ['contract_number' => 'RC-ALPHA']);
        $this->contract(RevenueContract::STATUS_DRAFT, ['contract_number' => 'RC-ALPHA-2']);
        $this->contract(RevenueContract::STATUS_ACTIVE, ['contract_number' => 'RC-BETA']);

        $response = $this->apiGet('/sales/revenue-contracts?status=active&search=ALPHA');

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_contract_is_created_with_its_obligations(): void
    {
        $this->apiPost('/sales/revenue-contracts', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('message', 'Revenue contract created.')
            ->assertJsonCount(1, 'data.performance_obligations');
    }

    public function test_an_obligation_account_of_another_organization_is_refused(): void
    {
        $foreign = Account::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'code' => '9999',
            'name' => 'Foreign revenue',
            'currency_code' => null,
        ]);

        $payload = $this->payload();
        $payload['obligations'][0]['revenue_account_id'] = $foreign->id;

        $this->apiPost('/sales/revenue-contracts', $payload)->assertStatus(422);

        $payload = $this->payload();
        $payload['obligations'][0]['deferred_account_id'] = $foreign->id;

        $this->apiPost('/sales/revenue-contracts', $payload)->assertStatus(422);

        $this->assertSame(0, RevenueContract::withoutGlobalScopes()->count());
    }

    public function test_only_a_draft_contract_is_updated(): void
    {
        $active = $this->contract(RevenueContract::STATUS_ACTIVE);
        $draft = $this->contract(RevenueContract::STATUS_DRAFT, ['contract_number' => 'RC-DRAFT']);

        $this->apiPut("/sales/revenue-contracts/{$active->id}", ['total_transaction_price' => 5])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Only draft contracts can be updated.');

        $this->apiPut("/sales/revenue-contracts/{$draft->id}", ['status' => RevenueContract::STATUS_ACTIVE])
            ->assertOk()
            ->assertJsonPath('message', 'Contract updated.')
            ->assertJsonPath('data.status', RevenueContract::STATUS_ACTIVE);
    }

    public function test_a_contract_activated_meanwhile_is_not_updated_through_a_stale_draft(): void
    {
        $contract = $this->contract(RevenueContract::STATUS_DRAFT);
        $stale = RevenueContract::findOrFail($contract->id);

        RevenueContract::whereKey($contract->id)->update(['status' => RevenueContract::STATUS_ACTIVE]);

        try {
            app(RevenueRecognitionService::class)->updateDraftContract($stale, ['status' => RevenueContract::STATUS_CANCELLED]);
            $this->fail('An active contract must not be updated as a draft.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(RevenueContract::STATUS_ACTIVE, $contract->fresh()->status);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'contract_number' => 'RC-NEW',
            'contact_id' => $this->customer->id,
            'contract_date' => '2025-01-01',
            'total_transaction_price' => 1000,
            'recognition_method' => RevenueContract::METHOD_POINT_IN_TIME,
            'obligations' => [
                [
                    'description' => 'Licence',
                    'standalone_selling_price' => 1000,
                    'recognition_method' => 'point_in_time',
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $attributes */
    private function contract(string $status, array $attributes = []): RevenueContract
    {
        return RevenueContract::create(array_merge([
            'organization_id' => $this->organization->id,
            'contract_number' => 'RC-'.uniqid(),
            'contact_id' => $this->customer->id,
            'contract_date' => '2025-01-01',
            'total_transaction_price' => 1000,
            'recognition_method' => RevenueContract::METHOD_POINT_IN_TIME,
            'status' => $status,
        ], $attributes));
    }
}
