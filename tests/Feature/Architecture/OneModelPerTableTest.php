<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A table has one model.
 *
 * Where two models share a table, they disagree about it. leave_types has a
 * model naming annual_quota and carry_forward, which the table has, and
 * another naming leave_policy_id and gender_restriction, which it does not —
 * so one half of the leave module works and the other answers 500. The same
 * split put an organisation-scoped model on the platform feature_flags table.
 *
 * Whichever model is right, two of them cannot be. This is a ratchet against
 * tests/Fixtures/duplicate-models.txt: the list may shrink and may not grow,
 * and an entry that is resolved must be removed.
 */
class OneModelPerTableTest extends TestCase
{
    private const BASELINE = __DIR__.'/../../Fixtures/duplicate-models.txt';

    public function test_each_table_has_one_model(): void
    {
        $byTable = [];

        foreach ($this->models() as $class) {
            $byTable[(new $class)->getTable()][] = $class;
        }

        $found = [];

        foreach ($byTable as $table => $classes) {
            if (count($classes) > 1) {
                sort($classes);
                $found[] = $table.': '.implode(', ', $classes);
            }
        }

        sort($found);
        $declared = $this->baseline();

        $new = array_values(array_diff($found, $declared));
        sort($new);

        $this->assertSame([], $new, sprintf(
            "These tables gained a second model.\n%s",
            implode("\n", $new)
        ));

        $resolved = array_values(array_diff($declared, $found));
        sort($resolved);

        $this->assertSame([], $resolved, sprintf(
            "These are in %s and no longer clash. Remove them.\n%s",
            basename(self::BASELINE),
            implode("\n", $resolved)
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
