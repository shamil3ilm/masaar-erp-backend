<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A role granted only view permissions cannot write.
 *
 * check.permission guards 895 of the 1,901 write endpoints. On the rest,
 * auth:api and check.organization were the only gates, so the Viewer role -
 * seeded with every permission ending in .view and described as "Read-only
 * access to all features" - could create records on any of them. It was
 * refused on a guarded endpoint, because it holds no .create, and waved
 * through on an unguarded one because nothing asked.
 *
 * These use an endpoint with no check.permission of its own, so the only thing
 * between a viewer and a written row is the middleware under test.
 */
class ReadOnlyRoleTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private const UNGUARDED_WRITE = '/api/v1/account-groups';

    public function test_view_only_role_cannot_write(): void
    {
        $this->setUpAuthenticatedUser(['accounting.account_groups.view']);

        $this->postJson(self::UNGUARDED_WRITE, [
            'name' => 'Assets',
            'code' => 'AST',
        ], $this->authHeaders())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_view_only_role_can_still_read(): void
    {
        $this->setUpAuthenticatedUser(['accounting.account_groups.view']);

        $this->getJson(self::UNGUARDED_WRITE, $this->authHeaders())
            ->assertOk();
    }

    /**
     * The guard asks what a user may do, not what their role is called, so a
     * role holding any non-view permission reaches the endpoint. Whether the
     * endpoint then accepts the body is its own business; the assertion is
     * only that this middleware is no longer what refuses.
     */
    public function test_a_role_with_any_write_capability_reaches_the_endpoint(): void
    {
        $this->setUpAuthenticatedUser([
            'accounting.account_groups.view',
            'accounting.account_groups.create',
        ]);

        $response = $this->postJson(self::UNGUARDED_WRITE, [
            'name' => 'Assets',
            'code' => 'AST',
        ], $this->authHeaders());

        $this->assertNotSame(403, $response->getStatusCode(),
            'A role holding a create permission was refused by the read-only guard.');
    }
}
