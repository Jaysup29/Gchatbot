<?php

namespace App\Providers;

use App\Services\FaqService;
use App\Services\OpenAIService;
use App\Services\PromptService;
use App\Services\ChatSessionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FaqService::class, function ($app) {
            return new FaqService();
        });

        $this->app->singleton(ChatSessionService::class, function ($app) {
            return new ChatSessionService();
        });

        $this->app->singleton(PromptService::class, function ($app) {
            return new PromptService();
        });

        $this->app->singleton(OpenAIService::class, function ($app) {
            return new OpenAIService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
