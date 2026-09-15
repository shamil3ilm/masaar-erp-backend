<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Core\GdprService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GdprController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly GdprService $service) {}

    public function requests(Request $request): JsonResponse
    {
        return $this->paginated($this->service->listRequests($request->user()->organization_id));
    }

    public function submitRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_type'    => 'required|in:access,erasure,portability,rectification,restriction,objection',
            'requester_name'  => 'required|string|max:255',
            'requester_email' => 'required|email',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['requester_id']    = $request->user()->id;

        $dsr = $this->service->submitDataRequest($data);

        return $this->created($dsr, 'Data subject request submitted. Deadline: 30 days.');
    }

    public function processRequest(Request $request, int $id): JsonResponse
    {
        $dsr = $this->service->findRequest($request->user()->organization_id, $id);

        return $this->tryAction(fn () => $this->service->processRequest($dsr), 'Request processed');
    }

    public function processingRegister(Request $request): JsonResponse
    {
        $register = $this->service->getProcessingRegister($request->user()->organization_id);
        return $this->success($register);
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_name'          => 'required|string|max:255',
            'purpose'                => 'required|string',
            'legal_basis'            => 'required|in:consent,contract,legal_obligation,vital_interests,public_task,legitimate_interests',
            'data_categories'        => 'required|array',
            'retention_period_days'  => 'nullable|integer|min:1',
            'third_country_transfers' => 'boolean',
            'dpia_required'          => 'boolean',
        ]);

        $data['organization_id'] = $request->user()->organization_id;

        return $this->created($this->service->createProcessingActivity($data));
    }

    public function recordConsent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'   => ['nullable', 'integer', $this->ownedBy('contacts')],
            'purpose'      => 'required|string|max:255',
            'consent_text' => 'nullable|string',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['ip_address']      = $request->ip();

        $record = $this->service->recordConsent($data);

        return $this->created($record);
    }

    public function withdrawConsent(Request $request, int $id): JsonResponse
    {
        $record = $this->service->findConsent($request->user()->organization_id, $id);

        return $this->tryAction(fn () => $this->service->withdrawConsent($record), 'Consent withdrawn');
    }
}
