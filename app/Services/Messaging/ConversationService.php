<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Messaging\Conversation;
use App\Models\Messaging\ConversationMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Internal chat between users of one organization.
 *
 * A conversation is reachable only by its participants, and only within the
 * organization it was started in.
 */
class ConversationService
{
    /**
     * Conversations the user still takes part in, most recent message first.
     */
    public function paginateForParticipant(int $organizationId, int $userId, int $perPage): LengthAwarePaginator
    {
        return Conversation::with(['participants:id,name', 'latestMessage'])
            ->where('organization_id', $organizationId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId)->whereNull('left_at'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A conversation of the organization the user takes part in, or null.
     *
     * @param  list<string>  $with
     */
    public function findForParticipant(int $organizationId, int $userId, int $conversationId, array $with = []): ?Conversation
    {
        return Conversation::with($with)
            ->where('organization_id', $organizationId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $userId))
            ->find($conversationId);
    }

    /**
     * Start a conversation with the creator and the given users as participants.
     *
     * The participant ids must already be validated as users of the organization.
     *
     * @param  list<int>  $participantIds
     */
    public function start(int $organizationId, int $creatorId, array $participantIds, ?string $subject): Conversation
    {
        return DB::transaction(function () use ($organizationId, $creatorId, $participantIds, $subject) {
            $conversation = Conversation::create([
                'organization_id' => $organizationId,
                'subject' => $subject,
                'type' => count($participantIds) > 1 ? 'group' : 'direct',
                'created_by' => $creatorId,
            ]);

            foreach (array_unique([$creatorId, ...$participantIds]) as $participantId) {
                $conversation->participants()->attach($participantId, ['joined_at' => now()]);
            }

            return $conversation->load('participants:id,name');
        });
    }

    /**
     * The conversation's messages, newest first.
     */
    public function paginateMessages(Conversation $conversation, int $perPage): LengthAwarePaginator
    {
        return ConversationMessage::with('sender:id,name')
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Post a message and move the conversation's last activity to now.
     */
    public function send(Conversation $conversation, int $senderId, array $data): ConversationMessage
    {
        return DB::transaction(function () use ($conversation, $senderId, $data) {
            $message = ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $senderId,
                'content' => $data['content'],
                'type' => $data['type'] ?? 'text',
                'file_url' => $data['file_url'] ?? null,
                'file_name' => $data['file_name'] ?? null,
                'file_size' => $data['file_size'] ?? null,
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $message->load('sender:id,name');
        });
    }
}
