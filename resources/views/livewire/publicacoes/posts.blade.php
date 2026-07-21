<div>
    <x-page-header
        eyebrow="Formato · A"
        title="Posts de página única"
        cota="686.2 · IAT · '26"
        lead="Compõe uma peça quadrada e guarda-a como rascunho no vault." />

    <a href="{{ route('publicacoes') }}" class="inline-block mb-4 font-mono text-[0.62rem] text-teal hover:underline">← Publicações</a>

    <div class="grid lg:grid-cols-5 gap-6">
        {{-- Composição --}}
        <div class="lg:col-span-3">
            <x-panel eyebrow="Composição" title="Nova peça" glyph="❦">
                @if ($guardado)
                    <div class="mb-4 border border-good/40 bg-good/10 text-good rounded-sm px-4 py-3 font-mono text-sm">
                        ✓ Rascunho «{{ $guardado }}» guardado no vault. Veja em
                        <a href="{{ route('rascunhos') }}" class="underline">Rascunhos</a>.
                    </div>
                @endif

                <form wire:submit="criarRascunho" class="space-y-4">
                    <div>
                        <label class="eyebrow block mb-1.5">Título</label>
                        <input type="text" wire:model="titulo" placeholder="Sabia que…"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                        @error('titulo') <span class="text-bad text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5">Plataforma</label>
                        <select wire:model="plataforma"
                                class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                            <option value="instagram">Instagram</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="tiktok">TikTok</option>
                            <option value="youtube">YouTube</option>
                        </select>
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5">Legenda / corpo (Markdown)</label>
                        <textarea wire:model="legenda" rows="6" placeholder="Escreva o texto da peça…"
                                  class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
                        @error('legenda') <span class="text-bad text-sm">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit"
                            class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                        Guardar rascunho
                    </button>
                </form>
            </x-panel>
        </div>

        {{-- Pré-visualização (moldura gravada) --}}
        <div class="lg:col-span-2">
            <div class="eyebrow mb-2">Pré-visualização</div>
            <div class="aspect-square frame-engraved foxing bg-vellum/60 rounded-sm p-8 flex flex-col justify-center shadow-engraved">
                <div class="eyebrow mb-3">Ex · Libris</div>
                <h3 class="font-display text-3xl text-ink leading-tight">{{ $titulo ?: 'Sabia que…' }}</h3>
                <div class="my-4"><x-fleuron /></div>
                <p class="text-ink-soft dropcap">{{ $legenda ?: 'O texto da sua peça aparecerá aqui, com a primeira letra em capitular à boa maneira dos livros antigos.' }}</p>
            </div>
        </div>
    </div>
</div>
