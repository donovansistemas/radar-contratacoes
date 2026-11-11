<?php

use App\Http\Controllers\PerfilBuscaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');

    Route::livewire('oportunidades', 'pages::oportunidades.index')->name('oportunidades.index');
    Route::livewire('perfis', 'pages::perfis.index')->name('perfis.index');

    /*
     * Versão CLÁSSICA (MVC) da tela de perfis, para comparação.
     *
     * `Route::resource` cria as 7 rotas REST de uma vez. Aqui usamos todas menos
     * `show` — não existe tela de detalhe de perfil. Os names abaixo são o que o
     * controller usa em `redirect()->route('perfis-classico.index')`.
     */
    Route::resource('perfis-classico', PerfilBuscaController::class)
        ->except('show')
        ->parameters(['perfis-classico' => 'perfil'])
        ->names('perfis-classico');
});

require __DIR__.'/settings.php';
