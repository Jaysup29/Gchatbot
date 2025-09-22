<?php
// 4. app/Models/FAQ.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FAQ extends Model
{
    protected $table = 'faqs';
    
    protected $fillable = [
        'question',
        'answer',
        'keywords',
        'is_active',
        'view_count',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'view_count' => 'integer',
    ];

    // Scopes for common queries
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->orderBy('view_count', 'desc')->limit($limit);
    }

    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('question', 'LIKE', "%{$searchTerm}%")
              ->orWhere('answer', 'LIKE', "%{$searchTerm}%")
              ->orWhereRaw('MATCH(question, answer) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
        });
    }

    // Find matching FAQs based on user input
    public static function findMatching($userInput, $limit = 5)
    {
        return static::active()
            ->search($userInput)
            ->popular()
            ->limit($limit)
            ->get();
    }

    // Increment view count when FAQ is accessed
    public function incrementView()
    {
        $this->increment('view_count');
    }

    // Check if keywords match user input
    public function matchesKeywords($userInput): bool
    {
        if (!$this->keywords) {
            return false;
        }

        $input = strtolower($userInput);
        foreach ($this->keywords as $keyword) {
            if (str_contains($input, strtolower($keyword))) {
                return true;
            }
        }
        
        return false;
    }

    // Get formatted FAQ for display
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'views' => $this->view_count,
            'keywords' => $this->keywords,
        ];
    }
}