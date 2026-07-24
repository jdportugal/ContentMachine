@props(['item', 'metricas' => true])
@php
    $score = $metricas ? ($item['score'] ?? null) : null;
    $tone = $score === null ? 'neutral' : ($score >= 70 ? 'good' : ($score >= 45 ? 'warn' : 'bad'));
    $thumb = trim((string) ($item['thumbnail'] ?? ''));
    $toneClass = $tone === 'good' ? 'text-good' : ($tone === 'warn' ? 'text-warn' : 'text-bad');
@endphp
<div class="flex items-center gap-4 py-3 border-b border-ink-soft/10 last:border-0">
    {{-- Post thumbnail; without a thumbnail, the score circle. --}}
    @if ($thumb !== '')
        <div class="shrink-0 w-14 h-14 rounded-sm overflow-hidden border border-ink-soft/20 bg-surface/40 relative">
            <img src="{{ $thumb }}" alt="" referrerpolicy="no-referrer" loading="lazy"
                 class="w-full h-full object-cover" onerror="this.style.display='none'">
            @if ($score !== null)
                <span class="absolute bottom-0 right-0 px-1 font-display text-sm leading-none bg-nocturna/80 {{ $toneClass }}">{{ $score }}</span>
            @endif
        </div>
    @else
        <div class="shrink-0 w-14 text-center">
            <div class="font-display text-2xl leading-none {{ $toneClass }}">{{ $score ?? '—' }}</div>
            <div class="eyebrow !text-[0.55rem]">score</div>
        </div>
    @endif
    <div class="min-w-0 flex-1">
        <div class="font-body text-ink truncate">{{ $item['titulo'] }}</div>
        <div class="mt-0.5 flex items-center gap-2 flex-wrap">
            <x-badge tone="teal">{{ $item['tipo'] }}</x-badge>
            <span class="font-mono text-[0.62rem] text-ink-faint">{{ \Illuminate\Support\Carbon::parse($item['publicado_em'])->translatedFormat('d M') }}</span>
            @if ($metricas)
                <span class="font-mono text-[0.62rem] text-ink-faint">· {{ number_format(($item['views'] ?? 0) / 1000, 1) }}k views</span>
                @if (($item['outlier'] ?? 0) >= 1.5)
                    <x-badge tone="gold">▲ {{ $item['outlier'] }}× median</x-badge>
                @endif
            @endif
        </div>
    </div>
    @if ($metricas)
        <div class="hidden sm:block shrink-0 text-right font-mono text-[0.62rem] text-ink-soft leading-relaxed">
            <div>♥ {{ number_format($item['likes'] ?? 0) }}</div>
            <div>✎ {{ number_format($item['comentarios'] ?? 0) }}</div>
        </div>
    @endif
</div>
