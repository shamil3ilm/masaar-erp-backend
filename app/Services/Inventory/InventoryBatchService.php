<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryBatch;

/**
 * Looks up inventory batches for the endpoints that name a batch in the URL.
 */
class InventoryBatchService
{
    /**
     * The batch with the given id when it belongs to the organization;
     * otherwise a not-found error.
     */
    public function findOrFail(int $organizationId, int|string $batchId): InventoryBatch
    {
        return InventoryBatch::where('organization_id', $organizationId)->findOrFail($batchId);
    }
}
