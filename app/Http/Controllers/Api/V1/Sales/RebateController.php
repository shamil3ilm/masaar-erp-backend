<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\RebateMaster;
use App\Services\Sales\RebateAccrualService;
use App\Services\Sales\RebateMasterService;
use App\Services\Sales\RebateSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Rebate management: masters, accruals, and period-end settlement (SAP SD BO01/VB01).
 */
class RebateController extends Controller
{
    public function __construct(
        private readonly RebateAccrualService $accrualService,
        private readonly RebateSettlementService $settlementService,
        private readonly RebateMasterService $rebateMasterService,
    ) {}

    /** GET /rebates */
    public function index(Request $request): JsonResponse
    {
        $rebates = $this->rebateMasterService->list(
            $request->user()->organization_id,
            ['status' => $request->status, 'contact_id' => $request->contact_id],
            (int) $request->get('per_page', 20)
        );

        return $this->paginated($rebates, null, 'Rebate masters retrieved');
    }

    /** GET /rebates/{rebate} */
    public function show(RebateMaster $rebate): JsonResponse
    {
        return $this->success(
            $this->rebateMasterService->loadDetails($rebate),
            'Rebate master retrieved',
        );
    }

    /** POST /rebates */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'rebate_type' => ['required', 'in:percentage,fixed_amount,tiered'],
            'calculation_base' => ['required', 'in:invoice_value,quantity,gross_profit'],
            'rebate_rate' => ['required', 'numeric', 'min:0'],
            'accrual_method' => ['required', 'in:periodic,on_invoice'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],
            'minimum_purchase' => ['nullable', 'numeric', 'min:0'],
            'maximum_rebate' => ['nullable', 'numeric', 'min:0'],
            'accrual_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $orgId)],
            'expense_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $orgId)],
            'description' => ['nullable', 'string'],
        ]);

        $rebate = $this->rebateMasterService->create($orgId, $data);

        return $this->success($rebate, 'Rebate master created', 201);
    }

    /** PUT /rebates/{rebate} */
    public function update(Request $request, RebateMaster $rebate): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'name' => ['string', 'max:255'],
            'rebate_rate' => ['numeric', 'min:0'],
            'valid_to' => ['nullable', 'date'],
            'minimum_purchase' => ['nullable', 'numeric', 'min:0'],
            'maximum_rebate' => ['nullable', 'numeric', 'min:0'],
            'accrual_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $orgId)],
            'expense_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $orgId)],
            'status' => ['in:active,inactive'],
        ]);

        return $this->success($this->rebateMasterService->update($rebate, $data), 'Rebate master updated');
    }

    /** GET /rebates/{rebate}/balance */
    public function balance(RebateMaster $rebate): JsonResponse
    {
        return $this->success(
            $this->settlementService->getOutstandingBalance($rebate),
            'Rebate outstanding balance',
        );
    }

    /** POST /rebates/{rebate}/settle */
    public function settle(Request $request, RebateMaster $rebate): JsonResponse
    {
        $data = $request->validate([
            'settlement_date' => ['required', 'date'],
            'settlement_method' => ['in:credit_note,payment'],
        ]);

        $result = $this->settlementService->settle(
            rebate: $rebate,
            settlementDate: Carbon::parse($data['settlement_date']),
            settlementMethod: $data['settlement_method'] ?? 'credit_note',
            settledByUserId: $request->user()->id,
        );

        return $this->success($result, 'Rebate settled');
    }

    /** POST /rebates/period-end-run */
    public function periodEndRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_end' => ['required', 'date'],
        ]);

        $result = $this->settlementService->periodEndRun(
            organizationId: $request->user()->organization_id,
            periodEnd: Carbon::parse($data['period_end']),
        );

        return $this->success($result, 'Period-end rebate settlement run completed');
    }
}
