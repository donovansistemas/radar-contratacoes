<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validação do perfil de busca — versão CLÁSSICA (MVC).
 *
 * No Livewire a validação fica dentro do componente (`$this->validate([...])`).
 * Aqui ela sai para uma classe própria, declarada por tipo de requisição. É o
 * padrão do Laravel clássico: o controller recebe a requisição já validada e
 * não sabe como a validação foi feita.
 */
class PerfilBuscaRequest extends FormRequest
{
    /**
     * O middleware `auth` já barra visitante. Qualquer usuário autenticado pode
     * criar perfil para si mesmo — o `user_id` nunca vem do formulário, vem da
     * sessão (ver controller).
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'palavras_chave' => ['nullable', 'string', 'max:500'],
            'palavras_excluidas' => ['nullable', 'string', 'max:500'],
            'ufs' => ['array'],
            'ufs.*' => ['string', Rule::in(config('radar.ufs'))],
            'modalidades' => ['array'],
            'modalidades.*' => ['integer', Rule::in(array_keys(config('radar.modalidades')))],
            'valor_min' => ['nullable', 'numeric', 'min:0'],
            'valor_max' => ['nullable', 'numeric', 'min:0'],
            'ativo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'Dê um nome ao perfil.',
            'ufs.*.in' => 'UF inválida.',
            'modalidades.*.in' => 'Modalidade inválida.',
            'valor_min.numeric' => 'O valor mínimo precisa ser um número.',
            'valor_max.numeric' => 'O valor máximo precisa ser um número.',
        ];
    }

    /**
     * Comparação de faixa depois da validação básica: com campos opcionais, o
     * `gte` do Laravel compara contra string vazia e o erro sai confuso.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $min = $this->input('valor_min');
            $max = $this->input('valor_max');

            if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float) $max < (float) $min) {
                $validator->errors()->add('valor_max', 'O valor máximo não pode ser menor que o mínimo.');
            }
        });
    }

    /**
     * Converte o formulário (texto separado por vírgula) nos atributos do model.
     *
     * @return array<string, mixed>
     */
    public function dadosDoPerfil(): array
    {
        $validado = $this->validated();

        return [
            'nome' => $validado['nome'],
            'palavras_chave' => $this->listaDeTermos($validado['palavras_chave'] ?? null),
            'palavras_excluidas' => $this->listaDeTermos($validado['palavras_excluidas'] ?? null),
            'ufs' => array_values($validado['ufs'] ?? []),
            'modalidades' => array_map(intval(...), $validado['modalidades'] ?? []),
            'valor_min' => ($validado['valor_min'] ?? '') === '' ? null : $validado['valor_min'],
            'valor_max' => ($validado['valor_max'] ?? '') === '' ? null : $validado['valor_max'],
            // Checkbox desmarcado não é enviado: ausência = false.
            'ativo' => $this->boolean('ativo'),
        ];
    }

    /**
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
}
