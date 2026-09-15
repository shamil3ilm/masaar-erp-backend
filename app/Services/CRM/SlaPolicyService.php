<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\SlaPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The organization's service-level policies, which set a ticket's response
 * and resolution deadlines.
 */
class SlaPolicyService
{
    /**
     * @param  array{priority?: ?string, active_only?: bool}  $filters
     */
    public function paginate(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return SlaPolicy::query()
            ->where('organization_id', $organizationId)
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['active_only'] ?? false, fn ($query) => $query->active())
            ->orderBy('priority')
            ->paginate($perPage);
    }

    public function create(int $organizationId, array $data): SlaPolicy
    {
        return SlaPolicy::create([...$data, 'organization_id' => $organizationId]);
    }
}
