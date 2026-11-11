<?php

namespace App\Services\Pncp;

use Illuminate\Support\Facades\Log;

/**
 * Uma página de resultados de /v1/contratacoes/proposta.
 *
 * O envelope do PNCP traz `data`, `totalRegistros`, `totalPaginas`,
 * `numeroPagina` e `paginasRestantes`. Guardamos o que serve para
 * controlar a paginação e descartamos o resto.
 */
final readonly class ContratacoesPage
{
    /**
     * @param  list<ContratacaoData>  $items
     */
    public function __construct(
        public array $items,
        public int $numeroPagina = 1,
        public int $totalPaginas = 0,
        public int $totalRegistros = 0,
    ) {}

    /**
     * O PNCP devolve 204 (sem corpo) quando a página pedida passou do fim.
     */
    public static function vazia(int $numeroPagina = 1): self
    {
        return new self(items: [], numeroPagina: $numeroPagina);
    }

    /**
     * @param  mixed  $payload  Corpo já decodificado da resposta 200.
     *
     * @throws PncpException
     */
    public static function fromPayload(mixed $payload, int $numeroPagina = 1): self
    {
        if (! is_array($payload) || ! array_key_exists('data', $payload)) {
            throw PncpException::respostaInvalida('o corpo não tem a chave "data"');
        }

        if (! is_array($payload['data'])) {
            throw PncpException::respostaInvalida('"data" não é uma lista');
        }

        $items = [];
        $descartados = 0;

        foreach ($payload['data'] as $registro) {
            if (! is_array($registro)) {
                $descartados++;

                continue;
            }

            try {
                $items[] = ContratacaoData::fromArray($registro);
            } catch (PncpException $e) {
                // Um registro podre não pode derrubar a página inteira:
                // importa-se o que dá, registra-se o que sobrou.
                $descartados++;
                Log::warning('Contratação descartada na importação do PNCP.', [
                    'motivo' => $e->getMessage(),
                    'numero_controle_pncp' => $registro['numeroControlePNCP'] ?? null,
                ]);
            }
        }

        return new self(
            items: $items,
            numeroPagina: (int) ($payload['numeroPagina'] ?? $numeroPagina),
            totalPaginas: (int) ($payload['totalPaginas'] ?? 0),
            totalRegistros: (int) ($payload['totalRegistros'] ?? 0),
        );
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function temProximaPagina(): bool
    {
        return $this->numeroPagina < $this->totalPaginas;
    }
}
