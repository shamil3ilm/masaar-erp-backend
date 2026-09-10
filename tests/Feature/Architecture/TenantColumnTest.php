<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A tenant-scoped model needs the column its scope filters on.
 *
 * BelongsToOrganization adds a global scope on organization_id. If the table
 * has no such column the scope still builds its where clause, and SQLite
 * reads the unknown quoted identifier as a string rather than failing, so the
 * clause matches nothing — or, depending on the query, stops narrowing
 * anything. A scope that silently does not scope is the worst way for this
 * particular thing to break, so it is worth stating as a rule.
 */
class TenantColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_scoped_model_has_the_column(): void
    {
        $missing = [];
        $scoped = 0;

        foreach ($this->models() as $class) {
            $traits = array_map(
                static fn (string $t) => substr($t, strrpos($t, chr(92)) + 1),
                array_values(class_uses_recursive($class))
            );

            if (! in_array('BelongsToOrganization', $traits, true)) {
                continue;
            }

            $scoped++;

            $table = (new $class)->getTable();

            if (! Schema::hasTable($table)) {
                $missing[] = $class.' -> no table named '.$table;

                continue;
            }

            if (! Schema::hasColumn($table, 'organization_id')) {
                $missing[] = $class.' ('.$table.')';
            }
        }

        // Without this the check passes by examining nothing, which is how a
        // rename of the trait or a change to the model directory would turn it
        // into a green tick that means nothing.
        $this->assertGreaterThan(
            100,
            $scoped,
            'Far fewer scoped models than expected — the trait check is probably broken.'
        );

        sort($missing);

        $this->assertSame([], $missing, sprintf(
            "These models are scoped to an organisation by a column their table does not have.\n%s",
            implode("\n", $missing)
        ));
    }

    /** @return list<class-string<Model>> */
    private function models(): array
    {
        $out = [];
        $sep = chr(92);

        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            $src = (string) file_get_contents($file->getRealPath());

            if (! preg_match('/^namespace\s+([^;]+);/m', $src, $ns)) {
                continue;
            }

            if (! preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $src, $cl)) {
                continue;
            }

            $class = trim($ns[1]).$sep.$cl[1];

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if (! $reflection->isAbstract() && $reflection->isSubclassOf(Model::class)) {
                $out[] = $class;
            }
        }

        return $out;
    }
}
