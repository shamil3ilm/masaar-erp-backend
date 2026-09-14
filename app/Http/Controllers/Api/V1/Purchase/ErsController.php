<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\ErsConfigurationResource;
use App\Http\Resources\Purchase\ErsRunItemResource;
use App\Models\Purchase\ErsConfiguration;
use App\Services\Purchase\ErsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ErsController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly ErsService $service) {}

    public function configs(Request $request): JsonResponse
    {
        $configs = $this->service->listConfigs(
            (int) Auth::user()->organization_id,
            $request->integer('per_page', 20)
        );

        return $this->success(
            $configs->through(fn (ErsConfiguration $config) => new ErsConfigurationResource($config)),
            'ERS configurations retrieved.'
        );
    }

    public function saveConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'         => ['required', 'integer', $this->ownedBy('contacts')],
            'is_enabled'        => 'boolean',
            'auto_post'         => 'boolean',
            'tolerance_percent' => 'numeric|min:0|max:100',
        ]);

        $config = $this->service->saveConfig(
            (int) Auth::user()->organization_id,
            (int) $validated['vendor_id'],
            $validated
        );

        return $this->success(new ErsConfigurationResource($config->load('vendor')), 'ERS configuration saved.');
    }

    public function runErs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_ids'   => 'nullable|array',
            'vendor_ids.*' => ['integer', $this->ownedBy('contacts')],
        ]);

        $run = $this->service->runErs(
            (int) Auth::user()->organization_id,
            $validated['vendor_ids'] ?? null
        );

        return $this->success($run, 'ERS run completed.');
    }

    public function getRuns(Request $request): JsonResponse
    {
        $runs = $this->service->listRuns(
            (int) Auth::user()->organization_id,
            $request->integer('per_page', 20)
        );

        return $this->success($runs, 'ERS runs retrieved.');
    }

    public function getRunItems(string $runId): JsonResponse
    {
        $items = $this->service->runItems((int) Auth::user()->organization_id, $runId);

        return $this->success(ErsRunItemResource::collection($items), 'ERS run items retrieved.');
    }
}
