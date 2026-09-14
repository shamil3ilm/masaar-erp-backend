<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DirectDebitCollection;
use App\Models\Accounting\DirectDebitMandate;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DirectDebitTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.direct-debit.manage',
            'accounting.direct-debit.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMandate(array $overrides = []): DirectDebitMandate
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        return DirectDebitMandate::create(array_merge([
            'organization_id'   => $this->organization->id,
            'mandate_reference' => 'DDM-' . fake()->unique()->numerify('####'),
            'mandate_type'      => 'core',
            'direction'         => 'collection',
            'counterparty_id'   => $contact->id,
            'currency_code'     => 'SAR',
            'status'            => 'draft',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // List Mandates
    // -------------------------------------------------------------------------

    public function test_list_mandates_returns_paginated_list(): void
    {
        $this->makeMandate();
        $this->makeMandate();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/direct-debit/mandates');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_mandates_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/direct-debit/mandates');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Create Mandate
    // -------------------------------------------------------------------------

    public function test_create_mandate_stores_new_mandate(): void
    {
        $contact = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/mandates', [
                'mandate_reference' => 'DDM-TEST-001',
                'counterparty_id'   => $contact->id,
                'currency_code'     => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_create_mandate_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/mandates', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show Mandate
    // -------------------------------------------------------------------------

    public function test_show_mandate_returns_details(): void
    {
        $mandate = $this->makeMandate();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/direct-debit/mandates/' . $mandate->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $mandate->id);
    }

    public function test_show_mandate_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/direct-debit/mandates/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update Mandate
    // -------------------------------------------------------------------------

    public function test_update_mandate_modifies_fields(): void
    {
        $mandate = $this->makeMandate();

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/direct-debit/mandates/' . $mandate->id, [
                'currency_code' => 'USD',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('USD', $mandate->fresh()->currency_code);
    }

    // -------------------------------------------------------------------------
    // State transitions
    // -------------------------------------------------------------------------

    public function test_activate_mandate_sets_active_status(): void
    {
        $mandate = $this->makeMandate(['status' => 'draft']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/mandates/' . $mandate->id . '/activate');

        $response->assertStatus(200);
        $this->assertEquals('active', $mandate->fresh()->status);
    }

    public function test_pause_mandate_sets_paused_status(): void
    {
        $mandate = $this->makeMandate(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/mandates/' . $mandate->id . '/pause');

        $response->assertStatus(200);
        $this->assertEquals('paused', $mandate->fresh()->status);
    }

    public function test_cancel_mandate_sets_cancelled_status(): void
    {
        $mandate = $this->makeMandate(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/mandates/' . $mandate->id . '/cancel');

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $mandate->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Collections
    // -------------------------------------------------------------------------

    public function test_collections_returns_list_for_mandate(): void
    {
        $mandate = $this->makeMandate(['status' => 'active']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/direct-debit/mandates/' . $mandate->id . '/collections');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_due_collections_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/direct-debit/due-collections');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_generate_collections_runs_successfully(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/generate-collections');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_collections_lists_only_the_mandates_collections_newest_first(): void
    {
        $mandate = $this->makeMandate(['status' => 'active']);
        $other = $this->makeMandate(['status' => 'active']);
        $this->makeCollection($mandate, ['collection_date' => '2025-01-01']);
        $this->makeCollection($mandate, ['collection_date' => '2025-02-01']);
        $this->makeCollection($other, ['collection_date' => '2025-03-01']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/direct-debit/mandates/' . $mandate->id . '/collections');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(
            ['2025-02-01', '2025-01-01'],
            array_map(fn (array $row) => substr($row['collection_date'], 0, 10), $response->json('data')),
        );
    }

    public function test_process_collection_submits_a_scheduled_collection(): void
    {
        $collection = $this->makeCollection($this->makeMandate(['status' => 'active']));

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/collections/' . $collection->id . '/process');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_mandate_and_collection_endpoints_return_404_for_another_organization(): void
    {
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $mandate = DirectDebitMandate::create([
            'organization_id'   => $otherOrg->id,
            'mandate_reference' => 'DDM-FOREIGN',
            'direction'         => 'collection',
            'counterparty_id'   => Contact::factory()->create(['organization_id' => $otherOrg->id])->id,
            'currency_code'     => 'SAR',
            'status'            => 'draft',
        ]);
        $collection = $this->makeCollection($mandate, ['organization_id' => $otherOrg->id]);
        $base = '/api/v1/direct-debit/mandates/' . $mandate->id;

        $this->withToken($this->token)->getJson($base)->assertStatus(404);
        $this->withToken($this->token)->putJson($base, ['currency_code' => 'USD'])->assertStatus(404);
        $this->withToken($this->token)->postJson($base . '/activate')->assertStatus(404);
        $this->withToken($this->token)->postJson($base . '/pause')->assertStatus(404);
        $this->withToken($this->token)->postJson($base . '/cancel')->assertStatus(404);
        $this->withToken($this->token)->getJson($base . '/collections')->assertStatus(404);
        $this->withToken($this->token)
            ->postJson('/api/v1/direct-debit/collections/' . $collection->id . '/process')
            ->assertStatus(404);

        $this->assertDatabaseHas('direct_debit_mandates', ['id' => $mandate->id, 'status' => 'draft', 'currency_code' => 'SAR']);
        $this->assertDatabaseHas('direct_debit_collections', ['id' => $collection->id, 'status' => 'scheduled']);
    }

    private function makeCollection(DirectDebitMandate $mandate, array $overrides = []): DirectDebitCollection
    {
        return DirectDebitCollection::create(array_merge([
            'organization_id'         => $this->organization->id,
            'direct_debit_mandate_id' => $mandate->id,
            'collection_date'         => '2025-01-15',
            'amount'                  => 100,
            'status'                  => 'scheduled',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/direct-debit/mandates')->assertStatus(401);
    }
}
