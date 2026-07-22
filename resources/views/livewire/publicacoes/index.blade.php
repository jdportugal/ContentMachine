<div>
    <x-page-header
        eyebrow="Tomus · V"
        title="Publicações"
        cota="686.2 · IAT · '26"
        lead="Composição de peças para redes sociais. Escolha a oficina consoante o formato." />

    <div class="grid md:grid-cols-2 gap-6">
        @foreach ($this->tipos as $tipo => $def)
            <a href="{{ route('publicacoes.oficina', $tipo) }}" class="block group" wire:key="tipo-{{ $tipo }}">
                <x-panel class="h-full transition group-hover:border-teal/40">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="eyebrow mb-2">{{ $def['formato'] === 'carousel' ? 'Carrossel' : 'Peça única' }} · {{ $def['proporcao'] }}</div>
                            <h2 class="font-display text-3xl text-ink">{{ $def['label'] }}</h2>
                            <p class="mt-2 text-ink-soft">{{ $def['descricao'] }}</p>
                        </div>
                        <span class="text-4xl text-gold/70 select-none">{{ $def['glifo'] }}</span>
                    </div>
                    <div class="mt-6 font-mono text-[0.62rem] text-teal">abrir oficina →</div>
                </x-panel>
            </a>
        @endforeach
    </div>

    <x-fleuron glyph="❧" />
    <p class="text-center text-ink-soft italic">Acrescentar um formato é só acrescentar uma entrada ao registo de tipos.</p>
</div>
