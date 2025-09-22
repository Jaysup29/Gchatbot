<?php

use Livewire\Volt\Component;
use App\Services\ChatSessionService;
use App\Services\PromptService;
use App\Services\FaqService;
use App\Services\OpenAIService;

new #[Layout('layouts.app')] class extends Component {
    // State Properties
    public array $messages = [];
    public string $newMessage = '';
    public bool $isTyping = false;
    public bool $isSubmitted = false;
    public bool $isListening = false;
    public ?int $chatSessionId = null;

    // Services
    private ChatSessionService $sessionService;
    private PromptService $promptService;
    private FaqService $faqService;
    private OpenAIService $openAiService;

    // Validation
    protected $rules = [
        'newMessage' => 'required|string|min:1',
    ];

    public function boot()
    {
        $this->sessionService = app(ChatSessionService::class);
        $this->promptService = app(PromptService::class);
        $this->faqService = app(FaqService::class);
        $this->openAiService = app(OpenAIService::class);
    }

    public function mount()
    {
        $this->messages = session('chat_messages', []);
        $this->initializeSession();
    }

    // Session Management
    private function initializeSession(): void
    {
        $chatSession = $this->sessionService->initializeSession();
        $this->chatSessionId = $chatSession->id;
        $this->loadChatHistory();
    }

    private function loadChatHistory(): void
    {
        if (!$this->chatSessionId) return;
        
        $historyMessages = $this->sessionService->loadHistory($this->chatSessionId);
        
        if (count($historyMessages) > 0) {
            $this->messages = $historyMessages;
        }
    }

    // Message Handling
    public function send()
    {
        $this->validate();
        if (trim($this->newMessage) === '') return;

        $this->isSubmitted = true;
        $userMessage = $this->newMessage;

        $this->addMessageToUI('You', $userMessage);
        $this->reset('newMessage');
        session(['chat_messages' => $this->messages]);

        $this->dispatch('process-ai-response', userMessage: $userMessage);
    }

    #[\Livewire\Attributes\On('process-ai-response')]
    public function processAiResponse($userMessage): void
    {
        $this->sessionService->saveMessage($this->chatSessionId, 'user', $userMessage);
        $this->faqService->trackAndCreateFaq($userMessage);

        // Check prompts first
        $promptResponse = $this->promptService->findMatchingPrompt($userMessage);
        if ($promptResponse) {
            $this->handleInstantResponse($promptResponse['content'], 'prompt', $promptResponse['id']);
            return;
        }

        // Check FAQs second
        $faqResponse = $this->faqService->findMatchingFaq($userMessage);
        if ($faqResponse) {
            $this->handleInstantResponse($faqResponse['answer'], 'faq', $faqResponse['id']);
            return;
        }

        // Fall back to AI API
        $this->callAiApi();
    }

    private function handleInstantResponse(string $response, string $source, int $sourceId = null): void
    {
        $this->addMessageToUI('GAB', $response);
        
        $metadata = ['source' => $source];
        if ($sourceId) {
            $metadata[$source . '_id'] = $sourceId;
        }

        $this->sessionService->saveMessage($this->chatSessionId, 'assistant', $response, $metadata);
        $this->sessionService->updateActivity($this->chatSessionId);
        $this->finalizeBotResponse();
    }

    private function callAiApi(): void
    {
        $this->showTypingIndicator();
        $typingIndex = array_key_last($this->messages);

        $prompts = $this->openAiService->buildConversationPrompts($this->messages);
        $response = $this->openAiService->generateResponse($prompts);

        $this->sessionService->saveMessage($this->chatSessionId, 'assistant', $response['content'], [
            'model' => 'gpt-4o-mini',
            'tokens' => $response['tokens'],
            'source' => $response['error'] ? 'error' : 'openai'
        ]);

        $this->replaceTypingWithResponse($typingIndex, $response['content']);
        $this->sessionService->updateActivity($this->chatSessionId);
        $this->finalizeBotResponse();
    }

    // UI Helper Methods
    private function addMessageToUI(string $sender, string $content): void
    {
        $this->messages[] = [
            'user' => $sender,
            'text' => $content,
            'time' => now()->format('H:i'),
        ];
    }

    private function showTypingIndicator(): void
    {
        $this->messages[] = [
            'user' => 'GAB',
            'text' => 'GAB is thinking… ❄️',
            'time' => now()->format('H:i'),
            'typing' => true,
        ];
        $this->isTyping = true;
        $this->dispatch('scroll-to-bottom');
    }

    private function replaceTypingWithResponse(int $index, string $content): void
    {
        $this->messages[$index] = [
            'user' => 'GAB',
            'text' => $content,
            'time' => now()->format('H:i'),
        ];
    }

    private function finalizeBotResponse(): void
    {
        $this->isTyping = false;
        $this->isSubmitted = false;
        $this->dispatch('scroll-to-bottom');
    }

    // Chat Management
    public function clearChat()
    {
        if ($this->chatSessionId) {
            $this->sessionService->clearMessages($this->chatSessionId);
        }
        
        $this->messages = [];
        session()->forget('chat_messages');
        $this->dispatch('chat-cleared');
    }

    public function restoreChat()
    {
        if ($this->chatSessionId) {
            $this->sessionService->restoreMessages($this->chatSessionId);
            $this->loadChatHistory();
            $this->dispatch('chat-restored');
        }
    }

    public function permanentlyDeleteChat()
    {
        if ($this->chatSessionId) {
            $this->sessionService->permanentlyDeleteMessages($this->chatSessionId);
            $this->messages = [];
            $this->dispatch('chat-permanently-deleted');
        }
    }

    public function hasMessagesToRestore(): bool
    {
        return $this->chatSessionId ? $this->sessionService->getSoftDeletedCount($this->chatSessionId) > 0 : false;
    }

    public function cleanupOldDeletedMessages($daysOld = 30)
    {
        return $this->sessionService->cleanupOldDeletedMessages($daysOld);
    }

    // Voice Recognition
    public function toggleVoice()
    {
        $this->isListening = !$this->isListening;
        $this->dispatch('toggle-voice-recognition', listening: $this->isListening);
    }

    #[\Livewire\Attributes\On('voice-result')]
    public function handleVoiceResult($transcript)
    {
        $this->newMessage = $transcript;
        $this->isListening = false;
    }

    #[\Livewire\Attributes\On('voice-error')]
    public function handleVoiceError()
    {
        $this->isListening = false;
    }
}; ?>

