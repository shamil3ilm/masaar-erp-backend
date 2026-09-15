<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Core\ClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassificationController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly ClassificationService $service,
    ) {}

    // -------------------------------------------------------------------------
    // Classes
    // -------------------------------------------------------------------------

    public function indexClasses(Request $request): JsonResponse
    {
        $objectType = $request->string('object_type', '')->toString();

        if ($objectType === '') {
            return $this->error('object_type query parameter is required.', 'VALIDATION_ERROR', 422);
        }

        $results = $this->service->listClasses(
            objectType: $objectType,
            filters: $request->only(['is_active', 'search']),
            perPage: $request->integer('per_page', 20),
        );

        return $this->paginated($results);
    }

    public function storeClass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_code'  => ['required', 'string', 'max:30'],
            'class_name'  => ['required', 'string', 'max:100'],
            'object_type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $class = $this->service->createClass($validated);

        return $this->created($class, 'Classification class created.');
    }

    public function showClass(string $id): JsonResponse
    {
        return $this->success($this->service->classDetails($id));
    }

    public function updateClass(Request $request, string $id): JsonResponse
    {
        $class = $this->service->findClass($id);

        $validated = $request->validate([
            'class_name'  => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $class = $this->service->updateClass($class, $validated);

        return $this->success($class, 'Classification class updated.');
    }

    public function destroyClass(string $id): JsonResponse
    {
        $this->service->deleteClass($this->service->findClass($id));

        return $this->success(null, 'Classification class deleted.');
    }

    // -------------------------------------------------------------------------
    // Characteristics
    // -------------------------------------------------------------------------

    public function addCharacteristic(Request $request, string $classId): JsonResponse
    {
        $class = $this->service->findClass($classId);

        $validated = $request->validate([
            'characteristic_code' => ['required', 'string', 'max:30'],
            'characteristic_name' => ['required', 'string', 'max:100'],
            'data_type'           => ['required', 'in:text,numeric,date,boolean,list'],
            'unit_of_measure'     => ['nullable', 'string', 'max:20'],
            'is_required'         => ['nullable', 'boolean'],
            'is_searchable'       => ['nullable', 'boolean'],
            'min_value'           => ['nullable', 'numeric'],
            'max_value'           => ['nullable', 'numeric'],
            'allowed_values'      => ['nullable', 'array'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $characteristic = $this->service->addCharacteristic($class, $validated);

        return $this->created($characteristic, 'Characteristic added.');
    }

    public function updateCharacteristic(Request $request, string $classId, string $charId): JsonResponse
    {
        $characteristic = $this->service->findCharacteristic($classId, $charId);

        $validated = $request->validate([
            'characteristic_name' => ['sometimes', 'string', 'max:100'],
            'unit_of_measure'     => ['nullable', 'string', 'max:20'],
            'is_required'         => ['nullable', 'boolean'],
            'is_searchable'       => ['nullable', 'boolean'],
            'min_value'           => ['nullable', 'numeric'],
            'max_value'           => ['nullable', 'numeric'],
            'allowed_values'      => ['nullable', 'array'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $characteristic = $this->service->updateCharacteristic($characteristic, $validated);

        return $this->success($characteristic, 'Characteristic updated.');
    }

    // -------------------------------------------------------------------------
    // Assignments & Values
    // -------------------------------------------------------------------------

    public function assignToObject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'object_type' => ['required', 'string', 'max:50'],
            'object_id'   => ['required', 'integer', 'min:1'],
            'class_id'    => ['required', $this->ownedBy('classification_classes')],
        ]);

        $assignment = $this->service->assignClassToObject(
            objectType: $validated['object_type'],
            objectId: (int) $validated['object_id'],
            classId: (int) $validated['class_id'],
            orgId: $this->organizationId($request),
        );

        return $this->created($assignment, 'Class assigned to object.');
    }

    public function setValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'object_type'                => ['required', 'string', 'max:50'],
            'object_id'                  => ['required', 'integer', 'min:1'],
            'values'                     => ['required', 'array', 'min:1'],
            'values.*.characteristic_id' => ['required', $this->ownedBy('class_characteristics')],
            'values.*.value'             => ['present'],
        ]);

        $saved = $this->service->setCharacteristicValues(
            $validated['object_type'],
            (int) $validated['object_id'],
            $validated['values'],
            $this->organizationId($request),
        );

        return $this->success($saved, count($saved) . ' value(s) saved.');
    }

    public function getForObject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'object_type' => ['required', 'string'],
            'object_id'   => ['required', 'integer', 'min:1'],
        ]);

        $assignments = $this->service->getClassesForObject(
            objectType: $validated['object_type'],
            objectId: (int) $validated['object_id'],
        );

        return $this->success($assignments);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'object_type'                => ['required', 'string'],
            'criteria'                   => ['required', 'array', 'min:1'],
            'criteria.*.characteristic_id' => ['required', 'integer'],
            'criteria.*.value'           => ['present'],
        ]);

        $results = $this->service->searchByCharacteristics(
            objectType: $validated['object_type'],
            criteria: $validated['criteria'],
        );

        return $this->success($results);
    }
}
