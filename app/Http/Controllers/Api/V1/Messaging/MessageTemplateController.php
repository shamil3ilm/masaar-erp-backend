<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Messaging\MessageTemplate;
use App\Services\Messaging\MessageService;
use App\Services\Messaging\MessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private MessageService $messageService,
        private MessageTemplateService $templates,
    ) {}

    /**
     * List message templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $templates = $this->templates->paginate(
            $request->user()->organization_id,
            $request->only(['channel_type', 'category', 'language', 'code', 'is_active', 'is_system', 'search']),
            $this->safeSortBy($request->sort_by, ['name', 'type', 'created_at', 'updated_at'], 'name'),
            $this->safeSortOrder($request->sort_order, 'asc'),
            (int) ($request->per_page ?? 15)
        );

        return $this->paginated($templates);
    }

    /**
     * Store a new message template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'channel_type' => 'required|in:email,sms,whatsapp,push_notification',
            'category' => 'required|in:transactional,promotional,reminder,notification',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'html_body' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'attachments_config' => 'nullable|array',
            'language' => 'nullable|string|max:5',
            'parent_template_id' => ['nullable', $this->ownedBy('message_templates')],
            'is_active' => 'nullable|boolean',
        ]);

        return $this->created($this->templates->create($request->user()->organization_id, $validated));
    }

    /**
     * Show a specific message template.
     */
    public function show(MessageTemplate $messageTemplate): JsonResponse
    {
        return $this->success(
            $messageTemplate->load(['parentTemplate', 'translations', 'channelApprovals'])
        );
    }

    /**
     * Update a message template.
     */
    public function update(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50',
            'channel_type' => 'sometimes|in:email,sms,whatsapp,push_notification',
            'category' => 'sometimes|in:transactional,promotional,reminder,notification',
            'subject' => 'nullable|string|max:255',
            'body' => 'sometimes|string',
            'html_body' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'attachments_config' => 'nullable|array',
            'language' => 'nullable|string|max:5',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->tryAction(
            fn () => $this->templates->update($messageTemplate, $validated),
            'Message template updated successfully.',
            'SYSTEM_TEMPLATE',
        );
    }

    /**
     * Delete a message template.
     */
    public function destroy(MessageTemplate $messageTemplate): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->templates->delete($messageTemplate),
            'Message template deleted successfully.',
            'SYSTEM_TEMPLATE',
        );
    }

    /**
     * Preview a rendered template with sample data.
     */
    public function preview(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        $sampleData = $validated['data'] ?? $this->generateSampleData($messageTemplate);
        $rendered = $this->messageService->renderTemplate($messageTemplate, $sampleData);

        return $this->success([
            'template_id' => $messageTemplate->uuid,
            'channel_type' => $messageTemplate->channel_type,
            'sample_data' => $sampleData,
            'rendered' => $rendered,
        ], 'Template preview generated successfully.');
    }

    /**
     * Render a template with provided data (for actual use).
     */
    public function render(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $rendered = $this->messageService->renderTemplate($messageTemplate, $validated['data']);

        return $this->success($rendered, 'Template rendered successfully.');
    }

    /**
     * Generate sample data for template preview.
     */
    protected function generateSampleData(MessageTemplate $template): array
    {
        $variables = $template->getAvailableVariables();
        $sampleData = [];

        foreach ($variables as $variable) {
            $sampleData[$variable] = "[{$variable}]";
        }

        return $sampleData;
    }
}
