<?php

use App\Models\Contratacao;
use App\Models\PerfilBusca;
use App\Models\User;
use App\Services\Match\MotorDeMatch;

beforeEach(function () {
    $this->motor = MotorDeMatch::aPartirDaConfig();
});

function perfilCom(array $atributos): PerfilBusca
{
    return PerfilBusca::factory()->create($atributos);
}

it('casa pela palavra-chave no objeto, ignorando acento e caixa', function () {
    $perfil = perfilCom(['palavras_chave' => ['uniforme']]);

    $contratacao = Contratacao::factory()->create([
        'objeto' => 'AQUISIÇÃO DE UNIFORMES ESCOLARES',
    ]);

    expect($this->motor->avalia($perfil, $contratacao))->toBeTrue();
});

it('encontra por sinônimo cadastrado', function () {
    $perfil = perfilCom(['palavras_chave' => ['uniforme']]);

    $contratacao = Contratacao::factory()->create([
        'objeto' => 'Registro de preços para aquisição de fardamento escolar',
    ]);

    expect($this->motor->avalia($perfil, $contratacao))->toBeTrue();
});

it('encontra a forma plural do termo', function () {
    $perfil = perfilCom(['palavras_chave' => ['medicamento']]);

    $contratacao = Contratacao::factory()->create([
        'objeto' => 'Aquisição de medicamentos para a rede municipal',
    ]);

    expect($this->motor->avalia($perfil, $contratacao))->toBeTrue();
});

it('não casa termo que só aparece no meio de outra palavra', function () {
    $perfil = perfilCom(['palavras_chave' => ['obra']]);

    $contratacao = Contratacao::factory()->create([
        'objeto' => 'Aquisição de manobra hidráulica',
    ]);

    expect($this->motor->avalia($perfil, $contratacao))->toBeFalse();
});

it('descarta quando a palavra excluída aparece', function () {
    $perfil = perfilCom([
        'palavras_chave' => ['uniforme'],
        'palavras_excluidas' => ['merenda'],
    ]);

    $contratacao = Contratacao::factory()->create([
        'objeto' => 'Uniformes e merenda escolar',
    ]);

    expect($this->motor->avalia($perfil, $contratacao))->toBeFalse();
});

it('exige ao menos uma palavra-chave quando há várias', function () {
    $perfil = perfilCom(['palavras_chave' => ['uniforme', 'merenda']]);

    $casa = Contratacao::factory()->create(['objeto' => 'Aquisição de merenda escolar']);
    $naoCasa = Contratacao::factory()->create(['objeto' => 'Aquisição de pneus']);

    expect($this->motor->avalia($perfil, $casa))->toBeTrue()
        ->and($this->motor->avalia($perfil, $naoCasa))->toBeFalse();
});

it('perfil sem palavra-chave aceita qualquer objeto que passe nos filtros', function () {
    $perfil = perfilCom(['palavras_chave' => [], 'ufs' => ['SP']]);

    $contratacao = Contratacao::factory()->create(['uf' => 'SP', 'objeto' => 'Qualquer coisa']);

    expect($this->motor->avalia($perfil, $contratacao))->toBeTrue();
});

it('respeita a faixa de valor', function () {
    $perfil = perfilCom(['palavras_chave' => [], 'valor_min' => 10000, 'valor_max' => 100000]);
    $motor = $this->motor;

    $dentro = Contratacao::factory()->create(['valor_estimado' => 50000]);
    $abaixo = Contratacao::factory()->create(['valor_estimado' => 5000]);
    $acima = Contratacao::factory()->create(['valor_estimado' => 500000]);

    expect($motor->avalia($perfil, $dentro))->toBeTrue()
        ->and($motor->avalia($perfil, $abaixo))->toBeFalse()
        ->and($motor->avalia($perfil, $acima))->toBeFalse();
});

it('não descarta contratação de valor não informado', function () {
    $perfil = perfilCom(['palavras_chave' => [], 'valor_min' => 10000]);

    $semValor = Contratacao::factory()->create(['valor_estimado' => 0]);

    expect($this->motor->avalia($perfil, $semValor))->toBeTrue();
});

it('ignora contratação com prazo de proposta vencido', function () {
    $perfil = perfilCom(['palavras_chave' => ['uniforme']]);

    Contratacao::factory()->create(['objeto' => 'Uniformes escolares']);
    Contratacao::factory()->encerrada()->create(['objeto' => 'Uniformes escolares']);

    $oportunidades = $this->motor->oportunidades([$perfil]);

    expect($oportunidades)->toHaveCount(1)
        ->and($oportunidades[0]['contratacao']->data_encerramento_proposta->isFuture())->toBeTrue();
});

it('filtra por UF antes de avaliar o texto', function () {
    $perfil = perfilCom(['palavras_chave' => ['uniforme'], 'ufs' => ['SP']]);

    Contratacao::factory()->create(['uf' => 'SP', 'objeto' => 'Uniformes escolares']);
    Contratacao::factory()->create(['uf' => 'RJ', 'objeto' => 'Uniformes escolares']);

    $oportunidades = $this->motor->oportunidades([$perfil]);

    expect($oportunidades)->toHaveCount(1)
        ->and($oportunidades[0]['contratacao']->uf)->toBe('SP');
});

it('filtra por modalidade', function () {
    $perfil = perfilCom(['palavras_chave' => [], 'modalidades' => [6]]);

    Contratacao::factory()->create(['modalidade_id' => 6]);
    Contratacao::factory()->create(['modalidade_id' => 4]);

    $oportunidades = $this->motor->oportunidades([$perfil]);

    expect($oportunidades)->toHaveCount(1)
        ->and($oportunidades[0]['contratacao']->modalidade_id)->toBe(6);
});

it('não repete a mesma contratação quando dois perfis casam', function () {
    $user = User::factory()->create();

    $primeiro = perfilCom(['user_id' => $user->id, 'nome' => 'Uniformes', 'palavras_chave' => ['uniforme']]);
    $segundo = perfilCom(['user_id' => $user->id, 'nome' => 'Fardamento', 'palavras_chave' => ['fardamento']]);

    Contratacao::factory()->create(['objeto' => 'Uniformes e fardamento escolar']);

    $oportunidades = $this->motor->oportunidades([$primeiro, $segundo]);

    expect($oportunidades)->toHaveCount(1)
        ->and($oportunidades[0]['perfis'])->toBe(['Uniformes', 'Fardamento']);
});

it('ordena pelo prazo mais curto', function () {
    $perfil = perfilCom(['palavras_chave' => ['uniforme']]);

    Contratacao::factory()->create([
        'objeto' => 'Uniformes A',
        'data_encerramento_proposta' => now()->addDays(10),
    ]);

    Contratacao::factory()->create([
        'objeto' => 'Uniformes B',
        'data_encerramento_proposta' => now()->addDays(2),
    ]);

    $oportunidades = $this->motor->oportunidades([$perfil]);

    expect($oportunidades[0]['contratacao']->objeto)->toBe('Uniformes B');
});

it('ignora perfil inativo', function () {
    $perfil = PerfilBusca::factory()->inativo()->create(['palavras_chave' => ['uniforme']]);

    Contratacao::factory()->create(['objeto' => 'Uniformes escolares']);

    expect($this->motor->oportunidades([$perfil]))->toBe([]);
});
