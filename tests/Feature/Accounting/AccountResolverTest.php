<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Core\Organization;
use App\Models\System\Setting;
use App\Services\Accounting\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Accounts for a posting role come from the organization's own chart, by
 * sub-type or by explicit mapping, never by matching names.
 */
class AccountResolverTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    public function test_the_system_account_of_a_sub_type_is_chosen(): void
    {
        $this->account(['code' => '1140', 'name' => 'Stock in Transit', 'is_system' => false]);
        $system = $this->account(['code' => '1150', 'name' => 'Inventory', 'is_system' => true]);
        $this->account(['code' => '1100', 'name' => 'Inventory Header', 'is_system' => true, 'is_header' => true]);

        $resolved = app(AccountResolver::class)->bySubType($this->organization->id, 'inventory');

        $this->assertTrue($system->is($resolved));
    }

    public function test_a_mapped_account_is_used(): void
    {
        $grni = Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_LIABILITY,
            'sub_type' => 'other_liability',
        ]);
        Setting::set('accounting', 'grni_account_id', $grni->id, null, $this->organization->id);

        $this->assertTrue($grni->is(app(AccountResolver::class)->mapped($this->organization->id, 'grni_account_id')));
    }

    public function test_another_organizations_account_is_never_resolved(): void
    {
        $theirs = Account::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => 'inventory',
        ]);
        Setting::set('accounting', 'grni_account_id', $theirs->id, null, $this->organization->id);

        $resolver = app(AccountResolver::class);

        $this->assertNull($resolver->bySubType($this->organization->id, 'inventory'));
        $this->assertNull($resolver->mapped($this->organization->id, 'grni_account_id'));
    }

    private function account(array $attributes): Account
    {
        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => Account::TYPE_ASSET,
            'sub_type' => 'inventory',
            ...$attributes,
        ]);
    }
}
