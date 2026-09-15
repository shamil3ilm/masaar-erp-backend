<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\WebhookDlqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookDlqController extends Controller
{
    public function __construct(private readonly WebhookDlqService $service) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();

        return $this->paginated($this->service->list($request->user()->organization_id, $status ?: null));
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->service->getDlqSummary($request->user()->organization_id);
        return $this->success($summary);
    }

    public function replay(Request $request, int $id): JsonResponse
    {
        $entry = $this->service->findForOrganization($request->user()->organization_id, $id);
        $this->service->replay($entry, $request->user()->id);

        return $this->success($entry->fresh(), 'Webhook event replayed');
    }

    public function bulkReplay(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $replayed = $this->service->replayMany($request->user()->organization_id, $data['ids'], $request->user()->id);

        return $this->success(['replayed' => $replayed], 'Bulk replay initiated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->delete($this->service->findForOrganization($request->user()->organization_id, $id));

        return $this->success(null, 'DLQ entry deleted');
    }
}
