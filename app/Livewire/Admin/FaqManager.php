<?php

namespace App\Livewire\Admin;

use App\Models\FAQ;
use Livewire\Component;
use App\Services\FaqService;
use Livewire\WithPagination;

class FaqManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showCreateForm = false;
    public $newQuestion = '';
    public $newAnswer = '';
    public $newKeywords = '';

    protected $rules = [
        'newQuestion' => 'required|min:10|max:500',
        'newAnswer' => 'required|min:20',
        'newKeywords' => 'nullable|string'
    ];

    public function render()
    {
        $faqs = FAQ::when($this->search, function($query) {
            $query->where('question', 'like', '%' . $this->search . '%')
                  ->orWhere('answer', 'like', '%' . $this->search . '%');
        })
        ->orderBy('view_count', 'desc')
        ->paginate(10);

        $stats = app(FaqService::class)->getStats();

        return view('livewire.admin.faq-manager', [
            'faqs' => $faqs,
            'stats' => $stats
        ]);
    }

    public function createFaq()
    {
        $this->validate();

        $keywords = empty($this->newKeywords) 
            ? [] 
            : array_map('trim', explode(',', $this->newKeywords));

        app(FaqService::class)->createManualFaq(
            $this->newQuestion,
            $this->newAnswer,
            $keywords
        );

        $this->reset(['newQuestion', 'newAnswer', 'newKeywords', 'showCreateForm']);
        session()->flash('message', 'FAQ created successfully!');
    }

    public function toggleActive($faqId)
    {
        $faq = FAQ::findOrFail($faqId);
        $faq->update(['is_active' => !$faq->is_active]);
    }

    public function deleteFaq($faqId)
    {
        FAQ::findOrFail($faqId)->delete();
        session()->flash('message', 'FAQ deleted successfully!');
    }
}