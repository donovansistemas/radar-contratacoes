<?php

use App\Models\Contratacao;
use App\Models\PerfilBusca;
use App\Models\User;
use Livewire\Livewire;

it('exige login', function () {
    $this->get(route('oportunidades.index'))->assertRedirect(route('login'));
});

it('convida a criar um perfil quando o usuário não tem nenhum', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('oportunidades.index'))
        ->assertOk()
        ->assertSee('Crie um perfil para começar');
});

it('mostra só as contratações que casam com o perfil', function () {
    $user = User::factory()->create();
    PerfilBusca::factory()->for($user)->create([
        'palavras_chave' => ['uniforme'],
        'ufs' => ['SP'],
    ]);

    Contratacao::factory()->create([
        'uf' => 'SP',
        'objeto' => 'Aquisição de uniformes escolares',
    ]);

    Contratacao::factory()->create([
        'uf' => 'SP',
        'objeto' => 'Aquisição de pneus',
    ]);

    Contratacao::factory()->create([
        'uf' => 'RJ',
        'objeto' => 'Aquisição de uniformes escolares',
    ]);

    $this->actingAs($user)
        ->get(route('oportunidades.index'))
        ->assertOk()
        ->assertSee('Aquisição de uniformes escolares')
        ->assertDontSee('Aquisição de pneus');
});

it('não mostra contratação com prazo vencido', function () {
    $user = User::factory()->create();
    PerfilBusca::factory()->for($user)->create(['palavras_chave' => ['uniforme']]);

    Contratacao::factory()->encerrada()->create(['objeto' => 'Uniformes fora de prazo']);

    $this->actingAs($user)
        ->get(route('oportunidades.index'))
        ->assertOk()
        ->assertDontSee('Uniformes fora de prazo')
        ->assertSee('Nenhuma oportunidade agora');
});

it('filtra por um perfil específico', function () {
    $user = User::factory()->create();

    PerfilBusca::factory()->for($user)->create([
        'nome' => 'Uniformes',
        'palavras_chave' => ['uniforme'],
    ]);

    PerfilBusca::factory()->for($user)->create([
        'nome' => 'Merenda',
        'palavras_chave' => ['merenda'],
    ]);

    Contratacao::factory()->create(['objeto' => 'Uniformes escolares']);
    Contratacao::factory()->create(['objeto' => 'Merenda escolar']);

    $this->actingAs($user)
        ->get(route('oportunidades.index'))
        ->assertOk()
        ->assertSee('Uniformes escolares')
        ->assertSee('Merenda escolar');
});

it('não deixa espiar perfil de outro usuário pela URL', function () {
    $dono = User::factory()->create();
    $intruso = User::factory()->create();

    $perfilDoDono = PerfilBusca::factory()->for($dono)->create([
        'palavras_chave' => ['uniforme'],
    ]);

    Contratacao::factory()->create(['objeto' => 'Uniformes escolares']);

    $componente = Livewire::actingAs($intruso)
        ->withQueryParams(['perfil' => (string) $perfilDoDono->id])
        ->test('pages::oportunidades.index');

    expect($componente->instance()->with()['total'])->toBe(0);
});

it('agrupa os perfis que casaram com a mesma contratação', function () {
    $user = User::factory()->create();

    PerfilBusca::factory()->for($user)->create(['nome' => 'Uniformes', 'palavras_chave' => ['uniforme']]);
    PerfilBusca::factory()->for($user)->create(['nome' => 'Fardamento', 'palavras_chave' => ['fardamento']]);

    Contratacao::factory()->create(['objeto' => 'Uniformes e fardamento escolar']);

    $this->actingAs($user)
        ->get(route('oportunidades.index'))
        ->assertOk()
        ->assertSee('Uniformes')
        ->assertSee('Fardamento');
});
