<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Inventory\BatchCharacteristic;
use App\Models\Inventory\BatchCharacteristicValue;
use App\Models\Inventory\BatchClass;
use App\Models\Inventory\BatchWhereUsedRecord;
use App\Models\Inventory\InventoryBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the batch class list, writes a batch's characteristic values all or
 * nothing, and keeps batch references inside the caller's organization.
 */
class BatchEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private BatchClass $class;
    private InventoryBatch $ourBatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.batch-classes.view',
            'inventory.batch-classes.manage',
            'inventory.batches.manage',
            'inventory.batch-where-used.view',
            'inventory.batch-where-used.manage',
        ]);

        $this->class = $this->batchClass($this->organization->id, 'OURS');
        $this->ourBatch = $this->batch($this->stockedProduct(), $this->warehouse(), 5);
    }

    public function test_the_class_list_holds_the_organizations_classes_with_their_characteristics(): void
    {
        $this->batchClass($this->otherOrganization()->id, 'THEIRS');
        $this->characteristic($this->class, 'PURITY');

        $list = $this->apiGet('/inventory/batch-classes')->assertOk();
        $this->assertSame([$this->class->id], array_column($list->json('data.data'), 'id'));
        $this->assertSame(1, $list->json('data.data.0.characteristics_count'));

        $this->apiGet("/inventory/batch-classes/{$this->class->id}/characteristics")
            ->assertOk()
            ->assertJsonPath('data.0.characteristic_code', 'PURITY');
    }

    public function test_values_are_not_kept_when_one_of_them_is_invalid(): void
    {
        $purity = $this->characteristic($this->class, 'PURITY');
        $moisture = $this->characteristic($this->class, 'MOISTURE');

        $response = $this->apiPost("/inventory/batches/{$this->ourBatch->id}/classification-values", [
            'values' => [
                ['characteristic_id' => $purity->id, 'value' => 5],
                ['characteristic_id' => $moisture->id, 'value' => 50],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertSame(0, BatchCharacteristicValue::count());
    }

    public function test_another_organizations_characteristic_is_refused(): void
    {
        $theirs = $this->characteristic($this->batchClass($this->otherOrganization()->id, 'THEIRS'), 'PURITY');

        $response = $this->apiPost("/inventory/batches/{$this->ourBatch->id}/classification-values", [
            'values' => [['characteristic_id' => $theirs->id, 'value' => 5]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['values.0.characteristic_id']);
    }

    public function test_usage_cannot_be_recorded_against_another_organizations_batch(): void
    {
        $response = $this->apiPost('/inventory/batch-where-used/record', [
            'inventory_batch_id' => $this->foreignBatch()->id,
            'usage_type' => 'adjustment',
            'reference_id' => 1,
            'quantity_used' => 1,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['inventory_batch_id']);
        $this->assertSame(0, BatchWhereUsedRecord::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_batch_is_not_found(): void
    {
        $this->apiGet("/inventory/batches/{$this->foreignBatch()->id}/where-used")->assertNotFound();
    }

    private function batchClass(int $organizationId, string $code): BatchClass
    {
        return BatchClass::create([
            'organization_id' => $organizationId,
            'class_code' => $code,
            'class_name' => $code,
            'is_active' => true,
        ]);
    }

    private function characteristic(BatchClass $class, string $code): BatchCharacteristic
    {
        return BatchCharacteristic::create([
            'organization_id' => $class->organization_id,
            'batch_class_id' => $class->id,
            'characteristic_code' => $code,
            'characteristic_name' => $code,
            'data_type' => BatchCharacteristic::TYPE_NUMERIC,
            'min_value' => 0,
            'max_value' => 10,
        ]);
    }
}
