<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Campaign;

use App\Http\Controllers\Controller;
use App\Services\Campaign\SegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    public function __construct(private readonly SegmentService $segmentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->segmentService->paginate($this->organizationId($request), $request->integer('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'conditions'  => 'required|array',
            'conditions.*.field'    => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value'    => 'required',
            'color'      => 'nullable|string|size:7|regex:/^#[0-9a-fA-F]{6}$/',
            'is_dynamic' => 'nullable|boolean',
        ]);

        $segment = $this->segmentService->create($this->organizationId($request), $request->user()->id, $validated);

        return $this->created($segment, 'Segment created successfully.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $segment = $this->segmentService->find($this->organizationId($request), $id);
        $members = $this->segmentService->paginateMembers($segment, $request->integer('per_page', 15));

        return $this->success([
            'segment' => $segment,
            'members' => [
                'data' => $members->items(),
                'meta' => [
                    'current_page' => $members->currentPage(),
                    'per_page'     => $members->perPage(),
                    'total'        => $members->total(),
                    'last_page'    => $members->lastPage(),
                ],
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $segment = $this->segmentService->find($this->organizationId($request), $id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'conditions'  => 'sometimes|array',
            'conditions.*.field'    => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string',
            'conditions.*.value'    => 'required_with:conditions',
            'color'      => 'nullable|string|size:7|regex:/^#[0-9a-fA-F]{6}$/',
            'is_dynamic' => 'nullable|boolean',
        ]);

        return $this->success($this->segmentService->update($segment, $validated), 'Segment updated successfully.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->segmentService->delete($this->segmentService->find($this->organizationId($request), $id));

        return $this->success(null, 'Segment deleted successfully.');
    }

    public function members(Request $request, string $id): JsonResponse
    {
        $segment = $this->segmentService->find($this->organizationId($request), $id);

        return $this->paginated($this->segmentService->paginateMembers($segment, $request->integer('per_page', 15)));
    }
}
