<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\UserAnalyticsService;
use App\Services\Analytics\UserClusteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAnalyticsController extends Controller
{
    public function __construct(
        private readonly UserClusteringService $clusteringService,
        private readonly UserAnalyticsService $analytics,
    ) {}

    public function activityLogs(Request $request): JsonResponse
    {
        return $this->paginated($this->analytics->paginateActivity(
            $this->organizationId($request),
            $this->filledFilters($request, ['user_id', 'module', 'from_date', 'to_date'])
        ));
    }

    public function featureUsage(Request $request): JsonResponse
    {
        return $this->paginated($this->analytics->paginateFeatureUsage(
            $this->organizationId($request),
            $this->filledFilters($request, ['module', 'from_date', 'to_date', 'user_id'])
        ));
    }

    public function sessions(Request $request): JsonResponse
    {
        return $this->paginated($this->analytics->paginateSessions(
            $this->organizationId($request),
            $this->filledFilters($request, ['user_id'])
        ));
    }

    public function clusters(Request $request): JsonResponse
    {
        return $this->success($this->analytics->clusterDistribution($this->organizationId($request)));
    }

    public function userClusters(Request $request, int $id): JsonResponse
    {
        $user = $this->analytics->findUser($this->organizationId($request), $id);

        return $this->success($this->analytics->clusterAssignments($user));
    }

    public function dimensions(Request $request, int $id): JsonResponse
    {
        $user = $this->analytics->findUser($this->organizationId($request), $id);

        return $this->success($this->clusteringService->getDimensions($user));
    }

    /**
     * The filters the request filled in; a user id is read as an integer.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function filledFilters(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $key === 'user_id' ? $request->integer($key) : $request->input($key);
            }
        }

        return $filters;
    }
}
