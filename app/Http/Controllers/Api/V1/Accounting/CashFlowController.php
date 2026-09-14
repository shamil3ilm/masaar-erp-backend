<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CashFlowForecast;
use App\Models\Accounting\CashFlowScenario;
use App\Services\Accounting\CashFlowForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashFlowController extends Controller
{
    public function __construct(
        private readonly CashFlowForecastService $forecastService
    ) {}

    // -------------------------------------------------------------------------
    // Forecasts
    // -------------------------------------------------------------------------

    public function generateForecast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'horizon_days'  => 'nullable|integer|in:30,60,90',
            'currency_code' => 'nullable|string|size:3',
            'scenario_id'   => 'nullable|exists:cash_flow_scenarios,id',
        ]);

        $organization = $this->organization($request);
        $scenario     = isset($validated['scenario_id'])
            ? $this->forecastService->findScenario($validated['scenario_id'])
            : null;

        $forecast = $this->forecastService->generateForecast(
            $organization,
            (int) ($validated['horizon_days'] ?? 90),
            $scenario,
            $validated['currency_code'] ?? 'SAR'
        );

        $summary = $this->forecastService->getPeriodSummary($forecast);

        return $this->success([
            'forecast' => $forecast->load(['scenario']),
            'period_summary' => $summary,
        ], 'Cash flow forecast generated.', 201);
    }

    public function showForecast(Request $request, CashFlowForecast $cashFlowForecast): JsonResponse
    {
        $summary = $this->forecastService->getPeriodSummary($cashFlowForecast);

        return $this->success([
            'forecast'       => $cashFlowForecast->load(['lines', 'scenario']),
            'period_summary' => $summary,
        ], 'Cash flow forecast retrieved.');
    }

    public function indexForecasts(Request $request): JsonResponse
    {
        $forecasts = $this->forecastService->listForecasts(
            $this->organizationId($request),
            $request->input('scenario_id'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($forecasts, null, 'Cash flow forecasts retrieved.');
    }

    public function refreshForecast(CashFlowForecast $cashFlowForecast): JsonResponse
    {
        $forecast = $this->forecastService->refreshForecast($cashFlowForecast);
        $summary  = $this->forecastService->getPeriodSummary($forecast);

        return $this->success([
            'forecast'       => $forecast->load(['lines', 'scenario']),
            'period_summary' => $summary,
        ], 'Cash flow forecast refreshed.');
    }

    public function forecastLines(Request $request, CashFlowForecast $cashFlowForecast): JsonResponse
    {
        $lines = $this->forecastService->listLines(
            $cashFlowForecast,
            $request->only(['flow_type', 'confidence', 'source_type']),
            $request->integer('per_page', 30),
        );

        return $this->paginated($lines, null, 'Cash flow lines retrieved.');
    }

    // -------------------------------------------------------------------------
    // Scenarios
    // -------------------------------------------------------------------------

    public function indexScenarios(Request $request): JsonResponse
    {
        $scenarios = $this->forecastService->listScenarios($this->organizationId($request));

        return $this->success($scenarios, 'Cash flow scenarios retrieved.');
    }

    public function storeScenario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string',
            'is_base_case' => 'nullable|boolean',
            'assumptions'  => 'nullable|array',
        ]);

        $scenario = $this->forecastService->createScenario(
            $this->organizationId($request),
            $validated,
            auth()->id(),
        );

        return $this->success($scenario->load(['creator']), 'Cash flow scenario created.', 201);
    }

    public function updateScenario(Request $request, CashFlowScenario $cashFlowScenario): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'description'  => 'nullable|string',
            'is_base_case' => 'nullable|boolean',
            'assumptions'  => 'nullable|array',
        ]);

        $scenario = $this->forecastService->updateScenario($cashFlowScenario, $validated);

        return $this->success($scenario->load(['creator']), 'Cash flow scenario updated.');
    }

    public function destroyScenario(CashFlowScenario $cashFlowScenario): JsonResponse
    {
        $cashFlowScenario->delete();

        return $this->success(null, 'Cash flow scenario deleted.');
    }
}
