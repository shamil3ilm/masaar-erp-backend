<?php

declare(strict_types=1);

/**
 * Write the failing tests to the job summary.
 *
 * A failing suite shows up in the Actions UI as "Process completed with exit
 * code 2" and nothing more, so reading it means opening the job and scrolling
 * the log. This puts the test names and messages on the run's own page.
 *
 * Usage: php .github/junit-summary.php <junit.xml> [<label>]
 */

$path = $argv[1] ?? null;
$label = $argv[2] ?? 'Tests';
$summary = getenv('GITHUB_STEP_SUMMARY') ?: 'php://stdout';

if ($path === null || ! is_file($path)) {
    file_put_contents($summary, "### {$label}\n\nNo JUnit file was written, so the run failed before the suite started.\n", FILE_APPEND);

    exit(0);
}

$xml = @simplexml_load_file($path);

if ($xml === false) {
    file_put_contents($summary, "### {$label}\n\nThe JUnit file could not be parsed.\n", FILE_APPEND);

    exit(0);
}

$cases = $xml->xpath('//testcase[failure or error]') ?: [];

if ($cases === []) {
    file_put_contents($summary, "### {$label}\n\nEvery test passed.\n", FILE_APPEND);

    exit(0);
}

$out = "### {$label}\n\n".count($cases)." test(s) did not pass.\n\n";

foreach ($cases as $case) {
    $name = (string) $case['class'].'::'.(string) $case['name'];
    $message = trim((string) ($case->failure ?? $case->error));

    // The first few lines carry the reason; the rest is a stack trace.
    $lines = [];

    foreach (preg_split('/\r?\n/', $message) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || preg_match('#^(/|[A-Za-z]:\\\\)#', $line) === 1) {
            continue;
        }

        $lines[] = $line;

        if (count($lines) >= 4) {
            break;
        }
    }

    $out .= "**{$name}**\n\n```\n".implode("\n", $lines)."\n```\n\n";
}

file_put_contents($summary, $out, FILE_APPEND);
