<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Messaging\NotificationPreference;
use App\Models\Sales\Contact;
use Illuminate\Support\Facades\DB;

/**
 * Which messages a contact of the organization agrees to receive.
 *
 * Writes first resolve the contact within the organization, so a preference
 * row is never written for another organization's contact.
 */
class NotificationPreferenceService
{
    public function forContact(int $organizationId, int $contactId): ?NotificationPreference
    {
        return NotificationPreference::query()
            ->where('organization_id', $organizationId)
            ->where('contact_id', $contactId)
            ->first();
    }

    /**
     * What a contact receives before any preference is saved: every channel and category.
     */
    public function defaultsFor(int $contactId): array
    {
        return [
            'contact_id' => $contactId,
            'email_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => true,
            'push_enabled' => true,
            'marketing_enabled' => true,
            'transactional_enabled' => true,
            'reminder_enabled' => true,
            'preferred_channel' => 'email',
            'preferred_language' => 'en',
            'timezone' => null,
            'quiet_hours' => null,
            'unsubscribed_at' => null,
        ];
    }

    /**
     * Create or update the contact's preferences.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the contact is not the organization's
     */
    public function save(int $organizationId, int $contactId, array $data): NotificationPreference
    {
        $this->findContact($organizationId, $contactId);

        return NotificationPreference::updateOrCreate(
            ['organization_id' => $organizationId, 'contact_id' => $contactId],
            [...$data, 'contact_id' => $contactId]
        );
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when the contact is not the organization's
     */
    public function unsubscribe(int $organizationId, int $contactId, ?string $reason): NotificationPreference
    {
        $this->findContact($organizationId, $contactId);

        return DB::transaction(function () use ($organizationId, $contactId, $reason) {
            $preference = NotificationPreference::firstOrCreate(
                ['organization_id' => $organizationId, 'contact_id' => $contactId]
            );
            $preference->unsubscribe($reason);

            return $preference;
        });
    }

    /**
     * Resubscribe the contact, or return null when no preference was ever saved.
     */
    public function resubscribe(int $organizationId, int $contactId): ?NotificationPreference
    {
        $preference = $this->forContact($organizationId, $contactId);
        $preference?->resubscribe();

        return $preference;
    }

    private function findContact(int $organizationId, int $contactId): Contact
    {
        return Contact::query()
            ->select(Contact::REFERENCE_COLUMNS)
            ->where('organization_id', $organizationId)
            ->findOrFail($contactId);
    }
}
