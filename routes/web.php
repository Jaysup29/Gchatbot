<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Volt::route('/chat', 'chat');
Volt::route('/detail', 'detail');