<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\AssetTransfer;
use App\Models\Accounting\FixedAsset;
use App\Models\Core\Organization;
use App\Services\Accounting\AssetTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AssetTransferTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.assets.view',
            'accounting.assets.dispose',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAsset(array $overrides = []): FixedAsset
    {
        $category = AssetCategory::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        $cost = 50000.0;

        return FixedAsset::factory()->create(array_merge([
            'organization_id'          => $this->organization->id,
            'asset_category_id'        => $category->id,
            'acquisition_cost'         => $cost,
            'book_value'               => $cost,
            'accumulated_depreciation' => 0,
            'status'                   => FixedAsset::STATUS_ACTIVE,
        ], $overrides));
    }

    private function makeTransfer(FixedAsset $asset, array $overrides = []): AssetTransfer
    {
        return AssetTransfer::create(array_merge([
            'transfer_number'           => 'TRF-' . fake()->unique()->numerify('######'),
            'fixed_asset_id'            => $asset->id,
            'sending_organization_id'   => $this->organization->id,
            'receiving_organization_id' => $this->organization->id,
            'transfer_date'             => '2025-06-01',
            'transfer_type'             => 'book_value',
            'gross_value'               => (float) $asset->acquisition_cost,
            'accumulated_depreciation'  => 0,
            'net_book_value'            => (float) $asset->book_value,
            'gain_loss_amount'          => 0,
            'status'                    => AssetTransfer::STATUS_PENDING,
            'created_by'                => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-transfers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $asset = $this->makeAsset();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/assets/' . $asset->uuid . '/transfers', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_pending_transfer(): void
    {
        $asset       = $this->makeAsset();
        $receivingOrg = Organization::factory()->create([
            'name'                   => 'Receiving Org',
            'country_code'           => 'SA',
            'base_currency'          => 'SAR',
            'parent_organization_id' => $this->organization->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/assets/' . $asset->uuid . '/transfers', [
                'receiving_organization_id' => $receivingOrg->id,
                'transfer_date'             => '2025-06-01',
                'transfer_type'             => 'book_value',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $asset    = $this->makeAsset();
        $transfer = $this->makeTransfer($asset);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-transfers/' . $transfer->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/asset-transfers/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    public function test_cancel_validates_reason_required(): void
    {
        $asset    = $this->makeAsset();
        $transfer = $this->makeTransfer($asset);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-transfers/' . $transfer->uuid . '/cancel', []);

        $response->assertStatus(422);
    }

    public function test_cancel_cancels_pending_transfer(): void
    {
        $asset    = $this->makeAsset();
        $transfer = $this->makeTransfer($asset);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/asset-transfers/' . $transfer->uuid . '/cancel', [
                'reason' => 'Business decision changed.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/asset-transfers')->assertStatus(401);
    }
    // -------------------------------------------------------------------------
    // Organization group
    // -------------------------------------------------------------------------

    public function test_store_refuses_a_receiver_outside_the_group_and_accepts_the_parent_and_a_sibling(): void
    {
        $parent   = Organization::factory()->create();
        $sibling  = Organization::factory()->create(['parent_organization_id' => $parent->id]);
        $stranger = Organization::factory()->create();

        $this->organization->parent_organization_id = $parent->id;
        $this->organization->save();

        $this->initiateTransfer($stranger)
            ->assertStatus(422)
            ->assertJsonValidationErrors('receiving_organization_id');

        $this->initiateTransfer($parent)->assertStatus(201);
        $this->initiateTransfer($sibling)->assertStatus(201);

        $this->assertSame(2, AssetTransfer::count());
    }

    private function initiateTransfer(Organization $receiver): TestResponse
    {
        return $this->withToken($this->token)
            ->postJson('/api/v1/assets/' . $this->makeAsset()->uuid . '/transfers', [
                'receiving_organization_id' => $receiver->id,
                'transfer_date'             => '2025-06-01',
                'transfer_type'             => 'book_value',
            ]);
    }

    /**
     * The request rule is not the only guard: a caller reaching the service
     * directly must not initiate or execute a transfer into a tenant outside
     * the sending organisation's group.
     */
    public function test_the_service_refuses_an_organization_outside_the_group(): void
    {
        $stranger = Organization::factory()->create();
        $service  = app(AssetTransferService::class);

        try {
            $service->create($this->makeAsset(), [
                'receiving_organization_id' => $stranger->id,
                'transfer_date'             => '2025-06-01',
            ], $this->user->id);
            $this->fail('The service initiated a transfer outside the group.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('outside', $e->getMessage());
        }

        $transfer = $this->makeTransfer($this->makeAsset(), ['receiving_organization_id' => $stranger->id]);

        try {
            $service->execute($transfer, [
                'organization_id' => $this->organization->id,
                'branch_id'       => $this->branch->id,
                'entry_date'      => '2025-06-01',
            ]);
            $this->fail('The service executed a transfer outside the group.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('outside', $e->getMessage());
        }

        $this->assertSame(AssetTransfer::STATUS_PENDING, $transfer->fresh()->status);
        $this->assertSame(0, FixedAsset::withoutGlobalScopes()->where('organization_id', $stranger->id)->count());
    }
}
