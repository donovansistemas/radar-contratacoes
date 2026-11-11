<?php

namespace App\Models;

use App\Support\Texto;
use Database\Factories\PerfilBuscaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um perfil de busca: as regras que decidem quais contratações avisam o usuário.
 *
 * @property int $id
 * @property int $user_id
 * @property string $nome
 * @property list<string> $palavras_chave
 * @property list<string>|null $palavras_excluidas
 * @property list<string>|null $ufs
 * @property list<int>|null $modalidades
 * @property string|null $valor_min
 * @property string|null $valor_max
 * @property bool $ativo
 */
#[Table('perfis_busca')]
#[Fillable([
    'user_id',
    'nome',
    'palavras_chave',
    'palavras_excluidas',
    'ufs',
    'modalidades',
    'valor_min',
    'valor_max',
    'ativo',
])]
class PerfilBusca extends Model
{
    /** @use HasFactory<PerfilBuscaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'palavras_chave' => 'array',
            'palavras_excluidas' => 'array',
            'ufs' => 'array',
            'modalidades' => 'array',
            'valor_min' => 'decimal:2',
            'valor_max' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, covariant $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<PerfilBusca>  $query
     */
    #[Scope]
    protected function ativos(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /**
     * Lista de termos já limpa: minúscula, sem acento, sem item vazio.
     *
     * @return list<string>
     */
    public function termosDeBusca(): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $termo): string => Texto::normalizar((string) $termo), $this->palavras_chave ?? []),
            static fn (string $termo): bool => $termo !== '',
        ));
    }

    /**
     * @return list<string>
     */
    public function termosExcluidos(): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $termo): string => Texto::normalizar((string) $termo), $this->palavras_excluidas ?? []),
            static fn (string $termo): bool => $termo !== '',
        ));
    }
}
