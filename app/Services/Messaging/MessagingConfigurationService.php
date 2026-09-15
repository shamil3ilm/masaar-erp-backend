<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Messaging\MessagingConfiguration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The organization's messaging channels, with at most one default per channel type.
 */
class MessagingConfigurationService
{
    /**
     * @param  array{channel_type?: ?string, provider?: ?string, is_active?: ?string}  $filters
     */
    public function paginate(int $organizationId, array $filters, int $perPage): LengthAwarePaginator
    {
        return MessagingConfiguration::query()
            ->where('organization_id', $organizationId)
            ->when($filters['channel_type'] ?? null, fn ($query, $type) => $query->forChannel($type))
            ->when($filters['provider'] ?? null, fn ($query, $provider) => $query->forProvider($provider))
            ->when(($filters['is_active'] ?? null) !== null, fn ($query) => $filters['is_active'] === 'true'
                ? $query->active()
                : $query->where('is_active', false))
            ->orderBy('channel_type')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a channel. A new default replaces the organization's previous
     * default for the same channel type.
     */
    public function create(int $organizationId, array $data): MessagingConfiguration
    {
        return DB::transaction(function () use ($organizationId, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefault($organizationId, $data['channel_type']);
            }

            return MessagingConfiguration::create([...$data, 'organization_id' => $organizationId]);
        });
    }

    public function update(MessagingConfiguration $configuration, array $data): MessagingConfiguration
    {
        DB::transaction(function () use ($configuration, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefault(
                    $configuration->organization_id,
                    $data['channel_type'] ?? $configuration->channel_type,
                    $configuration->id
                );
            }

            $configuration->update($data);
        });

        return $configuration->fresh();
    }

    /**
     * Delete a channel other than a default one, which messages fall back to.
     */
    public function delete(MessagingConfiguration $configuration): void
    {
        if ($configuration->isDefault()) {
            throw new InvalidArgumentException('Cannot delete the default channel configuration. Set another as default first.');
        }

        $configuration->delete();
    }

    private function clearDefault(int $organizationId, string $channelType, ?int $exceptId = null): void
    {
        MessagingConfiguration::query()
            ->where('organization_id', $organizationId)
            ->where('channel_type', $channelType)
            ->where('is_default', true)
            ->when($exceptId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->update(['is_default' => false]);
    }
}
