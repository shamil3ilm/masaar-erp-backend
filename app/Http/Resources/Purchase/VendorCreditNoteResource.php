<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

use App\Http\Resources\Sales\ContactResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vendor credit note with its own columns as stored.
 *
 * The vendor and the bill go out through ContactResource and BillResource, so
 * tax numbers are masked as everywhere else, and the posting and voiding users
 * as id and name only.
 */
class VendorCreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->attributesToArray(), [
            'vendor' => $this->whenLoaded('vendor', fn () => $this->vendor ? new ContactResource($this->vendor) : null),
            'bill' => $this->whenLoaded('bill', fn () => $this->bill ? new BillResource($this->bill) : null),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->toArray()),
            'posted_by' => $this->whenLoaded('postedBy', fn () => $this->userSummary($this->postedBy), $this->posted_by),
            'voided_by' => $this->whenLoaded('voidedBy', fn () => $this->userSummary($this->voidedBy), $this->voided_by),
        ]);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userSummary(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
