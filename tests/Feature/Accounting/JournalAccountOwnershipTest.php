<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A journal line posts only to its own organization's accounts.
 *
 * validateLines loaded accounts by id alone, so a line naming another tenant's
 * account passed as found and posted to it. config('erp.default_accounts')
 * holds a single id for every organization, which made that the default path.
 */
class JournalAccountOwnershipTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
    }

    public function test_a_line_cannot_post_to_another_tenants_account(): void
    {
        $own = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => 'cash',
        ]);
        $theirs = Account::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'account_type' => Account::TYPE_INCOME,
            'sub_type' => 'sales',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line 1: account not found.');

        app(JournalService::class)->createEntry([
            'organization_id' => $this->organization->id,
            'entry_date' => now()->toDateString(),
            'reference' => 'CROSS-1',
            'description' => 'Posting to another organization',
        ], [
            ['account_id' => $own->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $theirs->id, 'debit' => 0, 'credit' => 100],
        ]);
    }
}
