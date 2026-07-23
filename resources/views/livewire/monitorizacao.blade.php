<div>
    <x-page-header
        eyebrow="Tomus · II"
        title="Monitorização"
        cota="070.4 · IAT · '26"
        lead="Desempenho das redes sociais, com ênfase no último conteúdo de cada género e nos melhores desempenhos." />

    {{-- Separadores de plataforma --}}
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

    {{-- Recolha real (yt-dlp para YouTube · Apify para as outras redes) --}}
    @if ($recolheReal)
        <div class="flex flex-wrap items-center gap-3 mb-6 p-4 rounded-sm border border-ink-soft/15 bg-surface/30">
            <button wire:click="atualizar" wire:loading.attr="disabled" wire:target="atualizar"
                    @disabled(! $fonteDisponivel)
                    class="rounded-sm border border-teal/50 bg-teal/10 px-4 py-2 text-ink font-display text-lg
                           hover:bg-teal/20 hover:border-teal transition disabled:opacity-40">
                <span wire:loading.remove wire:target="atualizar">Atualizar dados</span>
                <span wire:loading wire:target="atualizar">A recolher via {{ $fonte }}…</span>
            </button>
            <div class="font-mono text-xs text-ink-faint">
                @if ($atualizadoEm)
                    última recolha · {{ $atualizadoEm }} · via {{ $fonte }}
                @else
                    ainda sem recolha para esta rede
                @endif
            </div>
            @if (trim($perfilUrl) === '')
                <span class="font-mono text-xs" style="color:#FF8FA6">⚠ defina o URL do perfil em
                    <a href="{{ route('definicoes') }}" class="underline">Definições</a></span>
            @elseif (! $fonteDisponivel)
                <span class="font-mono text-xs" style="color:#FF8FA6">⚠ recolha por Apify não configurada (defina APIFY_TOKEN no .env)</span>
            @else
                <span class="font-mono text-[0.62rem] text-ink-faint truncate max-w-full">{{ $perfilUrl }}</span>
            @endif
        </div>

        @if (empty($resumo) && empty($recentes))
            <x-panel class="mb-6">
                <p class="text-ink-soft italic">Sem dados para <span class="text-ink">{{ $meta['label'] }}</span>.
                    Carregue em <span class="text-teal">Atualizar dados</span> para recolher via {{ $fonte }}.
                    @if ($rede !== 'youtube')<br><span class="text-ink-faint text-sm">Nota: {{ $meta['label'] }} é recolhido por Apify (requer APIFY_TOKEN); pode exigir um perfil público.</span>@endif
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
            {{ $meta['label'] }} não expõe gostos/visualizações a quem não tem sessão — mostramos as publicações (miniatura + data). Para métricas, use YouTube/TikTok.
        </p>
    @endif

    <div class="grid lg:grid-cols-2 gap-6 mt-8">
        {{-- Último de cada tipo (ênfase pedida) --}}
        <x-panel eyebrow="Ênfase" title="Último de cada género" glyph="❧">
            <p class="text-sm text-ink-soft mb-3 -mt-2">Desempenho do conteúdo mais recente de cada tipo publicado.</p>
            @forelse ($ultimoPorTipo as $item)
                <x-content-row :item="$item" :metricas="! $semMetricas" />
            @empty
                <p class="text-ink-soft italic">Sem dados.</p>
            @endforelse
        </x-panel>

        {{-- Melhores --}}
        <x-panel eyebrow="Ranking" title="Melhores desempenhos" glyph="★">
            <p class="text-sm text-ink-soft mb-3 -mt-2">Conteúdos com maior índice de desempenho ponderado.</p>
            @foreach ($melhores as $i => $item)
                <div class="flex items-center gap-3">
                    <span class="font-display text-2xl text-gold w-6 text-center shrink-0">{{ $i + 1 }}</span>
                    <div class="flex-1 min-w-0"><x-content-row :item="$item" :metricas="! $semMetricas" /></div>
                </div>
            @endforeach
        </x-panel>
    </div>

    {{-- Recentes --}}
    <div class="mt-6">
        <x-panel eyebrow="Cronologia" title="Publicações recentes" glyph="⌛">
            @foreach ($recentes as $item)
                <x-content-row :item="$item" :metricas="! $semMetricas" />
            @endforeach
        </x-panel>
    </div>
</div>
