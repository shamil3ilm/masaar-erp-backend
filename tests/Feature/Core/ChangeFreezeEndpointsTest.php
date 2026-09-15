<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ChangeFreezeperiod;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the change freeze endpoints: creating, listing, showing, ending and
 * deleting the organization's freeze periods. Another organization's freeze
 * is not found.
 */
class ChangeFreezeEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.change-freeze.manage', 'core.change-freeze.view']);
    }

    public function test_a_freeze_is_created_listed_shown_ended_and_deleted(): void
    {
        $id = $this->apiPost('/change-freeze', [
            'name' => 'Year end',
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(3)->toDateTimeString(),
            'scope' => 'all',
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Change freeze period created successfully.')
            ->json('data.id');

        $this->apiGet('/change-freeze')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
        $this->apiGet("/change-freeze/{$id}")->assertOk()->assertJsonPath('data.name', 'Year end');

        $this->apiPost("/change-freeze/{$id}/end")->assertOk()->assertJsonPath('message', 'Change freeze period ended.');
        $this->assertFalse((bool) ChangeFreezeperiod::findOrFail($id)->is_active);

        $this->apiDelete("/change-freeze/{$id}")->assertNoContent();
        $this->assertSoftDeleted('change_freeze_periods', ['id' => $id]);
    }

    public function test_another_organizations_freeze_is_not_found(): void
    {
        $foreign = ChangeFreezeperiod::withoutGlobalScopes()->create([
            'organization_id' => Organization::factory()->create()->id,
            'name' => 'Foreign',
            'starts_at' => now()->addDay(),
            'scope' => 'all',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $this->apiGet("/change-freeze/{$foreign->id}")->assertNotFound();
        $this->apiPost("/change-freeze/{$foreign->id}/end")->assertNotFound();
        $this->apiDelete("/change-freeze/{$foreign->id}")->assertNotFound();
        $this->assertTrue((bool) ChangeFreezeperiod::withoutGlobalScopes()->findOrFail($foreign->id)->is_active);
    }
}
