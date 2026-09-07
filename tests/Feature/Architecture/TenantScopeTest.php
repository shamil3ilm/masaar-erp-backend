<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\RealEstate\RentalUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A record belonging to another organization must be out of reach.
 *
 * Authorization decides what a user may do; this decides whose rows they may
 * do it to. The two are independent, and holding a permission was enough:
 * VacancyController looked a unit up with RentalUnit::findOrFail($id) on a
 * model carrying no tenant scope, so a user with real_estate.units.manage
 * could vacate a unit belonging to someone else by guessing its id. The
 * request went through and the row changed.
 *
 * Scoping at the call site is what failed. Every other call site had
 * remembered - the carrier listing scopes, the vacancy report scopes - which
 * is exactly why the one that forgot was invisible. The global scope makes it
 * not a thing a call site can forget.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_another_organizations_unit_cannot_be_vacated(): void
    {
        $this->setUpOrganization('AE');
        $this->setUpAuthenticatedUser(['real_estate.units.manage']);
        OrganizationModule::updateOrCreate(
            ['organization_id' => $this->organization->id, 'module_code' => 'real_estate'],
            ['is_enabled' => true, 'enabled_at' => now()],
        );

        $other = Organization::factory()->create();
        $theirs = $this->rentalUnitFor($other);

        $response = $this->apiPost("/real-estate/units/{$theirs}/vacate", [
            'vacant_from' => now()->toDateString(),
            'reason' => 'lease_expired',
        ]);

        $this->assertSame(404, $response->status(),
            'A unit belonging to another organization was reachable by id.');

        $this->assertSame('occupied', DB::table('rental_units')->where('id', $theirs)->value('status'),
            'Another organization\'s unit was modified.');
    }

    /**
     * The scope names organization_id, so a model that applies it to a table
     * without that column breaks on every query it is used in. Two models were
     * in that state and threw "no such column" for anything that touched them.
     */
    public function test_the_scope_only_names_columns_that_exist(): void
    {
        $offenders = [];

        foreach ($this->modelsUsingTheScope() as $class) {
            /** @var Model $model */
            $model = new $class;
            $table = $model->getTable();

            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'organization_id')) {
                $offenders[] = class_basename($class).' on '.$table;
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, sprintf(
            "These models scope by organization_id on a table that has no such "
            ."column, so every query through them fails. Either the table is not "
            ."tenant-owned - intercompany and consolidation rows name two "
            ."organizations, not one - or the column is missing.\n%s",
            implode("\n", $offenders)
        ));
    }

    /** @return list<class-string<Model>> */
    private function modelsUsingTheScope(): array
    {
        $out = [];

        foreach ($this->phpFilesIn(app_path('Models')) as $path) {
            $src = (string) file_get_contents($path);

            // The trait as applied, not the word: FailedJobMonitor's docblock
            // says "do not add BelongsToOrganization here", and matching text
            // read that as using it.
            if (! preg_match('/^namespace\s+([^;]+);/m', $src, $ns)
                || ! preg_match('/^(?:final\s+)?class\s+(\w+)/m', $src, $cls)) {
                continue;
            }

            $class = trim($ns[1]).'\\'.$cls[1];

            if (class_exists($class)
                && is_subclass_of($class, Model::class)
                && in_array(BelongsToOrganization::class, class_uses_recursive($class), true)) {
                $out[] = $class;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function phpFilesIn(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    private function rentalUnitFor(Organization $org): int
    {
        $portfolio = DB::table('portfolios')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organization_id' => $org->id,
            'code' => 'PF1', 'name' => 'Theirs', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $property = DB::table('properties')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organization_id' => $org->id, 'portfolio_id' => $portfolio,
            'code' => 'PR1', 'name' => 'Theirs', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $building = DB::table('buildings')->insertGetId([
            'uuid' => (string) Str::uuid(), 'organization_id' => $org->id, 'property_id' => $property,
            'code' => 'BL1', 'name' => 'Theirs', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return RentalUnit::withoutGlobalScopes()->create([
            'organization_id' => $org->id, 'building_id' => $building,
            'code' => 'THEIRS-1', 'name' => 'Their unit',
            'unit_type' => 'office', 'status' => 'occupied',
        ])->id;
    }
}
