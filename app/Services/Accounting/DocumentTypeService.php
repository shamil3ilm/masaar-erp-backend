<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\DocumentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DocumentTypeService
{
    /**
     * Document types of the current organization, ordered by code.
     */
    public function list(bool $activeOnly, int $perPage): LengthAwarePaginator
    {
        return DocumentType::query()
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('code')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  Validated document type attributes.
     */
    public function create(array $data, int $organizationId): DocumentType
    {
        return DocumentType::create([...$data, 'organization_id' => $organizationId]);
    }
}
