<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Services\Automation\AutomationRuleRunner;
use App\Services\Automation\AutomationRuleService;
use App\Services\Automation\AutomationScheduleService;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * The automation services must be buildable by the container.
 *
 * Two services that take each other cannot be constructed: the container
 * builds the first, needs the second, needs the first again and recurses until
 * the process dies. The only ways out are a service locator inside one of
 * them, which hides the dependency rather than removing it, or a third service
 * holding what both need. Automation takes the second: AutomationRuleRunner
 * evaluates and executes a rule, AutomationScheduleService takes the runner,
 * and AutomationRuleService takes the schedule service.
 *
 * The first test builds each service for real. The second reads the
 * constructors instead, so a dependency that closes the circle again is
 * reported as a failure with the path that closes it, rather than as a crash.
 */
class AutomationServiceWiringTest extends TestCase
{
    /** @var list<class-string> */
    private const SERVICES = [
        AutomationRuleService::class,
        AutomationScheduleService::class,
        AutomationRuleRunner::class,
    ];

    public function test_the_container_builds_every_automation_service(): void
    {
        foreach (self::SERVICES as $class) {
            $this->assertInstanceOf($class, app($class), "The container cannot build {$class}.");
        }
    }

    public function test_no_automation_service_depends_on_itself_through_another(): void
    {
        foreach (self::SERVICES as $class) {
            $this->assertSame([], $this->cyclesFrom($class, []), sprintf(
                'A service reached from %s takes a service that takes it back. The container '
                .'cannot build either one. Move what both sides need into a service that depends '
                .'on neither, as AutomationRuleRunner holds the evaluation both the rule and the '
                .'schedule service need.',
                $class
            ));
        }
    }

    /**
     * Paths through constructor dependencies under App\Services that arrive
     * back at a class already on them.
     *
     * @param  list<class-string>  $path  The classes walked through to reach $class.
     * @return list<string>
     */
    private function cyclesFrom(string $class, array $path): array
    {
        if (in_array($class, $path, true)) {
            return [implode(' -> ', [...$path, $class])];
        }

        $constructor = (new ReflectionClass($class))->getConstructor();

        if (! $constructor) {
            return [];
        }

        $cycles = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            if (! str_starts_with($type->getName(), 'App\\Services\\')) {
                continue;
            }

            $cycles = [...$cycles, ...$this->cyclesFrom($type->getName(), [...$path, $class])];
        }

        return $cycles;
    }
}
