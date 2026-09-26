<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Tests\Feature\Architecture\Concerns\ScansSource;
use Tests\TestCase;

/**
 * Every decimal column a migration declares is cast decimal: at that scale.
 *
 * Laravel's decimal: cast returns a string on every driver. A column without
 * it is handed over as the driver returns it — a string from MySQL, a float
 * from SQLite — so production and this suite read the same column as two
 * different types, and the suite cannot fail on what production does. Money is
 * then added with bcadd((string) $model->amount, ...), and (string) on a float
 * writes only PHP's precision=14 significant digits: the last decimals go
 * silently, and a figure from 1e14 up or under 0.0001 down is written in an
 * exponent form bcmath refuses outright.
 *
 * 'float' is not a lesser version of the same thing but the same fault made
 * deliberate, on both drivers at once. A cast at the wrong scale is the fault
 * in miniature: a decimal(5,2) rate cast decimal:4 answers "15.0000" for a
 * column that cannot hold a ten-thousandth.
 *
 * Tables no model maps are skipped: there is no attribute to cast. A model
 * added for one of them later is picked up here on its next run.
 *
 * The assertion is equality, so a newly uncast column fails the build and an
 * entry below that no longer fails must be removed.
 */
class DecimalColumnCastTest extends TestCase
{
    use ScansSource;

    private const MIGRATIONS = __DIR__.'/../../../database/migrations';

    /**
     * Columns deliberately read as something other than a decimal string.
     *
     * Empty, and worth keeping so: a money column read one way in production
     * and another here is a difference no test can see.
     */
    private const ACCEPTED = [];

    public function test_every_decimal_column_is_cast_at_the_scale_it_declares(): void
    {
        $declared = $this->declaredDecimals();
        $this->assertNotEmpty($declared, 'No decimal() declarations found — the migration parser is broken.');

        $models = $this->modelsByTable();
        $this->assertNotEmpty($models, 'No models were found, so the scan would pass on nothing.');

        $found = [];
        $checked = 0;

        foreach ($declared as $table => $columns) {
            foreach ($models[$table] ?? [] as $model) {
                $casts = $model->getCasts();

                foreach ($columns as $column => $scale) {
                    $checked++;
                    $want = 'decimal:'.$scale;
                    $has = $casts[$column] ?? null;

                    if ($has !== $want) {
                        $found[] = sprintf(
                            '%s.%s (%s wants %s, has %s)',
                            $table, $column, class_basename($model), $want, $has ?? 'no cast'
                        );
                    }
                }
            }
        }

        $this->assertNotSame(0, $checked, 'No decimal column resolved to a model — the table lookup is broken.');

        sort($found);

        $this->assertSame(self::ACCEPTED, $found, sprintf(
            "These decimal columns are not read as a decimal string at the scale the migration declares, "
            ."so this suite and MySQL read them differently.\n%s",
            implode("\n", $found)
        ));
    }

    /**
     * Decimal columns per table, at the scale the last migration to declare
     * them gives. decimal() defaults to (8, 2).
     *
     * @return array<string, array<string, int>> table => column => scale
     */
    private function declaredDecimals(): array
    {
        $declared = [];

        foreach (glob(self::MIGRATIONS.'/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            foreach ($this->schemaBlocks($source) as $table => $body) {
                preg_match_all(
                    '/\$table->decimal\(\s*\'([a-z_0-9]+)\'\s*(?:,\s*\d+\s*(?:,\s*(\d+)\s*)?)?\)/',
                    $body, $matches, PREG_SET_ORDER
                );

                foreach ($matches as $match) {
                    $declared[$table][$match[1]] = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : 2;
                }
            }
        }

        ksort($declared);

        return $declared;
    }

    /**
     * One instance of every concrete model, grouped by the table it maps.
     *
     * @return array<string, list<Model>>
     */
    private function modelsByTable(): array
    {
        $models = [];

        foreach ($this->phpFilesIn('Models') as $path) {
            $code = $this->codeOf($path);

            if (! preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $code, $name)) {
                continue;
            }

            $class = $this->namespaceOf($code).chr(92).$name[1];

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $model = new $class;
            $models[$model->getTable()][] = $model;
        }

        return $models;
    }
}
