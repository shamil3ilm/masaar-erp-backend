<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Services\Compliance\ZatcaWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Receives the compliance platform's invoice events. The verify.zatca.webhook
 * middleware has checked the signature and the delivery id before this runs.
 */
class ZatcaWebhookController extends Controller
{
    public function __construct(
        private readonly ZatcaWebhookService $webhooks
    ) {}

    /**
     * Every verified delivery is acknowledged, including one this cannot
     * place: the platform counts any other answer as a failed delivery and
     * disables a subscription after ten, which would silently end every event
     * after it.
     */
    public function handle(Request $request): JsonResponse
    {
        $this->webhooks->process($request->input('event'), $request->input('data'));

        return response()->json(['success' => true, 'message' => 'Event processed'], 200);
    }
}
