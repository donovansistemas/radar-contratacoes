<?php

namespace App\Models;

use Database\Factories\ContratacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Uma contratação pública importada do PNCP.
 *
 * @property int $id
 * @property string $numero_controle_pncp
 * @property string|null $orgao_cnpj
 * @property string|null $orgao_razao_social
 * @property string|null $uf
 * @property string|null $municipio_nome
 * @property string|null $objeto
 * @property string|null $valor_estimado
 * @property int|null $modalidade_id
 * @property string|null $modalidade_nome
 * @property Carbon|null $data_encerramento_proposta
 * @property Carbon|null $importado_em
 */
#[Table('contratacoes')]
#[Fillable([
    'numero_controle_pncp',
    'orgao_cnpj',
    'orgao_razao_social',
    'orgao_poder',
    'orgao_esfera',
    'unidade_nome',
    'unidade_codigo',
    'uf',
    'municipio_nome',
    'municipio_codigo_ibge',
    'objeto',
    'informacao_complementar',
    'valor_estimado',
    'valor_homologado',
    'modalidade_id',
    'modalidade_nome',
    'modo_disputa_id',
    'modo_disputa_nome',
    'situacao_id',
    'situacao_nome',
    'srp',
    'ano_compra',
    'sequencial_compra',
    'numero_compra',
    'processo',
    'amparo_legal',
    'tipo_instrumento',
    'link_sistema_origem',
    'link_processo_eletronico',
    'data_abertura_proposta',
    'data_encerramento_proposta',
    'data_publicacao',
    'data_inclusao',
    'data_atualizacao',
    'importado_em',
])]
class Contratacao extends Model
{
    /** @use HasFactory<ContratacaoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'srp' => 'boolean',
            'valor_estimado' => 'decimal:2',
            'valor_homologado' => 'decimal:2',
            'ano_compra' => 'integer',
            'sequencial_compra' => 'integer',
            'modalidade_id' => 'integer',
            'modo_disputa_id' => 'integer',
            'situacao_id' => 'integer',
            'data_abertura_proposta' => 'datetime',
            'data_encerramento_proposta' => 'datetime',
            'data_publicacao' => 'datetime',
            'data_inclusao' => 'datetime',
            'data_atualizacao' => 'datetime',
            'importado_em' => 'datetime',
        ];
    }

    /**
     * Só o que ainda aceita proposta. É o que interessa ao usuário:
     * edital encerrado não é oportunidade, é histórico.
     *
     * @param  Builder<Contratacao>  $query
     */
    #[Scope]
    protected function propostaAberta(Builder $query): void
    {
        $query->where('data_encerramento_proposta', '>=', now());
    }

    /**
     * Página oficial da contratação no PNCP. É o link que o usuário clica
     * para ler o edital — nunca inventamos um endereço nosso no lugar.
     */
    public function urlPncp(): ?string
    {
        if ($this->orgao_cnpj === null || $this->ano_compra === null || $this->sequencial_compra === null) {
            return null;
        }

        return sprintf(
            'https://pncp.gov.br/app/editais/%s/%d/%d',
            $this->orgao_cnpj,
            $this->ano_compra,
            $this->sequencial_compra,
        );
    }
}
