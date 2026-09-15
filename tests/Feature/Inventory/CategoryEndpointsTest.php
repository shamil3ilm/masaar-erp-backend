<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * A category's parent and slug are checked within the caller's organization,
 * and a category never moves under its own descendant.
 */
class CategoryEndpointsTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.categories.view',
            'inventory.categories.create',
            'inventory.categories.edit',
        ]);
    }

    public function test_another_organizations_parent_is_refused_on_create_and_move(): void
    {
        $theirs = $this->category($this->otherOrganization()->id, 'theirs');
        $ours = $this->category($this->organization->id, 'ours');

        $this->apiPost('/inventory/categories', ['name' => 'Child', 'parent_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $this->apiPost("/inventory/categories/{$ours->id}/move", ['parent_id' => $theirs->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertNull($ours->fresh()->parent_id);
    }

    public function test_a_slug_used_by_another_organization_is_accepted(): void
    {
        $this->category($this->otherOrganization()->id, 'tools');

        $this->apiPost('/inventory/categories', ['name' => 'Tools', 'slug' => 'tools'])->assertCreated();
    }

    public function test_a_category_is_not_moved_under_its_descendant(): void
    {
        $root = $this->category($this->organization->id, 'root');
        $child = $this->category($this->organization->id, 'child', $root->id);

        $response = $this->apiPost("/inventory/categories/{$root->id}/move", ['parent_id' => $child->id]);

        $response->assertStatus(422)->assertJsonPath('error.message', 'Cannot move category under its own descendant.');
        $this->assertNull($root->fresh()->parent_id);
    }

    private function category(int $organizationId, string $slug, ?int $parentId = null): Category
    {
        return Category::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'parent_id' => $parentId,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }
}
