<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Core\IpAllowlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpAllowlistController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly IpAllowlistService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->list($request->user()->organization_id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rule_name'      => 'required|string|max:255',
            'ip_address'     => 'nullable|ip',
            'ip_range_start' => 'nullable|ip',
            'ip_range_end'   => 'nullable|ip',
            'cidr_notation'  => 'nullable|string|max:18',
            'rule_type'      => 'required|in:allow,deny',
            'applies_to'     => 'required|in:all,api,admin,specific_role',
            'role_id'        => ['nullable', 'integer', $this->ownedBy('roles')],
            'active'         => 'boolean',
        ]);

        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by']      = $request->user()->id;

        $rule = $this->service->addRule($data);

        return $this->created($rule);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rule = $this->service->findInOrganization($request->user()->organization_id, $id);
        $data = $request->validate([
            'rule_name'  => 'sometimes|string|max:255',
            'rule_type'  => 'sometimes|in:allow,deny',
            'applies_to' => 'sometimes|in:all,api,admin,specific_role',
            'active'     => 'sometimes|boolean',
        ]);

        return $this->success($this->service->updateRule($rule, $data));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $rule = $this->service->findInOrganization($request->user()->organization_id, $id);
        $this->service->deleteRule($rule);

        return $this->success(null, 'Rule deleted');
    }

    public function check(Request $request): JsonResponse
    {
        $ip     = $request->input('ip', $request->ip());
        $result = $this->service->checkAccess($ip, $request->user()->organization_id);

        return $this->success(['ip' => $ip, 'access' => $result ? 'allowed' : 'denied']);
    }
}
