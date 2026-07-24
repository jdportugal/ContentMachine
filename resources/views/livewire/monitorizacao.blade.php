<div>
    <x-page-header
        eyebrow="Tomus · II"
        title="Monitoring"
        cota="070.4 · IAT · '26"
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

    <div class="grid lg:grid-cols-2 gap-6 mt-8">
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
