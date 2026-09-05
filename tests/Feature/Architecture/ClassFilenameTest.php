<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every class must be named after the file that holds it.
 *
 * PSR-4 resolves a class name to a path. When the two disagree the class is
 * simply unreachable: nothing can autoload it, no consumer can reference it,
 * and a search for its users finds none — because it has none, and cannot.
 *
 * Three services were in that state. Services/Core/ExportService.php declared
 * AsyncExportService, Services/Reports/DashboardService.php declared
 * DashboardStatisticsService. Their controllers imported the class names,
 * which were correct and unloadable, so both endpoints had been failing since
 * they were written. A later cleanup then read the same evidence the other way
 * round and deleted all three as unused, which took a fourth service with them
 * that was genuinely in use by two others.
 *
 * The mistake is easy and the check is trivial, which is the argument for
 * having it. A file whose class it does not name is not a style problem; it is
 * code that cannot run.
 */
class ClassFilenameTest extends TestCase
{
    private const APP = __DIR__.'/../../../app';

    public function test_class_names_match_their_files(): void
    {
        $mismatches = [];

        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath(self::APP), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $checked = 0;

        foreach ($tree as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            if (! preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $m)) {
                continue;   // no top-level type: a helpers file, or a closure-only file
            }

            $checked++;
            $expected = $file->getBasename('.php');

            if ($m[1] !== $expected) {
                $mismatches[] = sprintf(
                    '%s declares %s',
                    str_replace(realpath(self::APP).DIRECTORY_SEPARATOR, '', $file->getPathname()),
                    $m[1]
                );
            }
        }

        $this->assertGreaterThan(500, $checked, 'The scan found too few files to be working.');

        sort($mismatches);

        $this->assertSame([], $mismatches, sprintf(
            "These files declare a class the filename does not name, so PSR-4 cannot "
            ."autoload them. Nothing can use them, and a search for consumers will "
            ."wrongly report they have none.\n%s",
            implode("\n", $mismatches)
        ));
    }
}
