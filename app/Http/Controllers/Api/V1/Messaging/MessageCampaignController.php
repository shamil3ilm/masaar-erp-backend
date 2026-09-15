<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageCampaign;
use App\Services\Messaging\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageCampaignController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private CampaignService $campaignService
    ) {}

    /**
     * List campaigns with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $campaigns = $this->campaignService->paginate(
            $request->user()->organization_id,
            $request->only(['is_active', 'channel_type', 'trigger_event', 'search']),
            $this->safeSortBy($request->sort_by, ['name', 'status', 'created_at', 'updated_at'], 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            (int) ($request->per_page ?? 15)
        );

        return $this->paginated($campaigns);
    }

    /**
     * Store a new campaign.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'required|string|max:100',
            'trigger_entity' => 'nullable|string|max:50',
            'timing' => 'nullable|in:immediate,delayed,scheduled',
            'delay_minutes' => 'nullable|integer|min:0',
            'delay_unit' => 'nullable|in:minutes,hours,days',
            'conditions' => 'nullable|array',
            'channel_type' => 'required|in:email,sms,whatsapp,push_notification',
            'template_id' => ['required', $this->ownedBy('message_templates')],
            'channel_id' => ['nullable', $this->ownedBy('messaging_channels')],
            'recipient_type' => 'nullable|in:contact,user,custom,role',
            'recipient_config' => 'nullable|array',
            'max_sends_per_contact' => 'nullable|integer|min:1',
            'rate_limit_period' => 'nullable|in:day,week,month',
            'is_active' => 'nullable|boolean',
        ]);

        $campaign = $this->campaignService->create($validated, $request->user()->id);

        return $this->created($campaign->load(['template', 'creator']));
    }

    /**
     * Show a specific campaign.
     */
    public function show(MessageCampaign $messageCampaign): JsonResponse
    {
        return $this->success(
            $messageCampaign->load(['template', 'channel', 'creator'])
        );
    }

    /**
     * Update a campaign.
     */
    public function update(Request $request, MessageCampaign $messageCampaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'sometimes|string|max:100',
            'trigger_entity' => 'nullable|string|max:50',
            'timing' => 'nullable|in:immediate,delayed,scheduled',
            'delay_minutes' => 'nullable|integer|min:0',
            'delay_unit' => 'nullable|in:minutes,hours,days',
            'conditions' => 'nullable|array',
            'channel_type' => 'sometimes|in:email,sms,whatsapp,push_notification',
            'template_id' => ['sometimes', $this->ownedBy('message_templates')],
            'channel_id' => ['nullable', $this->ownedBy('messaging_channels')],
            'recipient_type' => 'nullable|in:contact,user,custom,role',
            'recipient_config' => 'nullable|array',
            'max_sends_per_contact' => 'nullable|integer|min:1',
            'rate_limit_period' => 'nullable|in:day,week,month',
        ]);

        return $this->success(
            $this->campaignService->update($messageCampaign, $validated),
            'Campaign updated successfully.'
        );
    }

    /**
     * Delete a campaign.
     */
    public function destroy(MessageCampaign $messageCampaign): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->campaignService->delete($messageCampaign),
            'Campaign deleted successfully.',
            'CAMPAIGN_ACTIVE',
        );
    }

    /**
     * Launch a campaign.
     */
    public function launch(MessageCampaign $messageCampaign): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->campaignService->launch($messageCampaign),
            'Campaign launched successfully.',
            'CAMPAIGN_ERROR',
        );
    }

    /**
     * Pause or resume a message campaign.
     * PATCH /message-campaigns/{id}/state  {"action": "pause"|"resume"}
     */
    public function setState(Request $request, MessageCampaign $messageCampaign): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:pause,resume',
        ]);

        return $this->tryAction(
            fn() => $validated['action'] === 'pause'
                ? $this->campaignService->pause($messageCampaign)
                : $this->campaignService->resume($messageCampaign),
            $validated['action'] === 'pause' ? 'Campaign paused successfully.' : 'Campaign resumed successfully.',
            'CAMPAIGN_ERROR',
        );
    }

    /**
     * Cancel a campaign.
     */
    public function cancel(MessageCampaign $messageCampaign): JsonResponse
    {
        $campaign = $this->campaignService->cancel($messageCampaign);
        return $this->success($campaign, 'Campaign cancelled successfully.');
    }

    /**
     * Get campaign statistics.
     */
    public function stats(MessageCampaign $messageCampaign): JsonResponse
    {
        $stats = $this->campaignService->getStats($messageCampaign);
        return $this->success($stats, 'Campaign statistics retrieved successfully.');
    }

    /**
     * Get campaign recipients (outbound messages).
     */
    public function recipients(Request $request, MessageCampaign $messageCampaign): JsonResponse
    {
        return $this->paginated($this->campaignService->paginateRecipients(
            $messageCampaign,
            $request->status,
            (int) ($request->per_page ?? 15)
        ));
    }

    /**
     * Add recipients to a campaign.
     */
    public function addRecipients(Request $request, MessageCampaign $messageCampaign): JsonResponse
    {
        $validated = $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*.address' => 'nullable|string|max:255',
            'recipients.*.email' => 'nullable|email|max:255',
            'recipients.*.phone' => 'nullable|string|max:30',
            'recipients.*.name' => 'nullable|string|max:255',
            'recipients.*.contact_id' => ['nullable', $this->ownedBy('contacts')],
            'recipients.*.data' => 'nullable|array',
        ]);

        $results = $this->campaignService->addRecipients($messageCampaign, $validated['recipients'], $request->user()->id);

        return $this->success($results, 'Recipients added successfully.');
    }
}
