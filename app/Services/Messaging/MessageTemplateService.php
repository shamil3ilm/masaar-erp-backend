<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Messaging\MessageTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The organization's message templates. System templates are read-only.
 */
class MessageTemplateService
{
    /**
     * @param  array{channel_type?: ?string, category?: ?string, language?: ?string, code?: ?string,
     *     is_active?: ?string, is_system?: ?string, search?: ?string}  $filters
     */
    public function paginate(int $organizationId, array $filters, string $sortBy, string $sortOrder, int $perPage): LengthAwarePaginator
    {
        return MessageTemplate::query()
            ->where('organization_id', $organizationId)
            ->when($filters['channel_type'] ?? null, fn ($query, $type) => $query->forChannel($type))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->forCategory($category))
            ->when($filters['language'] ?? null, fn ($query, $language) => $query->forLanguage($language))
            ->when($filters['code'] ?? null, fn ($query, $code) => $query->forCode($code))
            ->when(($filters['is_active'] ?? null) !== null, fn ($query) => $filters['is_active'] === 'true'
                ? $query->active()
                : $query->where('is_active', false))
            ->when(($filters['is_system'] ?? null) !== null, fn ($query) => $filters['is_system'] === 'true'
                ? $query->system()
                : $query->custom())
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->search($search))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Create a template, active, custom and in English unless the data says otherwise.
     */
    public function create(int $organizationId, array $data): MessageTemplate
    {
        return MessageTemplate::create([
            ...$data,
            'organization_id' => $organizationId,
            'is_active' => $data['is_active'] ?? true,
            'is_system' => $data['is_system'] ?? false,
            'language' => $data['language'] ?? 'en',
        ]);
    }

    public function update(MessageTemplate $template, array $data): MessageTemplate
    {
        $this->assertCustom($template, 'modified');

        $template->update($data);

        return $template->fresh();
    }

    /**
     * Delete a custom template together with its channel approvals.
     */
    public function delete(MessageTemplate $template): void
    {
        $this->assertCustom($template, 'deleted');

        DB::transaction(function () use ($template) {
            $template->channelApprovals()->delete();
            $template->delete();
        });
    }

    private function assertCustom(MessageTemplate $template, string $action): void
    {
        if ($template->isSystem()) {
            throw new InvalidArgumentException("System templates cannot be {$action}.");
        }
    }
}
