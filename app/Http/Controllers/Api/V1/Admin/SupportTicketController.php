<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SupportTicket;
use App\Services\Admin\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(private SupportTicketService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->paginate($request->input('status'), $request->input('per_page', 20)));
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        return $this->success($ticket->load('messages'));
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $message = $this->service->reply($ticket, [
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'from_staff' => true,
        ]);

        return $this->created($message);
    }

    public function assign(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'admin_id' => 'required|integer|exists:platform_admins,id',
        ]);

        return $this->success($this->service->assign($ticket, (int) $validated['admin_id']));
    }

    public function close(SupportTicket $ticket): JsonResponse
    {
        return $this->success($this->service->resolve($ticket));
    }

    public function reopen(SupportTicket $ticket): JsonResponse
    {
        return $this->success($this->service->reopen($ticket));
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }
}
