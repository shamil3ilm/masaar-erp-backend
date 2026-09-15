<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ImpersonationAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationAuditController extends Controller
{
    public function __construct(private readonly ImpersonationAuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->audit->paginateSessions(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function show(string $sessionId): JsonResponse
    {
        $detail = $this->audit->sessionDetail($sessionId);

        return $detail === null ? $this->notFound('Impersonation session not found.') : $this->success($detail);
    }
}
