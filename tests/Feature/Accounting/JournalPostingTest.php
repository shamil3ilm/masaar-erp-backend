<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * A journal entry is posted only by JournalService::postEntry(), which stamps
 * who posted it and when and clears the balance caches of its accounts.
 * createEntry() stores a draft, and createAndPost() creates and posts.
 */
class JournalPostingTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private JournalService $journals;

    /** @var list<array<string, mixed>> */
    private array $lines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $bank = $this->ledgerAccount('1010', 'Main Bank', Account::TYPE_ASSET, Account::SUBTYPE_BANK);
        $income = $this->ledgerAccount('4900', 'Other Income', Account::TYPE_INCOME, Account::SUBTYPE_OTHER_INCOME);

        $this->lines = [
            ['account_id' => $bank->id, 'debit' => 50, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 50],
        ];

        $this->journals = app(JournalService::class);
    }

    public function test_an_entry_cannot_be_created_already_posted(): void
    {
        try {
            $this->journals->createEntry($this->header() + ['status' => JournalEntry::STATUS_POSTED], $this->lines);
            $this->fail('An entry was created with a posted status, bypassing postEntry().');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_create_and_post_posts_the_entry_through_post_entry(): void
    {
        $entry = $this->journals->createAndPost($this->header(), $this->lines);

        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertEquals($this->user->id, $entry->posted_by);
    }

    /** @return array<string, mixed> */
    private function header(): array
    {
        return [
            'organization_id' => $this->organization->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Interest received',
        ];
    }
}
