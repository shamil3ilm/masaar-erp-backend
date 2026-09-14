<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DunningBlock;
use App\Models\Accounting\DunningLevel;
use App\Models\Accounting\DunningRun;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DunningTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.dunning.view',
            'accounting.dunning.configure',
            'accounting.dunning.run',
            'accounting.dunning.send',
            'accounting.dunning.block',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLevel(array $overrides = []): DunningLevel
    {
        return DunningLevel::create(array_merge([
            'organization_id' => $this->organization->id,
            'level_number'    => fake()->unique()->numberBetween(1, 9),
            'name'            => 'Level ' . fake()->numerify('##'),
            'days_overdue_from' => 30,
            'is_active'       => true,
        ], $overrides));
    }

    private function makeRun(array $overrides = []): DunningRun
    {
        return DunningRun::create(array_merge([
            'organization_id' => $this->organization->id,
            'run_date'        => '2025-01-31',
            'status'          => 'draft',
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    private function makeContact(): Contact
    {
        return Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    private function makeBlock(Contact $contact, array $overrides = []): DunningBlock
    {
        return DunningBlock::create(array_merge([
            'organization_id' => $this->organization->id,
            'contact_id'      => $contact->id,
            'reason'          => 'Payment plan agreed',
            'blocked_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Index
    // -------------------------------------------------------------------------

    public function test_index_levels_returns_list(): void
    {
        $this->makeLevel();
        $this->makeLevel();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/levels');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_levels_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/levels');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Store
    // -------------------------------------------------------------------------

    public function test_store_level_creates_dunning_level(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/levels', [
                'level_number'      => 1,
                'name'              => 'First Notice',
                'days_overdue_from' => 30,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_level_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/levels', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Update
    // -------------------------------------------------------------------------

    public function test_update_level_modifies_level(): void
    {
        $level = $this->makeLevel(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/dunning/levels/' . $level->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $level->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Dunning Levels — Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_level_soft_deletes(): void
    {
        $level = $this->makeLevel();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/dunning/levels/' . $level->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('dunning_levels', ['id' => $level->id]);
    }

    // -------------------------------------------------------------------------
    // Dunning Runs
    // -------------------------------------------------------------------------

    public function test_run_dunning_validates_run_date(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/run', []);

        $response->assertStatus(422);
    }

    public function test_index_runs_returns_list(): void
    {
        $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/runs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_run_returns_details(): void
    {
        $run = $this->makeRun();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/runs/' . $run->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $run->id);
    }

    // -------------------------------------------------------------------------
    // Dunning Blocks
    // -------------------------------------------------------------------------

    public function test_index_blocks_returns_list(): void
    {
        $contact = $this->makeContact();
        $this->makeBlock($contact);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/dunning/blocks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_create_block_places_block_on_contact(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/blocks/contacts/' . $contact->uuid, [
                'reason' => 'Disputed invoice',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_create_block_validates_reason_required(): void
    {
        $contact = $this->makeContact();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/blocks/contacts/' . $contact->uuid, []);

        $response->assertStatus(422);
    }

    public function test_release_block_releases_active_block(): void
    {
        $contact = $this->makeContact();
        $block   = $this->makeBlock($contact);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/dunning/blocks/' . $block->uuid . '/release', [
                'release_reason' => 'Payment received',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotNull($block->fresh()->released_at);
    }

    public function test_index_blocks_filters_active_only(): void
    {
        $this->makeBlock($this->makeContact(), ['reason' => 'active']);
        $this->makeBlock($this->makeContact(), ['reason' => 'released', 'released_at' => now()->subDay()]);

        $all = $this->withToken($this->token)->getJson('/api/v1/dunning/blocks');
        $this->assertCount(2, $all->json('data'));

        $active = $this->withToken($this->token)->getJson('/api/v1/dunning/blocks?active_only=1');
        $active->assertStatus(200);
        $this->assertSame(['active'], array_column($active->json('data'), 'reason'));
    }

    public function test_index_levels_and_runs_are_ordered_within_the_organization(): void
    {
        $this->makeLevel(['level_number' => 3, 'name' => 'Third']);
        $this->makeLevel(['level_number' => 1, 'name' => 'First']);
        $this->makeRun(['run_date' => '2025-01-31']);
        $this->makeRun(['run_date' => '2025-02-28']);
        $otherOrg = \App\Models\Core\Organization::factory()->create();
        $this->makeLevel(['organization_id' => $otherOrg->id, 'level_number' => 2, 'name' => 'Foreign']);
        $this->makeRun(['organization_id' => $otherOrg->id, 'run_date' => '2025-03-31']);

        $levels = $this->withToken($this->token)->getJson('/api/v1/dunning/levels');
        $this->assertSame(['First', 'Third'], array_column($levels->json('data'), 'name'));

        $runs = $this->withToken($this->token)->getJson('/api/v1/dunning/runs');
        $this->assertSame(
            ['2025-02-28', '2025-01-31'],
            array_map(fn (array $run) => substr($run['run_date'], 0, 10), $runs->json('data')),
        );
    }

    public function test_store_level_is_created_in_the_callers_organization(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/dunning/levels', [
                'level_number'      => 2,
                'name'              => 'Reminder',
                'days_overdue_from' => 15,
                'organization_id'   => 999999,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Reminder');

        $this->assertDatabaseHas('dunning_levels', ['name' => 'Reminder', 'organization_id' => $this->organization->id]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/dunning/levels')->assertStatus(401);
    }
}
