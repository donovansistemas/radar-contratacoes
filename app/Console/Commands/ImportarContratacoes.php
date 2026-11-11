<?php

namespace App\Console\Commands;

use App\Models\Contratacao;
use App\Services\Pncp\PncpClient;
use App\Services\Pncp\PncpException;
use Illuminate\Console\Command;

/**
 * Varre o PNCP e popula a tabela `contratacoes`.
 *
 * Rodar duas vezes seguidas não duplica nada: o `numero_controle_pncp` é
 * único, e o comando faz upsert por ele. Contratação que mudou de valor ou
 * de prazo é atualizada, não reinserida.
 */
class ImportarContratacoes extends Command
{
    protected $signature = 'radar:importar
        {--uf= : Restringe a uma UF (ex.: SP)}
        {--modalidade= : Código da modalidade de contratação (ex.: 6 = Pregão Eletrônico)}
        {--dias= : Janela, em dias a partir de hoje, para o encerramento das propostas}
        {--paginas= : Máximo de páginas por execução}';

    protected $description = 'Importa do PNCP as contratações que ainda aceitam proposta';

    public function handle(PncpClient $pncp): int
    {
        $dias = $this->opcaoInteira('dias', (int) config('pncp.importacao.janela_dias'), 0);
        $paginas = $this->opcaoInteira('paginas', (int) config('pncp.importacao.paginas_por_execucao'), 1);
        $dataFinal = now()->addDays($dias)->format('Ymd');

        $filtros = [
            'dataFinal' => $dataFinal,
            'tamanhoPagina' => (int) config('pncp.importacao.tamanho_pagina'),
            'uf' => $this->uf(),
            'codigoModalidadeContratacao' => $this->modalidade(),
        ];

        $this->components->info(sprintf(
            'PNCP — propostas abertas até %s (hoje + %d dias).',
            now()->addDays($dias)->format('d/m/Y'),
            $dias,
        ));

        $lidas = 0;
        $novas = 0;
        $atualizadas = 0;
        $resumo = [];

        for ($pagina = 1; $pagina <= $paginas; $pagina++) {
            try {
                $resultado = $pncp->contratacoesComProposta($filtros + ['pagina' => $pagina]);
            } catch (PncpException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            if ($resultado->isEmpty()) {
                break;
            }

            $rows = array_map(
                static fn ($contratacao) => $contratacao->attributes(),
                $resultado->items,
            );

            $existentes = Contratacao::query()
                ->whereIn('numero_controle_pncp', array_column($rows, 'numero_controle_pncp'))
                ->count();

            $agora = now()->toDateTimeString();

            $rows = array_map(
                static function (array $row) use ($agora): array {
                    $row['created_at'] = $agora;
                    $row['updated_at'] = $agora;

                    return $row;
                },
                $rows,
            );

            Contratacao::upsert($rows, ['numero_controle_pncp'], $this->colunasAtualizaveis());

            $lidas += count($rows);
            $novas += count($rows) - $existentes;
            $atualizadas += $existentes;

            $resumo[] = [
                'Pagina' => $pagina,
                'Lidas' => count($rows),
                'Novas' => count($rows) - $existentes,
                'Atualizadas' => $existentes,
            ];

            if (! $resultado->temProximaPagina()) {
                break;
            }

            // Educação com uma API pública: não metralhar o servidor deles.
            usleep(300 * 1000);
        }

        if ($resumo === []) {
            $this->components->warn('Nenhuma contratação retornada para os filtros informados.');

            return self::SUCCESS;
        }

        $this->table(['Pagina', 'Lidas', 'Novas', 'Atualizadas'], $resumo);

        $this->components->info(sprintf(
            'Total: %d lidas, %d novas, %d atualizadas.',
            $lidas,
            $novas,
            $atualizadas,
        ));

        return self::SUCCESS;
    }

    private function opcaoInteira(string $nome, int $padrao, int $minimo): int
    {
        $valor = $this->option($nome);

        return $valor === null || $valor === ''
            ? $padrao
            : max($minimo, (int) $valor);
    }

    private function uf(): ?string
    {
        $uf = $this->option('uf');

        return $uf === null || $uf === '' ? null : mb_strtoupper(mb_substr(trim($uf), 0, 2));
    }

    private function modalidade(): ?int
    {
        $modalidade = $this->option('modalidade');

        return $modalidade === null || $modalidade === '' ? null : (int) $modalidade;
    }

    /**
     * Tudo que pode mudar entre duas importações da mesma contratação.
     *
     * @return list<string>
     */
    private function colunasAtualizaveis(): array
    {
        return [
            ...array_values(array_diff(
                (new Contratacao)->getFillable(),
                ['numero_controle_pncp'],
            )),
            'updated_at',
        ];
    }
}
