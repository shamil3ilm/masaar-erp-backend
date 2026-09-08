<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every attribute a model declares must exist as a column.
 *
 * A model that lists a column the migrations never created writes nothing and
 * reads null. On MySQL the write is an error; on SQLite a query filtering by
 * such a column is not an error at all, because SQLite reads an unknown
 * double-quoted identifier as a string literal and simply matches no rows. So
 * the suite passes, the endpoint answers 200, and the answer is empty.
 *
 * A ratchet against tests/Fixtures/model-columns.txt: the list may shrink and
 * may not grow, and an entry that is fixed must be removed.
 */
class ModelColumnTest extends TestCase
{
    use RefreshDatabase;

    private const BASELINE = __DIR__.'/../../Fixtures/model-columns.txt';

    public function test_every_model_attribute_has_a_column(): void
    {
        $found = [];

        foreach ($this->models() as $class) {
            $model = new $class;
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                $found[] = $class.' -> no table named '.$table;

                continue;
            }

            $declared = array_unique(array_merge($model->getFillable(), array_keys($model->getCasts())));
            $missing = array_values(array_diff($declared, Schema::getColumnListing($table)));
            sort($missing);

            if ($missing !== []) {
                $found[] = $class.' ('.$table.'): '.implode(', ', $missing);
            }
        }

        sort($found);
        $declared = $this->baseline();

        $new = array_values(array_diff($found, $declared));
        sort($new);

        $this->assertSame([], $new, sprintf(
            "These models declare an attribute no column backs.\n%s",
            implode("\n", $new)
        ));

        $fixed = array_values(array_diff($declared, $found));
        sort($fixed);

        $this->assertSame([], $fixed, sprintf(
            "These are in %s and no longer fail. Remove them.\n%s",
            basename(self::BASELINE),
            implode("\n", $fixed)
        ));
    }

    /** @return list<class-string<Model>> */
    private function models(): array
    {
        $out = [];
        $sep = chr(92);

        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            $src = file_get_contents($file->getRealPath());

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

    /** @return list<string> */
    private function baseline(): array
    {
        $out = [];

        foreach (file(self::BASELINE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! str_starts_with($line, '#')) {
                $out[] = $line;
            }
        }

        sort($out);

        return $out;
    }
}
