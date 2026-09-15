<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Core\Branch;
use App\Models\Inventory\PriceCheckLog;
use App\Models\Inventory\PriceCheckStation;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the station and log lists, keeps station tokens out of them, and
 * keeps price check references inside the caller's organization.
 */
class PriceCheckEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.barcodes.view',
            'inventory.price-check.manage',
        ]);
    }

    public function test_the_station_list_filters_by_branch_without_tokens(): void
    {
        $otherBranch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $match = $this->station($this->organization->id, $this->branch->id);
        $this->station($this->organization->id, $otherBranch->id);

        $response = $this->apiGet("/inventory/barcode/price-check/stations?branch_id={$this->branch->id}");

        $response->assertOk();
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
        $this->assertArrayNotHasKey('api_token', $response->json('data.0'));
    }

    public function test_a_station_for_another_organizations_branch_is_refused(): void
    {
        $theirBranch = Branch::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $response = $this->apiPost('/inventory/barcode/price-check/stations', [
            'branch_id' => $theirBranch->id,
            'name' => 'Kiosk',
            'station_code' => 'K-1',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
        $this->assertSame(0, PriceCheckStation::withoutGlobalScopes()->count());
    }

    public function test_a_check_refuses_another_organizations_station_or_customer(): void
    {
        $theirBranch = Branch::factory()->create(['organization_id' => $this->otherOrganization()->id]);
        $theirStation = $this->station($this->otherOrganization()->id, $theirBranch->id);
        $theirCustomer = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $response = $this->apiPost('/inventory/barcode/price-check/check', [
            'scan_value' => 'X-1',
            'scan_type' => 'barcode',
            'branch_id' => $this->branch->id,
            'station_id' => $theirStation->id,
            'contact_id' => $theirCustomer->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['station_id', 'contact_id']);
    }

    public function test_logs_filter_failed_scans(): void
    {
        $failed = $this->log(false);
        $this->log(true);

        $response = $this->apiGet('/inventory/barcode/price-check/logs?scan_successful=0');

        $response->assertOk();
        $this->assertSame([$failed->id], array_column($response->json('data'), 'id'));
    }

    private function station(int $organizationId, int $branchId): PriceCheckStation
    {
        return PriceCheckStation::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'name' => 'Kiosk',
            'station_code' => 'K-'.fake()->unique()->numerify('####'),
            'status' => PriceCheckStation::STATUS_ACTIVE,
            'api_token' => fake()->unique()->sha256(),
        ]);
    }

    private function log(bool $successful): PriceCheckLog
    {
        return PriceCheckLog::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'scan_type' => 'barcode',
            'scan_value' => 'X-1',
            'scan_successful' => $successful,
            'scanned_at' => now(),
        ]);
    }
}
