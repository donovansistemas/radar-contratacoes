{{-- Versão CLÁSSICA (MVC): sem Livewire, sem wire:click. Tudo é link e <form> com POST. --}}
<x-layouts::app :title="'Perfis de busca (clássico)'">
    <section class="w-full">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading size="xl">Perfis de busca</flux:heading>
                <flux:text class="mt-1">
                    Versão em controller clássico. A mesma tela em Livewire está em
                    <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">/perfis</code>.
                </flux:text>
            </div>

            <flux:button variant="primary" icon="plus" :href="route('perfis-classico.create')">
                Novo perfil
            </flux:button>
        </div>

        {{-- Mensagem de sucesso: o controller fez ->with('status', ...) --}}
        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:bg-green-950 dark:text-green-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($perfis->isEmpty())
            <flux:card class="mt-6 flex flex-col items-center gap-3 py-12 text-center">
                <flux:heading size="lg">Nenhum perfil ainda</flux:heading>
                <flux:text class="max-w-md">
                    Um perfil é um conjunto de regras: palavras-chave, UF, modalidade e faixa de valor.
                </flux:text>
                <flux:button variant="primary" :href="route('perfis-classico.create')">
                    Criar o primeiro perfil
                </flux:button>
            </flux:card>
        @else
            <flux:table class="mt-6">
                <flux:table.columns>
                    <flux:table.column>Perfil</flux:table.column>
                    <flux:table.column class="hidden md:table-cell">Palavras-chave</flux:table.column>
                    <flux:table.column class="hidden md:table-cell">Onde</flux:table.column>
                    <flux:table.column class="hidden lg:table-cell">Valor estimado</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($perfis as $perfil)
                        <flux:table.row :key="$perfil->id">
                            <flux:table.cell class="font-medium">
                                {{ $perfil->nome }}

                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 md:hidden">
                                    @foreach (array_slice($perfil->palavras_chave ?? [], 0, 3) as $termo)
                                        <flux:badge size="sm">{{ $termo }}</flux:badge>
                                    @endforeach
                                    <flux:text class="text-xs text-zinc-500">
                                        {{ empty($perfil->ufs) ? 'Brasil' : implode(', ', $perfil->ufs) }}
                                        · {{ $perfil->ativo ? 'ativo' : 'inativo' }}
                                    </flux:text>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="hidden md:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @foreach (array_slice($perfil->palavras_chave ?? [], 0, 4) as $termo)
                                        <flux:badge size="sm">{{ $termo }}</flux:badge>
                                    @endforeach
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="hidden md:table-cell">
                                <flux:text>{{ empty($perfil->ufs) ? 'Brasil' : implode(', ', $perfil->ufs) }}</flux:text>
                                <flux:text class="text-xs text-zinc-500">
                                    {{ empty($perfil->modalidades) ? 'qualquer modalidade' : (config('radar.modalidades')[$perfil->modalidades[0]] ?? 'modalidade '.$perfil->modalidades[0]) }}
                                </flux:text>
                            </flux:table.cell>

                            <flux:table.cell class="hidden lg:table-cell">
                                @if ($perfil->valor_min === null && $perfil->valor_max === null)
                                    <flux:text class="text-zinc-500">sem faixa</flux:text>
                                @else
                                    <flux:text>
                                        R$ {{ $perfil->valor_min === null ? '0' : number_format((float) $perfil->valor_min, 2, ',', '.') }}
                                        –
                                        {{ $perfil->valor_max === null ? 'sem teto' : 'R$ '.number_format((float) $perfil->valor_max, 2, ',', '.') }}
                                    </flux:text>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell class="text-end whitespace-nowrap">
                                {{-- Editar é só um link para o formulário --}}
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    :href="route('perfis-classico.edit', $perfil)"
                                    :aria-label="'Editar perfil '.$perfil->nome"
                                >
                                    <span class="hidden md:inline">Editar</span>
                                </flux:button>

                                {{-- Remover exige formulário: DELETE não existe em link nem em botão --}}
                                <form
                                    method="POST"
                                    action="{{ route('perfis-classico.destroy', $perfil) }}"
                                    class="inline"
                                    onsubmit="return confirm('Remover o perfil {{ $perfil->nome }}?')"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        type="submit"
                                        icon="trash"
                                        class="text-red-600 dark:text-red-400"
                                        :aria-label="'Remover perfil '.$perfil->nome"
                                    >
                                        <span class="hidden md:inline">Remover</span>
                                    </flux:button>
                                </form>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>
</x-layouts::app>
