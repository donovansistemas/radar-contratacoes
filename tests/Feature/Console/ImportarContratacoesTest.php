<?php

use App\Models\Contratacao;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['pncp.retry.sleep' => 0]);

    $this->payload = json_decode(
        file_get_contents(__DIR__.'/../../Fixtures/pncp-contratacoes-proposta.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
});

it('importa as contratações do PNCP para o banco', function () {
    Http::fake(['*' => Http::response($this->payload, 200)]);

    $this->artisan('radar:importar', ['--paginas' => 1])->assertSuccessful();

    expect(Contratacao::count())->toBe(3);

    $contratacao = Contratacao::where('numero_controle_pncp', '46523056000121-1-000258/2025')->sole();

    expect($contratacao->uf)->toBe('SP')
        ->and($contratacao->municipio_nome)->toBe('Suzano')
        ->and($contratacao->objeto)->toBe('Aquisição de mobiliário escolar')
        ->and($contratacao->modalidade_id)->toBe(6)
        ->and($contratacao->importado_em)->not->toBeNull();
});

it('não duplica quando roda duas vezes seguidas', function () {
    Http::fake(['*' => Http::response($this->payload, 200)]);

    $this->artisan('radar:importar', ['--paginas' => 1])->assertSuccessful();
    $this->artisan('radar:importar', ['--paginas' => 1])->assertSuccessful();

    expect(Contratacao::count())->toBe(3)
        ->and(Contratacao::where('numero_controle_pncp', '46523056000121-1-000258/2025')->count())->toBe(1);
});

it('atualiza a contratação quando o órgão muda o valor ou o prazo', function () {
    $alterado = $this->payload;
    $alterado['data'][0]['valorTotalEstimado'] = 999999.99;
    $alterado['data'][0]['dataEncerramentoProposta'] = '2026-12-20T18:00:00';

    // Uma resposta por execução: a primeira importa, a segunda traz a alteração.
    Http::fake(['*' => Http::sequence()
        ->push($this->payload, 200)
        ->push($alterado, 200)]);

    $this->artisan('radar:importar', ['--paginas' => 1])->assertSuccessful();
    $this->artisan('radar:importar', ['--paginas' => 1])->assertSuccessful();

    $contratacao = Contratacao::where('numero_controle_pncp', '46523056000121-1-000258/2025')->sole();

    expect((float) $contratacao->valor_estimado)->toBe(999999.99)
        ->and($contratacao->data_encerramento_proposta->format('Y-m-d H:i:s'))->toBe('2026-12-20 18:00:00')
        ->and(Contratacao::count())->toBe(3);
});

it('para a paginação quando o PNCP devolve página vazia (204)', function () {
    Http::fake(['*' => Http::response(null, 204)]);

    $this->artisan('radar:importar', ['--paginas' => 5])->assertSuccessful();

    expect(Contratacao::count())->toBe(0);

    Http::assertSentCount(1);
});

it('falha com mensagem clara quando o PNCP está fora do ar', function () {
    Http::fake(['*' => Http::response('', 503)]);

    $this->artisan('radar:importar', ['--paginas' => 1])->assertFailed();

    expect(Contratacao::count())->toBe(0);
});

it('repassa os filtros de UF e modalidade para a API', function () {
    Http::fake(['*' => Http::response($this->payload, 200)]);

    $this->artisan('radar:importar', [
        '--uf' => 'sp',
        '--modalidade' => 6,
        '--dias' => 15,
        '--paginas' => 1,
    ])->assertSuccessful();

    Http::assertSent(function ($request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['uf'] === 'SP'
            && $query['codigoModalidadeContratacao'] === '6'
            && $query['dataFinal'] === now()->addDays(15)->format('Ymd');
    });
});
