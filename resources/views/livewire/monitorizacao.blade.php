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

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($resumo as $kpi)
            <x-metric-card :label="$kpi['label']" :value="$kpi['value']" :delta="$kpi['delta']" :unit="$kpi['unit']" :accent="$meta['cor']" />
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mt-8">
        {{-- Último de cada tipo (ênfase pedida) --}}
        <x-panel eyebrow="Ênfase" title="Último de cada género" glyph="❧">
            <p class="text-sm text-ink-soft mb-3 -mt-2">Desempenho do conteúdo mais recente de cada tipo publicado.</p>
            @forelse ($ultimoPorTipo as $item)
                <x-content-row :item="$item" />
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
                    <div class="flex-1 min-w-0"><x-content-row :item="$item" /></div>
                </div>
            @endforeach
        </x-panel>
    </div>

    {{-- Recentes --}}
    <div class="mt-6">
        <x-panel eyebrow="Cronologia" title="Publicações recentes" glyph="⌛">
            @foreach ($recentes as $item)
                <x-content-row :item="$item" />
            @endforeach
        </x-panel>
    </div>
</div>
