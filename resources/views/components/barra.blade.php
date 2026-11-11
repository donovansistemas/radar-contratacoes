@props(['rotulo', 'quantidade', 'maximo' => 1, 'valor' => null])

@php
    $largura = $maximo > 0 ? max(2, (int) round($quantidade / $maximo * 100)) : 0;
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <span class="w-32 shrink-0 truncate text-sm text-zinc-700 dark:text-zinc-300" title="{{ $rotulo }}">
        {{ $rotulo }}
    </span>

    <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
        <div class="h-full rounded-full bg-[var(--color-accent)]" style="width: {{ $largura }}%"></div>
    </div>

    <span class="w-8 shrink-0 text-end text-sm font-medium tabular-nums">{{ $quantidade }}</span>

    @if ($valor !== null)
        <span class="hidden w-32 shrink-0 text-end text-xs text-zinc-500 tabular-nums sm:block">
            R$ {{ number_format((float) $valor, 2, ',', '.') }}
        </span>
    @endif
</div>
