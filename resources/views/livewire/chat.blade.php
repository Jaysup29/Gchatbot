<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    // State
    public array $messages = [];
    public string $newMessage = '';
    public bool $isTyping = false;
    public bool $isSubmitted = false;
    public bool $isListening = false;
    
    // Validation rules
    protected $rules = [
        'newMessage' => 'required|string|min:1',
    ];

    public function mount()
    {
        $this->messages = session('chat_messages', []);
    }

    // Actions
    public function send()
    {
        $this->validate();

        if (trim($this->newMessage) === '') return;
        $this->isSubmitted = true;
        
        // Store the user message
        $userMessage = $this->newMessage;

        // Push user message into prompts immediately
        $this->messages[] = [
            'user' => 'You',
            'text' => $userMessage,
            'time' => now()->format('H:i'),
        ];

        // Clear input immediately
        $this->reset('newMessage');

        // Save to session
        session(['chat_messages' => $this->messages]);

        // Dispatch the async API call after UI updates
        $this->dispatch('process-ai-response', userMessage: $userMessage);
    }

    #[\Livewire\Attributes\On('process-ai-response')]

    public function processAiResponse($userMessage) 
    {
        // Show typing indicator
        $this->messages[] = [
            'user' => 'GAB',
            'text' => 'GAB is thinking… ❄️',
            'time' => now()->format('H:i'),
            'typing' => true,
        ];

        // Set typing state
        $this->isTyping = true;

        // Store the index of the typing message
        $typingIndex = array_key_last($this->messages);

        // Force Livewire to update the view
        $this->dispatch('scroll-to-bottom');

        try {
            // // Build conversation history
            // $prompts = [
            //     ['role' => 'system', 'content' => config('glacierbot.system_prompt')],
            // ];
            
            // foreach ($this->messages as $message) {
            //     if (isset($message['typing']) && $message['typing']) {
            //         continue; // Skip typing indicators
            //     }
                
            //     $prompts[] = [
            //         'role' => $message['user'] === 'You' ? 'user' : 'assistant',
            //         'content' => $message['text'],
            //     ];
            // }

            // // Call OpenAI API
            // $response = Http::withToken(env('OPENAI_API_KEY'))
            //     ->timeout(30)
            //     ->post('https://api.openai.com/v1/chat/completions', [
            //         'model' => 'gpt-4o-mini',
            //         'messages' => $prompts,
            //         'max_completion_tokens' => 500,
            //         'temperature' => 0.2,
            //     ])->json();
            
            // $answer = $response['choices'][0]['message']['content'] 
            //     ?? 'I apologize, but I\'m experiencing some technical difficulties. Please try again in a moment.';


            // Build conversation history for Claude
            $messages = [];

            foreach ($this->messages as $message) {
                if (isset($message['typing']) && $message['typing']) {
                    continue; // Skip typing indicators
                }
                
                $messages[] = [
                    'role' => $message['user'] === 'You' ? 'user' : 'assistant',
                    'content' => $message['text'],
                ];
            }

            // Prepare request payload for Claude API
            $payload = [
                'model' => 'claude-3-haiku-20240307', // or 'claude-3-haiku-20240307' for faster responses
                'max_tokens' => 500,
                'temperature' => 0.2,
                'system' => config('glacierbot.system_prompt'), // System prompt is separate in Claude
                'messages' => $messages
            ];

            // Call Claude API
            $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-key' => env('ANTHROPIC_API_KEY'),
                    'anthropic-version' => '2023-06-01'
                ])
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', $payload)
                ->json();

            
            // Extract response from Claude's format
            $answer = $response['content'][0]['text'] 
                ?? 'I apologize, but I\'m experiencing some technical difficulties. Please try again in a moment.';


        } catch (\Exception $e) {
            $answer = 'I apologize, but I\'m experiencing some technical difficulties. Please try again in a moment.';
        }

        // Replace typing indicator with actual response
        $this->messages[$typingIndex] = [
            'user' => 'GAB',
            'text' => $answer,
            'time' => now()->format('H:i'),
        ];

        // Clear typing state
        $this->isTyping = false;
        $this->isSubmitted = false;

        // Save messages to session
        session(['chat_messages' => $this->messages]);
        // Dispatch event to speak
        // $this->dispatch('speak-message', text: $answer);
        // Scroll to bottom after response
        $this->dispatch('scroll-to-bottom');
    }

    public function clearChat()
    {
        $this->messages = [];
        session()->forget('chat_messages');
    }

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

