<div>
    <x-page-header
        :eyebrow="'Formato · '.\Illuminate\Support\Str::upper($tipo)"
        :title="$this->kind['label'] ?? 'Publicação'"
        cota="686.2 · IAT · '26"
        :lead="$this->kind['descricao'] ?? ''" />

    <a href="{{ route('publicacoes') }}" class="inline-block mb-4 font-mono text-[0.62rem] text-teal hover:underline">← Publicações</a>

    <div class="grid lg:grid-cols-5 gap-6">
        {{-- Composição --}}
        <div class="lg:col-span-3 space-y-6">
            @if ($guardado)
                <div class="border border-good/40 bg-good/10 text-good rounded-sm px-4 py-3 font-mono text-sm">
                    ✓ Rascunho «{{ $guardado }}» guardado no vault. Veja em
                    <a href="{{ route('rascunhos') }}" class="underline">Rascunhos</a>.
                </div>
            @endif

            {{-- Redação assistida por IA --}}
            <x-panel eyebrow="Assistente" title="Redigir com IA" glyph="✶">
                <p class="text-ink-soft text-sm mb-3">Descreva o tema; a IATECA compõe o {{ $this->kind['formato'] === 'carousel' ? 'carrossel' : 'texto' }}. Sem chave de API, usa uma redação heurística local.</p>
                <textarea wire:model="brief" rows="3" placeholder="Ex.: cinco termos essenciais para quem começa a usar IA…"
                          class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
                @error('brief') <span class="text-bad text-sm">{{ $message }}</span> @enderror
                <div class="mt-3">
                    <button type="button" wire:click="redigirComIa" wire:loading.attr="disabled"
                            class="border border-teal/50 text-teal hover:bg-teal/10 rounded-sm px-4 py-1.5 font-mono text-xs transition">
                        <span wire:loading.remove wire:target="redigirComIa">✶ redigir com IA</span>
                        <span wire:loading wire:target="redigirComIa">a redigir…</span>
                    </button>
                </div>
                @if ($aviso)
                    <p class="mt-3 text-ink-soft text-sm italic">{{ $aviso }}</p>
                @endif
            </x-panel>

            {{-- Composição manual --}}
            <x-panel eyebrow="Composição" :title="'Nova peça'" :glyph="$this->kind['glifo'] ?? '❦'">
                <form wire:submit="criarRascunho" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="eyebrow block mb-1.5">Título</label>
                            <input type="text" wire:model="titulo" placeholder="Título da peça"
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

                    @if ($this->kind['formato'] === 'carousel')
                        <div>
                            <label class="eyebrow block mb-1.5">Cartões</label>
                            @error('slides') <span class="block text-bad text-sm mb-2">{{ $message }}</span> @enderror
                            <div class="space-y-3">
                                @foreach ($slides as $i => $slide)
                                    <div class="flex gap-2" wire:key="slide-{{ $i }}">
                                        <span class="font-display text-2xl text-gold w-8 text-center shrink-0 pt-1">{{ $i + 1 }}</span>
                                        <div class="flex-1 space-y-1.5">
                                            <input type="text" wire:model="slides.{{ $i }}.titulo" placeholder="Título do cartão {{ $i + 1 }}"
                                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-body text-sm focus:border-teal focus:outline-none">
                                            <textarea wire:model="slides.{{ $i }}.texto" rows="2" placeholder="Texto do cartão…"
                                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
                                        </div>
                                        <button type="button" wire:click="removerSlide({{ $i }})"
                                                class="shrink-0 text-ink-faint hover:text-bad px-2 text-xl self-start" title="Remover">×</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" wire:click="adicionarSlide"
                                    class="mt-3 border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-teal/40 rounded-sm px-4 py-1.5 font-mono text-xs transition">
                                + acrescentar cartão
                            </button>
                        </div>
                    @else
                        <div>
                            <label class="eyebrow block mb-1.5">Legenda / corpo (Markdown)</label>
                            <textarea wire:model="legenda" rows="6" placeholder="Escreva o texto da peça…"
                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
                            @error('legenda') <span class="text-bad text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <button type="submit"
                                class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                            Guardar rascunho
                        </button>
                        <button type="button" wire:click="gerarImagens" wire:loading.attr="disabled"
                                class="border border-gold/50 text-gold hover:bg-gold/10 rounded-sm px-5 py-2 font-mono text-xs transition self-center">
                            <span wire:loading.remove wire:target="gerarImagens">❖ gerar imagens</span>
                            <span wire:loading wire:target="gerarImagens">a desenhar…</span>
                        </button>
                    </div>
                </form>
            </x-panel>
        </div>

        {{-- Pré-visualização --}}
        <div class="lg:col-span-2">
            <div class="eyebrow mb-2">Pré-visualização</div>
            @if (count($previews))
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($previews as $i => $arte)
                        <div class="border border-ink-soft/20 rounded-sm overflow-hidden bg-vellum/40 shadow-engraved" wire:key="prev-{{ $i }}">
                            @if (\Illuminate\Support\Str::startsWith(ltrim($arte), '<svg'))
                                <div class="w-full">{!! $arte !!}</div>
                            @else
                                <img src="{{ $arte }}" alt="Cartão {{ $i + 1 }}" class="w-full block">
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 font-mono text-[0.6rem] text-ink-faint">{{ count($previews) }} cartão(ões) · gabarito «{{ $this->kind['gabarito'] ?? '' }}»</p>
            @else
                <div class="aspect-[4/5] frame-engraved foxing bg-vellum/60 rounded-sm p-8 flex flex-col items-center justify-center text-center shadow-engraved">
                    <span class="text-5xl text-gold/60 select-none">{{ $this->kind['glifo'] ?? '❦' }}</span>
                    <p class="mt-4 text-ink-soft italic">Componha a peça e prima «gerar imagens» para ver os cartões desenhados.</p>
                </div>
            @endif
        </div>
    </div>
</div>
