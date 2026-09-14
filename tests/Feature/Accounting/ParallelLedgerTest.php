<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\SpecialLedger;
use App\Models\Accounting\SpecialLedgerEntry;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ParallelLedgerTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.ledgers.view',
            'accounting.ledgers.manage',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeLedger(array $overrides = []): SpecialLedger
    {
        return SpecialLedger::create(array_merge([
            'organization_id'      => $this->organization->id,
            'code'                 => 'PL-' . fake()->unique()->numerify('##'),
            'name'                 => 'IFRS Ledger',
            'accounting_principle' => 'IFRS',
            'is_leading'           => false,
            'is_active'            => true,
            'currency_code'        => 'SAR',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_ledger_list(): void
    {
        $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parallel-ledgers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_parallel_ledger(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers', [
                'code'                 => 'IFRS',
                'name'                 => 'IFRS Ledger',
                'accounting_principle' => 'IFRS',
                'currency_code'        => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_accounting_principle_enum(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers', [
                'code'                 => 'XX',
                'name'                 => 'Test',
                'accounting_principle' => 'INVALID',
                'currency_code'        => 'SAR',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function test_comparison_validates_fiscal_year_required(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parallel-ledgers/' . $ledger->id . '/comparison');

        $response->assertStatus(422);
    }

    public function test_comparison_returns_data_for_valid_ledger(): void
    {
        $ledger = $this->makeLedger();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parallel-ledgers/' . $ledger->id . '/comparison?fiscal_year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Post Entry
    // -------------------------------------------------------------------------

    public function test_post_entry_returns_404_for_missing_ledger(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers/99999/post/1');

        $response->assertStatus(404);
    }

    public function test_store_creates_an_active_ledger_in_the_users_organization(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parallel-ledgers', [
                'code'                 => 'TAX',
                'name'                 => 'Tax Ledger',
                'accounting_principle' => 'TAX',
                'currency_code'        => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'TAX')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_post_entry_posts_each_journal_line_to_the_ledger(): void
    {
        $ledger = $this->makeLedger();
        $entry  = $this->makeJournalEntry($this->organization->id);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/parallel-ledgers/{$ledger->id}/post/{$entry->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Journal entry posted to parallel ledger');

        $this->assertSame(2, SpecialLedgerEntry::where('special_ledger_id', $ledger->id)->count());
    }

    public function test_post_entry_returns_404_for_another_organizations_ledger_or_entry(): void
    {
        $otherOrg    = Organization::factory()->create();
        $ownLedger   = $this->makeLedger();
        $otherLedger = $this->makeLedger(['organization_id' => $otherOrg->id]);
        $ownEntry    = $this->makeJournalEntry($this->organization->id);
        $otherEntry  = $this->makeJournalEntry($otherOrg->id);

        $this->withToken($this->token)
            ->postJson("/api/v1/parallel-ledgers/{$otherLedger->id}/post/{$ownEntry->id}")
            ->assertStatus(404);

        $this->withToken($this->token)
            ->postJson("/api/v1/parallel-ledgers/{$ownLedger->id}/post/{$otherEntry->id}")
            ->assertStatus(404);

        $this->assertSame(0, SpecialLedgerEntry::withoutGlobalScopes()->count());
    }

    public function test_post_entry_writes_no_line_when_a_later_line_fails(): void
    {
        $ledger = $this->makeLedger();
        $entry  = $this->makeJournalEntry($this->organization->id);

        $created = 0;
        SpecialLedgerEntry::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new \RuntimeException('Simulated failure on the second ledger line.');
            }
        });

        $this->withToken($this->token)
            ->postJson("/api/v1/parallel-ledgers/{$ledger->id}/post/{$entry->id}")
            ->assertStatus(500);

        $this->assertSame(0, SpecialLedgerEntry::where('special_ledger_id', $ledger->id)->count());
    }

    private function makeJournalEntry(int $organizationId): JournalEntry
    {
        $account = Account::factory()->create(['organization_id' => $organizationId]);
        $entry   = JournalEntry::factory()->create([
            'organization_id' => $organizationId,
            'currency_code'   => 'SAR',
            'entry_date'      => '2025-03-15',
            'status'          => 'posted',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $account->id,
            'debit'            => 500,
            'credit'           => 0,
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $account->id,
            'debit'            => 0,
            'credit'           => 500,
        ]);

        return $entry;
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/parallel-ledgers')->assertStatus(401);
    }
}
