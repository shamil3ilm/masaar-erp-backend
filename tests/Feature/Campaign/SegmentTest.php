<?php

declare(strict_types=1);

namespace Tests\Feature\Campaign;

use App\Models\Campaign\UserSegment;
use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the segment endpoints and keeps a segment's members within the
 * caller's organization.
 */
class SegmentTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'crm.segments.view',
            'crm.segments.create',
            'crm.segments.edit',
            'crm.segments.delete',
        ]);
        $this->actingAs($this->user, 'api');

        $this->other = Organization::factory()->create();
    }

    public function test_index_lists_the_organizations_live_segments_by_name(): void
    {
        $beta = $this->segment(['name' => 'Beta']);
        $alpha = $this->segment(['name' => 'Alpha']);
        $this->segment(['name' => 'Deleted'])->delete();
        $this->segment(['name' => 'Aardvark', 'organization_id' => $this->other->id]);

        $response = $this->apiGet('/crm/segments');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$alpha->id, $beta->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_new_segment_is_evaluated_against_the_organizations_users_only(): void
    {
        $this->user->update(['phone' => null]);
        User::factory()->count(2)->create(['organization_id' => $this->organization->id, 'phone' => '+966500000001']);
        User::factory()->create(['organization_id' => $this->organization->id, 'phone' => null]);
        User::factory()->create(['organization_id' => $this->other->id, 'phone' => '+966500000002']);

        $response = $this->apiPost('/crm/segments', [
            'name' => 'Reachable by SMS',
            'conditions' => [['field' => 'has_phone', 'operator' => '=', 'value' => 1]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.color', '#6366f1')
            ->assertJsonPath('data.is_dynamic', true)
            ->assertJsonPath('data.created_by', $this->user->id);

        $segment = UserSegment::findOrFail($response->json('data.id'));
        $this->assertSame(2, $segment->member_count);
        $this->assertNotNull($segment->last_evaluated_at);
        $this->assertTrue($segment->members()->get()->every(fn (User $member) => $member->organization_id === $this->organization->id));

        $this->apiPut("/crm/segments/{$segment->id}", [
            'conditions' => [['field' => 'has_phone', 'operator' => '=', 'value' => 0]],
        ])->assertOk();
        $this->assertSame(2, $segment->fresh()->member_count);
    }

    public function test_show_and_members_page_the_members_and_another_organizations_segment_is_not_found(): void
    {
        $segment = $this->segment();
        $segment->members()->attach($this->user->id);
        $theirs = $this->segment(['organization_id' => $this->other->id]);

        $this->apiGet("/crm/segments/{$segment->id}")
            ->assertOk()
            ->assertJsonPath('data.segment.id', $segment->id)
            ->assertJsonPath('data.members.data.0.id', $this->user->id)
            ->assertJsonPath('data.members.meta.total', 1)
            ->assertJsonPath('data.members.meta.per_page', 15);

        $this->apiGet("/crm/segments/{$segment->id}/members")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->user->id)
            ->assertJsonPath('meta.total', 1);

        $this->apiGet("/crm/segments/{$theirs->id}")->assertNotFound();
        $this->apiGet("/crm/segments/{$theirs->id}/members")->assertNotFound();
        $this->apiPut("/crm/segments/{$theirs->id}", ['name' => 'Mine now'])->assertNotFound();
        $this->apiDelete("/crm/segments/{$theirs->id}")->assertNotFound();

        $this->apiPut("/crm/segments/{$segment->id}", ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->apiDelete("/crm/segments/{$segment->id}")->assertOk();
        $this->assertSoftDeleted($segment);
    }

    private function segment(array $attributes = []): UserSegment
    {
        $organizationId = $attributes['organization_id'] ?? $this->organization->id;

        return UserSegment::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'name' => 'Segment',
            'conditions' => [],
            'created_by' => $organizationId === $this->organization->id
                ? $this->user->id
                : User::factory()->create(['organization_id' => $organizationId])->id,
            ...$attributes,
        ]);
    }
}