<div class="h-screen max-h-screen overflow-hidden flex flex-col bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors duration-200">
    
    <!-- Enhanced Header - Fully Responsive -->
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="max-w-6xl mx-auto px-3 sm:px-4 lg:px-6 py-3 sm:py-4">
            <div class="flex items-center justify-between">
                <!-- Left Section - Bot Info -->
                <div class="flex items-center space-x-2 sm:space-x-3 min-w-0 flex-1">
                    <!-- Bot Avatar -->
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 rounded-full flex items-center justify-center shadow-lg flex-shrink-0">
                        <span class="text-sm sm:text-base text-white">❄️</span>
                    </div>
                    
                    <!-- Bot Name & Status -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center space-x-2">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm sm:text-base truncate">GAB</h3>
                            <!-- Mobile-friendly status indicator -->
                            <div class="flex items-center">
                                @if($isSubmitted)
                                    <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>
                                @elseif($isListening)
                                    <span class="w-1.5 h-1.5 bg-red-400 rounded-full animate-pulse"></span>
                                @else
                                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full"></span>
                                @endif
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            @if($isSubmitted)
                                Thinking...
                            @elseif($isListening)
                                Listening...
                            @else
                                <span class="hidden sm:inline">Glacier Megafridge Assistant</span>
                                <span class="sm:hidden">Ready to help</span>
                            @endif
                        </p>
                    </div>
                </div>
                
                <!-- Right Section - Actions -->
                <div class="flex items-center space-x-1 sm:space-x-2 flex-shrink-0">
                    <!-- Dark Mode Toggle -->
                    <button 
                        onclick="toggleDarkMode()"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        title="Toggle theme"
                        id="theme-toggle"
                    >
                        <svg id="sun-icon" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-600 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg id="moon-icon" class="w-4 h-4 sm:w-5 sm:h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>

                    <!-- Clear Chat Button with Restore Option -->
                    @if(count($this->messages) > 0 || $this->hasMessagesToRestore())
                        <div class="flex items-center space-x-2">
                            @if($this->hasMessagesToRestore())
                                <!-- Restore Button -->
                                <button 
                                    wire:click="restoreChat" 
                                    class="text-xs sm:text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 px-2 sm:px-3 py-1.5 rounded-lg border border-blue-300 dark:border-blue-600 hover:border-blue-400 dark:hover:border-blue-500 transition-colors"
                                    title="Restore cleared messages"
                                >
                                    <span class="hidden sm:inline">Restore</span>
                                    <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            @endif
                            
                            @if(count($this->messages) > 0)
                                <!-- Clear Button -->
                                <button 
                                    wire:click="clearChat" 
                                    class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 px-2 sm:px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 transition-colors"
                                    wire:confirm="Clear chat history? (You can restore it later)"
                                    title="Clear chat"
                                >
                                    <span class="hidden sm:inline">Clear</span>
                                    <svg class="w-4 h-4 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Container - Responsive -->
    <div class="flex-1 overflow-y-auto overflow-x-hidden" id="chat-container">
        <div class="max-w-4xl mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6 lg:py-8">
            @forelse ($this->messages as $message)
                <div class="mb-4 sm:mb-6 {{ $message['user'] === 'You' ? 'flex justify-end' : 'flex justify-start' }}" >
                    <div class="max-w-[85%] sm:max-w-[80%] md:max-w-2xl w-full">
                        
                        <!-- Message bubble - Enhanced for mobile -->
                        <div class="{{ $message['user'] === 'You' 
                                ? 'bg-blue-500 hover:bg-blue-600 text-white ml-auto shadow-lg' 
                                : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 shadow-sm' }}
                            px-3 sm:px-4 py-2 sm:py-3 rounded-2xl break-words transition-all duration-200 {{ $message['user'] === 'You' ? 'rounded-br-md' : 'rounded-bl-md' }}">

                            {{-- Typing indicator --}}
                            @if(isset($message['typing']) && $message['typing'])
                                <div class="flex items-center space-x-3">
                                    <div class="flex space-x-1">
                                        <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce"></span>
                                        <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce [animation-delay:-.2s]"></span>
                                        <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce [animation-delay:-.4s]"></span>
                                    </div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $message['text'] }}</span>
                                </div>
                            @else
                                {{-- Special case: AI requests ticket form --}}
                                @if($message['text'] === '[SHOW_TICKET_FORM]')
                                    <div class="leading-relaxed">
                                        <livewire:forms.ticket />
                                    </div>
                                @else
                                    {{-- Regular message --}}
                                    <div class="leading-relaxed text-sm sm:text-base break-words">
                                        {!! nl2br(e(preg_replace('/^- /m', '• ', $message['text']))) !!}
                                    </div>
                                @endif
                            @endif
                        </div>
                        
                        <!-- Timestamp - Mobile optimized -->
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 {{ $message['user'] === 'You' ? 'text-right' : 'text-left' }}">
                            <span class="hidden sm:inline">{{ $message['user'] }} • </span>{{ $message['time'] }}
                        </p>
                    </div>
                </div>
            @empty
                <!-- Empty state - Responsive -->
                <div class="text-center py-12 sm:py-16 lg:py-20 px-4">
                    <div class="text-4xl sm:text-5xl lg:text-6xl mb-4 sm:mb-6 opacity-60">❄️</div>
                    <h2 class="text-lg sm:text-xl lg:text-2xl font-light text-gray-600 dark:text-gray-300 mb-2 sm:mb-3">Ready when you are.</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base max-w-md mx-auto">Ask me anything about Glacier Megafridge Inc.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Enhanced Input Area - Fully Responsive -->
    <div class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 safe-area-padding-bottom">
        <div class="max-w-4xl mx-auto px-3 sm:px-4 lg:px-6 py-3 sm:py-4">
            <div class="flex items-end space-x-2 sm:space-x-3">
                <!-- Add button (hidden on very small screens) -->
                <button class="hidden md:flex w-10 h-10 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-full flex-shrink-0 items-center justify-center transition-colors group">
                    <svg class="w-4 h-4 lg:w-5 lg:h-5 text-gray-500 dark:text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </button>

                <!-- Input field - Enhanced for mobile -->
                <div class="flex-1 relative">
                    <textarea 
                        wire:model.live="newMessage" 
                        wire:keydown.enter.prevent="send"
                        placeholder="{{ $isSubmitted ? 'Please wait...' : 'Ask anything about Glacier...' }}"
                        rows="1"
                        class="w-full px-3 sm:px-4 py-2.5 sm:py-3 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-2xl 
                               text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 text-sm sm:text-base
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent 
                               transition-all resize-none overflow-hidden {{ $isSubmitted ? 'opacity-50 cursor-not-allowed' : 'hover:border-gray-400 dark:hover:border-gray-500' }}"
                        style="min-height: 44px; max-height: 120px;"
                        {{ $isSubmitted ? 'disabled' : '' }}
                        oninput="this.style.height = 'auto'; this.style.height = Math.min(Math.max(this.scrollHeight, 44), 120) + 'px';"
                    ></textarea>
                    
                    @if($newMessage && !$isSubmitted)
                        <button 
                            wire:click="$set('newMessage', '')"
                            class="absolute right-2 sm:right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 w-6 h-6 flex items-center justify-center transition-colors rounded-full hover:bg-gray-100 dark:hover:bg-gray-600"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    @endif
                </div>

                <!-- Action buttons - Mobile optimized -->
                <div class="flex space-x-1.5 sm:space-x-2 flex-shrink-0">
                    <!-- Microphone button -->
                    <button 
                        wire:click="toggleVoice"
                        class="w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center transition-all transform active:scale-95 flex-shrink-0
                               {{ $isListening ? 'bg-red-500 hover:bg-red-600 text-white animate-pulse shadow-lg' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                        {{ $isSubmitted ? 'disabled' : '' }}
                        title="{{ $isListening ? 'Stop listening' : 'Voice input' }}"
                    >
                        @if($isListening)
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 6h12v12H6z"/>
                            </svg>
                        @else
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500 dark:text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                            </svg>
                        @endif
                    </button>
                    
                    <!-- Send button - Fixed condition -->
                    <button 
                        wire:click="send"
                        class="w-10 h-10 sm:w-11 sm:h-11 rounded-full flex items-center justify-center transition-all transform active:scale-95 flex-shrink-0
                               {{ $newMessage && !$isSubmitted 
                                  ? 'bg-blue-500 hover:bg-blue-600 text-white shadow-lg hover:shadow-xl' 
                                  : 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed' }}"
                        {{ !$newMessage || $isSubmitted ? 'disabled' : '' }}
                    >
                        @if($isSubmitted)
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        @else
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced JavaScript with Mobile Support - Refactored -->
    <script>
        // ============================================================================
        // CONFIGURATION & STATE MANAGEMENT
        // ============================================================================
        
        const ChatConfig = {
            SPEECH_RECOGNITION: {
                TIMEOUT: 15000,
                MAX_RETRIES: 3,
                LANGUAGE: 'en-US'
            },
            NOTIFICATIONS: {
                DURATION: 4000,
                COLORS: {
                    success: 'bg-green-500',
                    info: 'bg-blue-500',
                    warning: 'bg-orange-500',
                    error: 'bg-red-500'
                }
            }
        };

        class ChatState {
            constructor() {
                this.recognition = null;
                this.isRecognitionSupported = false;
                this.recognitionTimeout = null;
                this.retryCount = 0;
                this.livewireComponent = null;
                this.initialViewportHeight = window.innerHeight;
                this.keyboardVisible = false;
            }

            setLivewireComponent(component) {
                this.livewireComponent = component;
            }

            resetRetryCount() {
                this.retryCount = 0;
            }

            incrementRetryCount() {
                this.retryCount++;
            }
        }

        const chatState = new ChatState();

        // ============================================================================
        // DARK MODE MANAGEMENT
        // ============================================================================
        
        class DarkModeManager {
            static initialize() {
                const darkMode = localStorage.getItem('darkMode') === 'true' || 
                            (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                
                document.documentElement.classList.toggle('dark', darkMode);
                this.updateThemeIcons(darkMode);
            }

            static toggle() {
                const isDark = document.documentElement.classList.contains('dark');
                const newDarkMode = !isDark;
                
                document.documentElement.classList.toggle('dark', newDarkMode);
                localStorage.setItem('darkMode', newDarkMode);
                this.updateThemeIcons(newDarkMode);
            }

            static updateThemeIcons(isDark) {
                const sunIcon = document.getElementById('sun-icon');
                const moonIcon = document.getElementById('moon-icon');
                
                if (sunIcon && moonIcon) {
                    if (isDark) {
                        sunIcon.classList.remove('hidden');
                        moonIcon.classList.add('hidden');
                    } else {
                        sunIcon.classList.add('hidden');
                        moonIcon.classList.remove('hidden');
                    }
                }
            }
        }

        // ============================================================================
        // SPEECH RECOGNITION MANAGEMENT
        // ============================================================================
        
        class SpeechRecognitionManager {
            static initialize() {
                if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                    chatState.isRecognitionSupported = true;
                    this.setupRecognition();
                } else {
                    console.warn('Speech recognition not supported in this browser');
                }
            }

            static setupRecognition() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                chatState.recognition = new SpeechRecognition();
                
                this.configureRecognition();
                this.attachRecognitionEvents();
            }

            static configureRecognition() {
                const recognition = chatState.recognition;
                recognition.continuous = false;
                recognition.interimResults = false;
                recognition.lang = ChatConfig.SPEECH_RECOGNITION.LANGUAGE;
                recognition.maxAlternatives = 1;
                recognition.timeout = ChatConfig.SPEECH_RECOGNITION.TIMEOUT;
                recognition.maxSpeechInputTime = ChatConfig.SPEECH_RECOGNITION.TIMEOUT;
            }

            static attachRecognitionEvents() {
                const recognition = chatState.recognition;
                
                recognition.onresult = this.handleResult.bind(this);
                recognition.onstart = this.handleStart.bind(this);
                recognition.onerror = this.handleError.bind(this);
                recognition.onend = this.handleEnd.bind(this);
            }

            static handleResult(event) {
                clearTimeout(chatState.recognitionTimeout);
                let transcript = '';
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        transcript = event.results[i][0].transcript.trim();
                        if (transcript.length > 0) {
                            if (chatState.livewireComponent) {
                                chatState.livewireComponent.call('handleVoiceResult', transcript);
                            }
                            chatState.resetRetryCount();
                            return;
                        }
                    }
                }
                
                if (!transcript && chatState.livewireComponent) {
                    chatState.livewireComponent.call('handleVoiceError');
                }
            }

            static handleStart() {
                console.log('Speech recognition started');
                chatState.resetRetryCount();
                
                chatState.recognitionTimeout = setTimeout(() => {
                    try {
                        chatState.recognition.stop();
                        if (chatState.livewireComponent) {
                            chatState.livewireComponent.call('handleVoiceError');
                        }
                    } catch (error) {
                        console.error('Timeout error:', error);
                    }
                }, ChatConfig.SPEECH_RECOGNITION.TIMEOUT);
            }

            static handleError(event) {
                clearTimeout(chatState.recognitionTimeout);
                console.error('Speech recognition error:', event.error);
                
                const errorInfo = this.getErrorInfo(event.error);
                NotificationManager.showError(errorInfo.message);
                
                if (errorInfo.shouldRetry && chatState.retryCount < ChatConfig.SPEECH_RECOGNITION.MAX_RETRIES) {
                    this.retryRecognition();
                } else if (chatState.livewireComponent) {
                    chatState.livewireComponent.call('handleVoiceError');
                }
            }

            static handleEnd() {
                clearTimeout(chatState.recognitionTimeout);
                console.log('Speech recognition ended');
                if (chatState.livewireComponent) {
                    chatState.livewireComponent.call('send');
                    if (chatState.livewireComponent.isListening) {
                        chatState.livewireComponent.call('handleVoiceError');
                    }
                }
            }

            static getErrorInfo(errorType) {
                const errorMap = {
                    'network': {
                        message: 'Network error. Checking connection...',
                        shouldRetry: true
                    },
                    'no-speech': {
                        message: 'No speech detected. Please try again.',
                        shouldRetry: chatState.retryCount < 2
                    },
                    'audio-capture': {
                        message: 'Microphone not available. Please check your microphone.',
                        shouldRetry: false
                    },
                    'not-allowed': {
                        message: 'Microphone permission denied. Please enable microphone access.',
                        shouldRetry: false
                    },
                    'service-not-allowed': {
                        message: 'Speech service not available. Please check your internet connection.',
                        shouldRetry: true
                    }
                };

                return errorMap[errorType] || {
                    message: `Voice recognition error (${errorType}). Please try again.`,
                    shouldRetry: true
                };
            }

            static retryRecognition() {
                chatState.incrementRetryCount();
                setTimeout(() => {
                    if (chatState.livewireComponent && chatState.livewireComponent.isListening) {
                        try {
                            chatState.recognition.start();
                        } catch (error) {
                            console.error('Retry failed:', error);
                            chatState.livewireComponent.call('handleVoiceError');
                        }
                    }
                }, 1000 * chatState.retryCount);
            }

            static start() {
                if (!chatState.isRecognitionSupported) {
                    NotificationManager.showError('Voice recognition is not supported in your browser. Please try Chrome, Edge, or Safari.');
                    return;
                }

                this.checkPermissionsAndStart();
            }

            static checkPermissionsAndStart() {
                if (navigator.permissions) {
                    navigator.permissions.query({name: 'microphone'}).then(result => {
                        if (result.state === 'denied') {
                            NotificationManager.showError('Microphone access denied. Please enable microphone permissions.');
                            if (chatState.livewireComponent) {
                                chatState.livewireComponent.call('handleVoiceError');
                            }
                            return;
                        }
                        this.startRecognition();
                    }).catch(() => {
                        this.startRecognition();
                    });
                } else {
                    this.startRecognition();
                }
            }

            static startRecognition() {
                try {
                    chatState.resetRetryCount();
                    chatState.recognition.start();
                } catch (error) {
                    console.error('Error starting recognition:', error);
                    this.handleStartError(error);
                }
            }

            static handleStartError(error) {
                if (error.message.includes('already started')) {
                    chatState.recognition.stop();
                    setTimeout(() => {
                        try {
                            chatState.recognition.start();
                        } catch (e) {
                            NotificationManager.showError('Unable to start voice recognition. Please try again.');
                            if (chatState.livewireComponent) {
                                chatState.livewireComponent.call('handleVoiceError');
                            }
                        }
                    }, 500);
                } else {
                    NotificationManager.showError('Unable to start voice recognition. Please check your microphone.');
                    if (chatState.livewireComponent) {
                        chatState.livewireComponent.call('handleVoiceError');
                    }
                }
            }

            static stop() {
                try {
                    clearTimeout(chatState.recognitionTimeout);
                    chatState.recognition.stop();
                } catch (error) {
                    console.error('Error stopping recognition:', error);
                }
            }
        }

        // ============================================================================
        // TEXT-TO-SPEECH MANAGEMENT
        // ============================================================================
        
        class TextToSpeechManager {
            static speak(text) {
                if (!('speechSynthesis' in window)) {
                    console.warn("Speech synthesis not supported in this browser.");
                    return;
                }

                speechSynthesis.cancel();
                const sentences = this.splitIntoSentences(text);
                const voices = speechSynthesis.getVoices();

                sentences.forEach(sentence => {
                    const utterance = this.createUtterance(sentence, voices);
                    speechSynthesis.speak(utterance);
                });
            }

            static splitIntoSentences(text) {
                return text.split(/([.!?])/).reduce((acc, cur, i, arr) => {
                    if (/[.!?]/.test(cur) && acc.length) {
                        acc[acc.length - 1] += cur;
                    } else if (cur.trim()) {
                        acc.push(cur.trim());
                    }
                    return acc;
                }, []);
            }

            static createUtterance(sentence, voices) {
                const utterance = new SpeechSynthesisUtterance(sentence);
                const isEnglish = /^[A-Za-z0-9\s.,!?'"-]+$/.test(sentence);
                
                utterance.lang = isEnglish ? "en-US" : "tl-PH";
                utterance.rate = 1;
                utterance.pitch = 1;
                utterance.volume = 1;

                const voice = voices.find(v => v.lang === utterance.lang);
                if (voice) utterance.voice = voice;

                return utterance;
            }
        }

        // ============================================================================
        // NOTIFICATION MANAGEMENT
        // ============================================================================
        
        class NotificationManager {
            static show(message, type = 'info') {
                const toast = this.createToast(message, type);
                document.body.appendChild(toast);
                this.scheduleRemoval(toast);
            }

            static showError(message) {
                this.show(message, 'error');
            }

            static showSuccess(message) {
                this.show(message, 'success');
            }

            static showInfo(message) {
                this.show(message, 'info');
            }

            static showWarning(message) {
                this.show(message, 'warning');
            }

            static createToast(message, type) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 ${ChatConfig.NOTIFICATIONS.COLORS[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 max-w-sm transition-all transform translate-x-0`;
                toast.textContent = message;
                return toast;
            }

            static scheduleRemoval(toast) {
                setTimeout(() => {
                    toast.classList.add('opacity-0', 'translate-x-full');
                    setTimeout(() => toast.remove(), 300);
                }, ChatConfig.NOTIFICATIONS.DURATION);
            }
        }

        // ============================================================================
        // MOBILE OPTIMIZATION MANAGEMENT
        // ============================================================================
        
        class MobileOptimizationManager {
            static initialize() {
                this.handleKeyboardDetection();
                this.setupTextareaOptimizations();
                this.handleSafeAreaInsets();
                this.setupOrientationHandling();
            }

            static handleKeyboardDetection() {
                window.addEventListener('resize', () => {
                    const currentHeight = window.innerHeight;
                    const heightDifference = chatState.initialViewportHeight - currentHeight;
                    
                    if (window.innerWidth <= 768 && heightDifference > 150) {
                        if (!chatState.keyboardVisible) {
                            chatState.keyboardVisible = true;
                            this.scrollToBottomOnKeyboard();
                        }
                    } else {
                        chatState.keyboardVisible = false;
                    }
                });
            }

            static scrollToBottomOnKeyboard() {
                setTimeout(() => {
                    const container = document.getElementById('chat-container');
                    if (container) {
                        container.scrollTo({
                            top: container.scrollHeight,
                            behavior: 'smooth'
                        });
                    }
                }, 300);
            }

            static setupTextareaOptimizations() {
                const textarea = document.querySelector('textarea[wire\\:model\\.live="newMessage"]');
                if (!textarea) return;

                this.setupAutoResize(textarea);
                this.preventIOSZoom(textarea);
            }

            static setupAutoResize(textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    const newHeight = Math.min(Math.max(this.scrollHeight, 44), 120);
                    this.style.height = newHeight + 'px';
                });
            }

            static preventIOSZoom(textarea) {
                if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                    textarea.addEventListener('focus', function() {
                        if (parseInt(window.getComputedStyle(this).fontSize) < 16) {
                            this.style.fontSize = '16px';
                        }
                    });
                    
                    textarea.addEventListener('blur', function() {
                        this.style.fontSize = '';
                    });
                }
            }

            static handleSafeAreaInsets() {
                const root = document.documentElement;
                if ('CSS' in window && CSS.supports('padding-bottom: env(safe-area-inset-bottom)')) {
                    root.style.setProperty('--safe-area-bottom', 'env(safe-area-inset-bottom)');
                } else {
                    root.style.setProperty('--safe-area-bottom', '0px');
                }
            }

            static setupOrientationHandling() {
                window.addEventListener('orientationchange', () => {
                    setTimeout(() => this.handleSafeAreaInsets(), 100);
                });
            }
        }

        // ============================================================================
        // SCROLL MANAGEMENT
        // ============================================================================
        
        class ScrollManager {
            static initialize() {
                this.setupAutoScroll();
                this.setupScrollOptimization();
            }

            static setupAutoScroll() {
                setTimeout(() => {
                    const container = document.getElementById('chat-container');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                }, 100);
            }

            static scrollToBottom() {
                setTimeout(() => {
                    const container = document.getElementById('chat-container');
                    if (container) {
                        container.scrollTo({
                            top: container.scrollHeight,
                            behavior: 'smooth'
                        });
                    }
                }, 100);
            }

            static setupScrollOptimization() {
                let scrollTimeout;
                const chatContainer = document.getElementById('chat-container');
                
                if (chatContainer) {
                    chatContainer.addEventListener('scroll', () => {
                        clearTimeout(scrollTimeout);
                        scrollTimeout = setTimeout(() => {
                            // Handle scroll-based optimizations here if needed
                        }, 100);
                    });
                }
            }
        }

        // ============================================================================
        // KEYBOARD SHORTCUTS MANAGEMENT
        // ============================================================================
        
        class KeyboardManager {
            static initialize() {
                document.addEventListener('keydown', this.handleKeydown.bind(this));
                document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
            }

            static handleKeydown(event) {
                this.handleSpaceForVoice(event);
                this.handleEscapeKey(event);
                this.handleClearChatShortcut(event);
            }

            static handleSpaceForVoice(event) {
                if (event.code === 'Space' && 
                    event.target.tagName !== 'INPUT' && 
                    event.target.tagName !== 'TEXTAREA' && 
                    chatState.livewireComponent && 
                    !chatState.livewireComponent.isSubmitted && 
                    !event.repeat) {
                    
                    event.preventDefault();
                    if (!chatState.livewireComponent.isListening && chatState.isRecognitionSupported) {
                        chatState.livewireComponent.call('toggleVoice');
                    }
                }
            }

            static handleEscapeKey(event) {
                if (event.code === 'Escape') {
                    if (chatState.livewireComponent && chatState.livewireComponent.isListening) {
                        chatState.livewireComponent.call('toggleVoice');
                    } else if (event.target.tagName === 'TEXTAREA') {
                        chatState.livewireComponent.set('newMessage', '');
                    }
                }
            }

            static handleClearChatShortcut(event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 'k' && !event.shiftKey) {
                    event.preventDefault();
                    if (confirm('Clear chat history?') && chatState.livewireComponent) {
                        chatState.livewireComponent.call('clearChat');
                    }
                }
            }

            static handleVisibilityChange() {
                if (document.hidden && chatState.livewireComponent && chatState.livewireComponent.isListening) {
                    chatState.livewireComponent.call('toggleVoice');
                }
            }
        }

        // ============================================================================
        // LIVEWIRE EVENT HANDLERS
        // ============================================================================
        
        class LivewireEventManager {
            static initialize() {
                this.setupChatEvents();
                this.setupVoiceEvents();
                this.setupTicketEvents();
                this.setupNotificationEvents();
            }

            static setupChatEvents() {
                Livewire.on('scroll-to-bottom', () => {
                    ScrollManager.scrollToBottom();
                    setTimeout(() => MobileOptimizationManager.setupTextareaOptimizations(), 100);
                });
            }

            static setupVoiceEvents() {
                Livewire.on('toggle-voice-recognition', (event) => {
                    if (event.listening) {
                        SpeechRecognitionManager.start();
                    } else {
                        SpeechRecognitionManager.stop();
                    }
                });

                Livewire.on('speak-message', (event) => {
                    TextToSpeechManager.speak(event.text);
                });
            }

            static setupTicketEvents() {
                Livewire.on('ticketCreated', (event) => {
                    const toast = document.createElement('div');
                    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-sm';
                    toast.innerHTML = `
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <p class="font-medium">Ticket Created!</p>
                                <p class="text-sm opacity-90">${event.message}</p>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.classList.add('opacity-0', 'transform', 'translate-x-full');
                        setTimeout(() => toast.remove(), 300);
                    }, 5000);
                });

                Livewire.on('ticketCancelled', () => {
                    console.log('Ticket form cancelled');
                });
            }

            static setupNotificationEvents() {
                Livewire.on('chat-cleared', () => {
                    NotificationManager.showInfo('Chat history cleared. You can restore it using the Restore button.');
                });
                
                Livewire.on('chat-restored', () => {
                    NotificationManager.showSuccess('Chat history restored successfully!');
                });
                
                Livewire.on('chat-permanently-deleted', () => {
                    NotificationManager.showWarning('Chat history permanently deleted.');
                });
            }
        }

        // ============================================================================
        // MAIN INITIALIZATION
        // ============================================================================
        
        // Global functions for backwards compatibility
        function toggleDarkMode() {
            DarkModeManager.toggle();
        }

        function showNotification(message, type = 'info') {
            NotificationManager.show(message, type);
        }

        // Initialize everything when DOM is ready
        DarkModeManager.initialize();

        document.addEventListener('livewire:initialized', () => {
            // Set up component reference
            chatState.setLivewireComponent(
                Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'))
            );
            
            // Initialize all managers
            SpeechRecognitionManager.initialize();
            MobileOptimizationManager.initialize();
            ScrollManager.initialize();
            KeyboardManager.initialize();
            LivewireEventManager.initialize();
        });
    </script>
</div>

