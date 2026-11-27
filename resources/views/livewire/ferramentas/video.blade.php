@php
    $v = $video;
    $estado = $v?->estado ?? \App\Models\FerramentaIa::RASCUNHO;
    $comVideo = $tipo['video'] ?? true;   // the cleanup: a photo tool, its approved photo is the result
    $passos = $comVideo ? ['Fotografia', 'Imagens', 'Vídeo'] : ['Fotografia', 'Fotografia limpa', 'Pronta'];
    $passo = ['rascunho' => 0, 'a_enquadrar' => 1, 'a_aprovar' => 1, 'a_animar' => 2, 'pronto' => 2][$estado];
    $ficheiro = fn (string $qual, array $q = []) => route('ferramentas.ficheiro', [$v->id, $qual, ...$q, 'v' => substr(md5((string) $v->{$qual}), 0, 8)]);
    $quadrosIa = $tipo['ai'];
    $semVideos = $disponiveis === 0;
    $botao = 'min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60';
    $secundario = 'min-h-11 px-5 rounded-full border border-[#D9D5CC] text-navy text-sm font-semibold hover:bg-[#EDEAE3] disabled:opacity-60';
    $campo = 'w-full min-h-[88px] px-4 py-3 rounded-[10px] bg-white text-navy text-[15px] border border-[#D9D5CC] focus:outline-none focus:ring-2 focus:ring-cyan';
