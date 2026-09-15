<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\DashboardLayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins dashboard layout management: a user's own layouts are created,
 * listed, changed and deleted, one default remains per type, another user's
 * private layout is not found, and a shared layout needs core.settings.edit.
 */
class DashboardLayoutEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.dashboards.view', 'core.dashboards.manage']);
    }

    public function test_layouts_are_created_listed_kept_to_one_default_changed_and_deleted(): void
    {
        $first = $this->apiPost('/dashboard/layouts', ['name' => 'Mine', 'type' => DashboardLayout::TYPE_MAIN, 'is_default' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Layout created successfully')
            ->assertJsonPath('data.user_id', $this->user->id)
            ->json('data.id');
        $second = $this->apiPost('/dashboard/layouts', ['name' => 'Other', 'type' => DashboardLayout::TYPE_MAIN, 'is_default' => true])
            ->assertOk()
            ->json('data.id');

        $this->assertFalse((bool) DashboardLayout::findOrFail($first)->is_default);

        $this->apiGet('/dashboard/layouts')->assertOk()->assertJsonCount(2, 'data.user_layouts');

        $this->apiPut("/dashboard/layouts/{$first}", ['name' => 'Renamed', 'is_default' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Layout updated successfully')
            ->assertJsonPath('data.name', 'Renamed');
        $this->assertFalse((bool) DashboardLayout::findOrFail($second)->is_default);

        $this->apiDelete("/dashboard/layouts/{$second}")->assertOk()->assertJsonPath('message', 'Layout deleted');
        $this->assertNull(DashboardLayout::find($second));
    }

    public function test_another_users_private_layout_is_not_found(): void
    {
        $colleague = User::factory()->create(['organization_id' => $this->organization->id]);
        $private = DashboardLayout::create([
            'organization_id' => $this->organization->id,
            'user_id' => $colleague->id,
            'name' => 'Private',
            'type' => DashboardLayout::TYPE_MAIN,
            'widgets' => [],
            'layout' => [],
            'is_default' => false,
            'is_shared' => false,
        ]);

        $this->apiPut("/dashboard/layouts/{$private->id}", ['name' => 'X'])->assertNotFound();
        $this->apiDelete("/dashboard/layouts/{$private->id}")->assertNotFound();
        $this->assertSame('Private', $private->fresh()->name);
    }

    public function test_a_shared_layout_needs_settings_edit(): void
    {
        $this->apiPost('/dashboard/layouts', ['name' => 'Team', 'type' => DashboardLayout::TYPE_MAIN, 'is_shared' => true])
            ->assertForbidden()
            ->assertJsonPath('error.message', 'Permission denied for shared layouts');

        $this->assertSame(0, DashboardLayout::count());
    }
}
