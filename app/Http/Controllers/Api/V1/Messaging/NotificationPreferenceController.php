<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Services\Messaging\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferences) {}

    /**
     * Get notification preferences for a contact.
     */
    public function show(Request $request, int $contactId): JsonResponse
    {
        $preference = $this->preferences->forContact($request->user()->organization_id, $contactId);

        if (! $preference) {
            return $this->success($this->preferences->defaultsFor($contactId), 'Default notification preferences.');
        }

        return $this->success($preference);
    }

    /**
     * Update notification preferences for a contact (create or update).
     */
    public function update(Request $request, int $contactId): JsonResponse
    {
        $validated = $request->validate([
            'email_enabled' => 'nullable|boolean',
            'sms_enabled' => 'nullable|boolean',
            'whatsapp_enabled' => 'nullable|boolean',
            'push_enabled' => 'nullable|boolean',
            'marketing_enabled' => 'nullable|boolean',
            'transactional_enabled' => 'nullable|boolean',
            'reminder_enabled' => 'nullable|boolean',
            'preferred_channel' => 'nullable|in:email,sms,whatsapp,push_notification',
            'preferred_language' => 'nullable|string|max:5',
            'timezone' => 'nullable|string|max:255',
            'quiet_hours' => 'nullable|array',
            'quiet_hours.start' => 'required_with:quiet_hours|string',
            'quiet_hours.end' => 'required_with:quiet_hours|string',
        ]);

        $preference = $this->preferences->save($request->user()->organization_id, $contactId, $validated);

        return $this->success($preference, 'Notification preferences updated successfully.');
    }

    /**
     * Unsubscribe a contact from all messaging.
     */
    public function unsubscribe(Request $request, int $contactId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $preference = $this->preferences->unsubscribe(
            $request->user()->organization_id,
            $contactId,
            $validated['reason'] ?? null
        );

        return $this->success($preference, 'Contact unsubscribed successfully.');
    }

    /**
     * Resubscribe a contact.
     */
    public function resubscribe(Request $request, int $contactId): JsonResponse
    {
        $preference = $this->preferences->resubscribe($request->user()->organization_id, $contactId);

        if (! $preference) {
            return $this->notFound('No preferences found for this contact.');
        }

        return $this->success($preference, 'Contact resubscribed successfully.');
    }
}
