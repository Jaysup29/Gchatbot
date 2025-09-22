<?php
// 5. app/Models/Prompt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    protected $fillable = [
        'trigger_phrase',
        'prompt_content',
        'prompt_type',
        'priority',
        'is_active',
        'metadata',
        'usage_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'usage_count' => 'integer',
    ];

    // Scopes for common queries
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('prompt_type', $type);
    }

    public function scopeSystem($query)
    {
        return $query->where('prompt_type', 'system');
    }

    public function scopeResponse($query)
    {
        return $query->where('prompt_type', 'response');
    }

    public function scopeInstruction($query)
    {
        return $query->where('prompt_type', 'instruction');
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    // Method to find matching prompts based on user input
    public static function findMatchingPrompts($userInput, $limit = 5)
    {
        return static::active()
            ->where(function ($query) use ($userInput) {
                $query->where('trigger_phrase', 'LIKE', "%{$userInput}%")
                      ->orWhereRaw('MATCH(trigger_phrase, prompt_content) AGAINST(? IN BOOLEAN MODE)', [$userInput]);
            })
            ->byPriority()
            ->limit($limit)
            ->get();
    }

    // Find system prompts for AI context
    public static function getSystemPrompts()
    {
        return static::active()
            ->system()
            ->byPriority()
            ->get();
    }

    // Find direct response prompts
    public static function findDirectResponse($userInput)
    {
        return static::active()
            ->response()
            ->where(function ($query) use ($userInput) {
                $keywords = explode(',', strtolower($userInput));
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    $query->orWhere('trigger_phrase', 'LIKE', "%{$keyword}%");
                }
            })
            ->byPriority()
            ->first();
    }

    // Increment usage count when prompt is used
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    // Check if prompt matches user input
    public function matches($userInput): bool
    {
        $input = strtolower($userInput);
        $triggers = explode(',', strtolower($this->trigger_phrase));
        
        foreach ($triggers as $trigger) {
            $trigger = trim($trigger);
            if (str_contains($input, $trigger)) {
                return true;
            }
        }
        
        return false;
    }

    // Get formatted prompt for display
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'trigger' => $this->trigger_phrase,
            'content' => $this->prompt_content,
            'type' => $this->prompt_type,
            'priority' => $this->priority,
            'usage' => $this->usage_count,
            'active' => $this->is_active,
        ];
    }
}