<?php

namespace App\Services\Dashboard;

use App\Models\Contratacao;
use App\Models\PerfilBusca;
use App\Services\Match\MotorDeMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Números da tela inicial.
 *
 * Tudo aqui é calculado sobre as oportunidades que casam com os perfis ativos
 * do usuário — é o radar dele, não um censo do PNCP. O total da base importada
 * aparece só como contexto, para o usuário saber que existe dado por trás.
 *
 * A agregação roda em PHP sobre as oportunidades já filtradas. Isso é coerente
 * com a listagem (mesma fonte, mesmo resultado) e evita duas definições de
 * "oportunidade" no sistema.
 */
final readonly class ResumoDoRadar
{
    /**
     * @param  list<array{rotulo: string, quantidade: int, valor: float}>  $porUf
     * @param  list<array{rotulo: string, quantidade: int, valor: float}>  $porModalidade
     * @param  list<array{rotulo: string, quantidade: int, valor: float}>  $porFaixaDeValor
     * @param  list<array{rotulo: string, quantidade: int, valor: float}>  $porMesDeEncerramento
     */
    public function __construct(
        public int $oportunidades,
        public float $valorEstimado,
        public int $encerrandoEmSeteDias,
        public array $porUf,
        public array $porModalidade,
        public array $porFaixaDeValor,
        public array $porMesDeEncerramento,
        public int $baseImportada,
        public ?Carbon $ultimaImportacao,
    ) {}

    /**
     * @param  iterable<PerfilBusca>  $perfis
     */
    public static function calcular(MotorDeMatch $motor, iterable $perfis): self
    {
        $oportunidades = new Collection(
            array_map(
                static fn (array $item): Contratacao => $item['contratacao'],
                $motor->oportunidades($perfis),
            ),
        );

        return new self(
            oportunidades: $oportunidades->count(),
            valorEstimado: (float) $oportunidades->sum(static fn (Contratacao $c): float => (float) $c->valor_estimado),
            encerrandoEmSeteDias: $oportunidades->filter(
                static fn (Contratacao $c): bool => $c->data_encerramento_proposta !== null
                    && $c->data_encerramento_proposta->lessThanOrEqualTo(now()->addDays(7)),
            )->count(),
            porUf: self::agrupar($oportunidades, static fn (Contratacao $c): string => $c->uf ?? 'sem UF'),
            porModalidade: self::agrupar($oportunidades, static fn (Contratacao $c): string => $c->modalidade_nome ?? 'sem modalidade'),
            porFaixaDeValor: self::porFaixaDeValor($oportunidades),
            porMesDeEncerramento: self::porMes($oportunidades),
            baseImportada: Contratacao::query()->count(),
            ultimaImportacao: Contratacao::query()->max('importado_em') === null
                ? null
                : Carbon::parse(Contratacao::query()->max('importado_em')),
        );
    }

    /**
     * @param  Collection<int, Contratacao>  $oportunidades
     * @param  callable(Contratacao): string  $chave
     * @return list<array{rotulo: string, quantidade: int, valor: float}>
     */
    private static function agrupar(Collection $oportunidades, callable $chave): array
    {
        return array_values($oportunidades
            ->groupBy($chave)
            ->map(static fn (Collection $grupo, string $rotulo): array => [
                'rotulo' => $rotulo,
                'quantidade' => $grupo->count(),
                'valor' => (float) $grupo->sum(static fn (Contratacao $c): float => (float) $c->valor_estimado),
            ])
            ->sortByDesc('quantidade')
            ->all());
    }

    /**
     * @param  Collection<int, Contratacao>  $oportunidades
     * @return list<array{rotulo: string, quantidade: int, valor: float}>
     */
    private static function porFaixaDeValor(Collection $oportunidades): array
    {
        $faixas = [
            'Até R$ 50 mil' => static fn (float $v): bool => $v > 0 && $v <= 50_000,
            'R$ 50 mil – 200 mil' => static fn (float $v): bool => $v > 50_000 && $v <= 200_000,
            'R$ 200 mil – 1 mi' => static fn (float $v): bool => $v > 200_000 && $v <= 1_000_000,
            'Acima de R$ 1 mi' => static fn (float $v): bool => $v > 1_000_000,
            'Não informado' => static fn (float $v): bool => $v <= 0,
        ];

        $resultado = [];

        foreach ($faixas as $rotulo => $criterio) {
            $grupo = $oportunidades->filter(
                static fn (Contratacao $c): bool => $criterio((float) $c->valor_estimado),
            );

            if ($grupo->isEmpty()) {
                continue;
            }

            $resultado[] = [
                'rotulo' => $rotulo,
                'quantidade' => $grupo->count(),
                'valor' => (float) $grupo->sum(static fn (Contratacao $c): float => (float) $c->valor_estimado),
            ];
        }

        return $resultado;
    }

    /**
     * Distribuição das oportunidades abertas pelos próximos meses de
     * encerramento. Não é série histórica — o banco ainda não guarda
     * importação acumulada o suficiente para isso.
     *
     * @param  Collection<int, Contratacao>  $oportunidades
     * @return list<array{rotulo: string, quantidade: int, valor: float}>
     */
    private static function porMes(Collection $oportunidades): array
    {
        $meses = [
            1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr', 5 => 'mai', 6 => 'jun',
            7 => 'jul', 8 => 'ago', 9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez',
        ];

        return array_values($oportunidades
            ->filter(static fn (Contratacao $c): bool => $c->data_encerramento_proposta !== null)
            ->groupBy(static fn (Contratacao $c): string => $c->data_encerramento_proposta->format('Y-m'))
            ->sortKeys()
            ->take(6)
            ->map(static function (Collection $grupo, string $chave) use ($meses): array {
                [$ano, $mes] = explode('-', $chave);

                return [
                    'rotulo' => $meses[(int) $mes].'/'.$ano,
                    'quantidade' => $grupo->count(),
                    'valor' => (float) $grupo->sum(static fn (Contratacao $c): float => (float) $c->valor_estimado),
                ];
            })
            ->all());
    }
}
