<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Messaging\MessagingConfiguration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The organization's messaging channels, with at most one default per channel
 * type. A default channel carries its channel type in default_for_type, over
 * which a unique index per organization holds that limit.
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
            $isDefault = ! empty($data['is_default']);
            unset($data['is_default']);

            if ($isDefault) {
                $this->clearDefault($organizationId, $data['channel_type']);
            }

            return MessagingConfiguration::create([
                ...$data,
                'organization_id' => $organizationId,
                'default_for_type' => $isDefault ? $data['channel_type'] : null,
            ]);
        });
    }

    /**
     * Update a channel. Its default marker follows the channel type, so a
     * default channel that changes type stays the default for the new one.
     */
    public function update(MessagingConfiguration $configuration, array $data): MessagingConfiguration
    {
        DB::transaction(function () use ($configuration, $data) {
            $channelType = $data['channel_type'] ?? $configuration->channel_type;
            $isDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : $configuration->is_default;
            unset($data['is_default']);

            if ($isDefault) {
                $this->clearDefault($configuration->organization_id, $channelType, $configuration->id);
            }

            $configuration->update([
                ...$data,
                'default_for_type' => $isDefault ? $channelType : null,
            ]);
        });

        return $configuration->fresh();
    }

    /**
     * Delete a channel other than a default one, which messages fall back to.
     */
    public function delete(MessagingConfiguration $configuration): void
    {
        if ($configuration->is_default) {
            throw new InvalidArgumentException('Cannot delete the default channel configuration. Set another as default first.');
        }

        $configuration->delete();
    }

    /**
     * Release the organization's default marker for a channel type, so the
     * channel taking it over does not collide with the one giving it up.
     */
    private function clearDefault(int $organizationId, string $channelType, ?int $exceptId = null): void
    {
        MessagingConfiguration::query()
            ->where('organization_id', $organizationId)
            ->where('default_for_type', $channelType)
            ->when($exceptId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->update(['default_for_type' => null]);
    }
}
