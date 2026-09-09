<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A relation's foreign key has to exist.
 *
 * Eloquent does not check. A belongsTo naming a column the table does not
 * have builds a query against it, and on SQLite an unknown quoted identifier
 * is read as a string, so the relation resolves to null instead of raising.
 * The record simply appears to have no parent.
 *
 * Only methods whose declared return type is a relation are called, so
 * nothing here runs application logic.
 */
class RelationKeyTest extends TestCase
{
    use RefreshDatabase;

    private const BASELINE = __DIR__.'/../../Fixtures/relation-keys.txt';

    public function test_every_relation_key_exists(): void
    {
        $found = [];

        foreach ($this->models() as $class) {
            foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getNumberOfRequiredParameters() > 0 || $method->isStatic()) {
                    continue;
                }

                $type = $method->getReturnType();

                if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $returns = $type->getName();

                if (! is_a($returns, Relation::class, true)) {
                    continue;
                }

                try {
                    $relation = (new $class)->{$method->getName()}();
                } catch (\Throwable) {
                    // A relation that cannot even be built is a different
                    // problem, and one the smoke test already reports.
                    continue;
                }

                foreach ($this->keysOf($relation) as [$table, $column]) {
                    if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
                        $found[] = $class.'::'.$method->getName().' -> '.$table.'.'.$column;
                    }
                }
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        $declared = $this->baseline();

        $new = array_values(array_diff($found, $declared));
        sort($new);

        $this->assertSame([], $new, sprintf(
            "These relations name a column that does not exist, so they always resolve to nothing.\n%s",
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

    /**
     * The table and column each side of the relation reads.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function keysOf(Relation $relation): array
    {
        $related = $relation->getRelated();
        $parent = $relation->getParent();

        // MorphTo extends BelongsTo but resolves its target at runtime, so
        // there is no single column to check.
        if ($relation instanceof MorphTo) {
            return [];
        }

        if ($relation instanceof BelongsTo) {
            return [
                [$parent->getTable(), $relation->getForeignKeyName()],
                [$related->getTable(), $relation->getOwnerKeyName()],
            ];
        }

        if ($relation instanceof HasMany || $relation instanceof HasOne) {
            return [
                [$related->getTable(), $relation->getForeignKeyName()],
                [$parent->getTable(), $relation->getLocalKeyName()],
            ];
        }

        return [];
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
