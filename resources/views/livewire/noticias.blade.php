<div>
    <x-page-header
        eyebrow="Tomus · VII"
        title="Agregador de Notícias"
        cota="070.4 · IAT · '26"
        lead="Reúne conteúdo de várias fontes e destila um relatório personalizado, pronto a virar guião." />

    <div class="grid lg:grid-cols-4 gap-6">
        {{-- Configuração de fontes --}}
        <div class="lg:col-span-1">
            <x-panel eyebrow="Fontes" title="Recolha" glyph="☙">
                <div class="space-y-2">
                    @foreach ($fontesDisponiveis as $fonte)
                        <label class="flex items-center gap-2 cursor-pointer text-ink-soft hover:text-ink">
                            <input type="checkbox" wire:model.live="fontes" value="{{ $fonte }}"
                                   class="accent-teal w-4 h-4">
                            <span class="capitalize font-body">{{ $fonte }}</span>
                        </label>
                    @endforeach
                </div>

                <button wire:click="guardarNoVault"
                        class="mt-5 w-full bg-teal text-papyrus font-display text-base px-4 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                    Guardar relatório no vault
                </button>

                @if ($guardado)
                    <div class="mt-3 border border-good/40 bg-good/10 text-good rounded-sm px-3 py-2 font-mono text-xs">
                        ✓ «{{ $guardado }}» arquivado no vault.
                    </div>
                @endif
            </x-panel>
        </div>

        {{-- Relatório --}}
        <div class="lg:col-span-3">
            <x-panel>
                <div class="flex items-start justify-between gap-4 mb-2">
                    <div>
                        <div class="eyebrow mb-1">Relatório personalizado</div>
                        <h2 class="font-display text-3xl text-ink leading-tight">{{ $relatorio['titulo'] }}</h2>
                    </div>
                    <x-selo label="IATECA" sub="NOTÍCIAS" date="MMXXVI" color="#c89b3c" />
                </div>

                <p class="text-lg text-ink-soft italic dropcap">{{ $relatorio['resumo'] }}</p>

                <x-fleuron glyph="☙" />

                <div class="eyebrow mb-2">Destaques</div>
                <div class="space-y-1">
                    @forelse ($relatorio['destaques'] as $d)
                        <div class="flex items-start gap-3 py-2.5 border-b border-ink-soft/10 last:border-0">
                            <x-badge tone="leather">{{ $d['fonte'] }}</x-badge>
                            <div class="min-w-0 flex-1">
                                <div class="text-ink">{{ $d['titulo'] }}</div>
                                <div class="text-sm text-ink-soft italic">↳ {{ $d['angulo'] }}</div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="font-display text-2xl text-teal leading-none">{{ $d['relevancia'] }}</div>
                                <div class="eyebrow !text-[0.55rem]">relevância</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-ink-soft italic">Seleccione pelo menos uma fonte à esquerda.</p>
                    @endforelse
                </div>

                @if (!empty($relatorio['ideias_guiao']))
                    <x-fleuron glyph="✒" />
                    <div class="eyebrow mb-2">Ideias de guião</div>
                    <ul class="space-y-2">
                        @foreach ($relatorio['ideias_guiao'] as $ideia)
                            <li class="flex gap-3 text-ink">
                                <span class="text-gold shrink-0">❦</span>
                                <span>{{ $ideia }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>
        </div>
    </div>
</div>
