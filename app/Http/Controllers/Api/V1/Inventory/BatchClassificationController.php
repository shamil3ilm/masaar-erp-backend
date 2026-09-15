<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Inventory\BatchClassificationService;
use App\Services\Inventory\InventoryBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BatchClassificationController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly BatchClassificationService $service,
        private readonly InventoryBatchService $batches,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $classes = $this->service->paginateClasses(
            (int) Auth::user()->organization_id,
            $request->boolean('active_only'),
            $request->integer('per_page', 20)
        );

        return $this->success($classes, 'Batch classes retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_code'  => 'required|string|max:30',
            'class_name'  => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $class = $this->service->createClass(
            (int) Auth::user()->organization_id,
            $validated
        );

        return $this->created($class, 'Batch class created.');
    }

    public function show(string $id): JsonResponse
    {
        $class = $this->service->findClassOrFail((int) Auth::user()->organization_id, $id, ['characteristics']);

        return $this->success($class, 'Batch class retrieved.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $class = $this->service->findClassOrFail((int) Auth::user()->organization_id, $id);

        $validated = $request->validate([
            'class_name'  => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $updated = $this->service->updateClass($class, $validated);

        return $this->success($updated, 'Batch class updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $class = $this->service->findClassOrFail((int) Auth::user()->organization_id, $id);

        $this->service->deleteClass($class);

        return $this->success(null, 'Batch class deleted.');
    }

    public function addCharacteristic(Request $request, string $id): JsonResponse
    {
        $class = $this->service->findClassOrFail((int) Auth::user()->organization_id, $id);

        $validated = $request->validate([
            'characteristic_code' => 'required|string|max:30',
            'characteristic_name' => 'required|string|max:100',
            'data_type'           => 'required|in:text,numeric,date,boolean',
            'unit_of_measure'     => 'nullable|string|max:20',
            'is_required'         => 'boolean',
            'min_value'           => 'nullable|numeric',
            'max_value'           => 'nullable|numeric|gte:min_value',
            'allowed_values'      => 'nullable|array',
            'allowed_values.*'    => 'string',
        ]);

        $characteristic = $this->service->addCharacteristic($class, $validated);

        return $this->created($characteristic, 'Characteristic added.');
    }

    public function getCharacteristics(string $id): JsonResponse
    {
        $class = $this->service->findClassOrFail((int) Auth::user()->organization_id, $id, ['characteristics']);

        return $this->success($class->characteristics, 'Characteristics retrieved.');
    }

    public function setBatchValues(Request $request, string $batchId): JsonResponse
    {
        $batch = $this->batches->findOrFail((int) Auth::user()->organization_id, $batchId);

        $validated = $request->validate([
            'values'                   => 'required|array',
            'values.*.characteristic_id' => ['required', 'integer', $this->ownedBy('batch_characteristics')],
            'values.*.value'           => 'nullable',
        ]);

        $results = $this->service->setBatchValues($batch, $validated['values']);

        return $this->success($results, 'Batch values set.');
    }

    public function getBatchValues(string $batchId): JsonResponse
    {
        $batch = $this->batches->findOrFail((int) Auth::user()->organization_id, $batchId);

        $values = $this->service->getValuesForBatch($batch);

        return $this->success($values, 'Batch classification values retrieved.');
    }

    public function searchByCharacteristic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'characteristic_id' => ['required', 'integer', $this->ownedBy('batch_characteristics')],
            'value'             => 'required',
        ]);

        $batches = $this->service->findBatchesByCharacteristic(
            (int) $validated['characteristic_id'],
            $validated['value']
        );

        return $this->success($batches, 'Batches retrieved.');
    }
}
