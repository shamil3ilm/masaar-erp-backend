<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use App\Http\Resources\Sales\ContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A purchasing record with its columns and loaded relations as stored, except
 * the relations that hold a supplier contact or a bill.
 *
 * A contact serialized as a plain model carries the supplier's tax number in
 * full, and a bill carries its own copy of it. Those relations go out through
 * ContactResource and BillResource, which mask it. A subclass names them.
 */
abstract class SupplierRecordResource extends JsonResource
{
    /** @var list<string> Relations of the record that hold a contact. */
    protected const CONTACT_RELATIONS = [];

    /** @var list<string> Relations of the record that hold a bill. */
    protected const BILL_RELATIONS = [];

    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();

        foreach (static::CONTACT_RELATIONS as $relation) {
            $data = $this->withRelationThrough($data, $relation, ContactResource::class);
        }

        foreach (static::BILL_RELATIONS as $relation) {
            $data = $this->withRelationThrough($data, $relation, BillResource::class);
        }

        return $data;
    }

    /**
     * The serialized record with one loaded relation replaced by its resource.
     *
     * @param  array<string, mixed>  $data
     * @param  class-string<JsonResource>  $resourceClass
     * @return array<string, mixed>
     */
    private function withRelationThrough(array $data, string $relation, string $resourceClass): array
    {
        if (! $this->resource->relationLoaded($relation)) {
            return $data;
        }

        $related = $this->resource->getRelation($relation);

        return array_merge($data, [
            Str::snake($relation) => $related === null ? null : new $resourceClass($related),
        ]);
    }
}
