<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Accounting\Account;
use App\Models\Core\ImportJob;
use App\Services\Core\Importers\ChartOfAccountImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The chart of accounts import could not create an account.
 *
 * It wrote the type to a "type" attribute the model does not accept, and it
 * wrote a null sub-type into a column that requires one.
 */
class ChartOfAccountImporterTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ImportJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_CHART_OF_ACCOUNTS,
        ]);
    }

    public function test_an_account_is_created_with_its_type_and_a_default_sub_type(): void
    {
        $account = (new ChartOfAccountImporter)->importRow([
            'code' => '1999',
            'name' => 'Imported Asset',
            'type' => 'Asset',
        ], $this->job);

        $fresh = $account->fresh();
        $this->assertSame('asset', $fresh->account_type);
        $this->assertSame('other_asset', $fresh->sub_type);
    }

    public function test_equity_defaults_to_capital(): void
    {
        $account = (new ChartOfAccountImporter)->importRow([
            'code' => '3999',
            'name' => 'Imported Equity',
            'type' => 'equity',
        ], $this->job);

        $this->assertSame('capital', $account->fresh()->sub_type);
    }

    public function test_a_given_sub_type_is_kept(): void
    {
        $account = (new ChartOfAccountImporter)->importRow([
            'code' => '1998',
            'name' => 'Imported Bank',
            'type' => 'asset',
            'sub_type' => 'bank',
        ], $this->job);

        $this->assertSame('bank', $account->fresh()->sub_type);
    }

    public function test_a_sub_type_that_does_not_belong_to_the_type_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sub type for asset');

        (new ChartOfAccountImporter)->importRow([
            'code' => '1997',
            'name' => 'Wrong',
            'type' => 'asset',
            'sub_type' => 'sales',
        ], $this->job);

        $this->assertSame(0, Account::where('code', '1997')->count());
    }
}
