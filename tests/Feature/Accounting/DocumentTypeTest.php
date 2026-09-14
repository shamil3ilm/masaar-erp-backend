<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DocumentType;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DocumentTypeTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.document-types.manage',
            'accounting.document-types.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDocumentType(array $overrides = []): DocumentType
    {
        return DocumentType::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'DT' . fake()->unique()->numerify('##'),
            'name'            => 'Test Document Type',
            'is_active'       => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeDocumentType();
        $this->makeDocumentType();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_document_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-types', [
                'code' => 'SA',
                'name' => 'Sales Invoice',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'SA');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-types', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_unique_code_per_org(): void
    {
        $this->makeDocumentType(['code' => 'DUP']);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/document-types', [
                'code' => 'DUP',
                'name' => 'Duplicate',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_document_type_details(): void
    {
        $dt = $this->makeDocumentType();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types/' . $dt->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $dt->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/document-types/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_document_type(): void
    {
        $dt = $this->makeDocumentType(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/document-types/' . $dt->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_document_type(): void
    {
        $dt = $this->makeDocumentType();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/document-types/' . $dt->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('accounting_document_types', ['id' => $dt->id]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/document-types')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Filters, tenant isolation and response shape
    // -------------------------------------------------------------------------

    public function test_index_filters_by_active_only(): void
    {
        $this->makeDocumentType(['code' => 'D1']);
        $this->makeDocumentType(['code' => 'D2', 'is_active' => false]);
        $this->makeDocumentType(['code' => 'D3', 'organization_id' => Organization::factory()->create()->id]);

        $all = $this->withToken($this->token)->getJson('/api/v1/document-types?per_page=5');
        $all->assertStatus(200)->assertJsonPath('meta.per_page', 5);
        $this->assertSame(['D1', 'D2'], array_column($all->json('data'), 'code'));

        $active = $this->withToken($this->token)->getJson('/api/v1/document-types?active_only=true');
        $active->assertJsonPath('meta.per_page', 20);
        $this->assertSame(['D1'], array_column($active->json('data'), 'code'));
    }

    public function test_store_sets_the_organization(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/document-types', ['code' => 'KR', 'name' => 'Vendor Invoice'])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Document type created.')
            ->assertJsonPath('data.code', 'KR')
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_show_returns_404_for_other_organization_document_type(): void
    {
        $other = $this->makeDocumentType(['organization_id' => Organization::factory()->create()->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/document-types/' . $other->getRouteKey())
            ->assertStatus(404);
    }
}
