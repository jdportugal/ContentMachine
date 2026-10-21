<div>
    <x-page-header
        eyebrow="Tomus · I"
        title="Dashboard"
        cota="006.3 · ACM · '26"
        lead="Overview of the house — network performance, drafts in progress and what's new in the world." />

    {{-- Channel totals, each with the same figure as of the last recorded day --}}
    @php
        use App\Services\Monitoring\MonitoringStats;
        // «yesterday» when it really is; otherwise name the day, so an older
        // figure is never read as last night's.
        $ontemData = $ontem['data'] ?? null;
        $ontemRotulo = $ontemData === null
            ? null
            : ($ontemData === now()->subDay()->toDateString()
                ? 'yesterday'
                : \Illuminate\Support\Carbon::parse($ontemData)->format('j M'));
        $antes = fn (string $m) => $ontemData === null
            ? null
            : MonitoringStats::numero((int) ($ontem['metricas'][$m] ?? 0));
    @endphp
    <div class="grid grid-cols-2 {{ $engajamento !== null ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-4">
        <x-metric-card label="Subscribers" :value="MonitoringStats::numero($estatisticas['subscritores'])" unit="total" accent="#2F5A85"
                       :previous="$antes('subscritores')" :previous-label="$ontemRotulo" />
        <x-metric-card label="Posts" :value="MonitoringStats::numero($estatisticas['publicacoes'])" unit="published" accent="#A06E1C"
                       :previous="$antes('publicacoes')" :previous-label="$ontemRotulo" :delta="$wowPosts" />
        <x-metric-card label="Views" :value="MonitoringStats::numero($estatisticas['visualizacoes'])" unit="recent" accent="#8A4A2B"
                       :previous="$antes('visualizacoes')" :previous-label="$ontemRotulo" :delta="$wowViews" />
        <x-metric-card label="Interactions" :value="MonitoringStats::numero($estatisticas['interacoes'])" unit="recent" accent="#7A4A8C"
                       :previous="$antes('interacoes')" :previous-label="$ontemRotulo" :delta="$wowInteracoes" />
        @if ($engajamento !== null)
            <x-metric-card label="Engagement" :value="number_format($engajamento, 1).'%'" unit="interactions / views" accent="#3D7A4C" />
        @endif
    </div>

    {{-- Daily trends from the recorded history (fills in as nightly collections run) --}}
    @if (count($historico) >= 2)
        @php $ultimoDia = end($historico); @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
            @foreach ([['subscritores', 'Subscribers', '#2F5A85'], ['visualizacoes', 'Views', '#8A4A2B'], ['interacoes', 'Interactions', '#7A4A8C']] as [$k, $rotulo, $cor])
                <div class="rounded-sm border border-ink-soft/15 bg-surface/20 p-3 min-w-0">
                    <div class="flex items-baseline justify-between gap-2 mb-2">
                        <span class="font-mono text-[0.62rem] uppercase tracking-wide" style="color: {{ $cor }}">{{ $rotulo }}</span>
                        <span class="font-display text-lg text-ink leading-none">{{ MonitoringStats::numero((int) $ultimoDia[$k]) }}</span>
                    </div>
                    <x-curve-chart :points="array_column($historico, $k)" :color="$cor" :height="44" />
                    <div class="flex justify-between font-mono text-[0.5rem] text-ink-faint mt-1">
                        <span>{{ $historico[0]['label'] }}</span>
                        <span>{{ $ultimoDia['label'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                    @if ($p['stats'])
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            @foreach ([
                                ['Followers', $p['stats']['seguidores'] > 0 ? MonitoringStats::numero($p['stats']['seguidores']) : '—'],
                                ['Posts', number_format($p['stats']['posts'])],
                                ['Views', MonitoringStats::numero($p['stats']['views'])],
                                ['Likes', MonitoringStats::numero($p['stats']['likes'])],
                                ['ER', $p['stats']['er'] !== null ? number_format($p['stats']['er'], 1).'%' : '—'],
                            ] as [$rotulo, $valor])
                                <div class="min-w-0">
                                    <div class="font-mono text-[0.52rem] uppercase tracking-wide text-ink-faint">{{ $rotulo }}</div>
                                    <div class="font-display text-base text-ink truncate">{{ $valor }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (array_sum($p['serie']) > 0)
                        <div class="mt-3">
                            <x-curve-chart :points="$p['serie']" :color="$meta['cor']" :height="36" />
                            <div class="font-mono text-[0.5rem] text-ink-faint mt-0.5">views · last 14 days</div>
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
            @forelse ($destaquesNoticias as $d)
                <div class="flex items-start gap-3 py-2.5 border-b border-ink-soft/10 last:border-0">
                    <x-badge tone="leather">{{ $d['fonte'] ?? $d['plataforma'] ?? '—' }}</x-badge>
                    <div class="min-w-0 flex-1">
                        <div class="text-ink">{{ $d['titulo'] ?? '' }}</div>
                        <div class="text-sm text-ink-soft italic">{{ $d['angulo'] ?? '' }}</div>
                    </div>
                    <div class="font-mono text-xs text-teal shrink-0">{{ $d['relevancia'] ?? '' }}</div>
                </div>
            @empty
                <p class="text-ink-soft text-sm py-2">No report yet — generate one in the <a href="{{ route('noticias') }}" class="text-teal hover:underline">News aggregator</a>.</p>
            @endforelse
        </x-panel>
    </div>
</div>
