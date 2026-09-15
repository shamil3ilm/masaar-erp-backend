<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins notification creation with numbered items, and keeps a notification's
 * equipment, responsible user and task assignees inside the caller's
 * organization.
 */
class MaintenanceNotificationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'maintenance.notifications.view',
            'maintenance.notifications.create',
            'maintenance.notifications.edit',
        ]);
        $this->actingAs($this->user, 'api');
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->other = Organization::factory()->create();
    }

    public function test_a_notification_is_created_with_numbered_items(): void
    {
        $response = $this->apiPost('/maintenance/notifications', $this->payload([
            'items' => [
                ['short_text' => 'Seal leaking'],
                ['short_text' => 'Bearing noisy'],
            ],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', MaintenanceNotification::STATUS_OUTSTANDING)
            ->assertJsonPath('data.items.0.item_number', 10)
            ->assertJsonPath('data.items.1.item_number', 20);
    }

    public function test_a_notification_for_another_organizations_equipment_or_responsible_user_is_refused(): void
    {
        $this->apiPost('/maintenance/notifications', $this->payload([
            'equipment_id' => Equipment::factory()->create(['organization_id' => $this->other->id])->id,
            'responsible_id' => $this->foreignUser()->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['equipment_id', 'responsible_id']);

        $this->assertSame(0, MaintenanceNotification::withoutGlobalScopes()->count());
    }

    public function test_a_responsible_user_or_task_assignee_of_another_organization_is_refused(): void
    {
        $uuid = $this->apiPost('/maintenance/notifications', $this->payload())->assertCreated()->json('data.uuid');

        $this->apiPut("/maintenance/notifications/{$uuid}", ['responsible_id' => $this->foreignUser()->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['responsible_id']);

        $this->apiPost("/maintenance/notifications/{$uuid}/tasks", [
            'description' => 'Replace seal',
            'assigned_to' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'notification_type' => 'M2',
            'short_text' => 'Pump vibrating',
            'priority' => '3_medium',
        ], $overrides);
    }

    private function foreignUser(): User
    {
        return User::factory()->create(['organization_id' => $this->other->id]);
    }
}
