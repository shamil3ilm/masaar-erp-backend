<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\FeatureFlag;
use App\Services\Admin\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function __construct(private readonly FeatureFlagService $flags) {}

    public function index(): JsonResponse
    {
        return $this->success($this->flags->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:feature_flags,code',
            'is_enabled' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'rollout_type' => 'nullable|string|max:30',
            'rollout_percentage' => 'nullable|integer|min:0|max:100',
            'specific_organization_ids' => 'nullable|array',
            'specific_subscription_plans' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        return $this->created($this->flags->create($validated));
    }

    public function show(FeatureFlag $flag): JsonResponse
    {
        return $this->success($flag);
    }

    public function update(Request $request, FeatureFlag $flag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:feature_flags,code,' . $flag->id,
            'is_enabled' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'rollout_type' => 'nullable|string|max:30',
            'rollout_percentage' => 'nullable|integer|min:0|max:100',
            'specific_organization_ids' => 'nullable|array',
            'specific_subscription_plans' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        return $this->success($this->flags->update($flag, $validated));
    }

    public function toggle(FeatureFlag $flag): JsonResponse
    {
        return $this->success($this->flags->toggle($flag));
    }

    public function checkFlag(string $code): JsonResponse
    {
        $flag = $this->flags->findByCode($code);

        return $flag ? $this->success($flag) : $this->notFound('Feature flag not found');
    }
}
