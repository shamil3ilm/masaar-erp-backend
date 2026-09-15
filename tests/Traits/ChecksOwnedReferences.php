<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Testing\TestResponse;

/**
 * Checks that a request accepts, for its id fields, only rows of the caller's
 * organization. Use with TestHelpers, which provides the token and the
 * organization.
 */
trait ChecksOwnedReferences
{
    use BuildsTenantRows;

    /**
     * Another organization's ids are rejected with a validation error on every
     * field in $references; the caller's own rows pass validation for each.
     *
     * @param  array<string, string>  $references  field => table; an array item is named as "lines.*.account_id"
     */
    protected function assertOnlyOwnRowsAccepted(string $method, string $uri, array $references): void
    {
        $fields = array_map(fn (string $field): string => str_replace('*', '0', $field), array_keys($references));

        $this->sendReferences($method, $uri, $references, $this->otherTenant()->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors($fields);

        $this->sendReferences($method, $uri, $references, $this->organization->id)
            ->assertJsonMissingValidationErrors($fields);
    }

    /**
     * A row of $table owned by the caller's organization.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function ownRow(string $table, array $attributes = []): int
    {
        return $this->tenantRow($table, $this->organization->id, $attributes);
    }

    /**
     * @param  array<string, string>  $references
     */
    private function sendReferences(string $method, string $uri, array $references, int $organizationId): TestResponse
    {
        $payload = [];
        foreach ($references as $field => $table) {
            data_set($payload, str_replace('*', '0', $field), $this->tenantRow($table, $organizationId));
        }

        $request = $this->withToken($this->token);

        return strtoupper($method) === 'GET'
            ? $request->getJson($uri.'?'.http_build_query($payload))
            : $request->json(strtoupper($method), $uri, $payload);
    }
}
