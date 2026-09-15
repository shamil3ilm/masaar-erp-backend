<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\CustomFieldDefinition;
use App\Models\Core\CustomFieldGroup;
use App\Services\Core\CustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomFieldController extends Controller
{
    public function __construct(
        protected CustomFieldService $customFieldService
    ) {}

    // --- Field Definitions ---

    /**
     * List custom field definitions, optionally filtered by entity type.
     */
    public function index(Request $request): JsonResponse
    {
        $definitions = $this->customFieldService->listDefinitions(
            $request->has('entity_type') ? (string) $request->input('entity_type') : null,
            $request->boolean('active_only'),
            $request->integer('per_page', 50),
        );

        return $this->paginated($definitions);
    }

    /**
     * Create a new custom field definition.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string|max:50',
            'field_name' => 'nullable|string|max:50|regex:/^[a-z][a-z0-9_]*$/',
            'field_label' => 'required|string|max:255',
            'field_type' => 'required|string|in:' . implode(',', CustomFieldDefinition::FIELD_TYPES),
            'description' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*.value' => 'required_with:options|string',
            'options.*.label' => 'required_with:options|string',
            'validation' => 'nullable|array',
            'validation.pattern' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (@preg_match($value, '') === false) {
                    $fail('The validation pattern is not a valid regular expression.');
                }
            }],
            'default_value' => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'field_group' => 'nullable|string|max:50',
            'is_required' => 'nullable|boolean',
            'is_unique' => 'nullable|boolean',
            'is_searchable' => 'nullable|boolean',
            'is_filterable' => 'nullable|boolean',
            'show_in_list' => 'nullable|boolean',
            'show_in_form' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $definition = $this->customFieldService->createDefinition($data);

        return $this->created($definition);
    }

    /**
     * Show a custom field definition.
     */
    public function show(CustomFieldDefinition $customFieldDefinition): JsonResponse
    {
        return $this->success($customFieldDefinition);
    }

    /**
     * Update a custom field definition.
     */
    public function update(Request $request, CustomFieldDefinition $customFieldDefinition): JsonResponse
    {
        $data = $request->validate([
            'field_label' => 'sometimes|required|string|max:255',
            'field_type' => 'sometimes|required|string|in:' . implode(',', CustomFieldDefinition::FIELD_TYPES),
            'description' => 'nullable|string',
            'options' => 'nullable|array',
            'options.*.value' => 'required_with:options|string',
            'options.*.label' => 'required_with:options|string',
            'validation' => 'nullable|array',
            'validation.pattern' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (@preg_match($value, '') === false) {
                    $fail('The validation pattern is not a valid regular expression.');
                }
            }],
            'default_value' => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'field_group' => 'nullable|string|max:50',
            'is_required' => 'nullable|boolean',
            'is_unique' => 'nullable|boolean',
            'is_searchable' => 'nullable|boolean',
            'is_filterable' => 'nullable|boolean',
            'show_in_list' => 'nullable|boolean',
            'show_in_form' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $definition = $this->customFieldService->updateDefinition($customFieldDefinition, $data);

        return $this->success($definition, 'Custom field updated successfully.');
    }

    /**
     * Delete a custom field definition.
     */
    public function destroy(CustomFieldDefinition $customFieldDefinition): JsonResponse
    {
        $this->customFieldService->deleteDefinition($customFieldDefinition);

        return $this->success(null, 'Custom field deleted successfully.');
    }

    // --- Field Groups ---

    /**
     * List custom field groups for an entity type.
     */
    public function groups(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|max:50',
        ]);

        $groups = $this->customFieldService->listGroups($request->input('entity_type'));

        return $this->success($groups);
    }

    /**
     * Create a custom field group.
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:50|regex:/^[a-z][a-z0-9_]*$/',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
            'is_collapsible' => 'nullable|boolean',
            'is_collapsed_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $group = $this->customFieldService->createGroup($data);

        return $this->created($group);
    }

    /**
     * Update a custom field group.
     */
    public function updateGroup(Request $request, CustomFieldGroup $customFieldGroup): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer|min:0',
            'is_collapsible' => 'nullable|boolean',
            'is_collapsed_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $group = $this->customFieldService->updateGroup($customFieldGroup, $data);

        return $this->success($group, 'Group updated successfully.');
    }

    /**
     * Delete a custom field group.
     */
    public function destroyGroup(CustomFieldGroup $customFieldGroup): JsonResponse
    {
        $this->customFieldService->deleteGroup($customFieldGroup);

        return $this->success(null, 'Group deleted successfully.');
    }

    // --- Field Values for Entities ---

    /**
     * Get fields and their values for a specific entity.
     */
    public function getEntityFields(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|max:50',
            'entity_id' => 'required|integer',
        ]);

        $values = $this->customFieldService->valuesForEntity(
            $this->organizationId($request),
            $request->input('entity_type'),
            (int) $request->input('entity_id'),
        );

        return $this->success($values);
    }

    /**
     * Set custom field values for an entity.
     */
    public function setEntityFields(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|max:255',
            'entity_id' => 'required|integer',
            'fields' => 'required|array',
        ]);

        $entityClass = $this->customFieldService->entityClassFor($request->input('entity_type'));

        if (!$entityClass) {
            return $this->error('Invalid entity type.', 'INVALID_ENTITY_TYPE', 400);
        }

        $entity = $this->customFieldService->findEntity($entityClass, $this->organizationId($request), (int) $request->input('entity_id'));

        if (!$entity) {
            return $this->error('Entity not found.', 'ENTITY_NOT_FOUND', 404);
        }

        try {
            $results = $this->customFieldService->setValues($entity, $request->input('fields'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->success($results, 'Custom field values saved successfully.');
    }

    /**
     * Get grouped fields with values for form rendering.
     */
    public function getGroupedFields(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|max:50',
        ]);

        $grouped = $this->customFieldService->getFieldsGrouped(
            $request->input('entity_type'),
            $this->organizationId($request)
        );

        return $this->success($grouped);
    }
}
