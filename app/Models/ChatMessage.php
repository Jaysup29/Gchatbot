<?php
// 2. app/Models/ChatMessage.php

namespace App\Models;

use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'session_id',
        'sender_type',
        'message_content',
        'metadata',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    // Scopes for filtering messages
    public function scopeUserMessages($query)
    {
        return $query->where('sender_type', 'user');
    }

    public function scopeAssistantMessages($query)
    {
        return $query->where('sender_type', 'assistant');
    }

    public function scopeSystemMessages($query)
    {
        return $query->where('sender_type', 'system');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sent_at', [$startDate, $endDate]);
    }

    // NEW SCOPES for soft delete management
    public function scopeWithTrashed($query)
    {
        return $query->withTrashed();
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->onlyTrashed();
    }

    // Check if message is from user
    public function isFromUser(): bool
    {
        return $this->sender_type === 'user';
    }

    // Check if message is from assistant
    public function isFromAssistant(): bool
    {
        return $this->sender_type === 'assistant';
    }

    // Get formatted message for display
    public function getFormattedMessage(): array
    {
        return [
            'user' => $this->sender_type === 'user' ? 'You' : 'GAB',
            'text' => $this->message_content,
            'time' => $this->sent_at->format('H:i'),
            'metadata' => $this->metadata,
        ];
    }

    // NEW METHODS for soft delete management
    
    // Restore a soft-deleted message
    public function restoreMessage()
    {
        return $this->restore();
    }

    // Permanently delete a message
    public function permanentlyDelete()
    {
        return $this->forceDelete();
    }

    // Check if message is soft deleted
    public function isSoftDeleted(): bool
    {
        return $this->trashed();
    }
}