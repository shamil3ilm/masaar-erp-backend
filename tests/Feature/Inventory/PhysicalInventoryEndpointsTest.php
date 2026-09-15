<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\PhysicalInventoryDocument;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\PhysicalInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the physical inventory list, keeps the warehouse and assignee inside
 * the caller's organization, and cancels only on the current row.
 */
class PhysicalInventoryEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Warehouse $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.physical-inventory.view',
            'inventory.physical-inventory.manage',
        ]);

        $this->store = $this->warehouse();
    }

    public function test_the_list_filters_by_status_latest_count_first(): void
    {
        $older = $this->document(PhysicalInventoryDocument::STATUS_COUNTED, '2025-01-10');
        $newer = $this->document(PhysicalInventoryDocument::STATUS_COUNTED, '2025-01-20');
        $this->document(PhysicalInventoryDocument::STATUS_CREATED, '2025-01-15');

        $response = $this->apiGet('/inventory/physical-inventory?status=counted');

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_document_is_created_for_the_organizations_warehouse_and_user(): void
    {
        $response = $this->apiPost('/inventory/physical-inventory', [
            'warehouse_id' => $this->store->id,
            'count_date' => now()->toDateString(),
            'assigned_to' => $this->user->id,
        ]);

        $response->assertCreated();
    }

    public function test_another_organizations_warehouse_or_user_is_refused(): void
    {
        $response = $this->apiPost('/inventory/physical-inventory', [
            'warehouse_id' => $this->foreignWarehouse()->id,
            'count_date' => now()->toDateString(),
            'assigned_to' => $this->foreignUser()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['warehouse_id', 'assigned_to']);
        $this->assertSame(0, PhysicalInventoryDocument::withoutGlobalScopes()->count());
    }

    public function test_a_document_is_shown_updated_and_cancelled_by_its_id(): void
    {
        $document = $this->document(PhysicalInventoryDocument::STATUS_CREATED, '2025-01-10');

        $this->apiGet("/inventory/physical-inventory/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.document_number', $document->document_number);

        $this->apiPut("/inventory/physical-inventory/{$document->id}", ['assigned_to' => $this->user->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_to', $this->user->id);

        $this->apiDelete("/inventory/physical-inventory/{$document->id}")->assertOk();

        $this->assertSame(PhysicalInventoryDocument::STATUS_CANCELLED, $document->fresh()->status);
    }

    public function test_another_organizations_document_is_not_found(): void
    {
        $theirs = PhysicalInventoryDocument::withoutGlobalScopes()->forceCreate([
            'organization_id' => $this->otherOrganization()->id,
            'document_number' => 'PI-THEIRS',
            'warehouse_id' => $this->foreignWarehouse()->id,
            'count_date' => '2025-01-10',
            'status' => PhysicalInventoryDocument::STATUS_CREATED,
        ]);

        $this->apiGet("/inventory/physical-inventory/{$theirs->id}")->assertNotFound();
        $this->apiDelete("/inventory/physical-inventory/{$theirs->id}")->assertNotFound();

        $this->assertSame(PhysicalInventoryDocument::STATUS_CREATED, $theirs->fresh()->status);
    }

    public function test_the_document_cannot_be_assigned_to_another_organizations_user(): void
    {
        $document = $this->document(PhysicalInventoryDocument::STATUS_CREATED, '2025-01-10');

        $response = $this->apiPut("/inventory/physical-inventory/{$document->id}", [
            'assigned_to' => $this->foreignUser()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);
        $this->assertNull($document->fresh()->assigned_to);
    }

    public function test_a_posted_document_is_not_cancelled(): void
    {
        $document = $this->document(PhysicalInventoryDocument::STATUS_POSTED, '2025-01-10');

        $this->apiDelete("/inventory/physical-inventory/{$document->id}")->assertStatus(422);

        $this->assertSame(PhysicalInventoryDocument::STATUS_POSTED, $document->fresh()->status);
    }

    public function test_a_cancel_from_a_stale_copy_does_not_undo_a_post(): void
    {
        $document = $this->document(PhysicalInventoryDocument::STATUS_COUNTED, '2025-01-10');
        $stale = PhysicalInventoryDocument::findOrFail($document->id);
        PhysicalInventoryDocument::whereKey($document->id)->update(['status' => PhysicalInventoryDocument::STATUS_POSTED]);

        $this->assertRejected(fn () => app(PhysicalInventoryService::class)->cancel($stale));

        $this->assertSame(PhysicalInventoryDocument::STATUS_POSTED, $document->fresh()->status);
    }

    private function document(string $status, string $countDate): PhysicalInventoryDocument
    {
        return PhysicalInventoryDocument::create([
            'organization_id' => $this->organization->id,
            'document_number' => 'PI-'.fake()->unique()->numerify('#####'),
            'warehouse_id' => $this->store->id,
            'count_date' => $countDate,
            'status' => $status,
        ]);
    }
}
