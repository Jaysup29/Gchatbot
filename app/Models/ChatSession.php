<?php
// 1. app/Models/ChatSession.php

namespace App\Models;

use App\Models\Ticket;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $fillable = [
        'session_id',
        'gtix_user_id',
        'gtix_employee_id',
        'gtix_username',
        'gtix_department',
        'ip_address',
        'user_agent',
        'is_authenticated',
        'started_at',
        'last_activity_at',
    ];

    protected $casts = [
        'is_authenticated' => 'boolean',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'session_id');
    }

    // Helper method to check if user can create tickets
    public function canCreateTickets(): bool
    {
        return $this->is_authenticated && !empty($this->gtix_user_id);
    }

    // Get latest messages for this session
    public function latestMessages($limit = 10)
    {
        return $this->messages()
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }

    // Update last activity timestamp
    public function updateActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }

    // Check if session is still active (within last 24 hours)
    public function isActive(): bool
    {
        return $this->last_activity_at->greaterThan(now()->subDay());
    }
}