<?php

use Livewire\Volt\Component;

new class extends Component {
    public $title = '';
    public $description = '';
    public $priority = '';
    public $category = '';
    public $isSubmitting = false;

    protected $rules = [
        'title' => 'required|string|max:255',
        'description' => 'required|string|min:10',
        'priority' => 'required|in:low,normal,high,urgent',
        'category' => 'required|in:technical,billing,general,feature_request',
    ];

    protected $messages = [
        'title.required' => 'Please provide a title for your ticket.',
        'title.max' => 'Title must be less than 255 characters.',
        'description.required' => 'Please describe your issue or request.',
        'description.min' => 'Description must be at least 10 characters.',
        'priority.required' => 'Please select a priority level.',
        'category.required' => 'Please select a category.',
    ];

    public function submit()
    {
        $this->validate();
        $this->isSubmitting = true;

        try {
            // Simulate API call delay
            sleep(1);

            // Save ticket in your ticketing system
            // \App\Models\Ticket::create([
            //     'title' => $this->title,
            //     'description' => $this->description,
            //     'priority' => $this->priority,
            //     'category' => $this->category,
            //     'user_id' => auth()->id(), // if using auth
            // ]);
            
            // Reset form
            $this->reset(['title', 'description', 'priority', 'category', 'isSubmitting']);

            // Notify chatbot of successful submission
            $this->dispatch('ticketCreated', [
                'message' => 'Ticket created successfully! Our team will review it and get back to you soon.'
            ]);

        } catch (\Exception $e) {
            $this->isSubmitting = false;
            session()->flash('error', 'Failed to create ticket. Please try again.');
        }
    }

    public function cancel()
    {
        $this->reset(['title', 'description', 'priority', 'category']);
        $this->dispatch('ticketCancelled');
    }
}; ?>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden transition-colors duration-200">
    <!-- Form Header -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 px-4 md:px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg md:text-xl font-semibold text-white">Create Support Ticket</h1>
                    <p class="text-blue-100 text-xs md:text-sm">We'll help you resolve your issue quickly</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Body -->
    <form wire:submit.prevent="submit" class="p-4 md:p-6 space-y-4 md:space-y-6">
        @if (session()->has('error'))
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-red-700 dark:text-red-400 text-sm">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Category Selection -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                Category <span class="text-red-500">*</span>
            </label>
            <select wire:model.live="category" 
                    class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg 
                           text-gray-900 dark:text-gray-100 text-sm md:text-base
                           focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                           {{ $isSubmitting ? 'opacity-50 cursor-not-allowed' : 'hover:border-gray-400 dark:hover:border-gray-500' }}"
                    {{ $isSubmitting ? 'disabled' : '' }}>
                <option value="">Select a category</option>
                <option value="technical">🔧 Technical Support</option>
                <option value="general">❓ General Inquiry</option>
                <option value="feature_request">💡 Feature Request</option>
            </select>
            @error('category') 
                <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> 
            @enderror
        </div>

        <!-- Priority Selection -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                Priority <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach(['low' => '🟢 Low', 'normal' => '🟡 Normal', 'high' => '🟠 High', 'urgent' => '🔴 Urgent'] as $value => $label)
                    <label class="relative cursor-pointer {{ $isSubmitting ? 'cursor-not-allowed opacity-50' : '' }}">
                        <input type="radio" wire:model.live="priority" value="{{ $value }}" 
                               class="sr-only peer" {{ $isSubmitting ? 'disabled' : '' }}>
                        <div class="p-3 border border-gray-300 dark:border-gray-600 rounded-lg text-center text-xs md:text-sm
                                   peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/30
                                   hover:border-gray-400 dark:hover:border-gray-500 transition-colors
                                   bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            {{ $label }}
                        </div>
                    </label>
                @endforeach
            </div>
            @error('priority') 
                <p class="text-red-500 dark:text-red-400 text-xs mt-1">{{ $message }}</p> 
            @enderror
        </div>

        <!-- Title Input -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                Ticket Title <span class="text-red-500">*</span>
            </label>
            <input type="text" wire:model.live="title"
                   placeholder="Brief description of your issue"
                   maxlength="255"
                   class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg 
                          text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 text-sm md:text-base
                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                          {{ $isSubmitting ? 'opacity-50 cursor-not-allowed' : 'hover:border-gray-400 dark:hover:border-gray-500' }}"
                   {{ $isSubmitting ? 'disabled' : '' }}>
            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                @error('title') 
                    <span class="text-red-500 dark:text-red-400">{{ $message }}</span> 
                @else
                    <span>Clear and specific titles help us assist you faster</span>
                @enderror
                <span>{{ strlen($title ?? '') }}/255</span>
            </div>
        </div>

        <!-- Description Textarea -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-900 dark:text-gray-100">
                Description <span class="text-red-500">*</span>
            </label>
            <textarea wire:model.live="description" 
                      rows="4" 
                      placeholder="Please provide detailed information about your issue, including any error messages, steps to reproduce, or specific requirements..."
                      class="w-full px-3 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg 
                             text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 text-sm md:text-base
                             focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none
                             {{ $isSubmitting ? 'opacity-50 cursor-not-allowed' : 'hover:border-gray-400 dark:hover:border-gray-500' }}"
                      {{ $isSubmitting ? 'disabled' : '' }}></textarea>
            @error('description') 
                <p class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</p> 
            @else
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    The more details you provide, the better we can help you
                    <span class="float-right">{{ strlen($description ?? '') }} characters</span>
                </p>
            @enderror
        </div>

        <!-- Form Actions -->
        <div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
            <button type="button" wire:click="cancel"
                    class="flex-1 sm:flex-none px-4 py-2.5 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 
                           border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 
                           transition-colors text-sm md:text-base font-medium
                           {{ $isSubmitting ? 'opacity-50 cursor-not-allowed' : '' }}"
                    {{ $isSubmitting ? 'disabled' : '' }}>
                Cancel
            </button>
            <button type="submit"
                    class="flex-1 sm:flex-none px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg 
                           transition-all transform active:scale-98 font-medium text-sm md:text-base
                           disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none
                           flex items-center justify-center space-x-2"
                    {{ $isSubmitting || !$title || !$description || !$priority || !$category ? 'disabled' : '' }}>
                @if($isSubmitting)
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Creating Ticket...</span>
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    <span>Create Ticket</span>
                @endif
            </button>
        </div>
    </form>
</div>