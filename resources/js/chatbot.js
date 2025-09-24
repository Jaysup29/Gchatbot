class ChatbotManager {
    constructor() {
        this.config = {
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

        this.state = {
            recognition: null,
            isRecognitionSupported: false,
            recognitionTimeout: null,
            retryCount: 0,
            livewireComponent: null,
            initialViewportHeight: window.innerHeight,
            keyboardVisible: false
        };

        this.init();
    }

    init() {
        this.initializeDarkMode();

        document.addEventListener('livewire:navigated', () => {
            this.setupLivewireComponent();

            if (this.state.livewireComponent) {
                this.initializeAllManagers();
            } else {
                console.warn("No Livewire component found yet, retrying...");
                setTimeout(() => {
                    this.setupLivewireComponent();
                    if (this.state.livewireComponent) {
                        this.initializeAllManagers();
                    }
                }, 500);
            }
        });
    }


    setupLivewireComponent() {
        const el = document.querySelector('[wire\\:id]');
        if (!el) {
            console.warn("No Livewire element found");
            return;
        }

        this.state.livewireComponent = Livewire.find(el.getAttribute('wire:id'));
    }



    initializeAllManagers() {
        console.log('Initializing all managers');
        try { this.initializeSpeechRecognition(); console.log("Speech OK"); } catch (e) { console.error("Speech failed", e); }
        try { this.initializeMobileOptimization(); console.log("Mobile OK"); } catch (e) { console.error("Mobile failed", e); }
        try { this.initializeScrollManager(); console.log("Scroll OK"); } catch (e) { console.error("Scroll failed", e); }
        try { this.initializeKeyboardManager(); console.log("Keyboard OK"); } catch (e) { console.error("Keyboard failed", e); }
        try { this.initializeLivewireEvents(); console.log("Livewire Events OK"); } catch (e) { console.error("Livewire Events failed", e); }
    }

    // ============================================================================
    // DARK MODE MANAGEMENT
    // ============================================================================
    
    initializeDarkMode() {
        const darkMode = localStorage.getItem('darkMode') === 'true' || 
                       (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        
        document.documentElement.classList.toggle('dark', darkMode);
        this.updateThemeIcons(darkMode);
    }

    toggleDarkMode() {
        const isDark = document.documentElement.classList.contains('dark');
        const newDarkMode = !isDark;
        
        document.documentElement.classList.toggle('dark', newDarkMode);
        localStorage.setItem('darkMode', newDarkMode);
        this.updateThemeIcons(newDarkMode);
    }

    updateThemeIcons(isDark) {
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

    // ============================================================================
    // SPEECH RECOGNITION MANAGEMENT
    // ============================================================================
    
    initializeSpeechRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            this.state.isRecognitionSupported = true;
            this.setupRecognition();
        } else {
            console.warn('Speech recognition not supported in this browser');
        }
    }

    setupRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.state.recognition = new SpeechRecognition();
        
        this.configureRecognition();
        this.attachRecognitionEvents();
    }

    configureRecognition() {
        const recognition = this.state.recognition;
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = this.config.SPEECH_RECOGNITION.LANGUAGE;
        recognition.maxAlternatives = 1;
        recognition.timeout = this.config.SPEECH_RECOGNITION.TIMEOUT;
        recognition.maxSpeechInputTime = this.config.SPEECH_RECOGNITION.TIMEOUT;
    }

    attachRecognitionEvents() {
        const recognition = this.state.recognition;
        
        recognition.onresult = this.handleSpeechResult.bind(this);
        recognition.onstart = this.handleSpeechStart.bind(this);
        recognition.onerror = this.handleSpeechError.bind(this);
        recognition.onend = this.handleSpeechEnd.bind(this);
    }

    handleSpeechResult(event) {
        clearTimeout(this.state.recognitionTimeout);
        let transcript = '';
        
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) {
                transcript = event.results[i][0].transcript.trim();
                if (transcript.length > 0) {
                    if (this.state.livewireComponent) {
                        this.state.livewireComponent.call('handleVoiceResult', transcript);
                    } else {
                        console.warn("Livewire component not ready, skipping voice result");
                    }
                    this.state.retryCount = 0;
                    return;
                }
            }
        }
        
        if (!transcript && this.state.livewireComponent) {
            this.state.livewireComponent.call('handleVoiceError');
        }
    }

    handleSpeechStart() {
        console.log('Speech recognition started');
        this.state.retryCount = 0;
        
        this.state.recognitionTimeout = setTimeout(() => {
            try {
                this.state.recognition.stop();
                if (this.state.livewireComponent) {
                    this.state.livewireComponent.call('handleVoiceError');
                }
            } catch (error) {
                console.error('Timeout error:', error);
            }
        }, this.config.SPEECH_RECOGNITION.TIMEOUT);
    }

    handleSpeechError(event) {
        clearTimeout(this.state.recognitionTimeout);
        console.error('Speech recognition error:', event.error);
        
        const errorInfo = this.getSpeechErrorInfo(event.error);
        this.showNotification(errorInfo.message, 'error');
        
        if (errorInfo.shouldRetry && this.state.retryCount < this.config.SPEECH_RECOGNITION.MAX_RETRIES) {
            this.retrySpeechRecognition();
        } else if (this.state.livewireComponent) {
            this.state.livewireComponent.call('handleVoiceError');
        }
    }

    handleSpeechEnd() {
        clearTimeout(this.state.recognitionTimeout);
        console.log('Speech recognition ended');
        if (this.state.livewireComponent) {
            this.state.livewireComponent.call('send');
            if (this.state.livewireComponent.isListening) {
                this.state.livewireComponent.call('handleVoiceError');
            }
        }
    }

    getSpeechErrorInfo(errorType) {
        const errorMap = {
            'network': {
                message: 'Network error. Checking connection...',
                shouldRetry: true
            },
            'no-speech': {
                message: 'No speech detected. Please try again.',
                shouldRetry: this.state.retryCount < 2
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

    retrySpeechRecognition() {
        this.state.retryCount++;
        setTimeout(() => {
            if (this.state.livewireComponent && this.state.livewireComponent.isListening) {
                try {
                    this.state.recognition.start();
                } catch (error) {
                    console.error('Retry failed:', error);
                    this.state.livewireComponent.call('handleVoiceError');
                }
            }
        }, 1000 * this.state.retryCount);
    }

    startSpeechRecognition() {
        if (!this.state.isRecognitionSupported) {
            this.showNotification('Voice recognition is not supported in your browser. Please try Chrome, Edge, or Safari.', 'error');
            return;
        }

        this.checkMicrophonePermissions();
    }

    checkMicrophonePermissions() {
        if (navigator.permissions) {
            navigator.permissions.query({name: 'microphone'}).then(result => {
                if (result.state === 'denied') {
                    this.showNotification('Microphone access denied. Please enable microphone permissions.', 'error');
                    if (this.state.livewireComponent) {
                        this.state.livewireComponent.call('handleVoiceError');
                    }
                    return;
                }
                this.attemptStartRecognition();
            }).catch(() => {
                this.attemptStartRecognition();
            });
        } else {
            this.attemptStartRecognition();
        }
    }

    attemptStartRecognition() {
        try {
            this.state.retryCount = 0;
            this.state.recognition.start();
        } catch (error) {
            console.error('Error starting recognition:', error);
            this.handleRecognitionStartError(error);
        }
    }

    handleRecognitionStartError(error) {
        if (error.message.includes('already started')) {
            this.state.recognition.stop();
            setTimeout(() => {
                try {
                    this.state.recognition.start();
                } catch (e) {
                    this.showNotification('Unable to start voice recognition. Please try again.', 'error');
                    if (this.state.livewireComponent) {
                        this.state.livewireComponent.call('handleVoiceError');
                    }
                }
            }, 500);
        } else {
            this.showNotification('Unable to start voice recognition. Please check your microphone.', 'error');
            if (this.state.livewireComponent) {
                this.state.livewireComponent.call('handleVoiceError');
            }
        }
    }

    stopSpeechRecognition() {
        try {
            clearTimeout(this.state.recognitionTimeout);
            this.state.recognition.stop();
        } catch (error) {
            console.error('Error stopping recognition:', error);
        }
    }

    // ============================================================================
    // TEXT-TO-SPEECH MANAGEMENT
    // ============================================================================
    
    speakText(text) {
        if (!('speechSynthesis' in window)) {
            console.warn("Speech synthesis not supported in this browser.");
            return;
        }

        speechSynthesis.cancel();
        const sentences = this.splitTextIntoSentences(text);
        const voices = speechSynthesis.getVoices();

        sentences.forEach(sentence => {
            const utterance = this.createSpeechUtterance(sentence, voices);
            speechSynthesis.speak(utterance);
        });
    }

    splitTextIntoSentences(text) {
        return text.split(/([.!?])/).reduce((acc, cur, i, arr) => {
            if (/[.!?]/.test(cur) && acc.length) {
                acc[acc.length - 1] += cur;
            } else if (cur.trim()) {
                acc.push(cur.trim());
            }
            return acc;
        }, []);
    }

    createSpeechUtterance(sentence, voices) {
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

    // ============================================================================
    // NOTIFICATION MANAGEMENT
    // ============================================================================
    
    showNotification(message, type = 'info') {
        const toast = this.createNotificationToast(message, type);
        document.body.appendChild(toast);
        this.scheduleNotificationRemoval(toast);
    }

    createNotificationToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 ${this.config.NOTIFICATIONS.COLORS[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 max-w-sm transition-all transform translate-x-0`;
        toast.textContent = message;
        return toast;
    }

    scheduleNotificationRemoval(toast) {
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, this.config.NOTIFICATIONS.DURATION);
    }

    // ============================================================================
    // MOBILE OPTIMIZATION MANAGEMENT
    // ============================================================================
    
    initializeMobileOptimization() {
        this.handleKeyboardDetection();
        this.setupTextareaOptimizations();
        this.handleSafeAreaInsets();
        this.setupOrientationHandling();
    }

    handleKeyboardDetection() {
        window.addEventListener('resize', () => {
            const currentHeight = window.innerHeight;
            const heightDifference = this.state.initialViewportHeight - currentHeight;
            
            if (window.innerWidth <= 768 && heightDifference > 150) {
                if (!this.state.keyboardVisible) {
                    this.state.keyboardVisible = true;
                    this.scrollToBottomOnKeyboard();
                }
            } else {
                this.state.keyboardVisible = false;
            }
        });
    }

    scrollToBottomOnKeyboard() {
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

    setupTextareaOptimizations() {
        const textarea = document.querySelector('textarea[wire\\:model\\.live="newMessage"]');
        if (!textarea) return;

        this.setupAutoResize(textarea);
        this.preventIOSZoom(textarea);
    }

    setupAutoResize(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            const newHeight = Math.min(Math.max(this.scrollHeight, 44), 120);
            this.style.height = newHeight + 'px';
        });
    }

    preventIOSZoom(textarea) {
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

    handleSafeAreaInsets() {
        const root = document.documentElement;
        if ('CSS' in window && CSS.supports('padding-bottom: env(safe-area-inset-bottom)')) {
            root.style.setProperty('--safe-area-bottom', 'env(safe-area-inset-bottom)');
        } else {
            root.style.setProperty('--safe-area-bottom', '0px');
        }
    }

    setupOrientationHandling() {
        window.addEventListener('orientationchange', () => {
            setTimeout(() => this.handleSafeAreaInsets(), 100);
        });
    }

    // ============================================================================
    // SCROLL MANAGEMENT
    // ============================================================================
    
    initializeScrollManager() {
        this.setupAutoScroll();
        this.setupScrollOptimization();
    }

    setupAutoScroll() {
        setTimeout(() => {
            const container = document.getElementById('chat-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }, 100);
    }

    scrollToBottom() {
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

    setupScrollOptimization() {
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

    // ============================================================================
    // KEYBOARD SHORTCUTS MANAGEMENT
    // ============================================================================
    
    initializeKeyboardManager() {
        document.addEventListener('keydown', this.handleKeydown.bind(this));
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this));
    }

    handleKeydown(event) {
        this.handleSpaceForVoice(event);
        this.handleEscapeKey(event);
        this.handleClearChatShortcut(event);
    }

    handleSpaceForVoice(event) {
        if (event.code === 'Space' && 
            event.target.tagName !== 'INPUT' && 
            event.target.tagName !== 'TEXTAREA' && 
            this.state.livewireComponent && 
            !this.state.livewireComponent.isSubmitted && 
            !event.repeat) {
            
            event.preventDefault();
            if (!this.state.livewireComponent.isListening && this.state.isRecognitionSupported) {
                this.state.livewireComponent.call('toggleVoice');
            }
        }
    }

    handleEscapeKey(event) {
        if (event.code === 'Escape') {
            if (this.state.livewireComponent && this.state.livewireComponent.isListening) {
                this.state.livewireComponent.call('toggleVoice');
            } else if (event.target.tagName === 'TEXTAREA') {
                this.state.livewireComponent.set('newMessage', '');
            }
        }
    }

    handleClearChatShortcut(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'k' && !event.shiftKey) {
            event.preventDefault();
            if (confirm('Clear chat history?') && this.state.livewireComponent) {
                this.state.livewireComponent.call('clearChat');
            }
        }
    }

    handleVisibilityChange() {
        if (document.hidden && this.state.livewireComponent && this.state.livewireComponent.isListening) {
            this.state.livewireComponent.call('toggleVoice');
        }
    }

    // ============================================================================
    // LIVEWIRE EVENT HANDLERS
    // ============================================================================
    
    initializeLivewireEvents() {
        this.setupChatEvents();
        this.setupVoiceEvents();
        this.setupTicketEvents();
        this.setupNotificationEvents();
    }

    setupChatEvents() {
        Livewire.on('scroll-to-bottom', () => {
            this.scrollToBottom();
            setTimeout(() => this.setupTextareaOptimizations(), 100);
        });
    }

    setupVoiceEvents() {
        Livewire.on('toggle-voice-recognition', (event) => {
            if (event.listening) {
                this.startSpeechRecognition();
            } else {
                this.stopSpeechRecognition();
            }
        });
        
        Livewire.on('speak-message', (event) => {
            this.speakText(event.text);
        });
    }

    setupTicketEvents() {
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

    setupNotificationEvents() {
        Livewire.on('chat-cleared', () => {
            this.showNotification('Chat history cleared. You can restore it using the Restore button.', 'info');
        });
        
        Livewire.on('chat-restored', () => {
            this.showNotification('Chat history restored successfully!', 'success');
        });
        
        Livewire.on('chat-permanently-deleted', () => {
            this.showNotification('Chat history permanently deleted.', 'warning');
        });
    }

    // ============================================================================
    // PUBLIC API FOR GLOBAL ACCESS
    // ============================================================================
    
    exposeGlobalMethods() {
        window.toggleDarkMode = () => this.toggleDarkMode();
        window.showNotification = (message, type) => this.showNotification(message, type);
    }
}

// Initialize the chatbot manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const chatbotManager = new ChatbotManager();
    chatbotManager.exposeGlobalMethods();
});

// Store reference globally for debugging
window.ChatbotManager = ChatbotManager;