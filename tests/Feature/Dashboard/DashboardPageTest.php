<?php

use App\Models\Contratacao;
use App\Models\PerfilBusca;
use App\Models\User;

it('pede um perfil quando o usuário ainda não tem nenhum', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Comece por um perfil de busca');
});

it('mostra os números das oportunidades do usuário', function () {
    $user = User::factory()->create();
    PerfilBusca::factory()->for($user)->create(['palavras_chave' => [], 'modalidades' => []]);

    Contratacao::factory()->create([
        'objeto' => 'Uniformes escolares',
        'valor_estimado' => 4000,
        'uf' => 'SP',
        'modalidade_nome' => 'Pregão - Eletrônico',
        'data_encerramento_proposta' => now()->addDays(3),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Oportunidades abertas')
        ->assertSee('R$ 4.000,00')
        ->assertSee('Por UF')
        ->assertSee('Por modalidade')
        ->assertSee('Por faixa de valor')
        ->assertSee('Encerramento por mês')
        ->assertSee('Base importada');
});

it('avisa quando há base importada mas nada casa com os perfis', function () {
    $user = User::factory()->create();
    PerfilBusca::factory()->for($user)->create(['palavras_chave' => ['helicóptero']]);

    Contratacao::factory()->count(3)->create(['objeto' => 'Aquisição de pneus']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Nenhuma oportunidade no radar')
        ->assertSee('radar:importar');
});

it('não mostra item de menu apontando para página vazia', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Visão geral');
});
