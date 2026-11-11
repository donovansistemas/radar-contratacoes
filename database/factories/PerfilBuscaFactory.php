<?php

namespace Database\Factories;

use App\Models\PerfilBusca;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerfilBusca>
 */
class PerfilBuscaFactory extends Factory
{
    protected $model = PerfilBusca::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nome' => 'Perfil '.$this->faker->word(),
            'palavras_chave' => ['uniforme'],
            'palavras_excluidas' => [],
            'ufs' => [],
            'modalidades' => [],
            'valor_min' => null,
            'valor_max' => null,
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn (): array => ['ativo' => false]);
    }
}
