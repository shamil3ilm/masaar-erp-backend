<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Tax\TaxCategory;
use App\Models\Tax\TaxRate;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `migrate:fresh --seed` has to work on an empty database.
 *
 * Units and tax categories belong to an organization, but their seeders wrote
 * them with none, so the tenant guard stopped db:seed partway on every fresh
 * install and nothing ran the seeders to notice.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_database_seeds_and_the_demo_organization_gets_units_and_tax_rates(): void
    {
        $this->seed([DatabaseSeeder::class, AdminUserSeeder::class, DemoOrganizationSeeder::class]);

        $organization = Organization::withoutGlobalScopes()->where('slug', 'masaar-demo')->sole();

        $this->assertTrue(UnitOfMeasure::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('symbol', 'kg')
            ->exists());

        $standard = TaxCategory::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('code', 'S')
            ->sole();

        $this->assertEquals(15, (float) TaxRate::where('tax_category_id', $standard->id)
            ->where('country_code', 'SA')
            ->value('rate'));
    }
}
