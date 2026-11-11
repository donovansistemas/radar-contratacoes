<?php

use App\Services\Pncp\PncpClient;
use App\Services\Pncp\PncpException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * O fixture é uma resposta 200 real do endpoint /v1/contratacoes/proposta
 * (PNCP, 10/11/2026), reduzido a 3 registros. Testar contra o formato que a
 * API realmente devolve é o que impede o teste de virar ficção.
 */
beforeEach(function () {
    $this->client = new PncpClient(
        baseUrl: 'https://pncp.test/api/consulta',
        timeout: 5,
        retryTimes: 3,
        retrySleep: 0,
    );

    $this->payload = json_decode(
        file_get_contents(__DIR__.'/../../Fixtures/pncp-contratacoes-proposta.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
});

it('mapeia a resposta real do PNCP para as colunas da tabela', function () {
    Http::fake(['*' => Http::response($this->payload, 200)]);

    $pagina = $this->client->contratacoesComProposta([
        'dataFinal' => '20261210',
        'pagina' => 1,
        'tamanhoPagina' => 50,
        'uf' => 'SP',
    ]);

    expect($pagina->items)->toHaveCount(3)
        ->and($pagina->numeroPagina)->toBe(1)
        ->and($pagina->totalPaginas)->toBe(1)
        ->and($pagina->totalRegistros)->toBe(3);

    $primeira = $pagina->items[0]->attributes();

    expect($primeira['numero_controle_pncp'])->toBe('46523056000121-1-000258/2025')
        ->and($primeira['orgao_cnpj'])->toBe('46523056000121')
        ->and($primeira['orgao_razao_social'])->toBe('MUNICIPIO DE SUZANO')
        ->and($primeira['orgao_poder'])->toBe('N')
        ->and($primeira['orgao_esfera'])->toBe('M')
        ->and($primeira['uf'])->toBe('SP')
        ->and($primeira['municipio_nome'])->toBe('Suzano')
        ->and($primeira['municipio_codigo_ibge'])->toBe('3552502')
        ->and($primeira['objeto'])->toBe('Aquisição de mobiliário escolar')
        ->and($primeira['valor_estimado'])->toBe(5494858)
        ->and($primeira['valor_homologado'])->toBeNull()
        ->and($primeira['modalidade_id'])->toBe(6)
        ->and($primeira['modalidade_nome'])->toBe('Pregão - Eletrônico')
        ->and($primeira['srp'])->toBeTrue()
        ->and($primeira['ano_compra'])->toBe(2025)
        ->and($primeira['sequencial_compra'])->toBe(258)
        ->and($primeira['data_encerramento_proposta'])->toBe('2026-11-13 09:00:00')
        ->and($primeira['data_publicacao'])->toBe('2025-12-10 07:12:52');
});

it('limpa espaço solto que a API usa no lugar de dado vazio', function () {
    Http::fake(['*' => Http::response($this->payload, 200)]);

    $pagina = $this->client->contratacoesComProposta(['dataFinal' => '20261210']);

    // informacaoComplementar vem como " Para as respostas..." no fixture:
    // o espaço à esquerda não pode virar parte do texto.
    $informacao = $pagina->items[0]->attributes()['informacao_complementar'];

    expect($informacao)->toStartWith('Para as respostas de esclarecimentos')
        ->and($pagina->items[1]->attributes()['link_sistema_origem'])->toBeNull();
});

it('envia só os parâmetros informados', function () {
    Http::fake(['*' => Http::response(['data' => [], 'totalPaginas' => 0], 200)]);

    $this->client->contratacoesComProposta([
        'dataFinal' => '20261210',
        'pagina' => 2,
        'tamanhoPagina' => 50,
        'uf' => 'SP',
    ]);

    Http::assertSent(function (Request $request): bool {
        $url = $request->url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query === [
            'dataFinal' => '20261210',
            'pagina' => '2',
            'tamanhoPagina' => '50',
            'uf' => 'SP',
        ] && str_ends_with((string) parse_url($url, PHP_URL_PATH), '/v1/contratacoes/proposta');
    });
});

it('trata 204 como fim da paginação, não como erro', function () {
    Http::fake(['*' => Http::response(null, 204)]);

    $pagina = $this->client->contratacoesComProposta(['dataFinal' => '20261210', 'pagina' => 400]);

    expect($pagina->isEmpty())->toBeTrue()
        ->and($pagina->numeroPagina)->toBe(400);
});

it('não repete erro de cliente e propaga a mensagem do PNCP', function () {
    Http::fake(['*' => Http::response([
        'path' => '/pncp-consulta/v1/contratacoes/proposta',
        'message' => 'Data Final inválida, deve estar no formato yyyyMMdd.',
        'error' => '422 UNPROCESSABLE_ENTITY',
        'status' => 422,
    ], 422)]);

    expect(fn () => $this->client->contratacoesComProposta(['dataFinal' => '20261210']))
        ->toThrow(PncpException::class, 'Data Final inválida');

    Http::assertSentCount(1);
});

it('repete quando o PNCP devolve erro de servidor', function () {
    Http::fake(['*' => Http::sequence()
        ->push('erro interno', 500)
        ->push($this->payload, 200)]);

    $pagina = $this->client->contratacoesComProposta(['dataFinal' => '20261210']);

    expect($pagina->items)->toHaveCount(3);

    Http::assertSentCount(2);
});

it('repete quando o PNCP pede calma (429)', function () {
    Http::fake(['*' => Http::sequence()
        ->push('Too Many Requests', 429, ['Retry-After' => '1'])
        ->push($this->payload, 200)]);

    $pagina = $this->client->contratacoesComProposta(['dataFinal' => '20261210']);

    expect($pagina->items)->toHaveCount(3);

    Http::assertSentCount(2);
});

it('desiste depois do limite de tentativas', function () {
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn () => $this->client->contratacoesComProposta(['dataFinal' => '20261210']))
        ->toThrow(PncpException::class, 'PNCP indisponível após 3 tentativa(s)');

    Http::assertSentCount(3);
});

it('converte falha de conexão em PncpException', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

    expect(fn () => $this->client->contratacoesComProposta(['dataFinal' => '20261210']))
        ->toThrow(PncpException::class, 'PNCP indisponível após 3 tentativa(s)');
});

it('recusa dataFinal fora do formato antes de chamar a API', function (mixed $dataFinal) {
    Http::fake();

    expect(fn () => $this->client->contratacoesComProposta(['dataFinal' => $dataFinal]))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'formato ISO' => '2026-12-10',
    'vazio' => '',
    'texto qualquer' => 'amanhã',
]);

it('descarta registro sem identificador e mantém o resto da página', function () {
    $payload = $this->payload;
    $payload['data'][] = ['objetoCompra' => 'registro sem numeroControlePNCP'];
    $payload['totalRegistros'] = 4;

    Http::fake(['*' => Http::response($payload, 200)]);

    $pagina = $this->client->contratacoesComProposta(['dataFinal' => '20261210']);

    expect($pagina->items)->toHaveCount(3);
});

it('avisa quando o corpo não tem o envelope esperado', function () {
    Http::fake(['*' => Http::response(['algo' => 'diferente'], 200)]);

    expect(fn () => $this->client->contratacoesComProposta(['dataFinal' => '20261210']))
        ->toThrow(PncpException::class, 'não tem a chave "data"');
});
