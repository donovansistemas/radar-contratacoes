<?php

namespace App\Livewire;

use App\Models\PerfilBusca;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Perfis de busca')]
class Perfis extends Component
{
    public ?int $editando = null;

    public string $nome = '';

    public string $palavrasChave = '';

    public string $palavrasExcluidas = '';

    /** @var list<string> */
    public array $ufs = [];

    /** @var list<int> */
    public array $modalidades = [];

    public string $valorMin = '';

    public string $valorMax = '';

    public bool $ativo = true;

    public function novo(): void
    {
        $this->resetFormulario();

        Flux::modal('formulario-perfil')->show();
    }

    public function editar(int $id): void
    {
        $perfil = $this->perfis()->findOrFail($id);

        $this->editando = $perfil->id;
        $this->nome = $perfil->nome;
        $this->palavrasChave = implode(', ', $perfil->palavras_chave ?? []);
        $this->palavrasExcluidas = implode(', ', $perfil->palavras_excluidas ?? []);
        $this->ufs = $perfil->ufs ?? [];
        $this->modalidades = array_map(intval(...), $perfil->modalidades ?? []);
        $this->valorMin = $perfil->valor_min === null ? '' : (string) $perfil->valor_min;
        $this->valorMax = $perfil->valor_max === null ? '' : (string) $perfil->valor_max;
        $this->ativo = $perfil->ativo;

        Flux::modal('formulario-perfil')->show();
    }

    public function salvar(): void
    {
        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:100'],
            'palavrasChave' => ['nullable', 'string', 'max:500'],
            'palavrasExcluidas' => ['nullable', 'string', 'max:500'],
            'ufs' => ['array'],
            'ufs.*' => ['string', Rule::in(config('radar.ufs'))],
            'modalidades' => ['array'],
            'modalidades.*' => ['integer', Rule::in(array_keys(config('radar.modalidades')))],
            'valorMin' => ['nullable', 'numeric', 'min:0'],
            'valorMax' => ['nullable', 'numeric', 'min:0'],
            'ativo' => ['boolean'],
        ], [
            'nome.required' => 'Dê um nome ao perfil.',
            'ufs.*.in' => 'UF inválida.',
            'modalidades.*.in' => 'Modalidade inválida.',
            'valorMin.numeric' => 'O valor mínimo precisa ser um número.',
            'valorMax.numeric' => 'O valor máximo precisa ser um número.',
        ]);

        // Comparação de faixa fora do `validate`: com campos opcionais, o `gte`
        // do Laravel compara contra string vazia e o erro sai confuso.
        if ($dados['valorMin'] !== '' && $dados['valorMax'] !== '' && (float) $dados['valorMax'] < (float) $dados['valorMin']) {
            $this->addError('valorMax', 'O valor máximo não pode ser menor que o mínimo.');

            return;
        }

        $atributos = [
            'nome' => $dados['nome'],
            'palavras_chave' => $this->listaDeTermos($dados['palavrasChave']),
            'palavras_excluidas' => $this->listaDeTermos($dados['palavrasExcluidas']),
            'ufs' => array_values($dados['ufs']),
            'modalidades' => array_map(intval(...), $dados['modalidades']),
            'valor_min' => $dados['valorMin'] === '' ? null : $dados['valorMin'],
            'valor_max' => $dados['valorMax'] === '' ? null : $dados['valorMax'],
            'ativo' => $this->ativo,
        ];

        if ($this->editando !== null) {
            $this->perfis()->findOrFail($this->editando)->update($atributos);

            Flux::toast(variant: 'success', text: 'Perfil atualizado.');
        } else {
            $this->perfis()->create($atributos);

            Flux::toast(variant: 'success', text: 'Perfil criado.');
        }

        Flux::modal('formulario-perfil')->close();

        $this->resetFormulario();
    }

    public function alternarAtivo(int $id): void
    {
        $perfil = $this->perfis()->findOrFail($id);
        $perfil->update(['ativo' => ! $perfil->ativo]);
    }

    public function excluir(int $id): void
    {
        $this->perfis()->findOrFail($id)->delete();

        Flux::toast(text: 'Perfil removido.');
    }

    /**
     * Dados que o `with()` injeta na view a cada render.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'perfis' => $this->perfis()->orderBy('nome')->get(),
            'listaUfs' => config('radar.ufs'),
            'listaModalidades' => config('radar.modalidades'),
        ];
    }

    /**
     * Sempre filtrado pelo dono: nenhuma tela deste sistema enxerga o perfil
     * de outro usuário, nem por id chutado na requisição.
     *
     * @return HasMany<PerfilBusca, covariant User>
     */
    private function perfis()
    {
        return Auth::user()->perfisBusca();
    }

    /**
     * "uniforme, fardamento; farda" → ['uniforme', 'fardamento', 'farda']
     *
     * @return list<string>
     */
    private function listaDeTermos(?string $texto): array
    {
        if ($texto === null || trim($texto) === '') {
            return [];
        }

        $termos = preg_split('/[,;]+/u', $texto) ?: [];

        return array_values(array_unique(array_filter(
            array_map(trim(...), $termos),
            static fn (string $termo): bool => $termo !== '',
        )));
    }

    private function resetFormulario(): void
    {
        $this->reset(['editando', 'nome', 'palavrasChave', 'palavrasExcluidas', 'ufs', 'modalidades', 'valorMin', 'valorMax']);
        $this->ativo = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.perfis');
    }
}
