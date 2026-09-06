<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every setting this application reads must be named in .env.example.
 *
 * A knob read from the environment and written down nowhere is undiscoverable.
 * The deployment silently takes the default and the operator has no way to know
 * a choice was made on their behalf. ERP_PO_APPROVAL_THRESHOLD was in that
 * state: purchase orders at or above it need approval, and every deployment
 * that had never heard of it was accepting 10000 in whatever currency the
 * organization happened to use.
 *
 * A commented-out entry counts. It still tells an operator the setting exists,
 * and is the right form for a secret, or for a default that should not be
 * disturbed without a reason.
 *
 * Only this application's own configuration is checked. Laravel's own config
 * files read hundreds of variables that ship undocumented in every project;
 * listing them would bury the thirteen that are actually ours.
 */
class EnvExampleTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    /**
     * Config files that come with the framework, not with this application.
     *
     * cors.php is deliberately absent. Laravel ships it with no env() calls at
     * all; this one reads CORS_ALLOWED_ORIGINS and fails closed without it, so
     * a deployment that has not set it rejects every browser request. That is
     * exactly the kind of setting this test exists to keep discoverable.
     */
    private const FRAMEWORK_CONFIG = [
        'app', 'auth', 'broadcasting', 'cache', 'database', 'filesystems',
        'hashing', 'logging', 'mail', 'queue', 'sanctum', 'services', 'session',
        'view', 'telescope',
    ];

    public function test_every_setting_is_documented(): void
    {
        $documented = $this->documented();
        $this->assertNotEmpty($documented, '.env.example is missing or unreadable.');

        $read = $this->settingsRead();
        $this->assertNotEmpty($read, 'Found no env() calls — the scan is broken.');

        $undocumented = array_values(array_diff(array_keys($read), $documented));
        sort($undocumented);

        $lines = array_map(
            fn (string $v): string => sprintf('%s (read in %s)', $v, $read[$v]),
            $undocumented
        );

        $this->assertSame([], $lines, sprintf(
            "These settings are read from the environment but named nowhere in "
            ."\n.env.example, so a deployment takes the default without knowing a "
            ."choice existed. A commented-out line is enough.\n%s",
            implode("\n", $lines)
        ));
    }

    /** @return array<string, string> variable => where it is read */
    private function settingsRead(): array
    {
        $out = [];

        foreach (glob(self::ROOT.'/config/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (in_array($name, self::FRAMEWORK_CONFIG, true)) {
                continue;
            }

            foreach ($this->envCalls((string) file_get_contents($file)) as $var) {
                $out[$var] ??= 'config/'.$name.'.php';
            }
        }

        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath(self::ROOT.'/app'), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach ($this->envCalls((string) file_get_contents($file->getPathname())) as $var) {
                $out[$var] ??= 'app/';
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function envCalls(string $source): array
    {
        preg_match_all("/env\(\s*'([A-Z][A-Z0-9_]+)'/", $source, $m);

        return $m[1];
    }

    /** @return list<string> */
    private function documented(): array
    {
        $path = self::ROOT.'/.env.example';

        if (! is_file($path)) {
            return [];
        }

        $out = [];

        foreach (file($path) ?: [] as $line) {
            if (preg_match('/^\s*#?\s*([A-Z][A-Z0-9_]+)\s*=/', $line, $m)) {
                $out[] = $m[1];
            }
        }

        return $out;
    }
}
