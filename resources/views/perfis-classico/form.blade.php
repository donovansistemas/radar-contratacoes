{{-- Mesmo formulário nas duas ações: criar (POST) e editar (PUT). --}}
<x-layouts::app :title="$perfil->exists ? 'Editar perfil (clássico)' : 'Novo perfil (clássico)'">
    <section class="w-full max-w-2xl">
        <flux:heading size="xl">
            {{ $perfil->exists ? 'Editar perfil' : 'Novo perfil' }}
        </flux:heading>
        <flux:text class="mt-1">Campos em branco significam "qualquer".</flux:text>

        {{-- Um único form; o verbo muda com @method --}}
        <form
            method="POST"
            action="{{ $perfil->exists ? route('perfis-classico.update', $perfil) : route('perfis-classico.store') }}"
            class="mt-6 space-y-6"
        >
            @csrf
            @if ($perfil->exists)
                @method('PUT')
            @endif

            <flux:input
                name="nome"
                label="Nome do perfil"
                placeholder="Ex.: Uniformes escolares"
                value="{{ old('nome', $perfil->nome) }}"
                required
                autofocus
            />

            <flux:field>
                <flux:label>Palavras-chave</flux:label>
                <flux:description>
                    Separe por vírgula. O objeto da contratação precisa conter ao menos uma delas.
                </flux:description>
                <flux:input
                    name="palavras_chave"
                    placeholder="uniforme, fardamento, merenda"
                    value="{{ old('palavras_chave', implode(', ', $perfil->palavras_chave ?? [])) }}"
                />
                <flux:error name="palavras_chave" />
            </flux:field>

            <flux:field>
                <flux:label>Descartar se aparecer</flux:label>
                <flux:description>
                    Contratação que contenha qualquer um destes termos é ignorada.
                </flux:description>
                <flux:input
                    name="palavras_excluidas"
                    placeholder="obra, reforma"
                    value="{{ old('palavras_excluidas', implode(', ', $perfil->palavras_excluidas ?? [])) }}"
                />
                <flux:error name="palavras_excluidas" />
            </flux:field>

            <flux:field>
                <flux:label>UFs</flux:label>
                <flux:description>Nenhuma marcada = todas.</flux:description>

                {{-- Checkbox nativo de propósito: em formulário clássico o valor
                     precisa viajar no POST, e `ufs[]` vira array no PHP. --}}
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach (config('radar.ufs') as $uf)
                        <label class="inline-flex items-center gap-1.5 text-sm">
                            <input
                                type="checkbox"
                                name="ufs[]"
                                value="{{ $uf }}"
                                @checked(in_array($uf, old('ufs', $perfil->ufs ?? []), true))
                                class="size-4 rounded border-zinc-300 text-[var(--color-accent)] focus:ring-[var(--color-accent)] dark:border-zinc-600 dark:bg-zinc-800"
                            >
                            {{ $uf }}
                        </label>
                    @endforeach
                </div>
                <flux:error name="ufs" />
            </flux:field>

            <flux:field>
                <flux:label>Modalidades</flux:label>
                <flux:description>Nenhuma marcada = todas.</flux:description>

                <div class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                    @foreach (config('radar.modalidades') as $codigo => $nomeModalidade)
                        <label class="inline-flex items-center gap-1.5 text-sm">
                            <input
                                type="checkbox"
                                name="modalidades[]"
                                value="{{ $codigo }}"
                                @checked(in_array($codigo, array_map(intval(...), old('modalidades', $perfil->modalidades ?? [])), true))
                                class="size-4 rounded border-zinc-300 text-[var(--color-accent)] focus:ring-[var(--color-accent)] dark:border-zinc-600 dark:bg-zinc-800"
                            >
                            {{ $nomeModalidade }}
                        </label>
                    @endforeach
                </div>
                <flux:error name="modalidades" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    name="valor_min"
                    label="Valor mínimo (R$)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="sem mínimo"
                    value="{{ old('valor_min', $perfil->valor_min) }}"
                />
                <flux:input
                    name="valor_max"
                    label="Valor máximo (R$)"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="sem teto"
                    value="{{ old('valor_max', $perfil->valor_max) }}"
                />
            </div>

            {{-- Os dois campos acima têm `label`, e por isso já renderizam o
                 próprio erro. Um <flux:error> aqui duplicaria a mensagem. --}}

            {{-- Checkbox sozinho: um hidden garante que "desmarcado" chegue como 0 --}}
            <input type="hidden" name="ativo" value="0">
            <label class="inline-flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    name="ativo"
                    value="1"
                    @checked((bool) old('ativo', $perfil->ativo))
                    class="size-4 rounded border-zinc-300 text-[var(--color-accent)] focus:ring-[var(--color-accent)] dark:border-zinc-600 dark:bg-zinc-800"
                >
                Perfil ativo
            </label>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('perfis-classico.index')">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $perfil->exists ? 'Salvar' : 'Criar perfil' }}
                </flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>
