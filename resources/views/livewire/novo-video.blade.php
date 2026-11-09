@php
    $passo = fn (int $n, string $titulo, string $sub) => '<div class="flex items-start gap-3"><span class="shrink-0 w-8 h-8 rounded-full bg-cyan/15 border border-cyan/50 text-cyan text-sm font-semibold flex items-center justify-center">'.$n.'</span><div><h2 class="font-display text-[26px] leading-tight text-ivory">'.e($titulo).'</h2><p class="mt-1 text-sm text-[#AEB5C1]">'.e($sub).'</p></div></div>';
    $opcao = fn (bool $on) => 'flex items-center gap-3 min-h-[56px] rounded-[14px] border px-4 py-3 cursor-pointer '.($on ? 'border-cyan bg-cyan/10' : 'border-ivory/15');
@endphp
<main class="max-w-[760px] mx-auto px-4 sm:px-6 pt-6 pb-40">
    <nav class="flex items-center justify-between gap-3">
        <a href="{{ route('conta') }}" class="inline-flex items-center min-h-11 -ml-2 px-2 text-sm text-[#C9CED6]">← Os meus vídeos</a>
        <span class="text-[11px] tracking-[.24em] text-cyan">NOVO VÍDEO</span>
    </nav>
    <h1 class="mt-3 font-display text-[clamp(30px,7vw,44px)] leading-[1.06] text-ivory">Fazer um vídeo</h1>
    <p class="mt-2 text-sm text-[#C9CED6]">Só o link é obrigatório. O resto é para o vídeo ficar como quer.</p>

    <form wire:submit="criar" class="mt-8 flex flex-col gap-12" novalidate>
        {{-- 1 · Link --}}
        <section class="flex flex-col gap-4">
            {!! $passo(1, 'O anúncio', 'RE/MAX, iad ou idealista. Lemos as fotos, o preço e a descrição.') !!}
            <input type="url" wire:model="anuncio" inputmode="url" placeholder="Cole aqui o link do anúncio"
                   class="w-full min-h-[56px] rounded-[14px] bg-navy border border-ivory/20 px-4 text-[16px] text-ivory placeholder:text-[#7C8696] focus:border-cyan focus:outline-none">
            @error('anuncio') <p class="text-sm text-coral">{{ $message }}</p> @enderror
        </section>

        {{-- 2 · Extra photos, dragged in or picked --}}
        <section class="flex flex-col gap-4">
            {!! $passo(2, 'Mais fotos', 'Fotos que não estão no anúncio (até '.\App\Livewire\NovoVideoPagina::MAX_FOTOS.'). Entram no vídeo com as do anúncio.') !!}
            <label x-data="{ sobre: false }"
                   x-on:dragover.prevent="sobre = true" x-on:dragleave.prevent="sobre = false"
                   x-on:drop.prevent="sobre = false; $wire.uploadMultiple('novas', $event.dataTransfer.files)"
                   :class="sobre ? 'border-cyan bg-cyan/10' : 'border-ivory/30'"
                   class="flex flex-col items-center justify-center gap-1 min-h-[140px] rounded-[16px] border-2 border-dashed px-4 py-6 text-center cursor-pointer transition-colors">
                <input type="file" accept="image/png,image/jpeg,image/webp" multiple wire:model="novas" class="sr-only">
                <span class="text-[15px] text-ivory">Arraste as fotos para aqui</span>
                <span class="text-sm text-[#AEB5C1]">ou toque para escolher</span>
                <span wire:loading wire:target="novas" class="mt-2 text-xs text-cyan">A enviar fotos…</span>
            </label>
            @error('novas.*') <p class="text-sm text-coral">{{ $message }}</p> @enderror
            @error('fotos') <p class="text-sm text-coral">{{ $message }}</p> @enderror
            @if ($fotos)
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                    @foreach ($fotos as $i => $foto)
                        <div class="relative aspect-square rounded-[10px] overflow-hidden bg-navy border border-ivory/15" wire:key="foto-{{ $i }}-{{ $foto->getFilename() }}">
                            <img src="{{ $foto->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                            <button type="button" wire:click="tirarFoto({{ $i }})" aria-label="Tirar esta foto"
                                    class="absolute right-1 top-1 w-8 h-8 rounded-full bg-navy/80 text-ivory text-lg leading-none flex items-center justify-center">×</button>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-[#AEB5C1]">{{ count($fotos) }} {{ count($fotos) === 1 ? 'foto adicionada' : 'fotos adicionadas' }}</p>
            @endif
        </section>

        {{-- 3 · Voiceover --}}
        <section class="flex flex-col gap-4">
            {!! $passo(3, 'Narração', 'O texto que a voz lê.') !!}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <label class="{{ $opcao($narrador === 'nos') }}">
                    <input type="radio" wire:model.live="narrador" value="nos" class="w-5 h-5 accent-cyan">
                    <span class="flex flex-col"><span class="text-[15px] text-ivory">Escrevemos nós</span><span class="text-xs text-[#AEB5C1]">A partir do anúncio</span></span>
                </label>
                <label class="{{ $opcao($narrador === 'eu') }}">
                    <input type="radio" wire:model.live="narrador" value="eu" class="w-5 h-5 accent-cyan">
                    <span class="flex flex-col"><span class="text-[15px] text-ivory">Escrevo eu</span><span class="text-xs text-[#AEB5C1]">A voz lê o seu texto</span></span>
                </label>
            </div>
            @if ($narrador === 'eu')
                <textarea wire:model="narracao" rows="8" maxlength="1500" placeholder="Ex.: Moradia T3 em Lajeosa, com jardim e vista para a serra…"
                          class="w-full rounded-[14px] bg-navy border border-ivory/20 p-4 text-[16px] leading-relaxed text-ivory placeholder:text-[#7C8696] focus:border-cyan focus:outline-none"></textarea>
                <div class="flex justify-between text-xs text-[#AEB5C1]" x-data><span>Cerca de 1 minuto no máximo. Termine com o seu contacto.</span><span x-text="$wire.narracao.length + ' / 1500'"></span></div>
            @endif
            @error('narracao') <p class="text-sm text-coral">{{ $message }}</p> @enderror
        </section>

        {{-- 4 · Music --}}
        <section class="flex flex-col gap-4">
            {!! $passo(4, 'Música', 'Toque em ▶ para ouvir antes de escolher.') !!}
            <div class="flex flex-col gap-2">
                <label class="{{ $opcao($musica === 'aleatoria') }}">
                    <input type="radio" wire:model.live="musica" value="aleatoria" class="w-5 h-5 accent-cyan">
                    <span class="flex flex-col"><span class="text-[15px] text-ivory">Escolhida por nós</span><span class="text-xs text-[#AEB5C1]">Uma das músicas da Imocena</span></span>
                </label>
                @foreach ($faixas as $f)
                    <div class="{{ $opcao($musica === $f['nome']) }}" wire:key="faixa-{{ $f['nome'] }}" x-data>
                        <label class="flex flex-1 items-center gap-3 min-w-0 cursor-pointer">
                            <input type="radio" wire:model.live="musica" value="{{ $f['nome'] }}" class="w-5 h-5 accent-cyan">
                            <span class="truncate text-[15px] text-ivory">{{ $f['titulo'] }}</span>
                        </label>
                        <audio x-ref="som" data-faixa src="{{ $f['ouvir'] }}" preload="none" wire:ignore
                               x-on:play="$el.closest('div').querySelector('[data-icone]').textContent = '❚❚'"
                               x-on:pause="$el.closest('div').querySelector('[data-icone]').textContent = '▶'"></audio>
                        <button type="button" aria-label="Ouvir"
                                x-on:click="document.querySelectorAll('audio[data-faixa]').forEach(o => o !== $refs.som && o.pause()); $refs.som.paused ? $refs.som.play() : $refs.som.pause()"
                                class="shrink-0 w-11 h-11 rounded-full border border-ivory/30 text-ivory text-sm flex items-center justify-center"><span data-icone>▶</span></button>
                    </div>
                @endforeach
                <label class="{{ $opcao($musica === 'propria') }}">
                    <input type="radio" wire:model.live="musica" value="propria" class="w-5 h-5 accent-cyan">
                    <span class="flex flex-col min-w-0"><span class="text-[15px] text-ivory">A minha música</span>
                        <span class="truncate text-xs text-[#AEB5C1]">{{ $ficheiroMusica ? '♪ '.$ficheiroMusica->getClientOriginalName() : 'MP3, M4A ou WAV, até 20 MB' }}</span></span>
                </label>
                @if ($musica === 'propria')
                    <label class="flex items-center justify-center min-h-[52px] rounded-[14px] border border-dashed border-ivory/30 text-sm text-ivory cursor-pointer">
                        <input type="file" accept="audio/*" wire:model="ficheiroMusica" class="sr-only"> {{ $ficheiroMusica ? 'Trocar ficheiro' : 'Escolher ficheiro de música' }}
                    </label>
                    <div wire:loading wire:target="ficheiroMusica" class="text-xs text-[#AEB5C1]">A enviar…</div>
                    <p class="text-xs text-[#AEB5C1]">Use só música que tem direito a usar: as redes sociais silenciam vídeos com música protegida.</p>
                @endif
                @error('ficheiroMusica') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                <label class="{{ $opcao($musica === 'nenhuma') }}">
                    <input type="radio" wire:model.live="musica" value="nenhuma" class="w-5 h-5 accent-cyan">
                    <span class="text-[15px] text-ivory">Sem música</span>
                </label>
            </div>
            @error('musica') <p class="text-sm text-coral">{{ $message }}</p> @enderror
        </section>
    </form>

    {{-- Create bar, fixed at the bottom --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-ivory/10 bg-navy/95 backdrop-blur px-4 py-3 pb-[max(12px,env(safe-area-inset-bottom))]">
        <div class="max-w-[760px] mx-auto flex flex-col gap-2">
            @if ($creditos > 0)
                <button type="button" wire:click="criar" wire:loading.attr="disabled" wire:target="criar,novas,ficheiroMusica"
                        class="w-full min-h-[56px] rounded-full bg-coral text-navy font-semibold text-[16px] disabled:opacity-50">
                    <span wire:loading.remove wire:target="criar">Criar vídeo</span>
                    <span wire:loading wire:target="criar">A criar…</span>
                </button>
                <p class="text-center text-xs text-[#C9CED6]">Usa 1 dos seus {{ $creditos }} {{ $creditos === 1 ? 'vídeo' : 'vídeos' }} · sem marca d’água · 2 edições grátis</p>
            @else
                <a href="{{ route('conta') }}#comprar" class="w-full min-h-[56px] rounded-full bg-coral text-navy font-semibold flex items-center justify-center">Comprar vídeos para continuar</a>
                <p class="text-center text-xs text-[#C9CED6]">Não tem vídeos disponíveis na conta.</p>
            @endif
        </div>
    </div>
</main>
