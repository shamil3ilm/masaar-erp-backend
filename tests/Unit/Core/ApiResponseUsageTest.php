<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ApiResponse::error() takes (message, code, status).
 *
 * Calling it as error(message, 422) passes an int where a string code is
 * declared, which under strict_types is a TypeError — so the endpoint returns
 * 500 instead of the status it meant to return. This guards against that shape
 * coming back.
 */
class ApiResponseUsageTest extends TestCase
{
    public function test_no_call_passes_a_status_code_as_the_error_code(): void
    {
        $offenders = [];

        foreach ($this->phpFilesUnder(dirname(__DIR__, 3) . '/app') as $path) {
            foreach ($this->twoArgErrorCalls($path) as $line) {
                $offenders[] = str_replace(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR, '', $path) . ':' . $line;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These calls pass an integer as ApiResponse::error()'s \$code and will throw a TypeError.\n"
            . "Use error(\$message, 'CODE', \$status) — or notFound(\$message) for 404:\n  "
            . implode("\n  ", $offenders)
        );
    }

    /**
     * @return list<string>
     */
    private function phpFilesUnder(string $dir): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Line numbers of `$this->error(x, 123)` calls in a file.
     *
     * @return list<int>
     */
    private function twoArgErrorCalls(string $path): array
    {
        $tokens = token_get_all(file_get_contents($path));
        $lines  = [];
        $count  = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->startsErrorCall($tokens, $i)) {
                continue;
            }

            $args = $this->argumentsAt($tokens, $i + 3);

            if (count($args) === 2 && preg_match('/^\d{3}$/', $args[1])) {
                $lines[] = $tokens[$i][2];
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, mixed>  $tokens
     */
    private function startsErrorCall(array $tokens, int $i): bool
    {
        return is_array($tokens[$i]) && $tokens[$i][0] === T_VARIABLE && $tokens[$i][1] === '$this'
            && isset($tokens[$i + 1]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_OBJECT_OPERATOR
            && isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && $tokens[$i + 2][1] === 'error'
            && isset($tokens[$i + 3]) && $tokens[$i + 3] === '(';
    }

    /**
     * Split a call's arguments at bracket depth 1.
     *
     * @param  array<int, mixed>  $tokens
     * @return list<string>
     */
    private function argumentsAt(array $tokens, int $open): array
    {
        $depth = 0;
        $args  = [''];

        for ($j = $open, $count = count($tokens); $j < $count; $j++) {
            $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];

            if ($text === '(' || $text === '[') {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } elseif ($text === ')' || $text === ']') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif ($text === ',' && $depth === 1) {
                $args[] = '';
                continue;
            }

            $args[count($args) - 1] .= $text;
        }

        return array_map('trim', $args);
    }
}
