<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Accounting\JournalEntry;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers the erp:archive command and the ArchiveService rules behind it.
 */
class ArchiveOldDataTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_dry_run_reports_counts_without_deleting(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => 'paid',
            'invoice_date'    => now()->subYears(3),
        ]);

        $this->artisan('erp:archive', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseCount('invoice_archives', 0);
    }

    public function test_settled_old_invoice_is_moved_to_the_archive_table(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => 'paid',
            'invoice_date'    => now()->subYears(3),
        ]);

        $this->artisan('erp:archive')->assertSuccessful();

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoice_archives', ['uuid' => $invoice->uuid]);
    }

    public function test_unpaid_invoice_is_never_archived(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => 'sent',
            'invoice_date'    => now()->subYears(3),
        ]);

        $this->artisan('erp:archive')->assertSuccessful();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseCount('invoice_archives', 0);
    }

    public function test_recent_invoice_is_not_archived(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => 'paid',
            'invoice_date'    => now()->subDays(5),
        ]);

        $this->artisan('erp:archive')->assertSuccessful();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_posted_old_journal_entry_is_archived(): void
    {
        $entry = JournalEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'status'          => 'posted',
            'entry_date'      => now()->subYears(3),
        ]);

        $this->artisan('erp:archive')->assertSuccessful();

        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('journal_entry_archives', ['uuid' => $entry->uuid]);
    }
}
