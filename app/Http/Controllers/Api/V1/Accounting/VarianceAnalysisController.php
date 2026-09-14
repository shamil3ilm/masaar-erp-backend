<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\VarianceAnalysisRun;
use App\Services\Accounting\VarianceAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VarianceAnalysisController extends Controller
{
    public function __construct(
        private readonly VarianceAnalysisService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'period'      => $request->filled('period') ? $request->integer('period') : null,
            'fiscal_year' => $request->filled('fiscal_year') ? $request->integer('fiscal_year') : null,
            'status'      => $request->filled('status') ? $request->input('status') : null,
        ];

        return $this->paginated($this->service->list($filters, $request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
            'run_type'    => ['required', Rule::in([
                VarianceAnalysisRun::RUN_TYPE_PRODUCTION_ORDER,
                VarianceAnalysisRun::RUN_TYPE_COST_CENTER,
                VarianceAnalysisRun::RUN_TYPE_PROJECT,
            ])],
        ]);

        $run = $this->service->runAnalysis(
            (int) $validated['period'],
            (int) $validated['fiscal_year'],
            $validated['run_type'],
            (int) $orgId,
            $request->user()->id
        );

        return $this->created($run);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->service->findRun($id));
    }

    public function results(int $id): JsonResponse
    {
        return $this->success($this->service->getResultsForRun($id));
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'      => ['required', 'integer', 'min:1', 'max:12'],
            'fiscal_year' => ['required', 'integer'],
        ]);

        $summary = $this->service->getSummaryByCategory(
            (int) $validated['period'],
            (int) $validated['fiscal_year'],
            (int) $this->organizationId($request)
        );

        return $this->success($summary);
    }
}
