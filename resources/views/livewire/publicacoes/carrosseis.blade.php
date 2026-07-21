<div>
    <x-page-header
        eyebrow="Formato · B"
        title="Carrosséis"
        cota="686.2 · IAT · '26"
        lead="Compõe uma sequência de cartões e guarda-a como rascunho no vault." />

    <a href="{{ route('publicacoes') }}" class="inline-block mb-4 font-mono text-[0.62rem] text-teal hover:underline">← Publicações</a>

    <x-panel eyebrow="Composição" title="Novo carrossel" glyph="☰">
        @if ($guardado)
            <div class="mb-4 border border-good/40 bg-good/10 text-good rounded-sm px-4 py-3 font-mono text-sm">
                ✓ Rascunho «{{ $guardado }}» guardado no vault. Veja em
                <a href="{{ route('rascunhos') }}" class="underline">Rascunhos</a>.
            </div>
        @endif

        <form wire:submit="criarRascunho" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="eyebrow block mb-1.5">Título</label>
                    <input type="text" wire:model="titulo" placeholder="5 termos para começar"
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
            </div>

            <div>
                <label class="eyebrow block mb-1.5">Cartões</label>
                @error('slides') <span class="block text-bad text-sm mb-2">{{ $message }}</span> @enderror
                <div class="space-y-3">
                    @foreach ($slides as $i => $slide)
                        <div class="flex gap-2" wire:key="slide-{{ $i }}">
                            <span class="font-display text-2xl text-gold w-8 text-center shrink-0 pt-1">{{ $i + 1 }}</span>
                            <textarea wire:model="slides.{{ $i }}" rows="2" placeholder="Texto do cartão {{ $i + 1 }}…"
                                      class="flex-1 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
                            <button type="button" wire:click="removerSlide({{ $i }})"
                                    class="shrink-0 text-ink-faint hover:text-bad px-2 text-xl" title="Remover">×</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="adicionarSlide"
                        class="mt-3 border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-teal/40 rounded-sm px-4 py-1.5 font-mono text-xs transition">
                    + acrescentar cartão
                </button>
            </div>

            <button type="submit"
                    class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                Guardar rascunho
            </button>
        </form>
    </x-panel>
</div>
