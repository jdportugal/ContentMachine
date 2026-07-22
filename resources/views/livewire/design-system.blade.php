<div>
    <x-page-header
        eyebrow="Tomus · IX"
        title="Sistema de Design"
        cota="006.1 · IAT · '26"
        lead="A identidade de marca aplicada ao conteúdo gerado — clips animados e publicações. Guardada no vault e injetada nos prompts dos geradores. Não altera o design da própria aplicação." />

    <form wire:submit="guardar" class="space-y-6">
        {{-- Carregar um .md --}}
        <x-panel eyebrow="Semear" title="Carregar design.md" glyph="⬆">
            <p class="text-ink-soft -mt-2 mb-4">
                Carregue um ficheiro Markdown para preencher o editor. Nada é guardado até premir
                <span class="text-teal">Guardar</span> — assim pode rever antes.
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
                    Carregar para o editor
                </button>
                <span wire:loading wire:target="ficheiro" class="text-ink-faint font-mono text-xs">a receber…</span>
            </div>
            @error('ficheiro') <p class="mt-2 text-sm" style="color:#d98b7a">{{ $message }}</p> @enderror
        </x-panel>

        {{-- Editor --}}
        <x-panel eyebrow="Cérebro" title="design-system.md" glyph="✎">
            <div class="flex items-center justify-between gap-3 -mt-2 mb-3">
                <p class="text-ink-soft">
                    Markdown livre — voz, paleta, tipografia, regras de composição. Quanto mais claro, melhor o gerador o segue.
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

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="rounded-sm border border-teal/50 bg-teal/10 px-5 py-2.5 text-ink font-display text-lg
                           hover:bg-teal/20 hover:border-teal transition">
                Guardar
            </button>
            @if ($guardado)
                <span class="font-mono text-sm text-teal">✓ guardado às {{ $guardado }}</span>
            @endif
        </div>
    </form>
</div>
