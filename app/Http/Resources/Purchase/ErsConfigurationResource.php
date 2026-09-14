<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use App\Http\Resources\Sales\ContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An ERS configuration with its columns as stored and the vendor through
 * ContactResource, so the vendor's tax number is masked.
 */
class ErsConfigurationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? new ContactResource($this->vendor) : null),
        ]);
    }
}
