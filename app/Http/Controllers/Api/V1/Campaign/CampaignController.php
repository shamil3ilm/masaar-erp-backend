<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Campaign;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Campaign\CampaignManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly CampaignManagementService $campaigns) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->campaigns->paginate($this->organizationId($request), $request->integer('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string|max:2000',
            'trigger_event'      => 'nullable|string|max:100',
            'conditions'         => 'nullable|array',
            'target_segment_id'  => ['nullable', 'integer', $this->ownedBy('user_segments')],
            'actions'            => 'required|array|min:1',
            'actions.*.type'     => 'required|string|in:notification,sms,database',
            'schedule_type'      => 'nullable|string|in:immediate,delayed,scheduled',
            'delay_minutes'      => 'nullable|integer|min:1',
            'scheduled_at'       => 'nullable|date',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'max_sends_per_user' => 'nullable|integer|min:1',
        ]);

        $campaign = $this->campaigns->create($this->organizationId($request), $request->user()->id, $validated);

        return $this->created($campaign, 'Campaign created successfully.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->success($this->campaigns->find($this->organizationId($request), $id, withSendCount: true));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $campaign = $this->campaigns->find($this->organizationId($request), $id);

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'description'        => 'nullable|string|max:2000',
            'trigger_event'      => 'nullable|string|max:100',
            'conditions'         => 'nullable|array',
            'target_segment_id'  => ['nullable', 'integer', $this->ownedBy('user_segments')],
            'actions'            => 'sometimes|array|min:1',
            'actions.*.type'     => 'required_with:actions|string|in:notification,sms,database',
            'schedule_type'      => 'nullable|string|in:immediate,delayed,scheduled',
            'delay_minutes'      => 'nullable|integer|min:1',
            'scheduled_at'       => 'nullable|date',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'max_sends_per_user' => 'nullable|integer|min:1',
        ]);

        return $this->success($this->campaigns->update($campaign, $validated), 'Campaign updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->campaigns->delete($this->campaigns->find($this->organizationId($request), $id));

        return $this->success(null, 'Campaign deleted successfully.');
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $campaign = $this->campaigns->find($this->organizationId($request), $id);

        return $this->success($this->campaigns->activate($campaign), 'Campaign activated successfully.');
    }

    public function pause(Request $request, string $id): JsonResponse
    {
        $campaign = $this->campaigns->find($this->organizationId($request), $id);

        return $this->success($this->campaigns->pause($campaign), 'Campaign paused successfully.');
    }
}
