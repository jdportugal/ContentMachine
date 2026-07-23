<div>
    <x-page-header
        :eyebrow="'Formato · '.\Illuminate\Support\Str::upper($tipo)"
        :title="$this->kind['label'] ?? 'Publicação'"
        cota="686.2 · IAT · '26"
        :lead="$this->kind['descricao'] ?? ''" />

    <a href="{{ route('publicacoes') }}" class="inline-block mb-4 font-mono text-[0.62rem] text-teal hover:underline">← Publicações</a>

    {{-- Sonda partilhada enquanto há imagens a desenhar --}}
    @if ($aGerar || count($gerando))
        <div wire:poll.1500ms="verificarImagens" class="hidden"></div>
    @endif

    <div class="max-w-4xl space-y-6">
        @if ($guardado)
            <div class="border border-good/40 bg-good/10 text-good rounded-sm px-4 py-3 font-mono text-sm">
                @if ($notaPath)
                    ✓ Alterações a «{{ $guardado }}» guardadas.
                @else
                    ✓ Rascunho «{{ $guardado }}» guardado. Veja em <a href="{{ route('publicacoes') }}" class="underline">Publicações</a>.
                @endif
            </div>
        @endif

        {{-- Imagens de referência --}}
        <x-panel eyebrow="Contexto" title="Imagens de referência" glyph="❖">
            <p class="text-ink-soft text-sm mb-3">Junte imagens (logótipo, produto, exemplos) e diga o que são. A IATECA usa as descrições ao redigir e as imagens como referência visual ao gerar os cartões.</p>
            <input type="file" wire:model="uploads" multiple accept="image/*"
                   class="block w-full text-sm text-ink-soft file:mr-3 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-teal/10 file:text-teal file:font-mono file:text-xs hover:file:bg-teal/20 file:cursor-pointer">
            <div wire:loading wire:target="uploads" class="mt-2 font-mono text-xs text-teal">a carregar…</div>
            @error('uploads.*') <span class="block text-bad text-sm mt-1">{{ $message }}</span> @enderror

            @if (count($referencias))
                <div class="mt-4 grid sm:grid-cols-2 gap-3">
                    @foreach ($referencias as $r => $ref)
                        <div class="flex gap-3 items-start border border-ink-soft/15 rounded-sm p-2 bg-vellum/30" wire:key="ref-{{ $r }}">
                            <img src="{{ \Illuminate\Support\Str::startsWith($ref['path'], 'http') ? $ref['path'] : asset($ref['path']) }}"
                                 class="w-14 h-14 object-cover rounded-sm border border-ink-soft/20 shrink-0">
                            <div class="flex-1 min-w-0">
                                <input type="text" wire:model.blur="referencias.{{ $r }}.descricao"
                                       placeholder="o que é: «logótipo IATECA», «foto do produto»…"
                                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink font-body text-sm focus:border-teal focus:outline-none">
                                <button type="button" wire:click="removerReferencia({{ $r }})"
                                        class="mt-1 text-ink-faint hover:text-bad font-mono text-[0.6rem]">× remover</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-panel>

        {{-- Redação assistida por IA --}}
        <x-panel eyebrow="Assistente" title="Redigir com IA" glyph="✶">
            <p class="text-ink-soft text-sm mb-3">Descreva o tema; a IATECA compõe o {{ $this->kind['formato'] === 'carousel' ? 'carrossel' : 'texto' }}.</p>
            <textarea wire:model="brief" rows="2" placeholder="Ex.: cinco termos essenciais para quem começa a usar IA…"
                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
            @error('brief') <span class="text-bad text-sm">{{ $message }}</span> @enderror
            <div class="mt-3 flex items-center gap-3">
                @if ($aRedigir)
                    <div wire:poll.1000ms="verificarPlano" class="flex items-center gap-2 font-mono text-xs text-teal">
                        <span class="inline-block animate-pulse">◌ a IA está a redigir…</span>
                    </div>
                    <button type="button" wire:click="cancelarRedacao" class="text-ink-faint hover:text-bad font-mono text-xs">cancelar</button>
                @else
                    <button type="button" wire:click="redigirComIa"
                            class="border border-teal/50 text-teal hover:bg-teal/10 rounded-sm px-4 py-1.5 font-mono text-xs transition">✶ redigir com IA</button>
                @endif
            </div>
            @if ($aviso)<p class="mt-3 text-ink-soft text-sm italic">{{ $aviso }}</p>@endif
            @if ($aRedigir)<p class="mt-1 font-mono text-[0.6rem] text-ink-faint">Precisa de um worker: <span class="text-teal">php artisan queue:work</span></p>@endif
        </x-panel>

        {{-- Composição + imagens por cartão --}}
        <x-panel eyebrow="Composição" :title="$notaPath ? 'Editar peça' : 'Nova peça'" :glyph="$this->kind['glifo'] ?? '❦'">
            <form wire:submit="criarRascunho" class="space-y-4">
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-1">
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
                    <div>
                        <label class="eyebrow block mb-1.5">Resolução</label>
                        <select wire:model="proporcao"
                                class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                            @foreach (config('contentmachine.publicacoes.proporcoes') as $valor => $rotulo)
                                <option value="{{ $valor }}">{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 font-mono text-[0.55rem] text-ink-faint">altere antes de gerar as imagens</p>
                    </div>
                </div>

                @if ($this->kind['formato'] === 'carousel')
                    <div>
                        <label class="eyebrow block mb-1.5">Cartões</label>
                        @error('slides') <span class="block text-bad text-sm mb-2">{{ $message }}</span> @enderror
                        <div class="space-y-4">
                            @foreach ($slides as $i => $slide)
                                <div class="flex gap-3 items-start" wire:key="slide-{{ $i }}">
                                    <span class="font-display text-2xl text-gold w-6 text-center shrink-0 pt-1">{{ $i + 1 }}</span>
                                    <div class="flex-1 space-y-1.5 min-w-0">
                                        <input type="text" wire:model="slides.{{ $i }}.titulo" placeholder="Título do cartão {{ $i + 1 }}"
                                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-body text-sm focus:border-teal focus:outline-none">
                                        <textarea wire:model="slides.{{ $i }}.texto" rows="3" placeholder="Texto do cartão…"
                                                  class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
                                        @include('livewire.publicacoes._anexos-cartao', ['i' => $i])
                                        @include('livewire.publicacoes._editar-cartao', ['i' => $i])
                                        <button type="button" wire:click="removerSlide({{ $i }})"
                                                class="text-ink-faint hover:text-bad font-mono text-[0.6rem]">× remover cartão</button>
                                    </div>
                                    @include('livewire.publicacoes._imagem-cartao', ['i' => $i])
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="adicionarSlide"
                                class="mt-3 border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-teal/40 rounded-sm px-4 py-1.5 font-mono text-xs transition">
                            + acrescentar cartão
                        </button>
                    </div>
                @else
                    <div class="flex gap-3 items-start">
                        <div class="flex-1 min-w-0">
                            <label class="eyebrow block mb-1.5">Legenda / corpo (Markdown)</label>
                            <textarea wire:model="legenda" rows="6" placeholder="Escreva o texto da peça…"
                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
                            @error('legenda') <span class="text-bad text-sm">{{ $message }}</span> @enderror
                            @include('livewire.publicacoes._anexos-cartao', ['i' => 0])
                            @include('livewire.publicacoes._editar-cartao', ['i' => 0])
                        </div>
                        <div class="pt-6">
                            @include('livewire.publicacoes._imagem-cartao', ['i' => 0])
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap gap-3 items-center pt-2 border-t border-ink-soft/10">
                    <button type="submit"
                            class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                        {{ $notaPath ? 'Guardar alterações' : 'Guardar rascunho' }}
                    </button>
                    @if ($aGerar)
                        <span class="self-center font-mono text-xs text-gold animate-pulse">❖ a desenhar todos os cartões…</span>
                        <button type="button" wire:click="cancelarImagens" class="text-ink-faint hover:text-bad font-mono text-xs self-center">cancelar</button>
                    @else
                        <button type="button" wire:click="gerarImagens"
                                class="border border-gold/50 text-gold hover:bg-gold/10 rounded-sm px-5 py-2 font-mono text-xs transition self-center">
                            ❖ gerar todas as imagens
                        </button>
                    @endif
                    @if ($notaPath)
                        <button type="button" wire:click="remover" wire:confirm="Remover esta publicação do vault?"
                                class="ml-auto text-ink-faint hover:text-bad font-mono text-xs self-center">remover</button>
                    @endif
                </div>
                <p class="font-mono text-[0.6rem] text-ink-faint">
                    Imagens: {{ config('contentmachine.publicacoes.render_driver') === 'kie' && config('services.kie.key') ? 'kie.ai · nano-banana-pro' : 'SVG (offline)' }}
                    · cada cartão pode ser regenerado ou editado por instrução.
                </p>
            </form>
        </x-panel>
    </div>

    @include('livewire.publicacoes._lightbox')
</div>
