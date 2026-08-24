<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Every permission referenced by a `check.permission:` route middleware must
 * exist in PermissionsSeeder.
 *
 * A permission that is checked but never seeded can never be granted to a role,
 * which silently makes the endpoint reachable only by super admins.
 */
class RoutePermissionsAreSeededTest extends TestCase
{
    public function test_every_route_permission_exists_in_the_seeder(): void
    {
        $seeded = $this->seededPermissionSlugs();
        $used   = $this->permissionSlugsUsedByRoutes();

        $this->assertNotEmpty($used, 'No check.permission middleware found on any route.');

        $missing = array_values(array_diff($used, $seeded));
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "These route permissions are missing from PermissionsSeeder:\n  " . implode("\n  ", $missing)
        );
    }

    /**
     * @return list<string>
     */
    private function seededPermissionSlugs(): array
    {
        $method = new ReflectionMethod(PermissionsSeeder::class, 'getPermissions');
        $method->setAccessible(true);

        /** @var array<string, array<string, string>> $byModule */
        $byModule = $method->invoke(new PermissionsSeeder());

        $slugs = [];
        foreach ($byModule as $permissions) {
            foreach (array_keys($permissions) as $slug) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * @return list<string>
     */
    private function permissionSlugsUsedByRoutes(): array
    {
        $slugs = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'check.permission:')) {
                    continue;
                }

                // A single middleware may list alternatives: check.permission:a.view,a.edit
                $argument = substr($middleware, strlen('check.permission:'));
                foreach (explode(',', $argument) as $slug) {
                    $slug = trim($slug);
                    if ($slug !== '') {
                        $slugs[] = $slug;
                    }
                }
            }
        }

        return array_values(array_unique($slugs));
    }
}
