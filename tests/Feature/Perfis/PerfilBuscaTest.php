<?php

use App\Models\PerfilBusca;
use App\Models\User;
use Livewire\Livewire;

it('exige login para ver os perfis', function () {
    $this->get(route('perfis.index'))->assertRedirect(route('login'));
});

it('abre a tela de perfis para o usuário autenticado', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('perfis.index'))
        ->assertOk()
        ->assertSee('Perfis de busca');
});

it('cria um perfil a partir dos campos do formulário', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::perfis.index')
        ->call('novo')
        ->set('nome', 'Uniformes escolares')
        ->set('palavrasChave', 'uniforme, fardamento; farda')
        ->set('palavrasExcluidas', 'merenda')
        ->set('ufs', ['SP', 'PR'])
        ->set('modalidades', [6])
        ->set('valorMin', '10000')
        ->set('valorMax', '500000')
        ->call('salvar')
        ->assertHasNoErrors();

    $perfil = PerfilBusca::sole();

    expect($perfil->user_id)->toBe($user->id)
        ->and($perfil->nome)->toBe('Uniformes escolares')
        ->and($perfil->palavras_chave)->toBe(['uniforme', 'fardamento', 'farda'])
        ->and($perfil->palavras_excluidas)->toBe(['merenda'])
        ->and($perfil->ufs)->toBe(['SP', 'PR'])
        ->and($perfil->modalidades)->toBe([6])
        ->and((float) $perfil->valor_min)->toBe(10000.0)
        ->and((float) $perfil->valor_max)->toBe(500000.0)
        ->and($perfil->ativo)->toBeTrue();
});

it('recusa perfil sem nome', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::perfis.index')
        ->call('novo')
        ->set('nome', '')
        ->call('salvar')
        ->assertHasErrors(['nome' => 'required']);

    expect(PerfilBusca::count())->toBe(0);
});

it('recusa faixa de valor invertida', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::perfis.index')
        ->call('novo')
        ->set('nome', 'Faixa errada')
        ->set('valorMin', '90000')
        ->set('valorMax', '1000')
        ->call('salvar')
        ->assertHasErrors('valorMax');

    expect(PerfilBusca::count())->toBe(0);
});

it('mostra a mensagem de faixa invertida uma única vez', function () {
    $componente = Livewire::actingAs(User::factory()->create())
        ->test('pages::perfis.index')
        ->call('novo')
        ->set('nome', 'Faixa errada')
        ->set('valorMin', '90000')
        ->set('valorMax', '1000')
        ->call('salvar');

    // `flux:input` com `label` já renderiza o próprio erro; um <flux:error>
    // extra depois do campo duplicava a mensagem na tela.
    expect(substr_count($componente->html(), 'não pode ser menor que o mínimo'))->toBe(1);
});

it('edita um perfil existente', function () {
    $user = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($user)->create(['nome' => 'Antigo']);

    Livewire::actingAs($user)
        ->test('pages::perfis.index')
        ->call('editar', $perfil->id)
        ->assertSet('nome', 'Antigo')
        ->set('nome', 'Novo nome')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($perfil->refresh()->nome)->toBe('Novo nome');
});

it('não deixa editar perfil de outro usuário', function () {
    $dono = User::factory()->create();
    $intruso = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($dono)->create();

    Livewire::actingAs($intruso)
        ->test('pages::perfis.index')
        ->call('editar', $perfil->id)
        ->assertStatus(404);
});

it('não deixa remover perfil de outro usuário', function () {
    $dono = User::factory()->create();
    $intruso = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($dono)->create();

    Livewire::actingAs($intruso)
        ->test('pages::perfis.index')
        ->call('excluir', $perfil->id)
        ->assertStatus(404);

    expect(PerfilBusca::whereKey($perfil->id)->exists())->toBeTrue();
});

it('remove o próprio perfil', function () {
    $user = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::perfis.index')
        ->call('excluir', $perfil->id)
        ->assertHasNoErrors();

    expect(PerfilBusca::count())->toBe(0);
});

it('liga e desliga o perfil', function () {
    $user = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($user)->create(['ativo' => true]);

    Livewire::actingAs($user)
        ->test('pages::perfis.index')
        ->call('alternarAtivo', $perfil->id);

    expect($perfil->refresh()->ativo)->toBeFalse();
});
