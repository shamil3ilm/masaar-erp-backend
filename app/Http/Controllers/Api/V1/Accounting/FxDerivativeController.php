<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FxForward;
use App\Services\Accounting\FxDerivativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class FxDerivativeController extends Controller
{
    public function __construct(private readonly FxDerivativeService $service) {}

    /** GET /fx-forwards */
    public function index(Request $request): JsonResponse
    {
        $forwards = $this->service->listForwards(
            organizationId: $request->user()->organization_id,
            status:         $request->status,
            buyCurrency:    $request->buy_currency,
            perPage:        (int) $request->get('per_page', 20),
        );

        return $this->paginated($forwards);
    }

    /** POST /fx-forwards */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'counterparty_bank'               => ['nullable', 'string'],
            'buy_currency'                    => ['required', 'string', 'size:3'],
            'sell_currency'                   => ['required', 'string', 'size:3'],
            'notional_amount'                 => ['required', 'numeric', 'min:0.01'],
            'forward_rate'                    => ['required', 'numeric', 'min:0.000001'],
            'trade_date'                      => ['required', 'date'],
            'maturity_date'                   => ['required', 'date', 'after:trade_date'],
            'purpose'                         => ['in:speculative,hedge'],
            'derivative_asset_account_id'     => ['nullable', 'integer'],
            'unrealised_gain_loss_account_id' => ['nullable', 'integer'],
            'realised_gain_loss_account_id'   => ['nullable', 'integer'],
        ]);

        $forward = $this->service->bookForward(
            organizationId: $request->user()->organization_id,
            data:           $data,
            createdBy:      $request->user()->id,
        );

        return $this->success($forward, 'FX forward booked', 201);
    }

    /** GET /fx-forwards/{forward} */
    public function show(FxForward $fxForward): JsonResponse
    {
        return $this->success($fxForward->load(['hedgeRelation', 'valuations']));
    }

    /** POST /fx-forwards/{forward}/designate-hedge */
    public function designateHedge(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'hedge_type'              => ['required', 'in:fair_value,cash_flow,net_investment'],
            'hedged_item_type'        => ['required', 'string'],
            'hedged_item_id'          => ['nullable', 'integer'],
            'hedged_item_description' => ['nullable', 'string'],
            'hedge_ratio'             => ['numeric', 'min:0.01', 'max:1.0'],
            'designation_date'        => ['required', 'date'],
        ]);

        $relation = $this->service->designateHedge($fxForward, $data);

        return $this->success($relation, 'Hedge relationship designated', 201);
    }

    /** POST /fx-forwards/{forward}/dedesignate-hedge */
    public function dedesignateHedge(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'dedesignation_date' => ['required', 'date'],
        ]);

        $relation = $this->service->dedesignateHedge(
            $this->service->findDesignatedHedge($fxForward),
            $data['dedesignation_date'],
        );

        return $this->success($relation, 'Hedge relationship de-designated');
    }

    /** POST /fx-forwards/{forward}/valuate */
    public function valuate(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'valuation_date' => ['required', 'date'],
            'spot_rate'      => ['required', 'numeric', 'min:0.000001'],
        ]);

        try {
            $valuation = $this->service->recordValuation(
                forward:        $fxForward,
                valuationDate:  Carbon::parse($data['valuation_date']),
                spotRate:       (string) $data['spot_rate'],
            );
        } catch (RuntimeException $e) {
            // The service refuses a valuation it cannot journal and names the
            // account mapping it is missing. That is a rule the caller can put
            // right, so it is reported as a refusal rather than a fault.
            return $this->error($e->getMessage(), 'VALUATION_REFUSED', 422);
        }

        return $this->success($valuation, 'MTM valuation recorded', 201);
    }

    /** POST /fx-forwards/{forward}/settle */
    public function settle(Request $request, FxForward $fxForward): JsonResponse
    {
        $data = $request->validate([
            'settlement_rate' => ['required', 'numeric', 'min:0.000001'],
            'settlement_date' => ['required', 'date'],
        ]);

        try {
            $forward = $this->service->settle(
                forward:         $fxForward,
                settlementRate:  (string) $data['settlement_rate'],
                settlementDate:  Carbon::parse($data['settlement_date']),
            );
        } catch (RuntimeException $e) {
            // A settlement it cannot journal leaves the forward untouched, and
            // the message names the account mapping that is missing.
            return $this->error($e->getMessage(), 'SETTLEMENT_REFUSED', 422);
        }

        return $this->success($forward, 'FX forward settled');
    }
}
