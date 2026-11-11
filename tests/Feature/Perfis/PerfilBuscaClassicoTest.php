<?php

use App\Models\PerfilBusca;
use App\Models\User;

/**
 * Versão clássica do CRUD (controller + FormRequest + Blade).
 * Comportamento esperado é idêntico ao da versão Livewire.
 */
function dadosDoPerfil(array $sobrescrever = []): array
{
    return array_merge([
        'nome' => 'Uniformes escolares',
        'palavras_chave' => 'uniforme, fardamento',
        'palavras_excluidas' => 'merenda',
        'ufs' => ['SP', 'PR'],
        'modalidades' => ['6'],
        'valor_min' => '10000',
        'valor_max' => '500000',
        'ativo' => '1',
    ], $sobrescrever);
}

it('exige login', function () {
    $this->get(route('perfis-classico.index'))->assertRedirect(route('login'));
});

it('lista os perfis do usuário autenticado', function () {
    $user = User::factory()->create();
    PerfilBusca::factory()->for($user)->create(['nome' => 'Meu perfil']);

    $this->actingAs($user)
        ->get(route('perfis-classico.index'))
        ->assertOk()
        ->assertSee('Meu perfil');
});

it('cria um perfil com POST', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('perfis-classico.store'), dadosDoPerfil())
        ->assertRedirect(route('perfis-classico.index'))
        ->assertSessionHas('status', 'Perfil criado.');

    $perfil = PerfilBusca::sole();

    expect($perfil->user_id)->toBe($user->id)
        ->and($perfil->palavras_chave)->toBe(['uniforme', 'fardamento'])
        ->and($perfil->palavras_excluidas)->toBe(['merenda'])
        ->and($perfil->ufs)->toBe(['SP', 'PR'])
        ->and($perfil->modalidades)->toBe([6])
        ->and((float) $perfil->valor_min)->toBe(10000.0)
        ->and($perfil->ativo)->toBeTrue();
});

it('marca o perfil como inativo quando o checkbox não vem', function () {
    $user = User::factory()->create();
    $dados = dadosDoPerfil();
    unset($dados['ativo']);

    $this->actingAs($user)->post(route('perfis-classico.store'), $dados);

    expect(PerfilBusca::sole()->ativo)->toBeFalse();
});

it('recusa perfil sem nome e volta com o erro', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('perfis-classico.create'))
        ->post(route('perfis-classico.store'), dadosDoPerfil(['nome' => '']))
        ->assertRedirect(route('perfis-classico.create'))
        ->assertSessionHasErrors(['nome' => 'Dê um nome ao perfil.']);

    expect(PerfilBusca::count())->toBe(0);
});

it('recusa faixa de valor invertida', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('perfis-classico.create'))
        ->post(route('perfis-classico.store'), dadosDoPerfil([
            'valor_min' => '90000',
            'valor_max' => '1000',
        ]))
        ->assertSessionHasErrors('valor_max');

    expect(PerfilBusca::count())->toBe(0);
});

it('mostra a mensagem de faixa invertida uma única vez', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('perfis-classico.create'))
        ->post(route('perfis-classico.store'), dadosDoPerfil([
            'valor_min' => '90000',
            'valor_max' => '1000',
        ]));

    // A mensagem vem no flash de erro da sessão, na requisição seguinte.
    $html = $this->actingAs($user)
        ->get(route('perfis-classico.create'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'não pode ser menor que o mínimo'))->toBe(1);
});

it('recusa UF inexistente', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('perfis-classico.create'))
        ->post(route('perfis-classico.store'), dadosDoPerfil(['ufs' => ['XX']]))
        ->assertSessionHasErrors('ufs.0');
});

it('abre o formulário de edição preenchido', function () {
    $user = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($user)->create([
        'nome' => 'Perfil antigo',
        'palavras_chave' => ['uniforme'],
    ]);

    $this->actingAs($user)
        ->get(route('perfis-classico.edit', $perfil))
        ->assertOk()
        ->assertSee('Perfil antigo')
        ->assertSee('uniforme');
});

it('atualiza com PUT', function () {
    $user = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($user)->create(['nome' => 'Antigo']);

    $this->actingAs($user)
        ->put(route('perfis-classico.update', $perfil), dadosDoPerfil(['nome' => 'Novo nome']))
        ->assertRedirect(route('perfis-classico.index'))
        ->assertSessionHas('status', 'Perfil atualizado.');

    expect($perfil->refresh()->nome)->toBe('Novo nome')
        ->and(PerfilBusca::count())->toBe(1);
});

it('remove com DELETE', function () {
    $user = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('perfis-classico.destroy', $perfil))
        ->assertRedirect(route('perfis-classico.index'));

    expect(PerfilBusca::count())->toBe(0);
});

it('dá 403 ao tentar editar perfil de outro usuário', function () {
    $dono = User::factory()->create();
    $intruso = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($dono)->create(['nome' => 'Do dono']);

    $this->actingAs($intruso)
        ->get(route('perfis-classico.edit', $perfil))
        ->assertForbidden();

    $this->actingAs($intruso)
        ->put(route('perfis-classico.update', $perfil), dadosDoPerfil())
        ->assertForbidden();

    expect($perfil->refresh()->nome)->toBe('Do dono');
});

it('dá 403 ao tentar remover perfil de outro usuário', function () {
    $dono = User::factory()->create();
    $intruso = User::factory()->create();
    $perfil = PerfilBusca::factory()->for($dono)->create();

    $this->actingAs($intruso)
        ->delete(route('perfis-classico.destroy', $perfil))
        ->assertForbidden();

    expect(PerfilBusca::whereKey($perfil->id)->exists())->toBeTrue();
});
