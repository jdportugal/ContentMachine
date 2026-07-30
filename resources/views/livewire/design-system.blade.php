<div>
    <x-page-header
        eyebrow="Tomus · IX"
        title="Design System"
        cota="006.1 · ACM · '26"
        lead="The brand identity applied to generated content — animated clips and posts. Stored in the vault and injected into the generators' prompts. It does not change the design of the application itself." />

    <form wire:submit="guardar" class="space-y-6">
        {{-- Load a .md --}}
        <x-panel eyebrow="Seed" title="Load design.md" glyph="⬆">
            <p class="text-ink-soft -mt-2 mb-4">
                Load a Markdown file to fill the editor. Nothing is saved until you press
                <span class="text-teal">Save</span> — so you can review first.
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
                    Load into editor
                </button>
                <span wire:loading wire:target="ficheiro" class="text-ink-faint font-mono text-xs">receiving…</span>
            </div>
            @error('ficheiro') <p class="mt-2 text-sm" style="color:#FF8FA6">{{ $message }}</p> @enderror
        </x-panel>

        {{-- Editor --}}
        <x-panel eyebrow="Brain" title="design-system.md" glyph="✎">
            <div class="flex items-center justify-between gap-3 -mt-2 mb-3">
                <p class="text-ink-soft">
                    Free-form Markdown — voice, palette, typography, composition rules. The clearer it is, the better the generator follows it.
                </p>
                @if ($atualizado)
                    <span class="shrink-0 font-mono text-[0.68rem] text-ink-faint">saved · {{ $atualizado }}</span>
                @endif
            </div>
            <textarea wire:model="conteudo" rows="24" spellcheck="false"
                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-4 py-3
                             text-ink font-mono text-sm leading-relaxed resize-y
                             focus:border-teal focus:outline-none"></textarea>
            <div class="mt-2 font-mono text-[0.62rem] text-ink-faint break-all">{{ $caminho }}</div>
        </x-panel>

        {{-- Extracted theme (what the animations will use) --}}
        @if ($tokens)
            <x-panel eyebrow="Applied to animations" title="Extracted theme" glyph="❖">
                <p class="text-ink-soft -mt-2 mb-4">
                    Colors, fonts and texture distilled from the design above. This is what the
                    <span class="text-teal">Animated Clips</span> now use to match the brand.
                </p>
                <div class="grid sm:grid-cols-2 gap-6">
                    <div>
                        <div class="eyebrow mb-2">Palette</div>
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
                        <div class="eyebrow mb-2">Typography &amp; texture</div>
                        <dl class="font-mono text-sm space-y-1 text-ink-soft">
                            <div><span class="text-ink-faint">display:</span> {{ $tokens['fonts']['display'] ?? '—' }}</div>
                            <div><span class="text-ink-faint">body:</span> {{ $tokens['fonts']['body'] ?? '—' }}</div>
                            <div><span class="text-ink-faint">mono:</span> {{ $tokens['fonts']['mono'] ?? '—' }}</div>
                            <div><span class="text-ink-faint">texture:</span> {{ $tokens['texture']['kind'] ?? 'paper' }}</div>
                        </dl>
                    </div>
                </div>
            </x-panel>
        @endif

        <div class="flex items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
                    class="rounded-sm border border-teal/50 bg-teal/10 px-5 py-2.5 text-ink font-display text-lg
                           hover:bg-teal/20 hover:border-teal transition disabled:opacity-50">
                <span wire:loading.remove wire:target="guardar">Save</span>
                <span wire:loading wire:target="guardar">Saving and extracting theme…</span>
            </button>
            @if ($guardado)
                <span class="font-mono text-sm text-teal" wire:loading.remove wire:target="guardar">✓ saved at {{ $guardado }}</span>
            @endif
        </div>
    </form>
</div>
