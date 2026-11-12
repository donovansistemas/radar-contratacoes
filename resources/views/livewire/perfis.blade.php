<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Perfis de busca</flux:heading>
            <flux:text class="mt-1">
                O que você quer ser avisado quando aparecer no PNCP.
            </flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="novo" data-test="novo-perfil">
            Novo perfil
        </flux:button>
    </div>

    @if ($perfis->isEmpty())
        <flux:card class="mt-6 flex flex-col items-center gap-3 py-12 text-center">
            <flux:icon name="magnifying-glass" class="size-8 text-zinc-400" />
            <flux:heading size="lg">Nenhum perfil ainda</flux:heading>
            <flux:text class="max-w-md">
                Um perfil é um conjunto de regras: palavras-chave, UF, modalidade e faixa de valor.
                Sem perfil, o sistema não tem o que procurar para você.
            </flux:text>
            <flux:button variant="primary" wire:click="novo">Criar o primeiro perfil</flux:button>
        </flux:card>
    @else
        <flux:table class="mt-6">
            <flux:table.columns>
                <flux:table.column>Perfil</flux:table.column>
                <flux:table.column class="hidden md:table-cell">Palavras-chave</flux:table.column>
                <flux:table.column class="hidden md:table-cell">Onde</flux:table.column>
                <flux:table.column class="hidden lg:table-cell">Valor estimado</flux:table.column>
                <flux:table.column class="hidden lg:table-cell">Status</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($perfis as $perfil)
                    <flux:table.row :key="$perfil->id">
                        <flux:table.cell class="font-medium">
                            {{ $perfil->nome }}

                            {{-- No celular sobram duas colunas; o resto desce para cá. --}}
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
                            @if (empty($perfil->palavras_chave))
                                <flux:text class="text-zinc-500">todas</flux:text>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach (array_slice($perfil->palavras_chave, 0, 4) as $termo)
                                        <flux:badge size="sm">{{ $termo }}</flux:badge>
                                    @endforeach
                                    @if (count($perfil->palavras_chave) > 4)
                                        <flux:badge size="sm" color="zinc">+{{ count($perfil->palavras_chave) - 4 }}</flux:badge>
                                    @endif
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="hidden md:table-cell">
                            <flux:text>
                                {{ empty($perfil->ufs) ? 'Brasil' : implode(', ', $perfil->ufs) }}
                            </flux:text>
                            <flux:text class="text-xs text-zinc-500">
                                {{ empty($perfil->modalidades) ? 'qualquer modalidade' : (config('radar.modalidades')[$perfil->modalidades[0]] ?? 'modalidade '.$perfil->modalidades[0]) }}
                                @if (count($perfil->modalidades) > 1)
                                    +{{ count($perfil->modalidades) - 1 }}
                                @endif
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

                        <flux:table.cell class="hidden lg:table-cell">
                            <flux:switch
                                :checked="$perfil->ativo"
                                wire:click="alternarAtivo({{ $perfil->id }})"
                                :label="$perfil->ativo ? 'Ativo' : 'Inativo'"
                            />
                        </flux:table.cell>

                        <flux:table.cell class="text-end whitespace-nowrap">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                                :aria-label="'Editar perfil '.$perfil->nome"
                                wire:click="editar({{ $perfil->id }})"
                            >
                                <span class="hidden md:inline">Editar</span>
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                class="text-red-600 dark:text-red-400"
                                :aria-label="'Remover perfil '.$perfil->nome"
                                wire:click="excluir({{ $perfil->id }})"
                                wire:confirm="Remover o perfil “{{ $perfil->nome }}”?"
                            >
                                <span class="hidden md:inline">Remover</span>
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="formulario-perfil" class="w-full max-w-2xl" :dismissible="false">
        <form wire:submit="salvar" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editando === null ? 'Novo perfil' : 'Editar perfil' }}</flux:heading>
                <flux:text class="mt-1">Campos em branco significam "qualquer".</flux:text>
            </div>

            <flux:input wire:model="nome" label="Nome do perfil" placeholder="Ex.: Uniformes escolares" required />

            <flux:field>
                <flux:label>Palavras-chave</flux:label>
                <flux:description>
                    Separe por vírgula. O objeto da contratação precisa conter ao menos uma delas.
                    Sinônimos conhecidos são aplicados automaticamente.
                </flux:description>
                <flux:input wire:model="palavrasChave" placeholder="uniforme, fardamento, merenda" />
                <flux:error name="palavrasChave" />
            </flux:field>

            <flux:field>
                <flux:label>Descartar se aparecer</flux:label>
                <flux:description>
                    Contratação que contenha qualquer um destes termos é ignorada, mesmo que case com as palavras-chave.
                </flux:description>
                <flux:input wire:model="palavrasExcluidas" placeholder="obra, reforma" />
                <flux:error name="palavrasExcluidas" />
            </flux:field>

            <flux:field>
                <flux:label>UFs</flux:label>
                <flux:description>Nenhuma marcada = todas.</flux:description>
                <flux:checkbox.group wire:model="ufs" variant="pills">
                    @foreach ($listaUfs as $uf)
                        <flux:checkbox :value="$uf" :label="$uf" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="ufs" />
            </flux:field>

            <flux:field>
                <flux:label>Modalidades</flux:label>
                <flux:description>Nenhuma marcada = todas.</flux:description>
                <flux:checkbox.group wire:model="modalidades" variant="pills">
                    @foreach ($listaModalidades as $codigo => $nomeModalidade)
                        <flux:checkbox :value="$codigo" :label="$nomeModalidade" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="modalidades" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="valorMin" label="Valor mínimo (R$)" type="number" step="0.01" min="0" placeholder="sem mínimo" />
                <flux:input wire:model="valorMax" label="Valor máximo (R$)" type="number" step="0.01" min="0" placeholder="sem teto" />
            </div>
            {{-- Os dois campos acima têm `label`, então já exibem o próprio erro.
                 Um <flux:error> aqui mostraria a mesma mensagem duas vezes. --}}

            <flux:switch wire:model="ativo" label="Perfil ativo" description="Desligado, o perfil não é usado na busca." />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="salvar-perfil">
                    {{ $editando === null ? 'Criar perfil' : 'Salvar' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
