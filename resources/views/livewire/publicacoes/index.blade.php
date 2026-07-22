<div>
    <x-page-header
        eyebrow="Tomus · V"
        title="Publicações"
        cota="686.2 · IAT · '26"
        lead="As suas peças para redes sociais. Componha novas ou reveja as já criadas." />

    {{-- Publicações criadas --}}
    <x-panel eyebrow="Estante" title="Publicações criadas" glyph="❦" class="mb-8">
        @forelse ($this->publicacoes as $nota)
            @php
                $tipo = $nota->get('tipo', 'post');
                $def = config('contentmachine.publicacoes.tipos.'.$tipo, []);
                $capa = ((array) $nota->get('imagens', []))[0] ?? null;
                $meta = config('contentmachine.plataformas_meta.'.$nota->get('plataforma'));
                $agendado = $nota->get('estado') === 'agendado';
            @endphp
            <div class="flex items-center gap-4 py-3 border-b border-ink-soft/10 last:border-0" wire:key="pub-{{ $nota->slug() }}">
                <a href="{{ route('publicacoes.oficina', ['tipo' => $tipo, 'nota' => $nota->slug()]) }}" class="shrink-0">
                    @if ($capa)
                        <img src="{{ \Illuminate\Support\Str::startsWith($capa, 'http') ? $capa : asset($capa) }}" alt="capa" class="w-14 h-16 object-cover rounded-sm border border-ink-soft/20">
                    @else
                        <div class="w-14 h-16 rounded-sm border border-ink-soft/20 bg-vellum/50 flex items-center justify-center text-2xl text-gold/60">{{ $def['glifo'] ?? '❦' }}</div>
                    @endif
                </a>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <x-badge tone="teal">{{ $def['label'] ?? $tipo }}</x-badge>
                        @if (($n = (int) $nota->get('cartoes', 0)) > 1)<x-badge tone="leather">{{ $n }} cartões</x-badge>@endif
                        @if ($meta)<x-badge tone="leather" style="color: {{ $meta['cor'] }}">{{ $meta['label'] }}</x-badge>@endif
                        @if ($agendado)<x-badge tone="good">✓ agendado</x-badge>@else<x-badge tone="warn">rascunho</x-badge>@endif
                    </div>
                    <a href="{{ route('publicacoes.oficina', ['tipo' => $tipo, 'nota' => $nota->slug()]) }}" class="font-display text-xl text-ink hover:text-teal transition leading-tight">{{ $nota->title() }}</a>
                </div>
                <a href="{{ route('publicacoes.oficina', ['tipo' => $tipo, 'nota' => $nota->slug()]) }}"
                   class="shrink-0 font-mono text-[0.62rem] text-teal hover:underline">editar →</a>
                <button wire:click="remover('{{ $nota->path }}')" wire:confirm="Remover esta publicação?"
                        class="shrink-0 text-ink-faint hover:text-bad px-1 text-lg" title="Remover">🗑</button>
            </div>
        @empty
            <x-empty-state>Ainda não há publicações. Componha a primeira abaixo.</x-empty-state>
        @endforelse
    </x-panel>

    {{-- Nova publicação --}}
    <div class="eyebrow mb-3">Nova publicação · escolha o formato</div>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($this->tipos as $tipo => $def)
            <a href="{{ route('publicacoes.oficina', $tipo) }}" class="block group" wire:key="tipo-{{ $tipo }}">
                <x-panel class="h-full transition group-hover:border-teal/40">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="eyebrow mb-1">{{ $def['formato'] === 'carousel' ? 'Carrossel' : 'Peça única' }} · {{ $def['proporcao'] }}</div>
                            <h2 class="font-display text-2xl text-ink">{{ $def['label'] }}</h2>
                            <p class="mt-1 text-ink-soft text-sm">{{ $def['descricao'] }}</p>
                        </div>
                        <span class="text-3xl text-gold/70 select-none">{{ $def['glifo'] }}</span>
                    </div>
                    <div class="mt-4 font-mono text-[0.62rem] text-teal">abrir oficina →</div>
                </x-panel>
            </a>
        @endforeach
    </div>
</div>
