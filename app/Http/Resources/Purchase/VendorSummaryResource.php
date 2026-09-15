<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vendor contact reduced to what a sourcing list shows: id, name and email.
 */
class VendorSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getDisplayName(),
            'email' => $this->email,
        ];
    }
}
