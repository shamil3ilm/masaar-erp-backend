<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vendor pricing record as stored, with its vendor reduced to id, name and
 * email so the contact's tax number does not leave with it.
 */
class VendorProductPricingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? new VendorSummaryResource($this->vendor) : null),
        ]);
    }
}
