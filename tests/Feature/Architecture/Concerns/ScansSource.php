<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture\Concerns;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Source reading shared by the layering ratchets.
 *
 * The ratchets match PHP as text. Comments are blanked to spaces before
 * matching, so a commented-out call does not count and every offset stays where
 * it was. Class names are resolved through the file's namespace and use
 * statements, so aliased and fully qualified references are recognised.
 */
trait ScansSource
{
    /**
     * PHP files under a directory of app/, keyed by their path relative to it.
     *
     * @return array<string, string> relative path => absolute path
     */
    private function phpFilesIn(string $appDirectory): array
    {
        $root = (string) realpath(__DIR__.'/../../../../app/'.$appDirectory);

        $this->assertDirectoryExists($root, "app/{$appDirectory} was not found, so the scan would pass on nothing.");

        $files = [];

        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if ($file->getExtension() === 'php') {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $files[$relative] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }

    private function codeOf(string $path): string
    {
        return $this->withoutComments((string) file_get_contents($path));
    }

    /** The source with line endings normalised and comments replaced by spaces. */
    private function withoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all(str_replace("\r\n", "\n", $source)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= preg_replace('/[^\n]/', ' ', $token[1]);
            } else {
                $code .= is_array($token) ? $token[1] : $token;
            }
        }

        return $code;
    }

    private function namespaceOf(string $code): string
    {
        return preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $code, $match) ? $match[1] : '';
    }

    /** @return array<string, string> local name => fully qualified name */
    private function importsOf(string $code): array
    {
        $imports = [];

        preg_match_all('/^use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $uses, PREG_SET_ORDER);

        foreach ($uses as $use) {
            $imports[$use[2] ?? $this->shortName($use[1])] = $use[1];
        }

        preg_match_all('/^use\s+\\\\?([\w\\\\]+)\\\\\{([^}]*)\}\s*;/m', $code, $groups, PREG_SET_ORDER);

        foreach ($groups as $group) {
            foreach (explode(',', $group[2]) as $member) {
                if (preg_match('/^\s*([\w\\\\]+)(?:\s+as\s+(\w+))?\s*$/', $member, $parts)) {
                    $imports[$parts[2] ?? $this->shortName($parts[1])] = $group[1].'\\'.$parts[1];
                }
            }
        }

        return $imports;
    }

    /**
     * The fully qualified name a class reference stands for in a file.
     *
     * @param  array<string, string>  $imports
     */
    private function resolveClass(string $name, array $imports, string $namespace): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $head = explode('\\', $name)[0];

        if (isset($imports[$head])) {
            return $imports[$head].substr($name, strlen($head));
        }

        return $namespace === '' ? $name : $namespace.'\\'.$name;
    }

    private function shortName(string $class): string
    {
        return substr($class, (int) strrpos('\\'.$class, '\\'));
    }

    /**
     * Fails unless every file's count equals its listed count, and prints the
     * entries to change in the form the list is written in.
     *
     * @param  array<string, int>  $listed
     * @param  array<string, int>  $found
     */
    private function assertCountsMatch(array $listed, array $found, string $advice): void
    {
        ksort($listed);
        ksort($found);

        $changes = [];

        foreach (array_keys($listed + $found) as $file) {
            $was = $listed[$file] ?? 0;
            $now = $found[$file] ?? 0;

            if ($was !== $now) {
                $changes[$file] = $now === 0
                    ? sprintf("        // remove '%s' (listed %d, found 0)", $file, $was)
                    : sprintf("        '%s' => %d, // listed %d", $file, $now, $was);
            }
        }

        ksort($changes);

        $this->assertSame($listed, $found, $advice."\n\nEntries to change:\n".implode("\n", $changes));
    }
}
