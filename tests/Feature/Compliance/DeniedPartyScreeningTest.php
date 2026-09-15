<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Compliance\DpsListEntry;
use App\Models\Compliance\DpsSanctionList;
use App\Models\Compliance\DpsScreeningRun;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Denied-party screening: sanction lists and their entries, screening a
 * contact against them, and a compliance officer clearing a match.
 */
class DeniedPartyScreeningTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    private DpsSanctionList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['compliance.dps.view', 'compliance.dps.manage']);
        $this->otherOrganization = Organization::factory()->create();

        $this->list = $this->sanctionList($this->organization->id, 'OFAC SDN');
        $this->entry($this->list, 'Acme Arms Trading');
        $this->entry($this->list, 'Retired Name', ['is_active' => false]);
    }

    // ----------------------------------------------------------------
    // Lists and entries
    // ----------------------------------------------------------------

    public function test_lists_shows_this_organizations_lists_with_their_active_entry_count(): void
    {
        $this->sanctionList($this->otherOrganization->id, 'Foreign list');

        $this->apiGet('/compliance/dps/lists')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.list_name', 'OFAC SDN')
            ->assertJsonPath('data.0.entries_count', 1);
    }

    public function test_a_list_is_created_shown_and_updated(): void
    {
        $id = $this->apiPost('/compliance/dps/lists', [
            'list_name' => 'UN Consolidated',
            'list_authority' => 'UN',
            'list_type' => 'denied_party',
        ])->assertCreated()->json('data.id');

        $this->apiPut("/compliance/dps/lists/{$id}", ['list_name' => 'UN Consolidated 2026'])
            ->assertOk()
            ->assertJsonPath('data.list_name', 'UN Consolidated 2026');

        $this->apiGet("/compliance/dps/lists/{$id}")
            ->assertOk()
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.entries_count', 0);
    }

    public function test_another_organizations_list_and_its_entries_are_unreachable(): void
    {
        $foreign = $this->sanctionList($this->otherOrganization->id, 'Foreign list');

        $this->apiGet("/compliance/dps/lists/{$foreign->id}")->assertNotFound();
        $this->apiGet("/compliance/dps/lists/{$foreign->id}/entries")->assertNotFound();
        $this->apiPost("/compliance/dps/lists/{$foreign->id}/entries", ['entry_type' => 'entity', 'name' => 'Probe'])->assertNotFound();
        $this->apiPost("/compliance/dps/lists/{$foreign->id}/import", ['entries' => [['name' => 'Probe']]])->assertNotFound();

        $this->assertSame(0, DpsListEntry::where('dps_sanction_list_id', $foreign->id)->count());
    }

    public function test_entries_are_added_searched_and_imported(): void
    {
        $this->apiPost("/compliance/dps/lists/{$this->list->id}/entries", [
            'entry_type' => 'person',
            'name' => 'Ivan Petrov',
            'aliases' => ['I. Petrov'],
        ])->assertCreated()->assertJsonPath('data.dps_sanction_list_id', $this->list->id);

        $this->apiPost("/compliance/dps/lists/{$this->list->id}/import", [
            'entries' => [['name' => 'Blue Ocean Shipping'], ['name' => 'Red Sky Aviation', 'entry_type' => 'aircraft']],
        ])->assertOk()->assertJsonPath('data.imported', 2);

        $this->assertSame(4, $this->list->fresh()->entry_count);

        $this->apiGet("/compliance/dps/lists/{$this->list->id}/entries?search=Ocean")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Blue Ocean Shipping');
    }

    // ----------------------------------------------------------------
    // Screening
    // ----------------------------------------------------------------

    public function test_screening_a_contact_records_its_matches(): void
    {
        $contact = $this->contact('Acme Arms Trading');

        $this->apiPost('/compliance/dps/screen-contact', ['contact_id' => $contact->id])
            ->assertOk()
            ->assertJsonPath('data.status', DpsScreeningRun::STATUS_CONFIRMED_MATCH)
            ->assertJsonPath('data.results.0.list_entry.name', 'Acme Arms Trading');

        $this->apiGet("/compliance/dps/contacts/{$contact->id}/status")
            ->assertOk()
            ->assertJsonPath('data.is_clean', false)
            ->assertJsonPath('data.latest_run_status', DpsScreeningRun::STATUS_CONFIRMED_MATCH);

        $this->apiGet('/compliance/dps/pending-reviews')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_screening_refuses_a_contact_of_another_organization(): void
    {
        $foreign = Contact::factory()->create([
            'organization_id' => $this->otherOrganization->id,
            'company_name' => 'Acme Arms Trading',
        ]);

        $this->apiPost('/compliance/dps/screen-contact', ['contact_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DpsScreeningRun::withoutGlobalScopes()->count());
    }

    public function test_runs_are_listed_by_status(): void
    {
        $this->screeningRun(DpsScreeningRun::STATUS_CLEAN);
        $this->screeningRun(DpsScreeningRun::STATUS_POTENTIAL_MATCH);

        $this->apiGet('/compliance/dps/runs?status=potential_match')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', DpsScreeningRun::STATUS_POTENTIAL_MATCH);
    }

    // ----------------------------------------------------------------
    // Clearing a match
    // ----------------------------------------------------------------

    public function test_a_match_is_cleared_and_shows_who_cleared_it_by_name(): void
    {
        $run = $this->screeningRun(DpsScreeningRun::STATUS_POTENTIAL_MATCH);

        $this->apiPost("/compliance/dps/runs/{$run->id}/clear", ['notes' => 'Different company, verified registry.'])
            ->assertOk()
            ->assertJsonPath('data.status', DpsScreeningRun::STATUS_CLEARED)
            ->assertJsonPath('data.cleared_by.name', $this->user->name);

        $clearedBy = $this->apiGet("/compliance/dps/runs/{$run->id}")->assertOk()->json('data.cleared_by');

        $this->assertSame(['id', 'name'], array_keys($clearedBy));
    }

    public function test_a_clearance_is_not_overwritten_and_a_clean_run_is_not_cleared(): void
    {
        $cleared = $this->screeningRun(DpsScreeningRun::STATUS_POTENTIAL_MATCH);
        $clean = $this->screeningRun(DpsScreeningRun::STATUS_CLEAN);

        $this->apiPost("/compliance/dps/runs/{$cleared->id}/clear", ['notes' => 'First officer review.'])->assertOk();

        $this->apiPost("/compliance/dps/runs/{$cleared->id}/clear", ['notes' => 'Second officer rewrite.'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SCREENING_NOT_REVIEWABLE');

        $this->apiPost("/compliance/dps/runs/{$clean->id}/clear", ['notes' => 'Nothing to clear.'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SCREENING_NOT_REVIEWABLE');

        $this->assertSame('First officer review.', $cleared->fresh()->clearance_notes);
        $this->assertSame(DpsScreeningRun::STATUS_CLEAN, $clean->fresh()->status);
    }

    public function test_another_organizations_run_is_unreachable(): void
    {
        $foreign = $this->screeningRun(DpsScreeningRun::STATUS_POTENTIAL_MATCH, $this->otherOrganization->id);

        $this->apiGet("/compliance/dps/runs/{$foreign->id}")->assertNotFound();
        $this->apiPost("/compliance/dps/runs/{$foreign->id}/clear", ['notes' => 'Cross-tenant probe.'])->assertNotFound();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function sanctionList(int $organizationId, string $name): DpsSanctionList
    {
        return DpsSanctionList::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'list_name' => $name,
            'list_authority' => 'OFAC',
            'list_type' => 'denied_party',
            'is_active' => true,
        ]);
    }

    private function entry(DpsSanctionList $list, string $name, array $attributes = []): DpsListEntry
    {
        return DpsListEntry::create(array_merge([
            'dps_sanction_list_id' => $list->id,
            'entry_type' => DpsListEntry::ENTRY_ENTITY,
            'name' => $name,
            'is_active' => true,
        ], $attributes));
    }

    private function contact(string $companyName): Contact
    {
        return Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'company_name' => $companyName,
        ]);
    }

    private function screeningRun(string $status, ?int $organizationId = null): DpsScreeningRun
    {
        return DpsScreeningRun::withoutGlobalScopes()->create([
            'organization_id' => $organizationId ?? $this->organization->id,
            'screened_entity_type' => 'contact',
            'screened_entity_id' => 1,
            'screening_date' => now(),
            'match_threshold' => 80,
            'status' => $status,
            'triggered_by' => DpsScreeningRun::TRIGGER_MANUAL,
        ]);
    }
}
