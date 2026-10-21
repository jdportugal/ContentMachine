<div>
    <x-page-header
        eyebrow="Tomus · IX"
        title="Sistema de design"
        cota="006.1 · ACM · '26"
        lead="A identidade da marca aplicada ao conteúdo gerado — vídeos animados e publicações. Guardada no cofre e injetada nos prompts dos geradores. Não altera o design da própria aplicação." />

    <form wire:submit="guardar" class="space-y-6">
        {{-- Load a .md --}}
        <x-panel eyebrow="Ponto de partida" title="Carregar design.md" glyph="⬆">
            <p class="text-ink-soft -mt-2 mb-4">
                Carregue um ficheiro Markdown para preencher o editor. Nada é guardado até carregar em
                <span class="text-teal">Guardar</span> — assim pode rever primeiro.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <input type="file" wire:model="ficheiro" accept=".md,.markdown,text/markdown"
                       class="text-sm text-ink-soft font-mono
                              file:mr-3 file:rounded-sm file:border file:border-ink-soft/25
                              file:bg-papyrus/60 file:px-3 file:py-1.5 file:text-ink file:font-body
                              file:cursor-pointer hover:file:border-teal">
                <button type="button" wire:click="carregar"
                        class="rounded-sm border border-ink-soft/25 bg-surface/40 px-4 py-2 text-ink font-body
                               hover:border-teal transition disabled:opacity-40"
                        @disabled(! $ficheiro)>
                    Carregar no editor
                </button>
                <span wire:loading wire:target="ficheiro" class="text-ink-faint font-mono text-xs">a receber…</span>
            </div>
            @error('ficheiro') <p class="mt-2 text-sm" style="color:#A93A48">{{ $message }}</p> @enderror
        </x-panel>

        {{-- Editor --}}
        <x-panel eyebrow="Cérebro" title="design-system.md" glyph="✎">
            <div class="flex items-center justify-between gap-3 -mt-2 mb-3">
                <p class="text-ink-soft">
                    Markdown livre — tom de voz, paleta, tipografia, regras de composição. Quanto mais claro, melhor o gerador o segue.
                </p>
                @if ($atualizado)
                    <span class="shrink-0 font-mono text-[0.68rem] text-ink-faint">guardado · {{ $atualizado }}</span>
                @endif
            </div>
            <textarea wire:model="conteudo" rows="24" spellcheck="false"
                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-4 py-3
                             text-ink font-mono text-sm leading-relaxed resize-y
                             focus:border-teal focus:outline-none"></textarea>
            <div class="mt-2 font-mono text-[0.62rem] text-ink-faint break-all">{{ $caminho }}</div>
        </x-panel>

        {{-- Per-project direction appended to every animation prompt. --}}
        <x-panel eyebrow="Aplicado às animações" title="Instruções de animação" glyph="✦">
            <p class="text-ink-soft -mt-2 mb-3">
                Indicações extra acrescentadas ao fim do prompt do planeador para <strong>todos os vídeos deste projeto</strong> —
                o visual, o ritmo, o que evitar. É assim que um projeto se anima de forma diferente de outro.
                Um vídeo pode substituí-las na sua própria caixa de «instruções» ao ser criado.
            </p>
            <textarea wire:model="estilo" rows="14" spellcheck="false"
                      placeholder="ex. Abrir com um fundo escuro. Preferir diagramas a gráficos. Nunca usar a camada de terminal. Legendas com no máximo quatro palavras."
                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-4 py-3
                             text-ink font-mono text-sm leading-relaxed resize-y
                             focus:border-teal focus:outline-none"></textarea>
            <div class="mt-2 font-mono text-[0.62rem] text-ink-faint break-all">{{ $caminhoEstilo }}</div>
        </x-panel>

        {{-- Extracted theme (what the animations will use) --}}
        @if ($tokens)
            <x-panel eyebrow="Aplicado às animações" title="Tema extraído" glyph="❖">
                <p class="text-ink-soft -mt-2 mb-4">
                    Cores, tipos de letra e textura extraídos do design acima. É isto que os vídeos de
                    <span class="text-teal">Imóveis</span> usam agora para acompanhar a marca.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <div class="eyebrow mb-2">Paleta</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach (($tokens['colors'] ?? []) as $nome => $cor)
                                <div class="flex flex-col items-center gap-1">
                                    <span class="w-10 h-10 rounded-sm border border-ink-soft/25" style="background: {{ $cor }}"></span>
                                    <span class="font-mono text-[0.55rem] text-ink-faint">{{ $nome }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="eyebrow mb-2">Tipografia e textura</div>
                        <dl class="font-mono text-sm space-y-1 text-ink-soft">
                            <div><span class="text-ink-faint">display:</span> {{ $tokens['fonts']['display'] ?? '—' }}</div>
                            <div><span class="text-ink-faint">body:</span> {{ $tokens['fonts']['body'] ?? '—' }}</div>
                            <div><span class="text-ink-faint">mono:</span> {{ $tokens['fonts']['mono'] ?? '—' }}</div>
                            <div><span class="text-ink-faint">textura:</span> {{ $tokens['texture']['kind'] ?? 'paper' }}</div>
                        </dl>
                    </div>
                </div>
            </x-panel>
        @endif

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
                    class="rounded-sm border border-teal/50 bg-teal/10 px-5 py-2.5 text-ink font-display text-lg
                           hover:bg-teal/20 hover:border-teal transition disabled:opacity-50">
                <span wire:loading.remove wire:target="guardar">Guardar</span>
                <span wire:loading wire:target="guardar">A guardar e a extrair o tema…</span>
            </button>
            @if ($guardado)
                <span class="font-mono text-sm text-teal" wire:loading.remove wire:target="guardar">✓ guardado às {{ $guardado }}</span>
            @endif
        </div>
    </form>

    {{-- Its own component: uploads save on pick, so it lives outside the design-system form. --}}
    <div class="mt-6">
        <livewire:project-resources-panel />
    </div>
</div>
