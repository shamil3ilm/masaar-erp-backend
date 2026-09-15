<?php

declare(strict_types=1);

namespace Tests\Feature\TM;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\TM\Carrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Freight rate tables, priced for the organization's own carriers.
 */
class RateTableEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'tm',
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
        $this->setUpAuthenticatedUser(['tm.agreements.view', 'tm.rate-tables.manage']);
    }

    public function test_a_rate_table_is_created_for_the_organizations_carrier_and_listed(): void
    {
        $carrier = $this->carrier($this->organization->id);

        $this->apiPost('/tm/rate-tables', $this->payload(['carrier_id' => $carrier->id]))
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.carrier_id', $carrier->id);

        $this->apiGet('/tm/rate-tables')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_rate_table_refuses_a_carrier_of_another_organization(): void
    {
        $foreign = $this->carrier(Organization::factory()->create()->id);

        $this->apiPost('/tm/rate-tables', $this->payload(['carrier_id' => $foreign->id]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('freight_rate_tables')->count());
    }

    private function carrier(int $organizationId): Carrier
    {
        return Carrier::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'code' => 'CAR-'.$organizationId,
            'name' => 'Desert Haulage',
            'type' => 'road',
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'RT-2026',
            'name' => 'Road rates 2026',
            'valid_from' => '2026-01-01',
            'basis' => 'weight',
        ], $overrides);
    }
}
