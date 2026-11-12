<?php

use App\Livewire\Perfis;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');

    Route::livewire('oportunidades', 'pages::oportunidades.index')->name('oportunidades.index');

    // Componente com classe e view separadas: a classe é o controller, o Blade é a view.
    Route::get('perfis', Perfis::class)->name('perfis.index');
});

require __DIR__.'/settings.php';
