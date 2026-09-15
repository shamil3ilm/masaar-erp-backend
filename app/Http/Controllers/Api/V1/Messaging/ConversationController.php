<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Messaging\Conversation;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly ConversationService $conversations) {}

    /**
     * List conversations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->conversations->paginateForParticipant(
            $request->user()->organization_id,
            $request->user()->id,
            $request->integer('per_page', 20)
        ));
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', $this->ownedBy('users')],
            'subject' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = $this->conversations->start(
            $request->user()->organization_id,
            $request->user()->id,
            $validated['participant_ids'],
            $validated['subject'] ?? null
        );

        return $this->created($conversation, 'Conversation created successfully');
    }

    /**
     * Show a specific conversation.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = $this->findOwn($request, $id, ['participants:id,name']);

        return $conversation ? $this->success($conversation) : $this->notFound('Conversation not found');
    }

    /**
     * Get messages for a conversation.
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->findOwn($request, $conversationId);

        if (! $conversation) {
            return $this->notFound('Conversation not found');
        }

        return $this->paginated($this->conversations->paginateMessages($conversation, $request->integer('per_page', 50)));
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:text,file,image'],
            'file_url' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer', 'min:0'],
        ]);

        $conversation = $this->findOwn($request, $conversationId);

        if (! $conversation) {
            return $this->notFound('Conversation not found');
        }

        $message = $this->conversations->send($conversation, $request->user()->id, $validated);

        return $this->created($message, 'Message sent successfully');
    }

    /**
     * Mark a conversation as read for the authenticated user.
     */
    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->findOwn($request, $conversationId);

        if (! $conversation) {
            return $this->notFound('Conversation not found');
        }

        $conversation->markAsRead($request->user()->id);

        return $this->success(null, 'Conversation marked as read');
    }

    /**
     * @param  list<string>  $with
     */
    private function findOwn(Request $request, int $conversationId, array $with = []): ?Conversation
    {
        return $this->conversations->findForParticipant(
            $request->user()->organization_id,
            $request->user()->id,
            $conversationId,
            $with
        );
    }
}
