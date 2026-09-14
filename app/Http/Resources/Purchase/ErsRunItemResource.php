<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use App\Http\Resources\Sales\ContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An ERS run item with its columns and goods receipt as stored, the vendor
 * through ContactResource and the bill through BillResource, so tax numbers
 * are masked.
 */
class ErsRunItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? new ContactResource($this->vendor) : null),
            'bill' => $this->whenLoaded('bill', fn () => $this->bill ? new BillResource($this->bill) : null),
        ]);
    }
}
