<?php

use App\Models\Contratacao;
use App\Models\PerfilBusca;
use App\Services\Match\MotorDeMatch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Oportunidades')] class extends Component
{
    private const POR_PAGINA = 15;

    #[Url]
    public string $perfil = 'todos';

    public function updatedPerfil(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $motor = app(MotorDeMatch::class);

        $perfis = Auth::user()->perfisBusca()->orderBy('nome')->get();
        $selecionados = $this->perfisSelecionados($perfis);

        $oportunidades = $selecionados === []
            ? []
            : $motor->oportunidades($selecionados);

        $pagina = Paginator::resolveCurrentPage();
        $fatiado = array_slice($oportunidades, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA);

        $paginador = new LengthAwarePaginator(
            items: $fatiado,
            total: count($oportunidades),
            perPage: self::POR_PAGINA,
            currentPage: $pagina,
            options: ['path' => Paginator::resolveCurrentPath()],
        );

        return [
            'perfis' => $perfis,
            'perfisAtivos' => $perfis->where('ativo', true)->count(),
            'paginador' => $paginador,
            'total' => count($oportunidades),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PerfilBusca>  $perfis
     * @return list<PerfilBusca>
     */
    private function perfisSelecionados($perfis): array
    {
        if ($this->perfil === 'todos') {
            return $perfis->where('ativo', true)->values()->all();
        }

        // `find` sobre a coleção do próprio usuário: id de outro usuário na URL
        // simplesmente não existe aqui.
        $perfil = $perfis->firstWhere('id', (int) $this->perfil);

        return $perfil === null ? [] : [$perfil];
    }

    private function diasRestantes(Contratacao $contratacao): ?int
    {
        if ($contratacao->data_encerramento_proposta === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($contratacao->data_encerramento_proposta->startOfDay());
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Oportunidades</flux:heading>
            <flux:text class="mt-1">
                Contratações do PNCP que ainda aceitam proposta e casam com os seus perfis.
            </flux:text>
        </div>

        @if ($perfis->isNotEmpty())
            <flux:select wire:model.live="perfil" class="max-w-xs">
                <flux:select.option value="todos">Todos os perfis ativos</flux:select.option>
                @foreach ($perfis as $opcao)
                    <flux:select.option :value="$opcao->id">
                        {{ $opcao->nome }}{{ $opcao->ativo ? '' : ' (inativo)' }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    @if ($perfis->isEmpty())
        <flux:card class="mt-6 flex flex-col items-center gap-3 py-12 text-center">
            <flux:icon name="magnifying-glass" class="size-8 text-zinc-400" />
            <flux:heading size="lg">Crie um perfil para começar</flux:heading>
            <flux:text class="max-w-md">
                A busca cruza cada contratação importada do PNCP com as regras dos seus perfis.
                Sem perfil, não há o que cruzar.
            </flux:text>
            <flux:button variant="primary" :href="route('perfis.index')" wire:navigate>
                Ir para perfis de busca
            </flux:button>
        </flux:card>
    @elseif ($total === 0)
        <flux:card class="mt-6 flex flex-col items-center gap-3 py-12 text-center">
            <flux:icon name="clock" class="size-8 text-zinc-400" />
            <flux:heading size="lg">Nenhuma oportunidade agora</flux:heading>
            <flux:text class="max-w-md">
                Nenhuma contratação importada casa com {{ $perfil === 'todos' ? 'os seus perfis' : 'este perfil' }}.
                O comando <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">php artisan radar:importar</code>
                traz dado novo; o agendamento diário ainda não está ligado (v0.2).
            </flux:text>
        </flux:card>
    @else
        <flux:text class="mt-4 text-sm text-zinc-500">
            {{ $total }} {{ $total === 1 ? 'oportunidade' : 'oportunidades' }}
            @if ($perfil === 'todos')
                nos {{ $perfisAtivos }} {{ $perfisAtivos === 1 ? 'perfil ativo' : 'perfis ativos' }}
            @endif
        </flux:text>

        <div class="mt-4 space-y-3">
            @foreach ($paginador as $oportunidade)
                @php($contratacao = $oportunidade['contratacao'])
                @php($dias = $this->diasRestantes($contratacao))

                <flux:card class="space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <flux:heading size="sm" class="leading-snug line-clamp-3">
                                {{ $contratacao->objeto }}
                            </flux:heading>

                            <flux:text class="mt-1 text-sm text-zinc-500">
                                {{ $contratacao->orgao_razao_social }}
                                @if ($contratacao->unidade_nome)
                                    · {{ $contratacao->unidade_nome }}
                                @endif
                            </flux:text>
                        </div>

                        @if ($dias !== null)
                            <flux:badge
                                :color="$dias <= 2 ? 'red' : ($dias <= 7 ? 'amber' : 'zinc')"
                                size="sm"
                            >
                                {{ $dias <= 0 ? 'encerra hoje' : "fecha em {$dias} " . ($dias === 1 ? 'dia' : 'dias') }}
                            </flux:badge>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1">
                            <flux:icon name="map-pin" class="size-4" />
                            {{ $contratacao->municipio_nome }}/{{ $contratacao->uf }}
                        </span>

                        <span class="inline-flex items-center gap-1">
                            <flux:icon name="banknotes" class="size-4" />
                            @if ((float) $contratacao->valor_estimado > 0)
                                R$ {{ number_format((float) $contratacao->valor_estimado, 2, ',', '.') }}
                            @else
                                valor não informado
                            @endif
                        </span>

                        <span class="inline-flex items-center gap-1">
                            <flux:icon name="tag" class="size-4" />
                            {{ $contratacao->modalidade_nome }}
                        </span>

                        <span class="inline-flex items-center gap-1">
                            <flux:icon name="calendar" class="size-4" />
                            {{ $contratacao->data_encerramento_proposta?->format('d/m/Y H:i') }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($oportunidade['perfis'] as $nomePerfil)
                                <flux:badge size="sm" color="blue">{{ $nomePerfil }}</flux:badge>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            @if ($contratacao->urlPncp())
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon-trailing="arrow-top-right-on-square"
                                    :href="$contratacao->urlPncp()"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Abrir no PNCP
                                </flux:button>
                            @endif

                            @if ($contratacao->linkSistemaOrigem && $contratacao->linkSistemaOrigem !== $contratacao->urlPncp())
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon-trailing="arrow-top-right-on-square"
                                    :href="$contratacao->linkSistemaOrigem"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Portal do órgão
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $paginador->links() }}
        </div>
    @endif
</section>
