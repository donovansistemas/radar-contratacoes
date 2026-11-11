<?php

use App\Services\Dashboard\ResumoDoRadar;
use App\Services\Match\MotorDeMatch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    public function with(): array
    {
        $perfis = Auth::user()->perfisBusca()->where('ativo', true)->get();

        return [
            'temPerfil' => Auth::user()->perfisBusca()->exists(),
            'perfisAtivos' => $perfis->count(),
            'resumo' => ResumoDoRadar::calcular(app(MotorDeMatch::class), $perfis),
        ];
    }

    /**
     * Maior quantidade da lista, para as barras ficarem proporcionais.
     *
     * @param  list<array{rotulo: string, quantidade: int, valor: float}>  $lista
     */
    public function maximo(array $lista): int
    {
        return max(1, ...array_column($lista, 'quantidade'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Visão geral</flux:heading>
            <flux:text class="mt-1">
                O que os seus perfis estão encontrando no PNCP agora.
            </flux:text>
        </div>

        <flux:text class="text-sm text-zinc-500">
            @if ($resumo->ultimaImportacao)
                Última importação em {{ $resumo->ultimaImportacao->format('d/m/Y H:i') }}
            @else
                Nenhuma importação ainda
            @endif
        </flux:text>
    </div>

    @if (! $temPerfil)
        <flux:card class="flex flex-col items-start gap-4 py-8">
            <div>
                <flux:heading size="lg">Comece por um perfil de busca</flux:heading>
                <flux:text class="mt-1 max-w-xl">
                    O dashboard só mostra o que interessa a você: contratações que casam com as regras
                    dos seus perfis. Sem perfil, não há o que resumir.
                </flux:text>
            </div>

            <flux:button variant="primary" :href="route('perfis.index')" wire:navigate>
                Criar perfil de busca
            </flux:button>
        </flux:card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card>
                <flux:text class="text-sm text-zinc-500">Oportunidades abertas</flux:text>
                <flux:heading size="xl" class="mt-1 tabular-nums">{{ $resumo->oportunidades }}</flux:heading>
            </flux:card>

            <flux:card>
                <flux:text class="text-sm text-zinc-500">Valor estimado somado</flux:text>
                <flux:heading size="xl" class="mt-1 tabular-nums">
                    R$ {{ number_format($resumo->valorEstimado, 2, ',', '.') }}
                </flux:heading>
            </flux:card>

            <flux:card>
                <flux:text class="text-sm text-zinc-500">Fecham em até 7 dias</flux:text>
                <flux:heading size="xl" class="mt-1 tabular-nums {{ $resumo->encerrandoEmSeteDias > 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                    {{ $resumo->encerrandoEmSeteDias }}
                </flux:heading>
            </flux:card>

            <flux:card>
                <flux:text class="text-sm text-zinc-500">Perfis ativos</flux:text>
                <flux:heading size="xl" class="mt-1 tabular-nums">{{ $perfisAtivos }}</flux:heading>
            </flux:card>
        </div>

        @if ($resumo->oportunidades === 0)
            <flux:card class="flex flex-col items-start gap-3">
                <flux:heading size="lg">Nenhuma oportunidade no radar</flux:heading>
                <flux:text class="max-w-2xl">
                    Existem <strong>{{ $resumo->baseImportada }}</strong> contratações importadas na base,
                    mas nenhuma casa com os seus perfis. Rode a importação para trazer dado novo:
                </flux:text>
                <code class="rounded bg-zinc-100 px-2 py-1 text-sm dark:bg-zinc-800">php artisan radar:importar --uf=SP --paginas=5</code>
                <flux:button variant="primary" size="sm" :href="route('perfis.index')" wire:navigate>
                    Revisar perfis
                </flux:button>
            </flux:card>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                <flux:card class="space-y-3">
                    <flux:heading size="sm">Por UF</flux:heading>
                    <div class="space-y-2">
                        @foreach ($resumo->porUf as $linha)
                            <x-barra
                                :rotulo="$linha['rotulo']"
                                :quantidade="$linha['quantidade']"
                                :maximo="$this->maximo($resumo->porUf)"
                                :valor="$linha['valor']"
                            />
                        @endforeach
                    </div>
                </flux:card>

                <flux:card class="space-y-3">
                    <flux:heading size="sm">Por modalidade</flux:heading>
                    <div class="space-y-2">
                        @foreach ($resumo->porModalidade as $linha)
                            <x-barra
                                :rotulo="$linha['rotulo']"
                                :quantidade="$linha['quantidade']"
                                :maximo="$this->maximo($resumo->porModalidade)"
                                :valor="$linha['valor']"
                            />
                        @endforeach
                    </div>
                </flux:card>

                <flux:card class="space-y-3">
                    <flux:heading size="sm">Por faixa de valor</flux:heading>
                    <div class="space-y-2">
                        @foreach ($resumo->porFaixaDeValor as $linha)
                            <x-barra
                                :rotulo="$linha['rotulo']"
                                :quantidade="$linha['quantidade']"
                                :maximo="$this->maximo($resumo->porFaixaDeValor)"
                                :valor="$linha['valor']"
                            />
                        @endforeach
                    </div>
                </flux:card>

                <flux:card class="space-y-3">
                    <flux:heading size="sm">Encerramento por mês</flux:heading>
                    <flux:text class="text-xs text-zinc-500">
                        Distribuição das oportunidades abertas pelos próximos meses.
                        Ainda não é série histórica — o banco não tem importação acumulada para isso.
                    </flux:text>
                    <div class="space-y-2">
                        @foreach ($resumo->porMesDeEncerramento as $linha)
                            <x-barra
                                :rotulo="$linha['rotulo']"
                                :quantidade="$linha['quantidade']"
                                :maximo="$this->maximo($resumo->porMesDeEncerramento)"
                                :valor="$linha['valor']"
                            />
                        @endforeach
                    </div>
                </flux:card>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:text class="text-sm text-zinc-500">
                    Base importada: <strong>{{ $resumo->baseImportada }}</strong> contratações.
                </flux:text>

                <flux:button variant="primary" :href="route('oportunidades.index')" wire:navigate icon-trailing="arrow-right">
                    Ver as oportunidades
                </flux:button>
            </div>
        @endif
    @endif
</section>
