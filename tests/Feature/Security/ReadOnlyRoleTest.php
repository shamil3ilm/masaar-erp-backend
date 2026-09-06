<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A role granted only view permissions cannot write.
 *
 * Where check.permission does not reach, auth:api and check.organization were
 * the only gates, so the Viewer role - seeded with every permission ending in
 * .view and described as "Read-only access to all features" - could create
 * records. It was refused on a guarded endpoint, because it holds no .create,
 * and waved through on an unguarded one because nothing asked.
 *
 * The endpoint has to have no check.permission of its own, or it proves the
 * wrong middleware. Employee self-service is the stable choice: it is scoped
 * to the caller's own record, so it is meant to stay ungated, where any
 * ordinary business endpoint is only ungated until someone gets to it. This
 * test previously used /account-groups and broke the day that was gated.
 */
class ReadOnlyRoleTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    private const UNGUARDED_WRITE = '/api/v1/hr/me/check-in';

    private const READABLE = '/api/v1/account-groups';

    public function test_view_only_role_cannot_write(): void
    {
        $this->setUpAuthenticatedUser(['accounting.account-groups.view']);

        $this->postJson(self::UNGUARDED_WRITE, [], $this->authHeaders())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_view_only_role_can_still_read(): void
    {
        $this->setUpAuthenticatedUser(['accounting.account-groups.view']);

        $this->getJson(self::READABLE, $this->authHeaders())
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
            'accounting.account-groups.view',
            'accounting.account-groups.manage',
        ]);

        $response = $this->postJson(self::UNGUARDED_WRITE, [], $this->authHeaders());

        $this->assertNotSame(403, $response->getStatusCode(),
            'A role holding a create permission was refused by the read-only guard.');
    }
}
