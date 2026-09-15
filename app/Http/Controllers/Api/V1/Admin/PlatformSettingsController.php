<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingsController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success($this->settings->all());
    }

    public function show(string $key): JsonResponse
    {
        return $this->success($this->settings->find($key));
    }

    public function update(Request $request, string $key): JsonResponse
    {
        return $this->success(
            $this->settings->put($key, $request->input('value'), $request->input('group', 'general'))
        );
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->settings->putMany($request->input('settings', []));

        return $this->success(['message' => 'Settings updated']);
    }
}
