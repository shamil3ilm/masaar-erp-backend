<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\TravelRequest;
use App\Services\HR\TravelExpenseReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TravelExpenseReportController extends Controller
{
    public function __construct(
        private TravelExpenseReportService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->listRequests(
            $request->only(['employee_id', 'status']),
            $request->integer('per_page', 15)
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'employee_id'         => ['required', 'integer', Rule::exists('employees', 'id')->where('organization_id', $organizationId)],
            'purpose'             => 'required|string|max:500',
            'destination'         => 'required|string|max:200',
            'departure_date'      => 'required|date',
            'return_date'         => 'required|date|after_or_equal:departure_date',
            'estimated_cost'      => 'nullable|numeric|min:0',
            'currency_code'       => 'nullable|string|size:3',
        ]);

        $validated['organization_id'] = $organizationId;
        $validated['created_by']      = auth()->id();
        $validated['status']          = TravelRequest::STATUS_DRAFT;

        $travelRequest = $this->service->createRequest($validated);

        return $this->created(
            $travelRequest->load(['employee', 'creator']),
            'Travel request created.'
        );
    }

    public function show(TravelRequest $travelRequest): JsonResponse
    {
        return $this->success(
            $travelRequest->load(['employee', 'approver', 'expenseClaims'])
        );
    }

    public function approve(Request $request, string $uuid): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->approveRequest($this->organizationId($request), $uuid, (int) auth()->id()),
            'Travel request approved.',
            'INVALID_STATE'
        );
    }

    public function indexReports(Request $request, string $uuid): JsonResponse
    {
        $travelRequest = $this->service->findRequestByUuid($uuid);

        return $this->paginated($this->service->listReports($travelRequest, $request->integer('per_page', 15)));
    }

    public function storeReport(Request $request, string $uuid): JsonResponse
    {
        $travelRequest = $this->service->findRequestByUuid($uuid);
        $organizationId = $travelRequest->organization_id;

        $validated = $request->validate([
            'employee_id'      => ['required', 'integer', Rule::exists('employees', 'id')->where('organization_id', $organizationId)],
            'report_date'      => 'nullable|date',
            'currency_code'    => 'nullable|string|size:3',
            'notes'            => 'nullable|string|max:2000',
            'lines'            => 'required|array|min:1',
            'lines.*.expense_type_id' => ['required', 'integer', Rule::exists('travel_expense_types', 'id')->where('organization_id', $organizationId)],
            'lines.*.expense_date'    => 'required|date',
            'lines.*.description'     => 'required|string|max:500',
            'lines.*.amount'          => 'required|numeric|min:0.0001',
            'lines.*.currency_code'   => 'nullable|string|size:3',
            'lines.*.amount_in_local' => 'nullable|numeric|min:0',
            'lines.*.receipt_attached' => 'nullable|boolean',
        ]);

        $validated['travel_request_id'] = $travelRequest->id;

        try {
            $report = $this->service->submitExpenseReport(
                $this->organizationId($request),
                $validated,
                (int) auth()->id()
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'REPORT_CREATION_FAILED', 422);
        }

        return $this->created($report, 'Expense report created.');
    }

    public function approveReport(Request $request, string $uuid): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->service->approveExpenseReport($this->organizationId($request), $uuid, (int) auth()->id()),
            'Expense report approved.',
            'INVALID_STATE'
        );
    }

    public function postReport(Request $request, string $uuid): JsonResponse
    {
        try {
            $report = $this->service->postExpenseReport(
                $this->organizationId($request),
                $uuid,
                (int) auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATE', 422);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 'POST_FAILED', 422);
        }

        return $this->success($report, 'Expense report posted to accounting.');
    }

    public function indexTypes(Request $request): JsonResponse
    {
        return $this->paginated($this->service->listExpenseTypes(
            $request->boolean('active_only'),
            $request->get('category'),
            $request->integer('per_page', 50)
        ));
    }

    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'             => 'required|string|max:20',
            'name'             => 'required|string|max:100',
            'category'         => 'required|in:accommodation,transport,meals,entertainment,other',
            'daily_limit'      => 'nullable|numeric|min:0',
            'gl_account_code'  => 'nullable|string|max:20',
            'requires_receipt' => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);

        $validated['organization_id'] = $this->organizationId($request);

        $type = $this->service->createExpenseType($validated);

        return $this->created($type, 'Expense type created.');
    }
}
