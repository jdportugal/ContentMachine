<div>
    <x-page-header
        eyebrow="Tomus · I"
        title="Dashboard"
        cota="006.3 · IAT · '26"
        lead="Overview of the house — network performance, drafts in progress and what's new in the world." />

    {{-- Channel totals --}}
    @php use App\Services\Monitoring\MonitoringStats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-metric-card label="Subscribers" :value="MonitoringStats::numero($estatisticas['subscritores'])" unit="total" accent="#5A7BFF" />
        <x-metric-card label="Posts" :value="MonitoringStats::numero($estatisticas['publicacoes'])" unit="published" accent="#FFB347" />
        <x-metric-card label="Views" :value="MonitoringStats::numero($estatisticas['visualizacoes'])" unit="recent" accent="#FF7A3D" />
        <x-metric-card label="Interactions" :value="MonitoringStats::numero($estatisticas['interacoes'])" unit="recent" accent="#C77DFF" />
    </div>
    @unless ($estatisticas['temDados'])
        <p class="mt-2 font-mono text-xs text-ink-faint">
            No channel data yet — go to
            <a href="{{ route('monitorizacao') }}" class="text-teal hover:underline">Monitoring</a>
            and click «Update data» to collect from your channels.
        </p>
    @endunless

    {{-- Performance by platform --}}
    <div class="mt-8">
        <div class="eyebrow mb-3">Networks · recent performance</div>
        <div class="grid md:grid-cols-2 gap-4">
            @foreach ($plataformas as $p)
                @php $meta = config('contentmachine.plataformas_meta.'.$p['plataforma']); @endphp
                <x-panel>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xl" style="color: {{ $meta['cor'] }}">{{ $meta['glifo'] }}</span>
                            <h3 class="font-display text-2xl text-ink">{{ $meta['label'] }}</h3>
                        </div>
                        <a href="{{ route('monitorizacao', ['rede' => $p['plataforma']]) }}"
                           class="font-mono text-[0.62rem] text-teal hover:underline">view all →</a>
                    </div>

                    @if ($p['resumo'])
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="font-display text-3xl text-ink">{{ $p['resumo']['value'] }}</span>
                            <span class="eyebrow">{{ $p['resumo']['label'] }}</span>
                        </div>
                    @endif

                    @if ($p['melhor'])
                        <div class="mt-4 pt-3 border-t border-ink-soft/10">
                            <div class="eyebrow mb-1">Best content</div>
                            <x-content-row :item="$p['melhor']" />
                        </div>
                    @endif
                </x-panel>
            @endforeach
        </div>
    </div>

    {{-- Featured news --}}
    <div class="mt-8">
        <div class="flex items-center justify-between mb-3">
            <div class="eyebrow">From the world · highlights</div>
            <a href="{{ route('noticias') }}" class="font-mono text-[0.62rem] text-teal hover:underline">aggregator →</a>
        </div>
        <x-panel glyph="☙">
            @foreach ($destaquesNoticias as $d)
                <div class="flex items-start gap-3 py-2.5 border-b border-ink-soft/10 last:border-0">
                    <x-badge tone="leather">{{ $d['fonte'] }}</x-badge>
                    <div class="min-w-0 flex-1">
                        <div class="text-ink">{{ $d['titulo'] }}</div>
                        <div class="text-sm text-ink-soft italic">{{ $d['angulo'] }}</div>
                    </div>
                    <div class="font-mono text-xs text-teal shrink-0">{{ $d['relevancia'] }}</div>
                </div>
            @endforeach
        </x-panel>
    </div>
</div>
