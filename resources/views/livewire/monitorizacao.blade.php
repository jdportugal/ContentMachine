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
                <span class="font-mono text-xs" style="color:#FF8FA6">⚠ set the profile URL in
                    <a href="{{ route('definicoes') }}" class="underline">Settings</a></span>
            @elseif (! $fonteDisponivel)
                <span class="font-mono text-xs" style="color:#FF8FA6">⚠ Apify collection not configured (set APIFY_TOKEN in .env)</span>
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
    </div>

    @if ($semMetricas && ! empty($recentes))
        <p class="mt-3 font-mono text-xs text-ink-faint">
            {{ $meta['label'] }} does not expose likes/views to signed-out visitors — we show the posts (thumbnail + date). For metrics, use YouTube/TikTok.
        </p>
    @endif

    {{-- ═══════════ Stats over time — a curve per metric (day / month) ═══════════ --}}
    @php
        $corBase = $meta['cor'] ?? '#4DE0E0';
        // One curve per stat. Kept only when there's something to show.
        $metricas = [
            ['key' => 'views',       'label' => 'Views',    'cor' => $corBase],
            ['key' => 'likes',       'label' => 'Likes',    'cor' => '#FF5C7A'],
            ['key' => 'comentarios', 'label' => 'Comments', 'cor' => '#9C7DFF'],
            ['key' => 'partilhas',   'label' => 'Shares',   'cor' => '#4DE0E0'],
            ['key' => 'guardados',   'label' => 'Saves',    'cor' => '#FFB347'],
            ['key' => 'posts',       'label' => 'Posts',    'cor' => '#4DE08A'],
        ];
        $somaAmbos = fn (string $k) => collect($serieDia)->sum($k) + collect($serieMes)->sum($k);
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
                    <button type="button" @click="g='mes'" :class="g==='mes' ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft'" class="font-mono text-xs px-3 py-1 rounded-sm border transition">By month</button>
                </div>
            </div>

            @foreach (['dia' => $serieDia, 'mes' => $serieMes] as $g => $serie)
                @php
                    $labels = array_column($serie, 'label');
                    $primeira = $labels[0] ?? '';
                    $ultima = end($labels) ?: '';
                @endphp
                <div x-show="g === '{{ $g }}'" @if ($g === 'mes') x-cloak @endif class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($metricas as $m)
                        @php $total = collect($serie)->sum($m['key']); @endphp
                        <div class="rounded-sm border border-ink-soft/15 bg-surface/20 p-3 min-w-0">
                            <div class="flex items-baseline justify-between gap-2 mb-2">
                                <span class="font-mono text-[0.62rem] uppercase tracking-wide" style="color: {{ $m['cor'] }}">{{ $m['label'] }}</span>
                                <span class="font-display text-lg text-ink leading-none">{{ number_format($total) }}</span>
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

        {{-- Top performers --}}
        <x-panel eyebrow="Ranking" title="Top performers" glyph="★">
            <p class="text-sm text-ink-soft mb-3 -mt-2">Content with the highest weighted performance index.</p>
            @foreach ($melhores as $i => $item)
                <div class="flex items-center gap-3">
                    <span class="font-display text-2xl text-gold w-6 text-center shrink-0">{{ $i + 1 }}</span>
                    <div class="flex-1 min-w-0"><x-content-row :item="$item" :metricas="! $semMetricas" /></div>
                </div>
            @endforeach
        </x-panel>
    </div>

    {{-- Recent --}}
    <div class="mt-6">
        <x-panel eyebrow="Timeline" title="Recent posts" glyph="⌛">
            @foreach ($recentes as $item)
                <x-content-row :item="$item" :metricas="! $semMetricas" />
            @endforeach
        </x-panel>
    </div>
</div>