@endphp
<div @if ($v?->aTrabalhar() || $v?->aAnalisar()) wire:poll.3s @endif class="font-sans max-w-[1100px]">
    <a href="{{ route('ferramentas') }}" class="text-sm text-ink-soft hover:text-ink">← Ferramentas IA</a>
    <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="eyebrow">Ferramentas IA</span>
            <h1 class="mt-2 font-display text-5xl font-medium leading-none">{{ $tipo['nome'] }}</h1>
            <p class="mt-3 max-w-2xl text-sm text-ink-soft">{{ $tipo['linha'] }}</p>
        </div>
        @if ($disponiveis !== null && $estado === 'rascunho')
            <div class="rounded-[18px] bg-navy px-6 py-4 min-w-[200px] flex items-center justify-between gap-6">
                <span class="text-[10px] tracking-[.3em] text-cyan leading-relaxed">VÍDEOS IA<br>DISPONÍVEIS</span>
                <span class="font-display text-[48px] leading-none text-ivory">{{ $disponiveis }}</span>
            </div>
        @endif
    </div>

    <ol class="mt-6 flex flex-wrap gap-2 text-[13px]">
        @foreach ($passos as $i => $nome)
            <li class="px-3 py-1 rounded-full {{ $i === $passo ? 'bg-navy text-ivory' : ($i < $passo ? 'bg-[#E3F0E7] text-[#22603A]' : 'bg-white border border-[#E4E1DA] text-[#6B7385]') }}">{{ $i < $passo ? '✓ ' : '' }}{{ $nome }}</li>
        @endforeach
    </ol>

    @if ($v?->erro)
        <p role="alert" class="mt-6 rounded-xl bg-[#FBE4E0] text-[#8A2B1F] px-5 py-3 text-sm font-medium">{{ $v->erro }}</p>
    @endif

    @if ($estado === 'rascunho')
        <form wire:submit="criar" class="mt-6 rounded-2xl bg-white border border-[#E4E1DA] p-7 md:p-8 flex flex-col gap-5">
            @if ($tipo['obras'] ?? false)
                {{-- the type of works first: it says what the photo should show and what gets built --}}
                <fieldset class="flex flex-col gap-3 text-sm text-[#4A5263]">
                    <legend class="font-semibold text-ivory mb-3">Que obra quer mostrar?</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($tipo['obras'] as $valor => $o)
                            <label class="inline-flex items-center gap-2 px-4 min-h-11 rounded-full border cursor-pointer {{ $obra === $valor ? 'border-coral text-navy font-semibold bg-[#FDEDEB]' : 'border-[#D9D5CC]' }}">
                                <input type="radio" wire:model.live="obra" value="{{ $valor }}" class="sr-only"> {{ $o['nome'] }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
            {{-- drop a photo anywhere on the card (or click the preview); the counter stops the highlight flickering over children --}}
            {{-- `carregar` covers the whole wait: the upload (with its %), then the server preparing it and the next page --}}
            <div x-data="{ sobre: 0, carregar: false, progresso: 0 }"
                 x-on:dragenter.prevent="sobre++" x-on:dragover.prevent x-on:dragleave.prevent="sobre--"
                 x-on:drop.prevent="sobre = 0; if (! carregar && $event.dataTransfer.files.length) { carregar = true; progresso = 0;
                     $wire.upload('foto', $event.dataTransfer.files[0], () => progresso = 100, () => carregar = false, (e) => progresso = e.detail.progress) }"
                 x-on:livewire-upload-start="carregar = true; progresso = 0" x-on:livewire-upload-progress="progresso = $event.detail.progress"
                 x-on:livewire-upload-finish="progresso = 100" x-on:livewire-upload-error="carregar = false"
                 :class="sobre > 0 && 'ring-2 ring-coral ring-offset-4 ring-offset-white'"
                 class="grid gap-6 md:grid-cols-2 items-center rounded-xl transition">
                <button type="button" x-on:click="carregar || $refs.ficheiro.click()" aria-label="Escolher fotografia"
                        class="relative aspect-video rounded-xl overflow-hidden bg-[#EDEAE3] flex items-center justify-center {{ $foto || $v ? '' : 'border-2 border-dashed border-[#C9C4B8] hover:border-coral' }}">
                    @if ($foto && method_exists($foto, 'isPreviewable') && $foto->isPreviewable())
                        <img src="{{ $foto->temporaryUrl() }}" alt="A sua fotografia" class="w-full h-full object-cover">
                    @elseif ($v)
                        <img src="{{ $ficheiro('original') }}" alt="A sua fotografia" class="w-full h-full object-cover">
                    @else
                        <span class="flex flex-col items-center gap-2 px-6 text-center text-sm text-[#6B7385]">
                            <span class="text-3xl leading-none text-coral" aria-hidden="true">⇪</span>
                            <span><strong class="text-navy">Arraste a fotografia para aqui</strong><br>ou clique para escolher</span>
                        </span>
                    @endif
                    <span x-show="sobre > 0" x-cloak class="absolute inset-0 flex items-center justify-center bg-navy/70 text-ivory font-semibold">Largue para carregar</span>
                    <span x-show="carregar" x-cloak role="status" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/85 text-sm font-semibold text-navy">
                        <span class="w-8 h-8 rounded-full border-[3px] border-[#E4E1DA] border-t-coral animate-spin"></span>
                        <span x-text="progresso < 100 ? 'A carregar a fotografia… ' + progresso + '%' : 'A preparar a fotografia…'">A carregar a fotografia…</span>
                        <span class="w-40 h-1.5 rounded-full bg-[#E4E1DA] overflow-hidden"><span class="block h-full bg-coral transition-all" :style="'width:' + progresso + '%'"></span></span>
                    </span>
                </button>
                <div class="flex flex-col gap-2 text-[13px] text-[#4A5263]">
                    <span class="font-display text-2xl text-ivory">{{ $v ? 'A sua fotografia' : $tipo['envio'] }}</span>
                    <span>Arraste a fotografia para este quadro ou escolha-a. JPG, PNG ou WebP até 15 MB. Use a fotografia mais nítida que tiver.</span>
                    {{-- the browser's own file button speaks its language ("Choose file"): ours instead --}}
                    <label class="self-start inline-flex items-center min-h-11 px-5 rounded-full bg-[#EDEAE3] text-navy text-sm font-semibold cursor-pointer hover:bg-[#E4E1DA] focus-within:ring-2 focus-within:ring-cyan">
                        {{ $v ? 'Trocar fotografia' : 'Escolher fotografia' }}
                        <input type="file" x-ref="ficheiro" accept="image/png,image/jpeg,image/webp" wire:model="foto" class="sr-only">
                    </label>
                    @error('foto') <span class="text-sm text-coral" x-init="carregar = false">{{ $message }}</span> @enderror
                    @if ($v) <span wire:key="foto-{{ md5((string) $v->original) }}" x-init="carregar = false" hidden></span> @endif
                </div>
            </div>
            @if ($tipo['direcao'] ?? false)
                <fieldset class="flex flex-wrap items-center gap-3 text-sm text-[#4A5263]">
                    <legend class="sr-only">Direção do movimento</legend>
                    <span class="font-semibold text-ivory">A câmara desliza</span>
                    @foreach (['esquerda' => '← Para a esquerda', 'direita' => 'Para a direita →'] as $valor => $rotulo)
                        <label class="inline-flex items-center gap-2 px-4 min-h-11 rounded-full border cursor-pointer {{ $direcao === $valor ? 'border-coral text-navy font-semibold bg-[#FDEDEB]' : 'border-[#D9D5CC]' }}">
                            <input type="radio" wire:model.live="direcao" value="{{ $valor }}" class="sr-only"> {{ $rotulo }}
                        </label>
                    @endforeach
                </fieldset>
            @endif
            @if ($tipo['campo'] ?? false)
                <label class="flex flex-col gap-1.5 text-sm text-[#4A5263]">
                    <span><span class="font-semibold text-ivory">{{ $tipo['campo']['titulo'] }}</span> {{ $tipo['campo']['ajuda'] }}</span>
                    <input type="text" wire:model="texto" maxlength="300" placeholder="{{ $tipo['obras'][$obra]['exemplo'] ?? $tipo['campo']['exemplo'] }}"
                           class="min-h-[52px] px-4 rounded-[10px] bg-white text-navy text-[15px] border border-[#D9D5CC] focus:outline-none focus:ring-2 focus:ring-cyan">
                </label>
            @endif
            {{-- the photo check for this tool: a warning, never a block --}}
            @if ($v?->aAnalisar())
                <p role="status" class="flex items-center gap-3 rounded-xl bg-[#F6F4EF] px-5 py-3 text-sm text-[#4A5263]">
                    <span class="w-4 h-4 rounded-full border-2 border-[#E4E1DA] border-t-coral animate-spin"></span>
                    A analisar a fotografia para {{ mb_strtolower($tipo['nome']) }}…
                </p>
            @elseif ($v?->fotoNaoIdeal())
                <div role="alert" class="rounded-xl bg-[#FBEBD9] text-[#6B3A0E] px-5 py-4 text-sm flex flex-col gap-2">
                    <p class="font-semibold">Esta fotografia não é a ideal para {{ mb_strtolower($tipo['nome']) }}. O vídeo pode não ficar tão bom.</p>
                    @if ($v->analise['problemas'] ?? [])
                        <ul class="list-disc pl-5 flex flex-col gap-1">
                            @foreach ($v->analise['problemas'] as $problema) <li>{{ $problema }}</li> @endforeach
                        </ul>
                    @endif
                    @if ($v->analise['dica'] ?? '') <p><strong>Melhor:</strong> {{ $v->analise['dica'] }}</p> @endif
                    <p>Pode trocar a fotografia acima, ou continuar com esta.</p>
                </div>
            @elseif (($v?->analise['estado'] ?? null) === 'feito')
                <p class="rounded-xl bg-[#E3F0E7] text-[#22603A] px-5 py-3 text-sm font-medium">Boa fotografia para {{ mb_strtolower($tipo['nome']) }}.</p>
            @endif
            @if (! $semVideos && $v)
                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" wire:loading.attr="disabled" wire:target="criar, foto" @disabled($v->aAnalisar()) class="{{ $botao }}">
                        <span wire:loading.remove wire:target="criar">{{ $v->fotoNaoIdeal() ? 'Continuar com esta fotografia →' : ($comVideo ? 'Criar imagens →' : 'Limpar fotografia →') }}</span>
                        <span wire:loading wire:target="criar">A enviar…</span>
                    </button>
                    <p class="text-xs text-[#6B7385]">
                        {{ $disponiveis === null ? 'Esta conta não paga.' : 'Usa 1 dos seus '.$disponiveis.' vídeos IA.' }}
                        @if ($comVideo) Inclui {{ config('contentmachine.ferramentas.editar_imagem') }} alterações às imagens e {{ config('contentmachine.ferramentas.refazer_video') }} novas versões do vídeo.
                        @else Inclui {{ config('contentmachine.ferramentas.editar_imagem') }} alterações à fotografia. @endif
                    </p>
                </div>
            @endif
        </form>
        @if ($semVideos)
            <section class="mt-6">
                <h2 class="font-display text-3xl">Já não tem vídeos IA</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ $v ? 'A sua fotografia fica guardada: depois de comprar, volta aqui para criar as imagens.' : 'Escolha um pack para continuar. Cada vídeo serve para qualquer ferramenta. IVA incluído.' }}</p>
                @include('livewire.ferramentas.packs', ['video' => $v])
                @if ($v)
                    <button type="button" wire:click="criar" class="mt-4 {{ $secundario }}">Já comprei: criar imagens</button>
                @endif
            </section>
        @endif

    @elseif ($estado === 'a_enquadrar' || $estado === 'a_animar')
        <div class="mt-6 rounded-2xl bg-white border border-[#E4E1DA] px-8 py-14 flex items-center justify-center gap-4 text-[#4A5263]" role="status">
            <span class="w-6 h-6 rounded-full border-[3px] border-[#E4E1DA] border-t-coral animate-spin"></span>
            {{ $estado === 'a_enquadrar' ? ($comVideo ? 'A criar as imagens…' : 'A limpar a fotografia…').' costuma levar 1 a 2 minutos.' : 'A criar o vídeo… costuma levar 3 a 6 minutos. Pode fechar esta página e voltar mais tarde.' }}
        </div>

    @elseif ($estado === 'a_aprovar')
        <div class="mt-6 grid gap-5 md:grid-cols-2 items-start">
            @foreach (['inicio' => 'Início · '.$tipo['inicio'], 'fim' => 'Fim · '.$tipo['fim']] as $qual => $rotulo)
                @php $ia = in_array($qual, $quadrosIa, true); $versoes = $v->versoes[$qual] ?? []; @endphp
                <figure class="rounded-2xl overflow-hidden bg-white border border-[#E4E1DA]">
                    <figcaption class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="font-semibold text-ivory">{{ $rotulo }}</span>
                        @if ($ia) <span class="px-2 py-0.5 rounded bg-cyan text-navy text-[11px] font-bold tracking-wider">IA</span> @endif
                    </figcaption>
                    <img src="{{ $ficheiro($qual) }}" alt="{{ $rotulo }}" class="w-full aspect-video object-cover bg-[#EDEAE3]">
                    @if ($ia && count($versoes) > 1)
                        <div class="flex flex-wrap gap-2 px-4 py-3">
                            @foreach ($versoes as $n => $versao)
                                <button type="button" wire:click="escolher('{{ $qual }}', {{ $n }})" class="px-3 py-1 rounded-md text-xs border {{ $versao === $v->{$qual} ? 'border-coral text-coral font-semibold' : 'border-[#D9D5CC] text-[#4A5263]' }}">Versão {{ $n + 1 }}</button>
                            @endforeach
                        </div>
                    @endif
                </figure>
            @endforeach
        </div>
        <form wire:submit="alterar" class="mt-6 rounded-2xl bg-white border border-[#E4E1DA] p-6 flex flex-col gap-3">
            <h2 class="font-display text-2xl text-ivory">Quer mudar alguma coisa {{ count($quadrosIa) > 1 ? 'numa das imagens' : 'na imagem IA' }}?</h2>
            @if ($v->edicoesRestantes() > 0 || auth()->user()->is_admin)
                @if (count($quadrosIa) > 1)
                    <div class="flex flex-wrap gap-4 text-sm text-[#4A5263]">
                        @foreach ($quadrosIa as $qual)
                            <label class="flex items-center gap-2"><input type="radio" wire:model="alvo" value="{{ $qual }}" @checked(($alvo ?: last($quadrosIa)) === $qual)> {{ $qual === 'inicio' ? 'Início' : 'Fim' }} · {{ $tipo[$qual] }}</label>
                        @endforeach
                    </div>
                @endif
                <textarea wire:model="pedido" maxlength="500" class="{{ $campo }}" placeholder="{{ $tipo['exemplo'] ?? 'Ex.: o que quer mudar' }}"></textarea>
                @error('pedido') <span class="text-sm text-coral">{{ $message }}</span> @enderror
                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" wire:loading.attr="disabled" class="{{ $secundario }}">Pedir alteração</button>
                    <span class="text-xs text-[#6B7385]">Restam {{ $v->edicoesRestantes() }} {{ $v->edicoesRestantes() === 1 ? 'alteração' : 'alterações' }}. A sua fotografia nunca é alterada.</span>
                </div>
            @else
                <p class="text-sm text-[#6B7385]">Já usou as {{ config('contentmachine.ferramentas.editar_imagem') }} alterações. Escolha acima a versão que prefere.</p>
            @endif
        </form>
        <div class="mt-6 flex flex-wrap items-center gap-4">
            @if ($comVideo)
                <button type="button" wire:click="aprovar" wire:loading.attr="disabled" class="{{ $botao }}">Aprovar e criar vídeo →</button>
                <span class="text-xs text-[#6B7385]">Depois de aprovar, as imagens ficam fixas. O vídeo anima de uma para a outra.</span>
            @else
                <button type="button" wire:click="aprovar" wire:loading.attr="disabled" class="{{ $botao }}">Aprovar fotografia →</button>
                <span class="text-xs text-[#6B7385]">Depois de aprovar, a fotografia fica pronta a descarregar.</span>
            @endif
        </div>

    @elseif ($estado === 'pronto' && ! $comVideo)
        {{-- before / after: drag the handle across the photo --}}
        <div x-data="{ corte: 50 }" class="mt-6 relative w-full rounded-2xl overflow-hidden bg-navy select-none">
            <img src="{{ $ficheiro('fim') }}" alt="{{ $tipo['fim'] }}" class="block w-full h-auto">
            <img src="{{ $ficheiro('inicio') }}" alt="{{ $tipo['inicio'] }}" class="absolute inset-0 w-full h-full object-cover" :style="'clip-path: inset(0 ' + (100 - corte) + '% 0 0)'">
            <span class="absolute inset-y-0 w-0.5 bg-white shadow" :style="'left:' + corte + '%'"></span>
            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-navy/80 text-ivory text-xs font-semibold">Antes</span>
            <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-navy/80 text-ivory text-xs font-semibold">Depois</span>
            <input type="range" min="0" max="100" x-model="corte" aria-label="Comparar antes e depois" class="absolute inset-0 w-full h-full opacity-0 cursor-ew-resize">
        </div>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ $ficheiro('fim', ['baixar' => 1]) }}" class="inline-flex items-center {{ $botao }}">Descarregar fotografia limpa</a>
            <a href="{{ $ficheiro('original', ['baixar' => 1]) }}" class="inline-flex items-center {{ $secundario }}">Fotografia original</a>
        </div>

    @elseif ($estado === 'pronto')
        <video src="{{ $ficheiro('video') }}" poster="{{ $ficheiro('inicio') }}" controls playsinline class="mt-6 w-full rounded-2xl bg-navy aspect-video"></video>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ $ficheiro('video', ['baixar' => 1]) }}" class="inline-flex items-center {{ $botao }}">Descarregar vídeo</a>
            <a href="{{ $ficheiro('inicio', ['baixar' => 1]) }}" class="inline-flex items-center {{ $secundario }}">Imagem de início</a>
            <a href="{{ $ficheiro('fim', ['baixar' => 1]) }}" class="inline-flex items-center {{ $secundario }}">Imagem de fim</a>
        </div>
        <form wire:submit="refazer" class="mt-6 rounded-2xl bg-white border border-[#E4E1DA] p-6 flex flex-col gap-3">
            <h2 class="font-display text-2xl text-ivory">Não ficou como queria?</h2>
            @if ($v->refeitosRestantes() > 0 || auth()->user()->is_admin)
                
                <textarea wire:model="nota" maxlength="500" class="{{ $campo }}" placeholder="Opcional. Ex.: mais devagar"></textarea>
                @error('nota') <span class="text-sm text-coral">{{ $message }}</span> @enderror
                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" wire:loading.attr="disabled" class="{{ $secundario }}">Refazer vídeo</button>
                    <span class="text-xs text-[#6B7385]">Restam {{ $v->refeitosRestantes() }} {{ $v->refeitosRestantes() === 1 ? 'nova versão' : 'novas versões' }}, sem custo. Usa as mesmas imagens.</span>
                </div>
            @else
                <p class="text-sm text-[#6B7385]">Já usou as {{ config('contentmachine.ferramentas.refazer_video') }} novas versões deste vídeo. Para outro resultado, comece um vídeo novo.</p>
            @endif
        </form>
    @endif
</div>
