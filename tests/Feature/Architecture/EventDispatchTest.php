<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Events\Accounting\JournalEntryPosted;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps every event class under app/Events connected to something that fires it.
 *
 * Listeners are discovered from their handle() type-hint, so an event nobody
 * dispatches still shows up in event:list with listeners attached. Nothing
 * fails: the listeners simply never run, and code written against them rots
 * unobserved - reading attributes that do not exist, writing columns that are
 * not there.
 *
 * The scan looks for a dispatch site in app/: Name::dispatch(), dispatchIf(),
 * dispatchUnless(), event(new Name(...)), Event::dispatch(new Name(...)) and
 * ->dispatch(new Name(...)), resolving the local name through each file's use
 * statements so aliased imports count.
 *
 * UNDISPATCHED is exact. An event that loses its last dispatch site fails until
 * it is listed; a listed event that gains one fails until it is delisted.
 */
class EventDispatchTest extends TestCase
{
    private const APP = __DIR__.'/../../../app';

    private const EVENTS = __DIR__.'/../../../app/Events';

    /** Directories under app/Events holding support code rather than events. */
    private const NOT_EVENTS = ['Contracts', 'Concerns'];

    /**
     * Events deliberately left without a dispatch site, each with the reason.
     *
     * @var array<class-string, string>
     */
    private const UNDISPATCHED = [
        JournalEntryPosted::class => 'Dispatching it starts parallel-ledger postings, which needs a product decision.',
    ];

    public function test_every_event_has_a_dispatch_site(): void
    {
        $events = $this->eventClasses();

        $this->assertGreaterThan(5, count($events),
            'Found too few event classes; the app/Events scan is not working.');

        $sources = $this->sources();

        $undispatched = [];

        foreach ($events as $event) {
            if (! $this->isDispatched($event, $sources)) {
                $undispatched[] = $event;
            }
        }

        sort($undispatched);

        $expected = array_keys(self::UNDISPATCHED);
        sort($expected);

        $this->assertSame($expected, $undispatched, sprintf(
            "The set of events with no dispatch site has changed.\n"
            ."An undispatched event's listeners never run. Dispatch it, delete it "
            ."with its listeners, or add it to UNDISPATCHED and say why.\n"
            ."Newly undispatched: %s\nListed but now dispatched or gone: %s",
            implode(', ', array_diff($undispatched, $expected)) ?: 'none',
            implode(', ', array_diff($expected, $undispatched)) ?: 'none'
        ));
    }

    public function test_every_undispatched_event_gives_a_reason(): void
    {
        foreach (self::UNDISPATCHED as $event => $reason) {
            $this->assertNotSame('', trim($reason), "{$event} is listed without a reason.");
        }
    }

    /** @return list<class-string> */
    private function eventClasses(): array
    {
        $root = (string) realpath(self::EVENTS);
        $classes = [];

        foreach ($this->phpFilesUnder($root) as $path) {
            $relative = str_replace([$root.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $path);

            if (in_array(explode('/', $relative)[0], self::NOT_EVENTS, true)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            if (! preg_match('/^namespace\s+([\w\\\\]+);/m', $source, $namespace)
                || ! preg_match('/^(?:final\s+|readonly\s+)*class\s+(\w+)/m', $source, $class)) {
                continue;
            }

            $classes[] = $namespace[1].'\\'.$class[1];
        }

        return $classes;
    }

    /**
     * @param  class-string  $event
     * @param  array<string, string>  $sources  path => contents
     */
    private function isDispatched(string $event, array $sources): bool
    {
        foreach ($sources as $source) {
            foreach ($this->localNames($event, $source) as $name) {
                $name = preg_quote($name, '/');

                $static = '/(?<![\w\\\\])'.$name.'::dispatch(?:If|Unless)?\s*\(/';
                $instance = '/(?:(?<![\w\\\\>])event|Event::dispatch|->dispatch)\(\s*new\s+'.$name.'\s*\(/';

                if (preg_match($static, $source) || preg_match($instance, $source)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The names by which a file can refer to the event: the fully qualified
     * name, the short name when imported or in the same namespace, and any alias.
     *
     * @return list<string>
     */
    private function localNames(string $event, string $source): array
    {
        $short = substr($event, (int) strrpos($event, '\\') + 1);
        $eventNamespace = substr($event, 0, (int) strrpos($event, '\\'));

        $names = ['\\'.$event];

        if (preg_match('/^namespace\s+([\w\\\\]+);/m', $source, $namespace) && $namespace[1] === $eventNamespace) {
            $names[] = $short;
        }

        preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $source, $uses, PREG_SET_ORDER);

        foreach ($uses as $use) {
            if (ltrim($use[1], '\\') === $event) {
                $names[] = $use[2] ?? $short;
            }
        }

        preg_match_all('/^use\s+([\w\\\\]+)\\\\\{([^}]*)\}\s*;/m', $source, $groups, PREG_SET_ORDER);

        foreach ($groups as $group) {
            foreach (explode(',', $group[2]) as $member) {
                if (preg_match('/^\s*([\w\\\\]+)(?:\s+as\s+(\w+))?\s*$/', $member, $parts)
                    && ltrim($group[1], '\\').'\\'.$parts[1] === $event) {
                    $names[] = $parts[2] ?? substr($parts[1], (int) strrpos('\\'.$parts[1], '\\'));
                }
            }
        }

        return array_values(array_unique($names));
    }

    /** @return array<string, string> */
    private function sources(): array
    {
        $out = [];

        foreach ($this->phpFilesUnder((string) realpath(self::APP)) as $path) {
            $out[$path] = (string) file_get_contents($path);
        }

        return $out;
    }

    /** @return list<string> */
    private function phpFilesUnder(string $root): array
    {
        $out = [];

        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if ($file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        sort($out);

        return $out;
    }
}
