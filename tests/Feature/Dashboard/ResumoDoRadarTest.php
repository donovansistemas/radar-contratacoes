<?php

use App\Models\Contratacao;
use App\Models\PerfilBusca;
use App\Models\User;
use App\Services\Dashboard\ResumoDoRadar;
use App\Services\Match\MotorDeMatch;

function resumoDe(User $user): ResumoDoRadar
{
    $perfis = $user->perfisBusca()->where('ativo', true)->get();

    return ResumoDoRadar::calcular(MotorDeMatch::aPartirDaConfig(), $perfis);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    PerfilBusca::factory()->for($this->user)->create([
        'palavras_chave' => [],
        'ufs' => [],
        'modalidades' => [],
    ]);
});

it('soma quantidade e valor das oportunidades abertas', function () {
    Contratacao::factory()->create(['valor_estimado' => 1000, 'uf' => 'SP']);
    Contratacao::factory()->create(['valor_estimado' => 3000, 'uf' => 'RJ']);
    Contratacao::factory()->create(['valor_estimado' => 0, 'uf' => 'SP']);

    $resumo = resumoDe($this->user);

    expect($resumo->oportunidades)->toBe(3)
        ->and($resumo->valorEstimado)->toBe(4000.0)
        ->and($resumo->baseImportada)->toBe(3);
});

it('agrupa por UF e por modalidade, do maior para o menor', function () {
    Contratacao::factory()->count(2)->create(['uf' => 'SP', 'modalidade_id' => 6, 'modalidade_nome' => 'Pregão - Eletrônico']);
    Contratacao::factory()->create(['uf' => 'RJ', 'modalidade_id' => 4, 'modalidade_nome' => 'Concorrência - Eletrônica']);

    $resumo = resumoDe($this->user);

    expect($resumo->porUf)->toBe([
        ['rotulo' => 'SP', 'quantidade' => 2, 'valor' => $resumo->porUf[0]['valor']],
        ['rotulo' => 'RJ', 'quantidade' => 1, 'valor' => $resumo->porUf[1]['valor']],
    ])->and($resumo->porModalidade[0]['rotulo'])->toBe('Pregão - Eletrônico')
        ->and($resumo->porModalidade[0]['quantidade'])->toBe(2);
});

it('distribui por faixa de valor, incluindo valor não informado', function () {
    Contratacao::factory()->create(['valor_estimado' => 30000]);
    Contratacao::factory()->create(['valor_estimado' => 90000]);
    Contratacao::factory()->create(['valor_estimado' => 800000]);
    Contratacao::factory()->create(['valor_estimado' => 5000000]);
    Contratacao::factory()->create(['valor_estimado' => 0]);

    $faixas = collect(resumoDe($this->user)->porFaixaDeValor)->pluck('quantidade', 'rotulo');

    expect($faixas['Até R$ 50 mil'])->toBe(1)
        ->and($faixas['R$ 50 mil – 200 mil'])->toBe(1)
        ->and($faixas['R$ 200 mil – 1 mi'])->toBe(1)
        ->and($faixas['Acima de R$ 1 mi'])->toBe(1)
        ->and($faixas['Não informado'])->toBe(1);
});

it('conta só o que encerra nos próximos sete dias', function () {
    Contratacao::factory()->create(['data_encerramento_proposta' => now()->addDays(3)]);
    Contratacao::factory()->create(['data_encerramento_proposta' => now()->addDays(20)]);

    expect(resumoDe($this->user)->encerrandoEmSeteDias)->toBe(1);
});

it('agrupa por mês de encerramento', function () {
    Contratacao::factory()->create(['data_encerramento_proposta' => now()->addDays(2)]);
    Contratacao::factory()->create(['data_encerramento_proposta' => now()->addDays(4)]);
    Contratacao::factory()->create(['data_encerramento_proposta' => now()->addDays(45)]);

    $meses = resumoDe($this->user)->porMesDeEncerramento;

    expect($meses)->toHaveCount(2)
        ->and($meses[0]['quantidade'])->toBe(2);
});

it('não conta contratação encerrada nas oportunidades, mas conta na base', function () {
    Contratacao::factory()->create();
    Contratacao::factory()->encerrada()->create();

    $resumo = resumoDe($this->user);

    expect($resumo->oportunidades)->toBe(1)
        ->and($resumo->baseImportada)->toBe(2);
});

it('registra a data da última importação', function () {
    $resumo = resumoDe($this->user);

    expect($resumo->ultimaImportacao)->toBeNull();

    Contratacao::factory()->create(['importado_em' => now()->subHours(2)]);

    expect(resumoDe($this->user)->ultimaImportacao)->not->toBeNull();
});
