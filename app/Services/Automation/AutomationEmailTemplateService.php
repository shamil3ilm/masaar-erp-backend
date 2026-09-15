<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Automation\AutomationEmailTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The organization's email templates used by automation rules.
 */
class AutomationEmailTemplateService
{
    /**
     * @param  array{category?: ?string, is_active?: ?string, search?: ?string}  $filters
     */
    public function paginate(int $organizationId, array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return AutomationEmailTemplate::query()
            ->where('organization_id', $organizationId)
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->forCategory($category))
            ->when(($filters['is_active'] ?? null) !== null, fn ($query) => $filters['is_active'] === 'true'
                ? $query->active()
                : $query->inactive())
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->search($search))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    public function create(int $organizationId, array $data): AutomationEmailTemplate
    {
        return AutomationEmailTemplate::create([...$data, 'organization_id' => $organizationId]);
    }

    public function update(AutomationEmailTemplate $template, array $data): AutomationEmailTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    public function delete(AutomationEmailTemplate $template): void
    {
        $template->delete();
    }
}
