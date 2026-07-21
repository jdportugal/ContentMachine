<div>
    <x-page-header
        eyebrow="Tomus · V"
        title="Publicações"
        cota="686.2 · IAT · '26"
        lead="Composição de peças para redes sociais. Escolha a oficina consoante o formato." />

    <div class="grid md:grid-cols-2 gap-6">
        <a href="{{ route('publicacoes.posts') }}" class="block group">
            <x-panel class="h-full transition group-hover:border-teal/40">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="eyebrow mb-2">Formato · A</div>
                        <h2 class="font-display text-3xl text-ink">Posts de página única</h2>
                        <p class="mt-2 text-ink-soft">Peças quadradas — «Sabia que…», citações, anúncios.</p>
                    </div>
                    <span class="text-4xl text-gold/70 select-none">❦</span>
                </div>
                <div class="mt-6 font-mono text-[0.62rem] text-teal">abrir oficina →</div>
            </x-panel>
        </a>

        <a href="{{ route('publicacoes.carrosseis') }}" class="block group">
            <x-panel class="h-full transition group-hover:border-teal/40">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="eyebrow mb-2">Formato · B</div>
                        <h2 class="font-display text-3xl text-ink">Carrosséis</h2>
                        <p class="mt-2 text-ink-soft">Sequências de vários cartões — capa, conteúdo, despedida.</p>
                    </div>
                    <span class="text-4xl text-gold/70 select-none">☰</span>
                </div>
                <div class="mt-6 font-mono text-[0.62rem] text-teal">abrir oficina →</div>
            </x-panel>
        </a>
    </div>

    <x-fleuron glyph="❧" />
    <p class="text-center text-ink-soft italic">Mais formatos serão acrescentados a esta estante com o tempo.</p>
</div>
