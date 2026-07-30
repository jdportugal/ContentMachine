<div>
    <x-page-header
        eyebrow="Tomus · I"
        title="Painel"
        cota="006.3 · IAT · '26"
        lead="Vista geral da casa — desempenho das redes, rascunhos em curso e o que há de novo no mundo." />

    {{-- Totais dos canais --}}
    @php use App\Services\Monitoring\MonitoringStats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-metric-card label="Subscritores" :value="MonitoringStats::numero($estatisticas['subscritores'])" unit="total" accent="#5A7BFF" />
        <x-metric-card label="Publicações" :value="MonitoringStats::numero($estatisticas['publicacoes'])" unit="publicadas" accent="#FFB347" />
        <x-metric-card label="Visualizações" :value="MonitoringStats::numero($estatisticas['visualizacoes'])" unit="recentes" accent="#FF7A3D" />
        <x-metric-card label="Interações" :value="MonitoringStats::numero($estatisticas['interacoes'])" unit="recentes" accent="#C77DFF" />
    </div>
    @unless ($estatisticas['temDados'])
        <p class="mt-2 font-mono text-xs text-ink-faint">
            Sem dados de canal ainda — vá a
            <a href="{{ route('monitorizacao') }}" class="text-teal hover:underline">Monitorização</a>
            e carregue em «Atualizar dados» para recolher dos seus canais.
        </p>
    @endunless

    {{-- Desempenho por plataforma --}}
    <div class="mt-8">
        <div class="eyebrow mb-3">Redes · desempenho recente</div>
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
                           class="font-mono text-[0.62rem] text-teal hover:underline">ver tudo →</a>
                    </div>

                    @if ($p['resumo'])
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="font-display text-3xl text-ink">{{ $p['resumo']['value'] }}</span>
                            <span class="eyebrow">{{ $p['resumo']['label'] }}</span>
                        </div>
                    @endif

                    @if ($p['melhor'])
                        <div class="mt-4 pt-3 border-t border-ink-soft/10">
                            <div class="eyebrow mb-1">Melhor conteúdo</div>
                            <x-content-row :item="$p['melhor']" />
                        </div>
                    @endif
                </x-panel>
            @endforeach
        </div>
    </div>

    {{-- Notícias em destaque --}}
    <div class="mt-8">
        <div class="flex items-center justify-between mb-3">
            <div class="eyebrow">Do mundo · destaques</div>
            <a href="{{ route('noticias') }}" class="font-mono text-[0.62rem] text-teal hover:underline">agregador →</a>
        </div>
        <x-panel glyph="☙">
            @forelse ($destaquesNoticias as $d)
                <div class="flex items-start gap-3 py-2.5 border-b border-ink-soft/10 last:border-0">
                    <x-badge tone="leather">{{ $d['fonte'] }}</x-badge>
                    <div class="min-w-0 flex-1">
                        <div class="text-ink">{{ $d['titulo'] }}</div>
                        <div class="text-sm text-ink-soft italic">{{ $d['angulo'] }}</div>
                    </div>
                    <div class="font-mono text-xs text-teal shrink-0">{{ $d['relevancia'] }}</div>
                </div>
            @empty
                <p class="font-mono text-xs text-ink-faint">
                    Sem destaques ainda — gere um relatório no
                    <a href="{{ route('noticias') }}" class="text-teal hover:underline">agregador</a>.
                </p>
            @endforelse
        </x-panel>
    </div>
</div>
