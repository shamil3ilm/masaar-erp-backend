<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\Contract;
use App\Models\Sales\Contact;
use App\Services\Purchase\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Contract endpoints: ids a request names must be the caller's
 * organization's, and status changes are checked on the locked contract.
 */
class ContractEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/contracts';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.contracts.view', 'purchase.contracts.create', 'purchase.contracts.edit',
            'purchase.contracts.delete', 'purchase.contracts.activate', 'purchase.contracts.terminate',
            'purchase.contracts.release',
        ]);

        $this->supplier = Contact::factory()->supplier()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_lists_only_this_organizations_contracts(): void
    {
        $this->contract($this->organization, $this->supplier);
        $this->contract($this->organization, $this->supplier);
        $other = Organization::factory()->create();
        $this->contract($other, Contact::factory()->supplier()->create(['organization_id' => $other->id]));

        $this->apiGet($this->baseUrl)->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_store_refuses_another_organizations_contact_branch_parent_and_product(): void
    {
        $other = Organization::factory()->create();
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => $other->id]);

        $this->apiPost($this->baseUrl, [
            'contract_type' => 'purchase',
            'contact_id' => $foreignSupplier->id,
            'title' => 'Supply',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'currency_code' => 'SAR',
            'branch_id' => Branch::factory()->create(['organization_id' => $other->id])->id,
            'parent_contract_id' => $this->contract($other, $foreignSupplier)->id,
            'lines' => [[
                'description' => 'Steel',
                'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['contact_id', 'branch_id', 'parent_contract_id', 'lines.0.product_id']);

        $this->assertSame(1, Contract::withoutGlobalScopes()->count());
    }

    public function test_update_refuses_an_active_contract(): void
    {
        $contract = $this->contract($this->organization, $this->supplier, Contract::STATUS_ACTIVE);

        $this->apiPut("{$this->baseUrl}/{$contract->id}", ['title' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft contracts can be updated.');
    }

    public function test_destroy_deletes_a_draft_with_its_lines_and_milestones(): void
    {
        $contract = $this->contract($this->organization, $this->supplier);
        $contract->lines()->create(['description' => 'Steel', 'sort_order' => 0]);
        $contract->milestones()->create(['milestone_name' => 'Kickoff', 'due_date' => now()->toDateString(), 'amount' => 10]);

        $this->apiDelete("{$this->baseUrl}/{$contract->id}")->assertOk();

        $this->assertSoftDeleted($contract);
        $this->assertSame(0, $contract->lines()->count());
        $this->assertSame(0, $contract->milestones()->count());
    }

    public function test_destroy_refuses_an_active_contract(): void
    {
        $contract = $this->contract($this->organization, $this->supplier, Contract::STATUS_ACTIVE);

        $this->apiDelete("{$this->baseUrl}/{$contract->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Only draft contracts can be deleted.');

        $this->assertNotSoftDeleted($contract);
    }

    public function test_activating_a_stale_copy_of_a_terminated_contract_is_refused(): void
    {
        $contract = $this->contract($this->organization, $this->supplier);
        $staleCopy = Contract::withoutGlobalScopes()->findOrFail($contract->id);
        $service = app(ContractService::class);

        $service->activateContract($contract);
        $service->terminateContract($contract->fresh(), ['reason' => 'Ended']);

        try {
            $service->activateContract($staleCopy);
            $this->fail('A terminated contract was activated again.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only draft contracts can be activated.', $e->getMessage());
        }

        $this->assertSame(Contract::STATUS_TERMINATED, $contract->fresh()->status);
    }

    public function test_releases_are_listed_newest_first(): void
    {
        $contract = $this->contract($this->organization, $this->supplier, Contract::STATUS_ACTIVE);
        $contract->releases()->create(['release_date' => '2026-01-01', 'amount' => 10, 'status' => 'pending']);
        $contract->releases()->create(['release_date' => '2026-03-01', 'amount' => 20, 'status' => 'pending']);

        $this->apiGet("{$this->baseUrl}/{$contract->id}/releases")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.amount', '20.0000');
    }

    private function contract(Organization $organization, Contact $supplier, string $status = Contract::STATUS_DRAFT): Contract
    {
        return Contract::create([
            'organization_id' => $organization->id,
            'contract_number' => 'CON-'.fake()->unique()->numerify('#####'),
            'contract_type' => 'purchase',
            'contact_id' => $supplier->id,
            'title' => 'Supply',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'currency_code' => 'SAR',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }
}
