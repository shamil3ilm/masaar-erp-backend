<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ClassAssignment;
use App\Models\Core\ClassCharacteristic;
use App\Models\Core\ClassificationClass;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the classification endpoints: classes, characteristics, assigning a
 * class to an object, its values and searching by them. A class or
 * characteristic named in a request belongs to the caller's organization, and
 * a search returns objects of the requested type only.
 */
class ClassificationEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.classification.view', 'core.classification.manage']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_class_and_its_characteristics_are_created_listed_changed_and_deleted(): void
    {
        $classId = $this->apiPost('/classification/classes', [
            'class_code' => 'COLOR',
            'class_name' => 'Colour',
            'object_type' => 'product',
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Classification class created.')
            ->json('data.id');

        $this->apiGet('/classification/classes?object_type=product')->assertOk()->assertJsonPath('meta.total', 1);

        $charId = $this->apiPost("/classification/classes/{$classId}/characteristics", [
            'characteristic_code' => 'SHADE',
            'characteristic_name' => 'Shade',
            'data_type' => 'text',
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Characteristic added.')
            ->json('data.id');

        $this->apiPut("/classification/classes/{$classId}/characteristics/{$charId}", ['characteristic_name' => 'Tone'])
            ->assertOk()
            ->assertJsonPath('data.characteristic_name', 'Tone');

        $this->apiGet("/classification/classes/{$classId}")
            ->assertOk()
            ->assertJsonPath('data.characteristics.0.id', $charId);

        $this->apiPut("/classification/classes/{$classId}", ['class_name' => 'Colours'])
            ->assertOk()
            ->assertJsonPath('message', 'Classification class updated.');

        $this->apiDelete("/classification/classes/{$classId}")
            ->assertOk()
            ->assertJsonPath('message', 'Classification class deleted.');
        $this->assertSoftDeleted('classification_classes', ['id' => $classId]);
    }

    public function test_another_organizations_class_and_characteristic_are_refused(): void
    {
        [$foreignClass, $foreignChar] = $this->classWithCharacteristic($this->otherOrg->id);
        [, $ownChar] = $this->classWithCharacteristic($this->organization->id);

        $this->apiGet("/classification/classes/{$foreignClass->id}")->assertNotFound();
        $this->apiPut("/classification/classes/{$foreignClass->id}", ['class_name' => 'X'])->assertNotFound();
        $this->apiDelete("/classification/classes/{$foreignClass->id}")->assertNotFound();
        $this->apiPut("/classification/classes/{$ownChar->classification_class_id}/characteristics/{$foreignChar->id}", ['characteristic_name' => 'X'])
            ->assertNotFound();

        $this->apiPost('/classification/assign', ['object_type' => 'product', 'object_id' => 1, 'class_id' => $foreignClass->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('class_id');

        $this->apiPost('/classification/values', [
            'object_type' => 'product',
            'object_id' => 1,
            'values' => [['characteristic_id' => $foreignChar->id, 'value' => 'red']],
        ])->assertStatus(422)->assertJsonValidationErrors('values.0.characteristic_id');

        $this->assertSame(0, ClassAssignment::withoutGlobalScopes()->count());
    }

    public function test_values_are_saved_together_and_read_back_for_the_object(): void
    {
        [$class, $shade] = $this->classWithCharacteristic($this->organization->id);
        $finish = ClassCharacteristic::create([
            'organization_id' => $this->organization->id,
            'classification_class_id' => $class->id,
            'characteristic_code' => 'FINISH',
            'characteristic_name' => 'Finish',
            'data_type' => 'text',
        ]);

        $this->apiPost('/classification/assign', ['object_type' => 'product', 'object_id' => 7, 'class_id' => $class->id])
            ->assertStatus(201);

        $this->apiPost('/classification/values', [
            'object_type' => 'product',
            'object_id' => 7,
            'values' => [
                ['characteristic_id' => $shade->id, 'value' => 'red'],
                ['characteristic_id' => $finish->id, 'value' => 'matte'],
            ],
        ])->assertOk()->assertJsonPath('message', '2 value(s) saved.');

        $characteristics = collect($this->apiGet('/classification/for-object?object_type=product&object_id=7')
            ->assertOk()
            ->json('data.0.classification_class.characteristics'))
            ->mapWithKeys(fn (array $characteristic) => [$characteristic['characteristic_code'] => $characteristic['value']['text_value'] ?? null]);

        $this->assertSame(['FINISH' => 'matte', 'SHADE' => 'red'], $characteristics->sortKeys()->all());
    }

    public function test_a_search_returns_objects_of_the_requested_type_only(): void
    {
        [, $shade] = $this->classWithCharacteristic($this->organization->id);

        foreach (['product', 'contact'] as $objectType) {
            $this->apiPost('/classification/values', [
                'object_type' => $objectType,
                'object_id' => 1,
                'values' => [['characteristic_id' => $shade->id, 'value' => 'red']],
            ])->assertOk();
        }

        $this->apiPost('/classification/search', [
            'object_type' => 'product',
            'criteria' => [['characteristic_id' => $shade->id, 'value' => 'red']],
        ])
            ->assertOk()
            ->assertJsonPath('data', [['object_type' => 'product', 'object_id' => 1]]);
    }

    /**
     * @return array{0: ClassificationClass, 1: ClassCharacteristic}
     */
    private function classWithCharacteristic(int $organizationId): array
    {
        $class = ClassificationClass::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'class_code' => 'C'.$organizationId,
            'class_name' => 'Class',
            'object_type' => 'product',
            'is_active' => true,
        ]);

        $characteristic = ClassCharacteristic::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'classification_class_id' => $class->id,
            'characteristic_code' => 'SHADE',
            'characteristic_name' => 'Shade',
            'data_type' => 'text',
        ]);

        return [$class, $characteristic];
    }
}
