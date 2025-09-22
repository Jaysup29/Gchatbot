<?php
// 1. Create app/Services/ChatSessionService.php
namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;

class ChatSessionService
{
    public function initializeSession(): ChatSession
    {
        $sessionId = session()->getId();
        
        return ChatSession::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'session_id' => $sessionId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'is_authenticated' => false,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]
        );
    }

    public function loadHistory(int $sessionId): array
    {
        $dbMessages = ChatMessage::where('session_id', $sessionId)
            ->orderBy('sent_at', 'asc')
            ->get();

        return $dbMessages->map(function($msg) {
            return [
                'user' => $msg->sender_type === 'user' ? 'You' : 'GAB',
                'text' => $msg->message_content,
                'time' => $msg->sent_at->format('H:i'),
            ];
        })->toArray();
    }

    public function saveMessage(int $sessionId, string $senderType, string $content, ?array $metadata = null): ChatMessage
    {
        return ChatMessage::create([
            'session_id' => $sessionId,
            'sender_type' => $senderType,
            'message_content' => $content,
            'metadata' => $metadata,
            'sent_at' => now(),
        ]);
    }

    public function updateActivity(int $sessionId): void
    {
        ChatSession::find($sessionId)->update([
            'last_activity_at' => now()
        ]);
    }

    public function clearMessages(int $sessionId): void
    {
        ChatMessage::where('session_id', $sessionId)->delete();
        $this->saveMessage($sessionId, 'assistant', 'Chat history cleared.', [
            'source' => 'system',
            'action' => 'chat_cleared'
        ]);
    }

    public function restoreMessages(int $sessionId): void
    {
        ChatMessage::where('session_id', $sessionId)
            ->onlyTrashed()
            ->restore();
    }

    public function permanentlyDeleteMessages(int $sessionId): void
    {
        ChatMessage::where('session_id', $sessionId)
            ->withTrashed()
            ->forceDelete();
    }

    public function getSoftDeletedCount(int $sessionId): int
    {
        return ChatMessage::where('session_id', $sessionId)
            ->onlyTrashed()
            ->count();
    }

    public function cleanupOldDeletedMessages(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        
        return ChatMessage::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->forceDelete();
    }
}