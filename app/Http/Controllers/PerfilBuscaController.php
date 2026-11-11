<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilBuscaRequest;
use App\Models\PerfilBusca;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de perfis de busca — versão CLÁSSICA (MVC), espelho da versão Livewire
 * em `resources/views/pages/perfis/⚡index.blade.php`.
 *
 * Existe para comparação didática: mesmo comportamento, arquitetura tradicional
 * do Laravel. As rotas são `Route::resource()` (ver routes/web.php), e cada
 * método abaixo corresponde a um verbo HTTP:
 *
 *   GET    /perfis-classico            index    lista
 *   GET    /perfis-classico/create      create   formulário em branco
 *   POST   /perfis-classico             store    grava novo      → redirect
 *   GET    /perfis-classico/{id}/edit   edit     formulário preenchido
 *   PUT    /perfis-classico/{id}        update   grava alteração → redirect
 *   DELETE /perfis-classico/{id}        destroy  remove          → redirect
 *
 * Note o que aparece aqui e não aparece no componente Livewire: método por
 * verbo HTTP, RedirectResponse, flash de sessão e `old()` no formulário.
 */
class PerfilBuscaController extends Controller
{
    public function index(Request $request): View
    {
        return view('perfis-classico.index', [
            'perfis' => $this->perfisDoUsuario($request)->orderBy('nome')->get(),
        ]);
    }

    public function create(): View
    {
        return view('perfis-classico.form', [
            'perfil' => new PerfilBusca(['ativo' => true]),
        ]);
    }

    public function store(PerfilBuscaRequest $request): RedirectResponse
    {
        $this->perfisDoUsuario($request)->create($request->dadosDoPerfil());

        return redirect()
            ->route('perfis-classico.index')
            ->with('status', 'Perfil criado.');
    }

    public function edit(Request $request, PerfilBusca $perfil): View
    {
        $this->garantirDono($request, $perfil);

        return view('perfis-classico.form', [
            'perfil' => $perfil,
        ]);
    }

    public function update(PerfilBuscaRequest $request, PerfilBusca $perfil): RedirectResponse
    {
        $this->garantirDono($request, $perfil);

        $perfil->update($request->dadosDoPerfil());

        return redirect()
            ->route('perfis-classico.index')
            ->with('status', 'Perfil atualizado.');
    }

    public function destroy(Request $request, PerfilBusca $perfil): RedirectResponse
    {
        $this->garantirDono($request, $perfil);

        $perfil->delete();

        return redirect()
            ->route('perfis-classico.index')
            ->with('status', 'Perfil removido.');
    }

    /**
     * Query já limitada ao dono. Nenhuma tela deste sistema enxerga dado de
     * outro usuário, nem por id chutado na URL.
     *
     * @return HasMany<PerfilBusca, covariant User>
     */
    private function perfisDoUsuario(Request $request)
    {
        return $request->user()->perfisBusca();
    }

    /**
     * O `Route::resource` resolve o model pelo id da URL — sem esta checagem,
     * qualquer usuário editaria o perfil de outro trocando o número.
     */
    private function garantirDono(Request $request, PerfilBusca $perfil): void
    {
        abort_unless($perfil->user_id === $request->user()->id, 403);
    }
}
