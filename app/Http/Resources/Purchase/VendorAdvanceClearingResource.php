<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vendor advance clearing with its columns and loaded relations as stored,
 * except the bill, which goes out through BillResource so the supplier tax
 * number is masked.
 */
class VendorAdvanceClearingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'bill' => $this->whenLoaded('bill', fn () => $this->bill ? new BillResource($this->bill) : null),
        ]);
    }
}
