<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Http\Controllers\Controller;
use App\Models\Automation\AutomationEmailTemplate;
use App\Services\Automation\AutomationEmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationEmailTemplateController extends Controller
{
    public function __construct(private readonly AutomationEmailTemplateService $templates) {}

    /**
     * List email templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $templates = $this->templates->paginate(
            $request->user()->organization_id,
            $request->only(['category', 'is_active', 'search']),
            $this->safeSortBy($request->sort_by, ['name', 'created_at', 'updated_at'], 'name'),
            $this->safeSortOrder($request->sort_order, 'asc'),
            (int) ($request->per_page ?? 15)
        );

        return $this->paginated($templates);
    }

    /**
     * Store a new email template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'category' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->created($this->templates->create($request->user()->organization_id, $validated));
    }

    /**
     * Show a specific email template.
     */
    public function show(AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        return $this->success($automationEmailTemplate);
    }

    /**
     * Update an email template.
     */
    public function update(Request $request, AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'subject' => 'sometimes|string|max:255',
            'body_html' => 'sometimes|string',
            'body_text' => 'nullable|string',
            'variables' => 'nullable|array',
            'variables.*' => 'string',
            'category' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        return $this->success(
            $this->templates->update($automationEmailTemplate, $validated),
            'Email template updated successfully.'
        );
    }

    /**
     * Delete an email template.
     */
    public function destroy(AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        $this->templates->delete($automationEmailTemplate);

        return $this->success(null, 'Email template deleted successfully.');
    }

    /**
     * Preview rendered email template with sample data.
     */
    public function preview(Request $request, AutomationEmailTemplate $automationEmailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'nullable|array',
        ]);

        $sampleData = $validated['data'] ?? $this->generateSampleData($automationEmailTemplate);
        $rendered = $automationEmailTemplate->render($sampleData);

        return $this->success([
            'template_id' => $automationEmailTemplate->id,
            'sample_data' => $sampleData,
            'rendered' => $rendered,
        ], 'Template preview generated successfully.');
    }

    /**
     * Generate sample data for template preview.
     */
    protected function generateSampleData(AutomationEmailTemplate $template): array
    {
        $variables = $template->getAvailableVariables();
        $sampleData = [];

        foreach ($variables as $variable) {
            $sampleData[$variable] = "{{$variable}_sample}";
        }

        return $sampleData;
    }
}