<div class="h-screen max-h-screen overflow-hidden flex flex-col bg-gray-900 text-gray-100">
    
    <!-- Header - Dark Minimalist -->
    <div class="bg-gray-800 border-b border-gray-700 p-4">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center">
                    <span class="text-sm">❄️</span>
                </div>
                <div>
                    <h3 class="font-medium text-gray-100">GAB</h3>
                    <p class="text-xs text-gray-400">
                        @if($isSubmitted)
                            <span class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-yellow-400 rounded-full animate-pulse mr-1.5"></span>
                                Thinking...
                            </span>
                        @elseif($isListening)
                            <span class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-red-400 rounded-full animate-pulse mr-1.5"></span>
                                Listening...
                            </span>
                        @else
                            <span class="flex items-center">
                                <span class="w-1.5 h-1.5 bg-green-400 rounded-full mr-1.5"></span>
                                Ready
                            </span>
                        @endif
                    </p>
                </div>
            </div>
            
            @if(count($this->messages) > 0)
                <button 
                    wire:click="clearChat" 
                    class="text-xs text-gray-400 hover:text-gray-200 px-3 py-1.5 rounded border border-gray-600 hover:border-gray-500 transition-colors"
                    wire:confirm="Clear chat history?"
                >
                    Clear
                </button>
            @endif
        </div>
    </div>

    <!-- Chat Container -->
    <div class="flex-1 overflow-y-auto" id="chat-container">
        <div class="max-w-4xl mx-auto px-4 py-8">
            @forelse ($this->messages as $message)
                <div class="mb-6 {{ $message['user'] === 'You' ? 'flex justify-end' : 'flex justify-start' }}">
                    <div class="max-w-2xl w-full">
                        <!-- Message bubble -->
                        <div class="{{ $message['user'] === 'You' 
                                ? 'bg-blue-600 text-white ml-auto' 
                                : 'bg-gray-800 text-gray-100 border border-gray-700' }}
                            px-4 py-3 rounded-2xl shadow-sm break-words {{ $message['user'] === 'You' ? 'max-w-[80%]' : 'max-w-[90%]' }}">

                            {{-- Typing indicator --}}
                            @if(isset($message['typing']) && $message['typing'])
                                <div class="flex items-center space-x-3">
                                    <div class="flex space-x-1">
                                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-.2s]"></span>
                                        <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-.4s]"></span>
                                    </div>
                                    <span class="text-sm text-gray-400">{{ $message['text'] }}</span>
                                </div>
                            @else
                                {{-- Regular message --}}
                                <div class="leading-relaxed">
                                    {!! nl2br(e(preg_replace('/^- /m', '• ', $message['text']))) !!}
                                </div>
                            @endif
                        </div>
                        
                        <!-- Timestamp -->
                        <p class="text-xs text-gray-500 mt-1.5 {{ $message['user'] === 'You' ? 'text-right' : 'text-left' }}">
                            {{ $message['user'] }} • {{ $message['time'] }}
                        </p>
                    </div>
                </div>
            @empty
                <!-- Empty state -->
                <div class="text-center py-20">
                    <div class="text-6xl mb-6 opacity-50">❄️</div>
                    <h2 class="text-2xl font-light text-gray-300 mb-3">Ready when you are.</h2>
                    <p class="text-gray-500">Ask me anything about Glacier Megafridge Inc.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Input Area -->
    <div class="border-t border-gray-700 bg-gray-800">
        <div class="max-w-4xl mx-auto p-4">
            <div class="flex items-center space-x-3">
                <!-- Add button -->
                <button class="w-10 h-10 bg-gray-700 hover:bg-gray-600 rounded-full flex items-center justify-center transition-colors group">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </button>

                <!-- Input field -->
                <div class="flex-1 relative">
                    <input 
                        type="text" 
                        wire:model.live="newMessage" 
                        wire:keydown.enter="send"
                        placeholder="{{ $isSubmitted ? 'Please wait...' : 'Ask anything' }}"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-full 
                               text-gray-100 placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent 
                               transition-all {{ $isSubmitted ? 'opacity-50 cursor-not-allowed' : 'hover:border-gray-500' }}"
                        {{ $isSubmitted ? 'disabled' : '' }}
                    />
                    
                    @if($newMessage && !$isSubmitted)
                        <button 
                            wire:click="$set('newMessage', '')"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-300 w-6 h-6 flex items-center justify-center"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    @endif
                </div>

                <!-- Action buttons -->
                <div class="flex space-x-2">
                    <!-- Microphone button -->
                    <button 
                        wire:click="toggleVoice"
                        class="w-10 h-10 rounded-full flex items-center justify-center transition-colors group
                               {{ $isListening ? 'bg-red-600 hover:bg-red-700 text-white animate-pulse' : 'bg-gray-700 hover:bg-gray-600' }}"
                        {{ $isSubmitted ? 'disabled' : '' }}
                    >
                        @if($isListening)
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 6h12v12H6z"/>
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                            </svg>
                        @endif
                    </button>
                    
                    <!-- Send button -->
                    <button 
                        wire:click="send"
                        class="w-10 h-10 rounded-full flex items-center justify-center transition-colors
                               {{ !empty(trim($newMessage)) && !$isSubmitted 
                                  ? 'bg-blue-600 hover:bg-blue-700 text-white' 
                                  : 'bg-gray-700 text-gray-400 cursor-not-allowed' }}"
                        {{ empty(trim($newMessage)) || $isSubmitted ? 'disabled' : '' }}
                    >
                        @if($isSubmitted)
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        let recognition = null;
        let isRecognitionSupported = false;
        let recognitionTimeout = null;
        let retryCount = 0;
        const maxRetries = 3;

        document.addEventListener('livewire:initialized', () => {
            // Check for Web Speech API support
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                isRecognitionSupported = true;
                initializeSpeechRecognition();
            } else {
                console.warn('Speech recognition not supported in this browser');
            }

            function initializeSpeechRecognition() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                recognition = new SpeechRecognition();
                
                // Configure speech recognition with better settings
                recognition.continuous = false;
                recognition.interimResults = false; // Changed to false for better stability
                recognition.lang = 'en-US';
                recognition.maxAlternatives = 1;
                
                // Add timeout to prevent hanging
                recognition.timeout = 10000; // 10 seconds timeout
                recognition.maxSpeechInputTime = 15000; // 15 seconds max speech time

                // Handle speech recognition results
                recognition.onresult = function(event) {
                    clearTimeout(recognitionTimeout);
                    let transcript = '';
                    
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        if (event.results[i].isFinal) {
                            transcript = event.results[i][0].transcript.trim();
                            if (transcript.length > 0) {
                                @this.call('handleVoiceResult', transcript);
                                retryCount = 0; // Reset retry count on success
                                return;
                            }
                        }
                    }
                    
                    // If no final result, stop recognition
                    if (!transcript) {
                        @this.call('handleVoiceError');
                    }
                };

                // Handle speech recognition start
                recognition.onstart = function() {
                    console.log('Speech recognition started');
                    retryCount = 0;
                    
                    // Set a timeout to prevent indefinite listening
                    recognitionTimeout = setTimeout(() => {
                        try {
                            recognition.stop();
                            @this.call('handleVoiceError');
                        } catch (error) {
                            console.error('Timeout error:', error);
                        }
                    }, 15000); // 15 second timeout
                };

                // Handle speech recognition errors with retry logic
                recognition.onerror = function(event) {
                    clearTimeout(recognitionTimeout);
                    console.error('Speech recognition error:', event.error);
                    
                    let errorMessage = '';
                    let shouldRetry = false;
                    
                    switch(event.error) {
                        case 'network':
                            errorMessage = 'Network error. Checking connection...';
                            shouldRetry = retryCount < maxRetries;
                            break;
                        case 'no-speech':
                            errorMessage = 'No speech detected. Please try again.';
                            shouldRetry = retryCount < 2; // Allow fewer retries for no-speech
                            break;
                        case 'audio-capture':
                            errorMessage = 'Microphone not available. Please check your microphone.';
                            shouldRetry = false;
                            break;
                        case 'not-allowed':
                            errorMessage = 'Microphone permission denied. Please enable microphone access in your browser settings.';
                            shouldRetry = false;
                            showPermissionHelp();
                            break;
                        case 'service-not-allowed':
                            errorMessage = 'Speech service not available. Please check your internet connection.';
                            shouldRetry = retryCount < maxRetries;
                            break;
                        case 'bad-grammar':
                        case 'language-not-supported':
                            errorMessage = 'Language not supported. Switching to default...';
                            recognition.lang = 'en-US';
                            shouldRetry = true;
                            break;
                        default:
                            errorMessage = `Voice recognition error (${event.error}). Please try again.`;
                            shouldRetry = retryCount < maxRetries;
                    }
                    
                    // Show error message to user
                    showErrorMessage(errorMessage);
                    
                    // Retry logic for network and service errors
                    if (shouldRetry) {
                        retryCount++;
                        console.log(`Retrying... Attempt ${retryCount}/${maxRetries}`);
                        setTimeout(() => {
                            if (@this.isListening) {
                                try {
                                    recognition.start();
                                } catch (error) {
                                    console.error('Retry failed:', error);
                                    @this.call('handleVoiceError');
                                }
                            }
                        }, 1000 * retryCount); // Exponential backoff
                    } else {
                        @this.call('handleVoiceError');
                    }
                };

                // Handle speech recognition end
                recognition.onend = function() {
                    clearTimeout(recognitionTimeout);
                    console.log('Speech recognition ended');
                    @this.send();
                    // Only call handleVoiceError if we're still supposed to be listening
                    // This prevents double-calls when manually stopped
                    if (@this.isListening) {
                        @this.call('handleVoiceError');
                    }
                };
            }

            // Show error messages to user
            function showErrorMessage(message) {
                // Create a temporary toast notification
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-red-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 text-sm';
                toast.textContent = message;
                document.body.appendChild(toast);
                
                // Remove toast after 4 seconds
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 4000);
            }

            // Show permission help
            function showPermissionHelp() {
                const helpMessage = `
                    To enable microphone access:
                    1. Click the microphone icon in your browser's address bar
                    2. Select "Allow" for microphone permission
                    3. Refresh the page and try again
                `;
                showErrorMessage(helpMessage);
            }

            // Handle voice toggle from Livewire
            Livewire.on('toggle-voice-recognition', (event) => {
                if (!isRecognitionSupported) {
                    showErrorMessage('Voice recognition is not supported in your browser. Please try Chrome, Edge, or Safari.');
                    @this.call('handleVoiceError');
                    return;
                }

                if (event.listening) {
                    // Check if microphone permission is granted
                    if (navigator.permissions) {
                        navigator.permissions.query({name: 'microphone'}).then(function(result) {
                            if (result.state === 'denied') {
                                showErrorMessage('Microphone access denied. Please enable microphone permissions.');
                                @this.call('handleVoiceError');
                                return;
                            }
                            startRecognition();
                        }).catch(function() {
                            // Fallback if permissions API not available
                            startRecognition();
                        });
                    } else {
                        startRecognition();
                    }
                } else {
                    stopRecognition();
                }
            });

            function startRecognition() {
                try {
                    // Reset retry count
                    retryCount = 0;
                    recognition.start();
                } catch (error) {
                    console.error('Error starting recognition:', error);
                    if (error.message.includes('already started')) {
                        // Recognition is already running, stop and restart
                        recognition.stop();
                        setTimeout(() => {
                            try {
                                recognition.start();
                            } catch (e) {
                                showErrorMessage('Unable to start voice recognition. Please try again.');
                                @this.call('handleVoiceError');
                            }
                        }, 500);
                    } else {
                        showErrorMessage('Unable to start voice recognition. Please check your microphone.');
                        @this.call('handleVoiceError');
                    }
                }
            }

            function stopRecognition() {
                try {
                    clearTimeout(recognitionTimeout);
                    recognition.stop();
                } catch (error) {
                    console.error('Error stopping recognition:', error);
                }
            }

            // Auto-scroll to bottom when new messages arrive
            Livewire.on('scroll-to-bottom', () => {
                setTimeout(() => {
                    const container = document.getElementById('chat-container');
                    if (container) {
                        container.scrollTo({
                            top: container.scrollHeight,
                            behavior: 'smooth'
                        });
                    }
                }, 100);
            });

            // Handle the async AI processing
            Livewire.on('process-ai-response', (event) => {
                // setTimeout(() => {
                //     @this.call('processAiResponse', event.userMessage);
                // }, 100);
            });
            
            // Auto-scroll on page load
            setTimeout(() => {
                const container = document.getElementById('chat-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            }, 100);

            // Keyboard shortcuts
            document.addEventListener('keydown', function(event) {
                // Press and hold Space to start voice recognition (when not typing)
                if (event.code === 'Space' && event.target.tagName !== 'INPUT' && !@this.isSubmitted && !event.repeat) {
                    event.preventDefault();
                    if (!@this.isListening && isRecognitionSupported) {
                        @this.call('toggleVoice');
                    }
                }
                
                // Press Escape to stop voice recognition
                if (event.code === 'Escape' && @this.isListening) {
                    @this.call('toggleVoice');
                }
            });

            // Handle page visibility changes (stop recognition when tab is hidden)
            document.addEventListener('visibilitychange', function() {
                if (document.hidden && @this.isListening) {
                    @this.call('toggleVoice');
                }
            });


            Livewire.on('speak-message', (event) => {
                speakText(event.text);
            });


            function speakText(text) {
                if (!('speechSynthesis' in window)) {
                    console.warn("Speech synthesis not supported in this browser.");
                    return;
                }

                // Stop any ongoing speech before starting new
                speechSynthesis.cancel();

                // Split text into sentences (keeps punctuation)
                const sentences = text.split(/([.!?])/).reduce((acc, cur, i, arr) => {
                    if (/[.!?]/.test(cur) && acc.length) {
                        acc[acc.length - 1] += cur; // attach punctuation
                    } else if (cur.trim()) {
                        acc.push(cur.trim());
                    }
                    return acc;
                }, []);

                const voices = speechSynthesis.getVoices();

                sentences.forEach(sentence => {
                    const utterance = new SpeechSynthesisUtterance(sentence);

                    // 🔹 Simple detection: English if mostly ASCII, Tagalog otherwise
                    const isEnglish = /^[A-Za-z0-9\s.,!?'"-]+$/.test(sentence);
                    utterance.lang = isEnglish ? "en-US" : "tl-PH";

                    // Pick a voice if available
                    const voice = voices.find(v => v.lang === utterance.lang);
                    if (voice) utterance.voice = voice;

                    // Customize tone
                    utterance.rate = 1;   // 1 = normal speed
                    utterance.pitch = 1;  // 1 = normal pitch
                    utterance.volume = 1; // 1 = full volume

                    speechSynthesis.speak(utterance);
                });
            }
        });
    </script>
</div>