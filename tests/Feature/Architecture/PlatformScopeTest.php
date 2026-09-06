<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Platform configuration is not a tenant administrator's to change.
 *
 * The seeder gives the admin role every permission that exists, and manager
 * everything but five. So a permission is not a way to withhold anything from
 * those two roles - seeding one grants it. Rate limits, module entitlements,
 * the job monitor, the IP allowlist and moving configuration between systems
 * are all platform concerns, and guarding them with a permission handed each
 * of them to every tenant administrator the moment it was seeded.
 *
 * They are behind super.admin instead, which no seeder can grant, and the
 * slugs are gone so nobody can grant them by accident either.
 */
class PlatformScopeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    /**
     * @return list<array{string, string}>
     */
    public static function platformEndpoints(): array
    {
        return [
            'rate limits' => ['put', '/rate-limits'],
            'module role permission' => ['put', '/module-access/roles/1/permissions/1'],
            'module user override' => ['put', '/module-access/users/1/overrides/1'],
            'job retry' => ['post', '/job-monitor/1/retry'],
            'job cleanup' => ['post', '/job-monitor/cleanup'],
            'ip allowlist' => ['post', '/security/ip-allowlist'],
            'change transport' => ['post', '/change-transport'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('platformEndpoints')]
    public function test_a_tenant_admin_is_refused(string $verb, string $uri): void
    {
        $this->setUpOrganization('SA');

        // Everything the seeder would ever grant a tenant administrator.
        $this->setUpAuthenticatedUser([
            'core.users.create',
            'core.roles.update',
            'accounting.accounts.create',
            'inventory.products.edit',
        ]);

        $response = $this->json($verb, '/api/v1'.$uri, [], $this->authHeaders());

        $this->assertSame(403, $response->status(), sprintf(
            '%s %s admitted a tenant administrator. Platform configuration must '
            .'be behind super.admin, which no seeder can grant.',
            strtoupper($verb), $uri
        ));
    }
}
