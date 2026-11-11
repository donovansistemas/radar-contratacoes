<?php

namespace App\Services\Match;

use App\Models\Contratacao;
use App\Models\PerfilBusca;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decide se uma contratação interessa a um perfil de busca.
 *
 * Duas etapas, de propósito:
 *
 * 1. Filtros estruturados (UF, modalidade, valor, prazo) rodam em SQL —
 *    o banco descarta o grosso antes de qualquer coisa chegar no PHP.
 * 2. Filtros de texto (palavras-chave, sinônimos, exclusões) rodam em PHP,
 *    porque dependem de normalização de acento, radical e dicionário de
 *    sinônimos. Colocar isso em SQL significaria `unaccent` + ILIKE por
 *    termo, e o dicionário viraria uma tabela só para economizar pouco.
 *
 * Consequência honesta: a etapa 2 é limitada por `LIMITE_DE_CANDIDATOS`.
 * Se o volume crescer a ponto de estourar, a saída é materializar o match
 * numa tabela (é o que o v0.2 vai precisar para o alerta). Está no README.
 */
class MotorDeMatch
{
    public const LIMITE_DE_CANDIDATOS = 2000;

    /**
     * termo normalizado => variantes (ele incluso), a partir do config.
     *
     * @var array<string, list<string>>
     */
    private array $sinonimos = [];

    /**
     * @param  list<list<string>>  $gruposDeSinonimos
     */
    public function __construct(array $gruposDeSinonimos = [])
    {
        foreach ($gruposDeSinonimos as $grupo) {
            $termos = array_values(array_unique(array_filter(array_map(
                static fn (string $termo): string => Texto::normalizar($termo),
                $grupo,
            ))));

            foreach ($termos as $termo) {
                $this->sinonimos[$termo] = array_values(array_unique([
                    ...($this->sinonimos[$termo] ?? []),
                    ...$termos,
                ]));
            }
        }
    }

    public static function aPartirDaConfig(): self
    {
        /** @var list<list<string>> $grupos */
        $grupos = config('radar.sinonimos', []);

        return new self($grupos);
    }

    /**
     * Consulta base com só o que dá para filtrar em SQL.
     *
     * @return Builder<Contratacao>
     */
    public function candidatos(PerfilBusca $perfil): Builder
    {
        return Contratacao::query()
            ->propostaAberta()
            ->when(
                ! empty($perfil->ufs),
                fn (Builder $query) => $query->whereIn('uf', $perfil->ufs),
            )
            ->when(
                ! empty($perfil->modalidades),
                fn (Builder $query) => $query->whereIn('modalidade_id', $perfil->modalidades),
            )
            ->when(
                $perfil->valor_min !== null,
                fn (Builder $query) => $query->where(
                    fn (Builder $interna) => $interna
                        ->whereNull('valor_estimado')
                        ->orWhere('valor_estimado', 0)
                        ->orWhere('valor_estimado', '>=', $perfil->valor_min),
                ),
            )
            ->when(
                $perfil->valor_max !== null,
                fn (Builder $query) => $query->where(
                    fn (Builder $interna) => $interna
                        ->whereNull('valor_estimado')
                        ->orWhere('valor_estimado', 0)
                        ->orWhere('valor_estimado', '<=', $perfil->valor_max),
                ),
            );
    }

    /**
     * A decisão final, já com texto.
     */
    public function avalia(PerfilBusca $perfil, Contratacao $contratacao): bool
    {
        if (! $this->passaPelaFaixaDeValor($perfil, $contratacao)) {
            return false;
        }

        $texto = Texto::normalizar($contratacao->objeto.' '.$contratacao->informacao_complementar);

        foreach ($perfil->termosExcluidos() as $excluido) {
            if ($this->contemVariante($texto, $excluido)) {
                return false;
            }
        }

        $termos = $perfil->termosDeBusca();

        // Perfil sem palavra-chave é perfil largo: vale qualquer contratação
        // que já tenha passado pelos filtros de UF, modalidade e valor.
        if ($termos === []) {
            return true;
        }

        foreach ($termos as $termo) {
            if ($this->contemVariante($texto, $termo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Oportunidades de um conjunto de perfis, sem repetir contratação.
     *
     * @param  iterable<PerfilBusca>  $perfis
     * @return list<array{contratacao: Contratacao, perfis: list<string>}>
     */
    public function oportunidades(iterable $perfis, int $limite = self::LIMITE_DE_CANDIDATOS): array
    {
        $encontradas = [];

        foreach ($perfis as $perfil) {
            if (! $perfil->ativo) {
                continue;
            }

            $candidatas = $this->candidatos($perfil)
                ->orderBy('data_encerramento_proposta')
                ->limit($limite)
                ->get();

            foreach ($candidatas as $contratacao) {
                if (! $this->avalia($perfil, $contratacao)) {
                    continue;
                }

                $id = $contratacao->getKey();

                if (isset($encontradas[$id])) {
                    $encontradas[$id]['perfis'][] = $perfil->nome;

                    continue;
                }

                $encontradas[$id] = [
                    'contratacao' => $contratacao,
                    'perfis' => [$perfil->nome],
                ];
            }
        }

        return $this->ordenarPorPrazo($encontradas);
    }

    /**
     * @param  array<int, array{contratacao: Contratacao, perfis: list<string>}>  $encontradas
     * @return list<array{contratacao: Contratacao, perfis: list<string>}>
     */
    private function ordenarPorPrazo(array $encontradas): array
    {
        return array_values((new Collection($encontradas))
            ->sortBy(fn (array $item): int => $item['contratacao']->data_encerramento_proposta?->getTimestamp() ?? PHP_INT_MAX)
            ->all());
    }

    /**
     * Valor 0 ou nulo no PNCP significa "não informado" — filtrar por faixa
     * nesse caso esconderia oportunidade boa. Então só filtra o que se sabe.
     */
    private function passaPelaFaixaDeValor(PerfilBusca $perfil, Contratacao $contratacao): bool
    {
        $valor = (float) $contratacao->valor_estimado;

        if ($valor <= 0) {
            return true;
        }

        if ($perfil->valor_min !== null && $valor < (float) $perfil->valor_min) {
            return false;
        }

        if ($perfil->valor_max !== null && $valor > (float) $perfil->valor_max) {
            return false;
        }

        return true;
    }

    /**
     * O termo ou qualquer sinônimo dele aparece no texto.
     */
    private function contemVariante(string $texto, string $termo): bool
    {
        foreach ($this->sinonimos[$termo] ?? [$termo] as $variante) {
            if (Texto::contemTermo($texto, $variante)) {
                return true;
            }
        }

        return false;
    }
}
