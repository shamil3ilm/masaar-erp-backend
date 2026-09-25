<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly ComplaintService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->list($request->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'complaint_number'        => ['required', 'string', 'max:50', Rule::unique('complaints', 'complaint_number')->where('organization_id', $request->user()->organization_id)],
            'complaint_source'        => 'required|in:customer,internal,regulatory,supplier',
            'contact_id'              => ['nullable', 'integer', $this->ownedBy('contacts')],
            'subject'                 => 'required|string|max:255',
            'description'             => 'required|string',
            'priority'                => 'required|in:critical,high,medium,low',
            'assigned_to_id'          => ['nullable', 'integer', $this->ownedBy('users')],
            'received_date'           => 'required|date',
            'target_resolution_date'  => 'nullable|date',
        ]);

        return $this->created($this->service->create($request->user()->organization_id, $data));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->service->findForDisplay($request->user()->organization_id, $id));
    }

    public function addCommunication(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'direction' => 'required|in:inbound,outbound',
            'channel'   => 'required|in:email,phone,letter,portal,in_person',
            'content'   => 'required|string',
        ]);

        $complaint = $this->service->find($request->user()->organization_id, $id);

        return $this->created($this->service->addCommunication($complaint, $data, $request->user()->id));
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'resolution_type'         => 'required|in:replacement,refund,credit,repair,explanation,apology,other',
            'resolution_description'  => 'required|string',
            'customer_accepted'       => 'boolean',
        ]);

        $complaint = $this->service->find($request->user()->organization_id, $id);

        return $this->created($this->service->resolve($complaint, $data, $request->user()->id));
    }
}
