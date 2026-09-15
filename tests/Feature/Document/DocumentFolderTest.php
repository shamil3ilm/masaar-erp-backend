<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Models\Core\Organization;
use App\Models\Document\DocumentFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the document folder endpoints and keeps a folder under the caller's
 * own parent folder.
 */
class DocumentFolderTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['documents.folders.view', 'documents.folders.create', 'documents.folders.manage']);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_lists_root_folders_by_name_or_the_children_of_a_parent(): void
    {
        $beta = $this->folder(['name' => 'Beta']);
        $alpha = $this->folder(['name' => 'Alpha']);
        $child = $this->folder(['name' => 'Child', 'parent_id' => $alpha->id]);
        $this->folder(['name' => 'Aardvark', 'organization_id' => $this->other->id]);

        $roots = $this->apiGet('/document-folders')->assertOk()->assertJsonPath('meta.per_page', 50);
        $this->assertSame([$alpha->id, $beta->id], array_column($roots->json('data'), 'id'));
        $this->assertSame(0, $roots->json('data.0.documents_count'));

        $children = $this->apiGet("/document-folders?parent_id={$alpha->id}")->assertOk();
        $this->assertSame([$child->id], array_column($children->json('data'), 'id'));
    }

    public function test_a_folder_cannot_be_placed_under_another_organizations_folder(): void
    {
        $theirs = $this->folder(['organization_id' => $this->other->id]);
        $mine = $this->folder(['name' => 'Mine']);

        $this->apiPost('/document-folders', ['name' => 'Borrowed', 'parent_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
        $this->apiPut("/document-folders/{$mine->getRouteKey()}", ['parent_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertNull($mine->fresh()->parent_id);
        $this->assertSame(1, DocumentFolder::count());
    }

    public function test_store_update_and_delete_a_folder_and_system_folders_are_kept(): void
    {
        $parent = $this->folder(['name' => 'Contracts']);

        $created = $this->apiPost('/document-folders', ['name' => 'Suppliers', 'parent_id' => $parent->id])
            ->assertCreated()
            ->assertJsonPath('data.creator.id', $this->user->id)
            ->assertJsonPath('data.organization_id', $this->organization->id);
        $folder = DocumentFolder::findOrFail($created->json('data.id'));
        $grandchild = $this->folder(['name' => 'Archive', 'parent_id' => $folder->id]);

        $this->apiPut("/document-folders/{$folder->getRouteKey()}", ['name' => 'Vendors'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Vendors');

        $system = $this->folder(['is_system' => true]);
        $this->apiDelete("/document-folders/{$system->getRouteKey()}")->assertStatus(403)->assertJsonPath('error.code', 'SYSTEM_FOLDER');

        $this->apiDelete("/document-folders/{$folder->getRouteKey()}")->assertOk();
        $this->assertNull(DocumentFolder::find($folder->id));
        $this->assertSame($parent->id, $grandchild->fresh()->parent_id);
    }

    public function test_another_organizations_folder_is_not_found(): void
    {
        $theirs = $this->folder(['organization_id' => $this->other->id]);

        $this->apiGet("/document-folders/{$theirs->getRouteKey()}")->assertNotFound();
    }

    private function folder(array $attributes = []): DocumentFolder
    {
        $organizationId = $attributes['organization_id'] ?? $this->organization->id;

        return DocumentFolder::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'name' => 'Folder',
            'access_level' => DocumentFolder::ACCESS_ORGANIZATION,
            'created_by' => $organizationId === $this->organization->id
                ? $this->user->id
                : User::factory()->create(['organization_id' => $organizationId])->id,
            ...$attributes,
        ]);
    }
}
