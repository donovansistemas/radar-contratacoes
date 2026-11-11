<?php

namespace App\Services\Pncp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Cliente da API de consulta do PNCP.
 *
 * Só existe um endpoint aqui: /v1/contratacoes/proposta — as contratações
 * que ainda aceitam proposta. É a peça que alimenta o resto do produto.
 *
 * Regras que vieram de olhar a API de verdade, não da documentação:
 *
 * - `dataFinal` é obrigatório e é o TETO da data de encerramento de propostas.
 *   Pedir hoje + 30 dias devolve tudo que fecha até lá e ainda está aberto.
 * - `pagina` começa em 1 e é obrigatório.
 * - `tamanhoPagina` aceita no máximo 50; 100 já devolve 400. O padrão é 10.
 * - Página além do fim devolve 204 com corpo vazio, não uma lista vazia.
 * - Erro de parâmetro devolve 400/422 com JSON {status, error, message}.
 */
class PncpClient
{
    private const ENDPOINT_PROPOSTA = '/v1/contratacoes/proposta';

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 30,
        private readonly int $retryTimes = 3,
        private readonly int $retrySleep = 1000,
    ) {}

    /**
     * Consulta contratações que ainda aceitam proposta.
     *
     * Chaves aceitas em `$filtros`:
     *
     * - `dataFinal` (obrigatória) — `AAAAMMDD`, teto do encerramento de propostas
     * - `pagina` — começa em 1
     * - `tamanhoPagina` — máximo 50
     * - `uf` — sigla de 2 letras
     * - `codigoMunicipioIbge` — 7 dígitos
     * - `codigoModalidadeContratacao` — ex.: 6 = Pregão Eletrônico
     * - `cnpj` — CNPJ do órgão, só dígitos
     *
     * @param  array<string, mixed>  $filtros
     *
     * @throws PncpException quando o PNCP recusa o pedido ou não responde
     */
    public function contratacoesComProposta(array $filtros): ContratacoesPage
    {
        $dataFinal = $filtros['dataFinal'] ?? null;

        if (! is_string($dataFinal) || preg_match('/^\d{8}$/', $dataFinal) !== 1) {
            throw new InvalidArgumentException(
                'dataFinal é obrigatório e deve estar no formato AAAAMMDD (ex.: 20261130).',
            );
        }

        $pagina = (int) ($filtros['pagina'] ?? 1);

        $query = array_filter(
            [
                'dataFinal' => $dataFinal,
                'pagina' => $pagina,
                'tamanhoPagina' => $filtros['tamanhoPagina'] ?? null,
                'uf' => $filtros['uf'] ?? null,
                'codigoMunicipioIbge' => $filtros['codigoMunicipioIbge'] ?? null,
                'codigoModalidadeContratacao' => $filtros['codigoModalidadeContratacao'] ?? null,
                'cnpj' => $filtros['cnpj'] ?? null,
            ],
            static fn (mixed $valor): bool => $valor !== null && $valor !== '',
        );

        return $this->executar($query, $pagina);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function executar(array $query, int $pagina): ContratacoesPage
    {
        $tentativa = 0;
        $espera = max(0, $this->retrySleep);

        while (true) {
            $tentativa++;

            try {
                $resposta = $this->http()->get(self::ENDPOINT_PROPOSTA, $query);
            } catch (ConnectionException $e) {
                if ($tentativa >= $this->retryTimes) {
                    throw PncpException::indisponivel($tentativa, $e->getMessage());
                }

                $this->aguardar($espera);
                $espera *= 2;

                continue;
            }

            // 204 = página além do fim. Fim da paginação, não erro.
            if ($resposta->status() === 204) {
                return ContratacoesPage::vazia($pagina);
            }

            if ($resposta->successful()) {
                return ContratacoesPage::fromPayload($resposta->json(), $pagina);
            }

            // 4xx = pedido errado. Repetir não conserta.
            if ($resposta->clientError()) {
                throw PncpException::requisicaoInvalida($resposta->status(), $this->mensagemDeErro($resposta));
            }

            if ($tentativa >= $this->retryTimes) {
                throw PncpException::indisponivel($tentativa, 'HTTP '.$resposta->status());
            }

            $this->aguardar($espera);
            $espera *= 2;
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->connectTimeout(min(10, $this->timeout))
            ->timeout($this->timeout)
            ->acceptJson()
            ->withUserAgent('radar-contratacoes/1.0');
    }

    /**
     * O PNCP responde erro no formato {status, error, message}; quando não
     * responde JSON, sobra o corpo cru truncado.
     */
    private function mensagemDeErro(Response $resposta): string
    {
        $json = $resposta->json();

        if (is_array($json) && isset($json['message']) && is_string($json['message'])) {
            return $json['message'];
        }

        return mb_substr(trim($resposta->body()), 0, 200);
    }

    private function aguardar(int $milissegundos): void
    {
        if ($milissegundos > 0) {
            usleep($milissegundos * 1000);
        }
    }
}
