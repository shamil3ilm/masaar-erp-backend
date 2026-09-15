<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ChangeTransportRequest;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the super admin change transport endpoints for the caller's
 * organization: creating a request, adding objects, releasing it and reading
 * its history. Only an open request is changed; another organization's
 * request is not found.
 */
class ChangeTransportEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->user->forceFill(['is_super_admin' => true])->save();
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_a_request_is_created_filled_released_and_closed_to_changes(): void
    {
        $id = $this->apiPost('/change-transport', [
            'description' => 'Tax settings',
            'request_type' => 'customizing',
            'category' => 'configuration',
            'target_environment' => 'quality',
        ])->assertStatus(201)->json('data.id');

        $this->apiGet('/change-transport?status=open')->assertOk()->assertJsonPath('meta.total', 1);
        $this->apiGet('/change-transport/open')->assertOk()->assertJsonCount(1, 'data');
        $this->apiGet("/change-transport/{$id}")->assertOk()->assertJsonPath('data.description', 'Tax settings');

        $this->apiPut("/change-transport/{$id}", ['description' => 'VAT settings'])
            ->assertOk()
            ->assertJsonPath('data.description', 'VAT settings');

        $this->apiPost("/change-transport/{$id}/objects", [
            'object_type' => 'setting',
            'object_name' => 'tax.default_rate',
            'change_type' => 'modify',
        ])->assertStatus(201);
        $this->apiGet("/change-transport/{$id}/objects")->assertOk()->assertJsonCount(1, 'data');

        $this->apiPost("/change-transport/{$id}/release")
            ->assertOk()
            ->assertJsonPath('message', 'Transport request released')
            ->assertJsonPath('data.status', ChangeTransportRequest::STATUS_RELEASED);

        $this->apiPut("/change-transport/{$id}", ['description' => 'Late change'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REQUEST_NOT_OPEN');

        $this->apiGet("/change-transport/{$id}/history")->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_another_organizations_request_is_not_found(): void
    {
        $foreign = ChangeTransportRequest::create([
            'organization_id' => Organization::factory()->create()->id,
            'request_number' => 'TR-FOREIGN',
            'description' => 'Foreign',
            'request_type' => 'workbench',
            'category' => 'feature',
            'target_environment' => 'quality',
            'status' => ChangeTransportRequest::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        $this->apiGet("/change-transport/{$foreign->id}")->assertNotFound();
        $this->apiPut("/change-transport/{$foreign->id}", ['description' => 'X'])->assertNotFound();
        $this->apiGet("/change-transport/{$foreign->id}/objects")->assertNotFound();
        $this->apiPost("/change-transport/{$foreign->id}/release")->assertNotFound();
        $this->apiGet("/change-transport/{$foreign->id}/history")->assertNotFound();
        $this->assertSame('Foreign', $foreign->fresh()->description);
    }
}
