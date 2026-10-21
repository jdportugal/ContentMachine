<div>
    <x-page-header
        eyebrow="Tomus · IV"
        title="Recursos"
        cota="778.6 · ACM · '26"
        lead="Biblioteca de media reutilizável na aplicação. As faixas carregadas aqui ficam disponíveis como música de fundo nos vídeos." />

    {{-- Upload-in-progress indicator --}}
    <div wire:loading wire:target="novaMusica, adicionarMusica, novasImagens"
         style="position:fixed; bottom:1rem; right:1rem; z-index:9998; width:20rem; max-width:90vw; border:1px solid rgba(47,90,133,.45); color:#2F5A85;"
         class="rounded-sm px-4 py-2.5 font-mono text-sm shadow-engraved bg-surface flex items-start gap-2">
        <span class="animate-pulse">⏳</span>
        <span class="flex-1">A carregar…</span>
    </div>

    {{-- Music library --}}
    <x-panel eyebrow="Áudio" title="Biblioteca de música" glyph="♪" class="mb-6">
        <p class="font-mono text-xs text-ink-faint mb-3">
            Carregue faixas de fundo (mp3, wav, m4a, aac, ogg, flac; máx. 30 MB). Nos vídeos, escolha uma faixa específica ou deixe em <span class="text-teal">Aleatória</span> — é sorteada uma da biblioteca ao gerar o vídeo.
        </p>

        <form wire:submit="adicionarMusica" class="flex flex-wrap items-center gap-3 mb-4">
            <input type="file" wire:model="novaMusica" accept="audio/*"
                   class="font-mono text-xs text-ink file:mr-3 file:border file:border-teal/40 file:text-teal file:bg-transparent file:rounded-sm file:px-3 file:py-1.5 file:font-mono file:text-xs file:uppercase file:tracking-wider file:cursor-pointer">
            <button type="submit" wire:loading.attr="disabled"
                    class="bg-teal/90 text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition disabled:opacity-40">
                Carregar
            </button>
        </form>
        @error('novaMusica') <p class="text-bad font-mono text-xs mb-3">{{ $message }}</p> @enderror

        @if (empty($musicas))
            <x-empty-state glyph="♪" title="Biblioteca vazia"
                note="Carregue faixas acima para usar como música de fundo nos vídeos." />
        @else
            <div class="space-y-2">
                @foreach ($musicas as $m)
                    <div class="flex items-center gap-3 border border-ink-soft/15 rounded-sm px-3 py-2 bg-surface/30">
                        <span class="font-mono text-xs text-ink truncate max-w-xs">♪ {{ $m['name'] }}</span>
                        <span class="font-mono text-[10px] text-ink-faint">{{ number_format($m['size'] / 1048576, 1) }} MB</span>
                        <audio controls preload="none" src="{{ route('clips.musica', $m['name']) }}" class="h-8 ml-auto max-w-[280px]"></audio>
                        <button wire:click="removerMusica('{{ $m['name'] }}')" wire:confirm="Remover esta faixa?"
                                class="text-bad font-mono text-xs hover:text-bad/70">✕</button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-panel>

    {{-- Image & video library --}}
    <x-panel eyebrow="Imagens e vídeo" title="Biblioteca de imagens" glyph="▦" class="mb-6">
        <p class="font-mono text-xs text-ink-faint mb-3">
            Imagens e vídeos reutilizáveis (logótipos, fotos da marca, capturas de ecrã, clips; png, jpg, webp, gif, mp4, mov; máx. 100 MB cada). Ao gerar um vídeo animado, o planeador procura aqui primeiro — uma boa correspondência é reutilizada em vez de lhe pedir ou gerar uma. Tudo o que adicionar aparece encaixado no vídeo. Descreva cada item para que possa ser encontrado.
        </p>

        <div x-data="{ over: false }"
             @dragover.prevent="over = true"
             @dragleave.prevent="over = false"
             @drop.prevent="over = false; if ($event.dataTransfer.files.length) $wire.uploadMultiple('novasImagens', $event.dataTransfer.files, () => {}, () => {})"
             :class="over ? 'border-teal bg-teal/5' : 'border-ink-soft/30'"
             class="border-2 border-dashed rounded-sm p-6 text-center transition mb-4">
            <p class="font-mono text-xs text-ink-soft">
                Arraste imagens para aqui, ou
                <label class="text-teal underline decoration-teal/40 underline-offset-2 hover:decoration-teal cursor-pointer">procure
                    <input type="file" class="hidden" multiple accept="image/*,video/mp4,video/quicktime,video/webm" wire:model="novasImagens" />
                </label>
            </p>
            <p wire:loading wire:target="novasImagens" class="font-mono text-[10px] text-ink-faint mt-2">a carregar…</p>
        </div>
        @error('novasImagens.*') <p class="text-bad font-mono text-xs mb-3">{{ $message }}</p> @enderror

        {{-- Staged images: describe each, then add to the library --}}
        @if (!empty($novasImagens))
            <div class="border border-teal/30 rounded-sm bg-teal/5 p-4 mb-4">
                <p class="font-mono text-xs text-ink-soft mb-3">Descreva cada imagem para que o planeador a possa encontrar e depois adicione-as.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-4">
                    @foreach ($novasImagens as $i => $img)
                        <div class="border border-ink-soft/15 rounded-sm bg-surface/40 overflow-hidden" wire:key="staged-{{ $i }}">
                            @php $stagedVideo = method_exists($img, 'getMimeType') && str_starts_with((string) $img->getMimeType(), 'video'); @endphp
                            <div class="aspect-square bg-vellum/40 flex items-center justify-center">
                                @if ($stagedVideo)
                                    <span class="font-mono text-[0.6rem] text-ink-faint">▶ vídeo</span>
                                @elseif (method_exists($img, 'temporaryUrl'))
                                    <img src="{{ $img->temporaryUrl() }}" class="w-full h-full object-contain" alt="" />
                                @endif
                            </div>
                            <input type="text" wire:model.blur="descricoes.{{ $i }}"
                                   placeholder="Descreva (ex. logótipo da agência)"
                                   class="w-full bg-transparent border-t border-ink-soft/15 px-2 py-1.5 font-mono text-[11px] text-ink placeholder:text-ink-faint focus:outline-none focus:bg-surface/40" />
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center gap-3">
                    <button wire:click="adicionarImagens" wire:loading.attr="disabled"
                            class="bg-teal/90 text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition disabled:opacity-40">
                        Adicionar {{ count($novasImagens) }} à biblioteca
                    </button>
                    <button wire:click="descartarImagens" class="font-mono text-xs text-ink-faint hover:text-bad">descartar</button>
                </div>
            </div>
        @endif

        @if (empty($imagens))
            <x-empty-state glyph="▦" title="Biblioteca vazia"
                note="Adicione imagens acima para serem reutilizadas automaticamente nos vídeos animados." />
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach ($imagens as $img)
                    <div class="border border-ink-soft/15 rounded-sm bg-surface/30 overflow-hidden" wire:key="lib-{{ $img['id'] }}">
                        <div class="relative aspect-square bg-vellum/40">
                            @if (!empty($img['video']))
                                <video src="{{ route('clips-animados.library-image', $img['id']) }}" muted playsinline controls
                                       class="w-full h-full object-contain"></video>
                            @else
                                <img src="{{ route('clips-animados.library-image', $img['id']) }}"
                                     onerror="this.style.visibility='hidden'"
                                     class="w-full h-full object-contain" alt="{{ $img['name'] }}" />
                            @endif
                            <button wire:click="removerImagem('{{ $img['id'] }}')" wire:confirm="Remover esta imagem da biblioteca?"
                                    class="absolute top-1 right-1 w-6 h-6 rounded-sm bg-nocturna/70 text-bad font-mono text-sm hover:bg-nocturna">✕</button>
                        </div>
                        <input type="text" value="{{ $img['description'] }}"
                               wire:change="atualizarDescricao('{{ $img['id'] }}', $event.target.value)"
                               placeholder="Descreva esta imagem (ex. logótipo da agência)"
                               class="w-full bg-transparent border-t border-ink-soft/15 px-2 py-1.5 font-mono text-[11px] text-ink placeholder:text-ink-faint focus:outline-none focus:bg-surface/40" />
                    </div>
                @endforeach
            </div>
        @endif
    </x-panel>
</div>
