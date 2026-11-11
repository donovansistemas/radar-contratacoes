<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('oportunidades', 'pages::oportunidades.index')->name('oportunidades.index');
    Route::livewire('perfis', 'pages::perfis.index')->name('perfis.index');
});

require __DIR__.'/settings.php';
