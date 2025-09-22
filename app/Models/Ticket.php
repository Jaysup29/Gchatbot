<?php
// 3. app/Models/Ticket.php

namespace App\Models;

use App\Models\ChatSession;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'control_no',
        'session_id',
        'gtix_user_id',
        'gtix_employee_id',
        'gtix_username',
        'title',
        'description',
        'priority',
        'category',
        'requester_email',
        'requester_phone',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    // Automatically generate gate pass number when creating
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($ticket) {
            $ticket->gate_pass_number = self::generateGatePassNumber();
        });
    }

    // Generate unique gate pass number
    public static function generateGatePassNumber(): string
    {
        do {
            $number = 'GP-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('gate_pass_number', $number)->exists());
        
        return $number;
    }

    // Scopes for filtering tickets
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByGtixUser($query, $gtixUserId)
    {
        return $query->where('gtix_user_id', $gtixUserId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Get priority color for UI
    public function getPriorityColor(): string
    {
        return match($this->priority) {
            'urgent' => 'red',
            'high' => 'orange',
            'normal' => 'yellow',
            'low' => 'green',
            default => 'gray'
        };
    }

    // Get category icon for UI
    public function getCategoryIcon(): string
    {
        return match($this->category) {
            'technical' => '🔧',
            'billing' => '💳',
            'general' => '❓',
            'feature_request' => '💡',
            default => '📝'
        };
    }

    // Format for display
    public function toDisplayArray(): array
    {
        return [
            'gate_pass' => $this->gate_pass_number,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'category' => $this->category,
            'priority_color' => $this->getPriorityColor(),
            'category_icon' => $this->getCategoryIcon(),
            'created' => $this->created_at->format('M d, Y H:i'),
            'requester' => $this->gtix_username,
        ];
    }
}