<?php


use App\Services\FaqService;
use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return response()->json(['message' => 'API is running']);
})->name('api.login');


Route::get('/api/faqs/search/{term}', function($term) {
    return app(FaqService::class)->searchFaqs($term);
});

Route::get('/api/faqs/stats', function() {
    return app(FaqService::class)->getStats();
});