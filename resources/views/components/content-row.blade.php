@props(['item'])
@php
    $score = $item['score'] ?? null;
    $tone = $score === null ? 'neutral' : ($score >= 70 ? 'good' : ($score >= 45 ? 'warn' : 'bad'));
@endphp
<div class="flex items-center gap-4 py-3 border-b border-ink-soft/10 last:border-0">
    <div class="shrink-0 w-14 text-center">
        <div class="font-display text-2xl leading-none {{ $tone === 'good' ? 'text-good' : ($tone === 'warn' ? 'text-warn' : 'text-bad') }}">{{ $score ?? '—' }}</div>
        <div class="eyebrow !text-[0.55rem]">score</div>
    </div>
    <div class="min-w-0 flex-1">
        <div class="font-body text-ink truncate">{{ $item['titulo'] }}</div>
        <div class="mt-0.5 flex items-center gap-2 flex-wrap">
            <x-badge tone="teal">{{ $item['tipo'] }}</x-badge>
            <span class="font-mono text-[0.62rem] text-ink-faint">{{ \Illuminate\Support\Carbon::parse($item['publicado_em'])->translatedFormat('d M') }}</span>
            <span class="font-mono text-[0.62rem] text-ink-faint">· {{ number_format($item['views'] / 1000, 1) }} mil views</span>
            @if (($item['outlier'] ?? 0) >= 1.5)
                <x-badge tone="gold">▲ {{ $item['outlier'] }}× mediana</x-badge>
            @endif
        </div>
    </div>
    <div class="hidden sm:block shrink-0 text-right font-mono text-[0.62rem] text-ink-soft leading-relaxed">
        <div>♥ {{ number_format($item['likes']) }}</div>
        <div>✎ {{ number_format($item['comentarios']) }}</div>
    </div>
</div>
