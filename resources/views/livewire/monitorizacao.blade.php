<div>
    <x-page-header
        eyebrow="Tomus · II"
        title="Monitoring"
        cota="070.4 · ACM · '26"
        lead="Social network performance, with emphasis on the latest content of each genre and on the top performers." />

    {{-- Platform tabs --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach ($plataformas as $p)
            @php $m = config('contentmachine.plataformas_meta.'.$p); $ativo = $p === $rede; @endphp
            <button wire:click="selecionar('{{ $p }}')"
                    class="flex items-center gap-2 px-4 py-2 rounded-sm border transition font-display text-lg
                           {{ $ativo ? 'bg-surface/70 border-ink-soft/30 text-ink' : 'border-ink-soft/15 text-ink-soft hover:text-ink hover:bg-surface/40' }}">
                <span style="color: {{ $m['cor'] }}">{{ $m['glifo'] }}</span>
                {{ $m['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Real collection (yt-dlp for YouTube · Apify for the other networks) --}}
    @if ($recolheReal)
        <div class="flex flex-wrap items-center gap-3 mb-6 p-4 rounded-sm border border-ink-soft/15 bg-surface/30">
            <button wire:click="atualizar" wire:loading.attr="disabled" wire:target="atualizar"
                    x-on:click="window.CMLoader.busy('Collecting via {{ $fonte }}…')"
                    @disabled(! $fonteDisponivel)
                    class="rounded-sm border border-teal/50 bg-teal/10 px-4 py-2 text-ink font-display text-lg
                           hover:bg-teal/20 hover:border-teal transition disabled:opacity-40">
                <span wire:loading.remove wire:target="atualizar">Refresh data</span>
                <span wire:loading wire:target="atualizar">Collecting via {{ $fonte }}…</span>
            </button>

            {{-- Every network at once. Independent of $fonteDisponivel, which only
                 reflects the network currently in focus. --}}
            <button wire:click="atualizarTodas" wire:loading.attr="disabled" wire:target="atualizarTodas"
                    x-on:click="window.CMLoader.busy('Collecting every network…')"
                    class="rounded-sm border border-ink-soft/25 px-4 py-2 text-ink-soft font-display text-lg
                           hover:text-ink hover:border-teal/40 transition disabled:opacity-40">
                <span wire:loading.remove wire:target="atualizarTodas">⟳ Collect all networks</span>
                <span wire:loading wire:target="atualizarTodas">Collecting all…</span>
            </button>
            <div class="font-mono text-xs text-ink-faint">
                @if ($atualizadoEm)
                    last collection · {{ $atualizadoEm }} · via {{ $fonte }}
                @else
                    no collection for this network yet
                @endif
            </div>
            @if (trim($perfilUrl) === '')
                <span class="font-mono text-xs" style="color:#A93A48">⚠ set the profile URL in
                    <a href="{{ route('definicoes') }}" class="underline">Settings</a></span>
            @elseif (! $fonteDisponivel)
                <span class="font-mono text-xs" style="color:#A93A48">⚠ Apify collection not configured (set APIFY_TOKEN in .env)</span>
            @else
                <span class="font-mono text-[0.62rem] text-ink-faint truncate max-w-full">{{ $perfilUrl }}</span>
            @endif
            <span class="font-mono text-[0.6rem] text-ink-faint w-full">Collected automatically every night at 00:00 (needs the scheduler running).</span>
        </div>

        @if (empty($resumo) && empty($recentes))
            <x-panel class="mb-6">
                <p class="text-ink-soft italic">No data for <span class="text-ink">{{ $meta['label'] }}</span>.
                    Click <span class="text-teal">Refresh data</span> to collect via {{ $fonte }}.
                    @if ($rede !== 'youtube')<br><span class="text-ink-faint text-sm">Note: {{ $meta['label'] }} is collected via Apify (requires APIFY_TOKEN); may require a public profile.</span>@endif
                </p>
            </x-panel>
        @endif
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($resumo as $kpi)
            <x-metric-card :label="$kpi['label']" :value="$kpi['value']" :delta="$kpi['delta']" :unit="$kpi['unit']" :accent="$meta['cor']" />
        @endforeach
        @if ($subscribers > 0)
            <x-metric-card label="Followers" :value="\App\Services\Monitoring\MonitoringStats::numero($subscribers)" unit="channel" :accent="$meta['cor']" />
        @endif
        @if ($engajamento !== null && ! $semMetricas)
            <x-metric-card label="Engagement rate" :value="number_format($engajamento, 1).'%'" unit="interactions / views" accent="#3D7A4C" />
        @endif
        @if (! empty($melhores))
            @php $topo = $melhores[0]; $topoThumb = trim((string) ($topo['thumbnail'] ?? '')); @endphp
            <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm px-5 py-4 shadow-engraved min-w-0">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full" style="background: #A8701A; box-shadow: 0 0 6px #A8701A"></span>
                    <span class="eyebrow">Best post</span>
                </div>
                <div class="mt-2 flex items-center gap-3 min-w-0">
                    @if ($topoThumb !== '')
                        <img src="{{ $topoThumb }}" alt="" referrerpolicy="no-referrer" loading="lazy"
                             class="w-10 h-10 rounded-sm object-cover border border-ink-soft/20 shrink-0" onerror="this.style.display='none'">
                    @endif
                    <div class="min-w-0">
                        <div class="font-body text-ink text-sm truncate">{{ $topo['titulo'] }}</div>
                        <div class="font-mono text-[0.62rem] text-ink-faint">
                            score {{ $topo['score'] ?? '—' }}
                            @if (($topo['url'] ?? '#') !== '#')
                                · <a href="{{ $topo['url'] }}" target="_blank" rel="noopener" class="text-teal hover:underline">view ↗</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($semMetricas && ! empty($recentes))
        <p class="mt-3 font-mono text-xs text-ink-faint">
            {{ $meta['label'] }} does not expose likes/views to signed-out visitors — we show the posts (thumbnail + date). For metrics, use YouTube/TikTok.
        </p>
    @endif

    {{-- ═══════════ Stats over time — a curve per metric (day / month) ═══════════ --}}
    @php
        $corBase = $meta['cor'] ?? '#2C6E6E';
        // One curve per stat. Kept only when there's something to show.
        $metricas = [
            ['key' => 'views',       'label' => 'Views',    'cor' => $corBase],
            ['key' => 'likes',       'label' => 'Likes',    'cor' => '#A93A48'],
            ['key' => 'comentarios', 'label' => 'Comments', 'cor' => '#5E5590'],
            ['key' => 'partilhas',   'label' => 'Shares',   'cor' => '#2C6E6E'],
            ['key' => 'guardados',   'label' => 'Saves',    'cor' => '#A06E1C'],
            ['key' => 'posts',       'label' => 'Posts',    'cor' => '#3D7A4C'],
        ];
        $somaAmbos = fn (string $k) => collect($serieDia)->sum($k) + collect($serieSemana)->sum($k) + collect($serieMes)->sum($k);
        $metricas = array_values(array_filter($metricas, fn ($m) => $somaAmbos($m['key']) > 0));
        $temDia = collect($serieDia)->sum('views') > 0 || collect($serieDia)->sum('posts') > 0;
    @endphp
    @if (! empty($metricas))
        <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-4 mt-8" x-data="{ g: '{{ $temDia ? 'dia' : 'mes' }}' }">
            <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                <div>
                    <div class="eyebrow">Trends</div>
                    <p class="text-ink-soft text-sm mt-1">Every stat over time — totals per period.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="g='dia'" :class="g==='dia' ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft'" class="font-mono text-xs px-3 py-1 rounded-sm border transition">By day</button>
                    <button type="button" @click="g='semana'" :class="g==='semana' ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft'" class="font-mono text-xs px-3 py-1 rounded-sm border transition">By week</button>
                    <button type="button" @click="g='mes'" :class="g==='mes' ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft'" class="font-mono text-xs px-3 py-1 rounded-sm border transition">By month</button>
                </div>
            </div>

            @foreach (['dia' => $serieDia, 'semana' => $serieSemana, 'mes' => $serieMes] as $g => $serie)
                @php
                    $labels = array_column($serie, 'label');
                    $primeira = $labels[0] ?? '';
                    $ultima = end($labels) ?: '';
                @endphp
                <div x-show="g === '{{ $g }}'" @if ($g !== 'dia') x-cloak @endif class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($metricas as $m)
                        @php
                            $total = collect($serie)->sum($m['key']);
                            $v = $wow[$m['key']] ?? null;
                        @endphp
                        <div class="rounded-sm border border-ink-soft/15 bg-surface/20 p-3 min-w-0">
                            <div class="flex items-baseline justify-between gap-2 mb-2">
                                <span class="font-mono text-[0.62rem] uppercase tracking-wide" style="color: {{ $m['cor'] }}">{{ $m['label'] }}</span>
                                <span class="flex items-baseline gap-2">
                                    {{-- week over week: last 7 days vs the 7 before --}}
                                    @if ($v !== null && $v['delta'] !== null)
                                        <span class="font-mono text-[0.6rem] {{ $v['delta'] >= 0 ? 'text-good' : 'text-bad' }}"
                                              title="{{ number_format($v['anterior']) }} → {{ number_format($v['atual']) }} · last 7 days vs the 7 before">
                                            {{ $v['delta'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($v['delta']), 1) }}% wow
                                        </span>
                                    @elseif ($v !== null && $v['atual'] > 0)
                                        <span class="font-mono text-[0.6rem] text-good" title="{{ number_format($v['atual']) }} in the last 7 days · none in the 7 before">▲ new wow</span>
                                    @endif
                                    <span class="font-display text-lg text-ink leading-none">{{ number_format($total) }}</span>
                                </span>
                            </div>
                            <x-curve-chart :points="array_map(fn ($b) => $b[$m['key']], $serie)" :color="$m['cor']" :height="52" />
                            <div class="flex justify-between font-mono text-[0.5rem] text-ink-faint mt-1">
                                <span>{{ $primeira }}</span>
                                <span>{{ $ultima }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    {{-- ═══════════ Best time to post · Follower evolution ═══════════ --}}
    @php $temHistorico = count($historico) >= 2 && collect($historico)->sum('subscritores') > 0; @endphp
    @if ($heatmap || $temHistorico)
        <div class="grid grid-cols-1 {{ $heatmap && $temHistorico ? 'lg:grid-cols-2' : '' }} gap-6 mt-8">
            @if ($heatmap)
                <x-panel eyebrow="Rhythm" title="Best time to post" glyph="◷">
                    <p class="text-sm text-ink-soft mb-3 -mt-2">Where the {{ $heatmap['metrica'] }} land, by weekday and publish hour.</p>
                    <div class="overflow-x-auto">
                        <div class="min-w-[430px]">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d => $dia)
                                <div class="flex items-center gap-1 mb-1">
                                    <span class="font-mono text-[0.55rem] text-ink-faint w-7 shrink-0">{{ $dia }}</span>
                                    @foreach ($heatmap['grade'][$d] as $h => $c)
                                        <div class="flex-1 aspect-square rounded-[2px] {{ $c['nivel'] === 0 ? 'bg-ink-soft/10' : '' }}"
                                             @if ($c['nivel'] > 0) style="background: {{ $meta['cor'] }}{{ ['', '40', '73', 'B3', 'FF'][$c['nivel']] }}" @endif
                                             title="{{ $dia }} {{ $h }}h — {{ $c['valor'] }} {{ $heatmap['metrica'] }}"></div>
                                    @endforeach
                                </div>
                            @endforeach
                            <div class="flex items-center gap-1 mt-1">
                                <span class="w-7 shrink-0"></span>
                                @foreach (range(0, 23) as $h)
                                    <span class="flex-1 text-center font-mono text-[0.5rem] text-ink-faint">{{ $h % 3 === 0 ? $h : '' }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @if (! empty($heatmap['melhores']))
                        <div class="flex items-center gap-2 flex-wrap mt-3">
                            <span class="font-mono text-[0.62rem] text-ink-faint">Best times:</span>
                            @foreach ($heatmap['melhores'] as $m)
                                <x-badge tone="teal">{{ $m['label'] }} · {{ \App\Services\Monitoring\MonitoringStats::numero($m['valor']) }}</x-badge>
                            @endforeach
                        </div>
                    @endif
                </x-panel>
            @endif

            @if ($temHistorico)
                <x-panel eyebrow="Evolution" title="Follower evolution" glyph="↗">
                    @php $ultimoDia = end($historico); @endphp
                    <div class="flex items-baseline justify-between mb-3 -mt-2">
                        <p class="text-sm text-ink-soft">Daily totals, all networks · last {{ count($historico) }} days recorded.</p>
                        <span class="font-display text-2xl text-ink leading-none">{{ \App\Services\Monitoring\MonitoringStats::numero((int) $ultimoDia['subscritores']) }}</span>
                    </div>
                    <x-curve-chart :points="array_column($historico, 'subscritores')" :color="$meta['cor']" :height="120" />
                    <div class="flex justify-between font-mono text-[0.5rem] text-ink-faint mt-1">
                        <span>{{ $historico[0]['label'] }}</span>
                        <span>{{ $ultimoDia['label'] }}</span>
                    </div>
                </x-panel>
            @endif
        </div>
    @endif

    {{-- ═══════════ Prediction — road to the next follower milestone ═══════════ --}}
    @if ($subscribers > 0)
        <x-panel eyebrow="Prediction" title="Road to {{ number_format($previsao['alvo'] ?? 1000) }} followers" glyph="✦" class="mt-8">
            @if ($previsao === null)
                <p class="text-ink-soft text-sm">Predictions unlock once a few days of follower history are recorded —
                    the nightly collection is writing it now. Check back in a couple of days.</p>
            @else
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                    @if ($previsao['posts'] !== null)
                        <div>
                            <div class="font-display text-4xl text-ink leading-none">≈ {{ number_format($previsao['posts']) }} posts</div>
                            <div class="font-mono text-[0.62rem] text-ink-faint mt-1">at your current pace and growth</div>
                        </div>
                        @if (count($previsao['mix']) > 1)
                            <div class="flex items-center gap-2 flex-wrap">
                                @foreach ($previsao['mix'] as $tipo => $n)
                                    <x-badge tone="teal">{{ number_format($n) }} {{ $tipo }}</x-badge>
                                @endforeach
                            </div>
                        @endif
                        <div>
                            <div class="font-display text-2xl text-ink leading-none">~ {{ number_format($previsao['dias']) }} days</div>
                            <div class="font-mono text-[0.62rem] text-ink-faint mt-1">around {{ $previsao['eta'] }}</div>
                        </div>
                    @elseif ($previsao['dias'] !== null)
                        <div>
                            <div class="font-display text-2xl text-ink leading-none">~ {{ number_format($previsao['dias']) }} days</div>
                            <div class="font-mono text-[0.62rem] text-ink-faint mt-1">around {{ $previsao['eta'] }} — no recent posts to estimate a post count from</div>
                        </div>
                    @else
                        <p class="text-ink-soft text-sm">No follower growth recorded since {{ \Illuminate\Support\Carbon::parse($previsao['desde'])->translatedFormat('j M') }} —
                            the trend is flat, so no honest ETA exists yet. Keep posting.</p>
                    @endif
                </div>
                <div class="mt-4">
                    <div class="h-1.5 rounded-full bg-ink-soft/10 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ min(100, round($subscribers / $previsao['alvo'] * 100, 1)) }}%; background: {{ $meta['cor'] }}"></div>
                    </div>
                    <div class="flex justify-between font-mono text-[0.58rem] text-ink-faint mt-1">
                        <span>{{ number_format($subscribers) }} followers · {{ number_format($previsao['faltam']) }} to go</span>
                        <span>{{ $previsao['crescimentoDia'] >= 0 ? '+' : '' }}{{ $previsao['crescimentoDia'] }}/day since {{ \Illuminate\Support\Carbon::parse($previsao['desde'])->translatedFormat('j M') }} · {{ $previsao['postsPorDia'] }} posts/day</span>
                    </div>
                </div>
            @endif
        </x-panel>
    @endif

    {{-- ═══════════ Platform breakdown (every collected network) ═══════════ --}}
    @if (! empty($breakdown))
        <x-panel eyebrow="Networks" title="Platform breakdown" glyph="◫" class="mt-8">
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="text-left font-mono text-[0.58rem] text-ink-faint uppercase tracking-wide">
                            <th class="py-1.5 pr-3">Platform</th>
                            <th class="py-1.5 px-3 text-right">Followers</th>
                            <th class="py-1.5 px-3 text-right">Posts</th>
                            <th class="py-1.5 px-3 text-right">Views</th>
                            <th class="py-1.5 px-3 text-right">Likes</th>
                            <th class="py-1.5 px-3 text-right">Comments</th>
                            <th class="py-1.5 px-3 text-right">Shares</th>
                            <th class="py-1.5 px-3 text-right">Saves</th>
                            <th class="py-1.5 pl-3 text-right">ER</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($breakdown as $linha)
                            @php $bm = config('contentmachine.plataformas_meta.'.$linha['plataforma']); @endphp
                            <tr class="border-t border-ink-soft/10">
                                <td class="py-2 pr-3 font-body text-ink">
                                    <span style="color: {{ $bm['cor'] ?? '#2C6E6E' }}">{{ $bm['glifo'] ?? '◦' }}</span>
                                    {{ $bm['label'] ?? $linha['plataforma'] }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ $linha['seguidores'] > 0 ? \App\Services\Monitoring\MonitoringStats::numero($linha['seguidores']) : '—' }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ number_format($linha['posts']) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink">{{ \App\Services\Monitoring\MonitoringStats::numero($linha['views']) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ \App\Services\Monitoring\MonitoringStats::numero($linha['likes']) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ \App\Services\Monitoring\MonitoringStats::numero($linha['comentarios']) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ $linha['partilhas'] > 0 ? \App\Services\Monitoring\MonitoringStats::numero($linha['partilhas']) : '—' }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ $linha['guardados'] > 0 ? \App\Services\Monitoring\MonitoringStats::numero($linha['guardados']) : '—' }}</td>
                                <td class="py-2 pl-3 text-right font-mono {{ $linha['er'] !== null ? 'text-good' : 'text-ink-faint' }}">{{ $linha['er'] !== null ? number_format($linha['er'], 1).'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    {{-- ═══════════ Averages by content type ═══════════ --}}
    @if (! empty($mediasPorTipo))
        <x-panel eyebrow="Averages" title="Averages by type" glyph="∑" class="mt-8">
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="text-left font-mono text-[0.58rem] text-ink-faint uppercase tracking-wide">
                            <th class="py-1.5 pr-3">Type</th>
                            <th class="py-1.5 px-3 text-right">Posts</th>
                            <th class="py-1.5 px-3 text-right">Avg views</th>
                            <th class="py-1.5 px-3 text-right">Avg likes</th>
                            <th class="py-1.5 px-3 text-right">Engagement</th>
                            @if ($subscribers > 0)
                                <th class="py-1.5 pl-3 text-right">Subs / post</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mediasPorTipo as $m)
                            <tr class="border-t border-ink-soft/10">
                                <td class="py-2 pr-3 font-body text-ink capitalize">{{ $m['tipo'] }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ number_format($m['posts']) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink">{{ number_format($m['views_med']) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ number_format($m['likes_med']) }}</td>
                                <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ number_format($m['engajamento'], 1) }}%</td>
                                @if ($subscribers > 0)
                                    <td class="py-2 pl-3 text-right font-mono text-teal">{{ number_format($m['subs_por']) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($subscribers > 0)
                <p class="font-mono text-[0.55rem] text-ink-faint mt-3">Channel: {{ number_format($subscribers) }} subscribers · «Subs / post» = subscribers ÷ posts of that type.</p>
            @endif
        </x-panel>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
        {{-- Latest of each type (requested emphasis) --}}
        <x-panel eyebrow="Emphasis" title="Latest of each genre" glyph="❧">
            <p class="text-sm text-ink-soft mb-3 -mt-2">Performance of the most recent content of each published type.</p>
            @forelse ($ultimoPorTipo as $item)
                <x-content-row :item="$item" :metricas="! $semMetricas" />
            @empty
                <p class="text-ink-soft italic">No data.</p>
            @endforelse
        </x-panel>

        {{-- Recent --}}
        <x-panel eyebrow="Timeline" title="Recent posts" glyph="⌛">
            @foreach ($recentes as $item)
                <x-content-row :item="$item" :metricas="! $semMetricas" />
            @endforeach
        </x-panel>
    </div>

    {{-- ═══════════ Top performing posts ═══════════ --}}
    @if (! empty($melhores))
        <x-panel eyebrow="Ranking" title="Top performing posts" glyph="★" class="mt-6">
            <p class="text-sm text-ink-soft mb-3 -mt-2">The highest-scoring content, with every collected metric.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="text-left font-mono text-[0.58rem] text-ink-faint uppercase tracking-wide">
                            <th class="py-1.5 pr-3">Post</th>
                            @unless ($semMetricas)
                                <th class="py-1.5 px-3 text-right">Views</th>
                                <th class="py-1.5 px-3 text-right">Likes</th>
                                <th class="py-1.5 px-3 text-right">Comments</th>
                                <th class="py-1.5 px-3 text-right">Shares</th>
                                <th class="py-1.5 px-3 text-right">Saves</th>
                                <th class="py-1.5 px-3 text-right">ER</th>
                            @endunless
                            <th class="py-1.5 pl-3 text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($melhores as $item)
                            @php
                                $er = \App\Services\Monitoring\MonitoringAnalytics::erItem($item);
                                $thumb = trim((string) ($item['thumbnail'] ?? ''));
                            @endphp
                            <tr class="border-t border-ink-soft/10">
                                <td class="py-2 pr-3">
                                    <a @if (($item['url'] ?? '#') !== '#') href="{{ $item['url'] }}" target="_blank" rel="noopener" @endif
                                       class="flex items-center gap-3 min-w-0 max-w-md group">
                                        @if ($thumb !== '')
                                            <img src="{{ $thumb }}" alt="" referrerpolicy="no-referrer" loading="lazy"
                                                 class="w-9 h-9 rounded-sm object-cover border border-ink-soft/20 shrink-0" onerror="this.style.display='none'">
                                        @endif
                                        <span class="min-w-0">
                                            <span class="block font-body text-ink truncate group-hover:text-teal transition">{{ $item['titulo'] }}</span>
                                            <span class="block font-mono text-[0.58rem] text-ink-faint">{{ \Illuminate\Support\Carbon::parse($item['publicado_em'])->translatedFormat('d M Y') }} · {{ $item['tipo'] }}</span>
                                        </span>
                                    </a>
                                </td>
                                @unless ($semMetricas)
                                    <td class="py-2 px-3 text-right font-mono text-ink">{{ \App\Services\Monitoring\MonitoringStats::numero((int) ($item['views'] ?? 0)) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ number_format($item['likes'] ?? 0) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ number_format($item['comentarios'] ?? 0) }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ ($item['partilhas'] ?? 0) > 0 ? number_format($item['partilhas']) : '—' }}</td>
                                    <td class="py-2 px-3 text-right font-mono text-ink-soft">{{ ($item['guardados'] ?? 0) > 0 ? number_format($item['guardados']) : '—' }}</td>
                                    <td class="py-2 px-3 text-right font-mono {{ $er !== null ? 'text-good' : 'text-ink-faint' }}">{{ $er !== null ? number_format($er, 1).'%' : '—' }}</td>
                                @endunless
                                <td class="py-2 pl-3 text-right font-display text-lg text-gold">{{ $item['score'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif
</div>
