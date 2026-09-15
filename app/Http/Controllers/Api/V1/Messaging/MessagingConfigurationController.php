<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\MessagingConfiguration;
use App\Services\Messaging\MessagingConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessagingConfigurationController extends Controller
{
    public function __construct(private readonly MessagingConfigurationService $configurations) {}

    /**
     * List messaging configurations (channels).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->configurations->paginate(
            $request->user()->organization_id,
            $request->only(['channel_type', 'provider', 'is_active']),
            (int) ($request->per_page ?? 15)
        ));
    }

    /**
     * Store a new messaging configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_type' => 'required|in:email,sms,whatsapp,push_notification',
            'name' => 'required|string|max:255',
            'provider' => 'required|in:smtp,sendgrid,twilio,vonage,firebase,whatsapp_business',
            'credentials' => 'required|array',
            'settings' => 'nullable|array',
            'sender_name' => 'nullable|string|max:255',
            'sender_address' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->created($this->configurations->create($request->user()->organization_id, $validated));
    }

    /**
     * Show a specific messaging configuration.
     */
    public function show(MessagingConfiguration $messagingConfiguration): JsonResponse
    {
        return $this->success($messagingConfiguration);
    }

    /**
     * Update a messaging configuration.
     */
    public function update(Request $request, MessagingConfiguration $messagingConfiguration): JsonResponse
    {
        $validated = $request->validate([
            'channel_type' => 'sometimes|in:email,sms,whatsapp,push_notification',
            'name' => 'sometimes|string|max:255',
            'provider' => 'sometimes|in:smtp,sendgrid,twilio,vonage,firebase,whatsapp_business',
            'credentials' => 'sometimes|array',
            'settings' => 'nullable|array',
            'sender_name' => 'nullable|string|max:255',
            'sender_address' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->success(
            $this->configurations->update($messagingConfiguration, $validated),
            'Messaging configuration updated successfully.'
        );
    }

    /**
     * Delete a messaging configuration.
     */
    public function destroy(MessagingConfiguration $messagingConfiguration): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->configurations->delete($messagingConfiguration),
            'Messaging configuration deleted successfully.',
            'DEFAULT_CHANNEL',
        );
    }
}
