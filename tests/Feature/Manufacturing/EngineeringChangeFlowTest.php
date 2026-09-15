<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\EngineeringChange;
use App\Services\Manufacturing\EngineeringChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Engineering changes move through their approval states once, on the locked
 * row, and name only the organization's own users.
 */
class EngineeringChangeFlowTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.planning.manage',
            'manufacturing.planning.view',
        ]);
    }

    public function test_another_organizations_user_cannot_request_a_change(): void
    {
        $response = $this->apiPost('/manufacturing/engineering-changes', [
            'change_number' => 'ECR-1',
            'description' => 'Swap fastener',
            'requested_by' => $this->foreignUser()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['requested_by']);
    }

    public function test_a_stale_copy_cannot_approve_a_rejected_change(): void
    {
        $change = EngineeringChange::factory()->submitted()->create(['organization_id' => $this->organization->id]);
        $stale = EngineeringChange::findOrFail($change->id);
        $service = app(EngineeringChangeService::class);

        $service->reject(EngineeringChange::findOrFail($change->id), $this->user->id, 'Not needed');

        try {
            $service->approve($stale, $this->user->id);
            $this->fail('A rejected engineering change was approved.');
        } catch (ValidationException) {
            // Refused on the locked row, as expected.
        }

        $this->assertSame(EngineeringChange::STATUS_REJECTED, $change->fresh()->status);
    }

    public function test_another_organizations_change_is_not_found(): void
    {
        $theirs = EngineeringChange::factory()->submitted()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/manufacturing/engineering-changes/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/engineering-changes/{$theirs->id}/approve")->assertNotFound();
        $this->apiDelete("/manufacturing/engineering-changes/{$theirs->id}")->assertNotFound();

        $this->assertSame(EngineeringChange::STATUS_SUBMITTED, $theirs->fresh()->status);
    }
}
