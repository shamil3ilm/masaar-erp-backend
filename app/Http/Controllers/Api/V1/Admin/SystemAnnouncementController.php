<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemAnnouncement;
use App\Services\Admin\SystemAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemAnnouncementController extends Controller
{
    public function __construct(private SystemAnnouncementService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginate($request->input('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|string|in:info,warning,maintenance,feature,critical',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'show_banner' => 'nullable|boolean',
            'banner_color' => 'nullable|string|max:7',
        ]);

        return $this->created($this->service->add($request->all()));
    }

    public function show(SystemAnnouncement $announcement): JsonResponse
    {
        return $this->success($announcement);
    }

    public function update(Request $request, SystemAnnouncement $announcement): JsonResponse
    {
        return $this->success($this->service->update($announcement, $request->all()));
    }

    public function destroy(SystemAnnouncement $announcement): JsonResponse
    {
        $this->service->delete($announcement);

        return $this->success(['message' => 'Announcement deleted']);
    }

    public function publish(SystemAnnouncement $announcement): JsonResponse
    {
        return $this->success($this->service->markPublished($announcement));
    }
}
