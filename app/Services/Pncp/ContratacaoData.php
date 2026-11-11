<?php

namespace App\Services\Pncp;

use Illuminate\Support\Carbon;

/**
 * Uma contratação do PNCP, já normalizada para as colunas da tabela.
 *
 * A conversão do JSON para cá é o único lugar do sistema que conhece o
 * formato da API. O resto do código (job, match, tela) trabalha com o array
 * que `attributes()` devolve.
 */
final readonly class ContratacaoData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function __construct(private array $attributes) {}

    /**
     * @param  array<string, mixed>  $payload  Um item de `data[]` em /v1/contratacoes/proposta.
     *
     * @throws PncpException quando vem sem o identificador único
     */
    public static function fromArray(array $payload): self
    {
        $numeroControle = self::texto($payload['numeroControlePNCP'] ?? null);

        if ($numeroControle === null) {
            throw PncpException::respostaInvalida('contratação sem numeroControlePNCP não pode ser deduplicada');
        }

        $orgao = is_array($payload['orgaoEntidade'] ?? null) ? $payload['orgaoEntidade'] : [];
        $unidade = is_array($payload['unidadeOrgao'] ?? null) ? $payload['unidadeOrgao'] : [];
        $amparo = is_array($payload['amparoLegal'] ?? null) ? $payload['amparoLegal'] : [];

        return new self([
            'numero_controle_pncp' => $numeroControle,
            'orgao_cnpj' => self::texto($orgao['cnpj'] ?? null),
            'orgao_razao_social' => self::texto($orgao['razaoSocial'] ?? null),
            'orgao_poder' => self::texto($orgao['poderId'] ?? null),
            'orgao_esfera' => self::texto($orgao['esferaId'] ?? null),
            'unidade_nome' => self::texto($unidade['nomeUnidade'] ?? null),
            'unidade_codigo' => self::texto($unidade['codigoUnidade'] ?? null),
            'uf' => self::siglaUf($unidade['ufSigla'] ?? null),
            'municipio_nome' => self::texto($unidade['municipioNome'] ?? null),
            'municipio_codigo_ibge' => self::texto($unidade['codigoIbge'] ?? null),
            'objeto' => self::texto($payload['objetoCompra'] ?? null),
            'informacao_complementar' => self::texto($payload['informacaoComplementar'] ?? null),
            'valor_estimado' => self::valor($payload['valorTotalEstimado'] ?? null),
            'valor_homologado' => self::valor($payload['valorTotalHomologado'] ?? null),
            'modalidade_id' => self::inteiro($payload['modalidadeId'] ?? null),
            'modalidade_nome' => self::texto($payload['modalidadeNome'] ?? null),
            'modo_disputa_id' => self::inteiro($payload['modoDisputaId'] ?? null),
            'modo_disputa_nome' => self::texto($payload['modoDisputaNome'] ?? null),
            'situacao_id' => self::inteiro($payload['situacaoCompraId'] ?? null),
            'situacao_nome' => self::texto($payload['situacaoCompraNome'] ?? null),
            'srp' => (bool) ($payload['srp'] ?? false),
            'ano_compra' => self::inteiro($payload['anoCompra'] ?? null),
            'sequencial_compra' => self::inteiro($payload['sequencialCompra'] ?? null),
            'numero_compra' => self::texto($payload['numeroCompra'] ?? null),
            'processo' => self::texto($payload['processo'] ?? null),
            'amparo_legal' => self::texto($amparo['nome'] ?? null),
            'tipo_instrumento' => self::texto($payload['tipoInstrumentoConvocatorioNome'] ?? null),
            'link_sistema_origem' => self::texto($payload['linkSistemaOrigem'] ?? null),
            'link_processo_eletronico' => self::texto($payload['linkProcessoEletronico'] ?? null),
            'data_abertura_proposta' => self::data($payload['dataAberturaProposta'] ?? null),
            'data_encerramento_proposta' => self::data($payload['dataEncerramentoProposta'] ?? null),
            'data_publicacao' => self::data($payload['dataPublicacaoPncp'] ?? null),
            'data_inclusao' => self::data($payload['dataInclusao'] ?? null),
            'data_atualizacao' => self::data($payload['dataAtualizacao'] ?? null),
            'importado_em' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function numeroControlePncp(): string
    {
        return $this->attributes['numero_controle_pncp'];
    }

    public function objeto(): string
    {
        return $this->attributes['objeto'] ?? '';
    }

    public function uf(): ?string
    {
        return $this->attributes['uf'];
    }

    /**
     * A API devolve bastante string com espaço solto (`" "`) onde não há dado.
     * Isso não é valor: vira null, senão a tela mostra campo "preenchido" vazio.
     */
    private static function texto(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private static function valor(mixed $valor): float|int|null
    {
        return is_numeric($valor) ? $valor + 0 : null;
    }

    private static function inteiro(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }

    private static function siglaUf(mixed $valor): ?string
    {
        $texto = self::texto($valor);

        return $texto === null ? null : mb_strtoupper(mb_substr($texto, 0, 2));
    }

    /**
     * As datas chegam como `2026-11-13T09:00:00`, sem fuso. Guardar como
     * `Y-m-d H:i:s` mantém o horário que o órgão publicou — e deixa o array
     * pronto para `upsert()`, que não passa pelos casts do model.
     */
    private static function data(mixed $valor): ?string
    {
        $texto = self::texto($valor);

        if ($texto === null) {
            return null;
        }

        try {
            return Carbon::parse($texto)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
