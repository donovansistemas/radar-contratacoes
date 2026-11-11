<?php

namespace Database\Factories;

use App\Models\Contratacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contratacao>
 */
class ContratacaoFactory extends Factory
{
    protected $model = Contratacao::class;

    public function definition(): array
    {
        $ano = (int) now()->format('Y');
        $sequencial = $this->faker->unique()->numberBetween(1, 999_999);
        $cnpj = $this->faker->numerify('########0001##');

        return [
            'numero_controle_pncp' => sprintf('%s-1-%06d/%d', $cnpj, $sequencial, $ano),
            'orgao_cnpj' => $cnpj,
            'orgao_razao_social' => 'Prefeitura Municipal de '.$this->faker->city(),
            'orgao_poder' => 'N',
            'orgao_esfera' => 'M',
            'unidade_nome' => 'Setor de Licitações',
            'unidade_codigo' => (string) $this->faker->numberBetween(1, 999_999),
            'uf' => $this->faker->randomElement(['SP', 'RJ', 'MG', 'PR', 'BA']),
            'municipio_nome' => $this->faker->city(),
            'municipio_codigo_ibge' => (string) $this->faker->numberBetween(1_100_000, 5_300_000),
            'objeto' => $this->faker->randomElement([
                'Aquisição de materiais de expediente',
                'Aquisição de gêneros alimentícios para a merenda escolar',
                'Contratação de empresa para prestação de serviços de limpeza',
                'Registro de preços para aquisição de medicamentos',
                'Contratação de serviços de manutenção predial',
                'Aquisição de equipamentos de informática',
            ]),
            'informacao_complementar' => null,
            'valor_estimado' => $this->faker->randomFloat(2, 1_000, 2_000_000),
            'valor_homologado' => null,
            'modalidade_id' => 6,
            'modalidade_nome' => 'Pregão - Eletrônico',
            'modo_disputa_id' => 1,
            'modo_disputa_nome' => 'Aberto',
            'situacao_id' => 1,
            'situacao_nome' => 'Divulgada no PNCP',
            'srp' => $this->faker->boolean(),
            'ano_compra' => $ano,
            'sequencial_compra' => $sequencial,
            'numero_compra' => (string) $this->faker->numberBetween(1, 99_999),
            'processo' => $this->faker->numerify('####/####'),
            'amparo_legal' => 'Lei 14.133/2021, Art. 28, I',
            'tipo_instrumento' => 'Edital',
            'link_sistema_origem' => null,
            'link_processo_eletronico' => null,
            'data_abertura_proposta' => now()->subDay(),
            'data_encerramento_proposta' => now()->addDays($this->faker->numberBetween(1, 30)),
            'data_publicacao' => now()->subDays(10),
            'data_inclusao' => now()->subDays(10),
            'data_atualizacao' => now()->subDay(),
            'importado_em' => now(),
        ];
    }

    /**
     * Contratação cujo prazo de proposta já venceu.
     */
    public function encerrada(): static
    {
        return $this->state(fn (): array => [
            'data_encerramento_proposta' => now()->subDays(3),
        ]);
    }
}
