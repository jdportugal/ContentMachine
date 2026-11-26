@php
    $v = $video;
    $estado = $v?->estado ?? \App\Models\FerramentaIa::RASCUNHO;
    $passo = ['rascunho' => 0, 'a_enquadrar' => 1, 'a_aprovar' => 1, 'a_animar' => 2, 'pronto' => 2][$estado];
    $ficheiro = fn (string $qual, ?int $n = null, array $q = []) => route('ferramentas.ficheiro', [$v->id, $qual, $n, ...$q, 'v' => substr(md5((string) ($qual === 'versao' ? ($v->versoes[$n] ?? '') : $v->{$qual})), 0, 8)]);
    $iaInicio = $tipo['ai'] === 'inicio';
    $semVideos = $disponiveis === 0;
    $botao = 'min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60';
    $secundario = 'min-h-11 px-5 rounded-full border border-[#D9D5CC] text-navy text-sm font-semibold hover:bg-[#EDEAE3] disabled:opacity-60';
    $campo = 'w-full min-h-[88px] px-4 py-3 rounded-[10px] bg-white text-navy text-[15px] border border-[#D9D5CC] focus:outline-none focus:ring-2 focus:ring-cyan';
@endphp
<div @if ($v?->aTrabalhar()) wire:poll.5s @endif class="font-sans max-w-[1100px]">
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
        @foreach (['Fotografia', 'Imagens', 'Vídeo'] as $i => $nome)
            <li class="px-3 py-1 rounded-full {{ $i === $passo ? 'bg-navy text-ivory' : ($i < $passo ? 'bg-[#E3F0E7] text-[#22603A]' : 'bg-white border border-[#E4E1DA] text-[#6B7385]') }}">{{ $i < $passo ? '✓ ' : '' }}{{ $nome }}</li>
        @endforeach
    </ol>

    @if ($v?->erro)
        <p role="alert" class="mt-6 rounded-xl bg-[#FBE4E0] text-[#8A2B1F] px-5 py-3 text-sm font-medium">{{ $v->erro }}</p>
    @endif

    @if ($estado === 'rascunho')
        <form wire:submit="criar" class="mt-6 rounded-2xl bg-white border border-[#E4E1DA] p-7 md:p-8 flex flex-col gap-5">
            <div class="grid gap-6 md:grid-cols-2 items-center">
                <div class="aspect-video rounded-xl overflow-hidden bg-[#EDEAE3] flex items-center justify-center">
                    @if ($foto && method_exists($foto, 'isPreviewable') && $foto->isPreviewable())
                        <img src="{{ $foto->temporaryUrl() }}" alt="A sua fotografia" class="w-full h-full object-cover">
                    @elseif ($v)
                        <img src="{{ $ficheiro('original') }}" alt="A sua fotografia" class="w-full h-full object-cover">
                    @else
                        <span class="text-sm text-[#6B7385] px-6 text-center">{{ $tipo['envio'] }}</span>
                    @endif
                </div>
                <label class="flex flex-col gap-2 text-[13px] text-[#4A5263]">
                    <span class="font-display text-2xl text-ivory">{{ $v ? 'A sua fotografia' : $tipo['envio'] }}</span>
                    <span>JPG, PNG ou WebP até 15 MB. Use a fotografia mais nítida que tiver.</span>
                    <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="foto"
                           class="text-sm file:mr-3 file:min-h-11 file:px-4 file:rounded-full file:border-0 file:bg-[#EDEAE3] file:text-navy file:font-medium">
                    <span wire:loading wire:target="foto" class="text-xs text-cyan">A carregar…</span>
                    @error('foto') <span class="text-sm text-coral">{{ $message }}</span> @enderror
                </label>
            </div>
            @if (! $semVideos)
                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" wire:loading.attr="disabled" wire:target="criar, foto" class="{{ $botao }}">
                        <span wire:loading.remove wire:target="criar">Criar imagens →</span>
                        <span wire:loading wire:target="criar">A enviar…</span>
                    </button>
                    <p class="text-xs text-[#6B7385]">
                        {{ $disponiveis === null ? 'Esta conta não paga.' : 'Usa 1 dos seus '.$disponiveis.' vídeos IA.' }}
                        Inclui {{ config('contentmachine.ferramentas.editar_imagem') }} alterações às imagens e {{ config('contentmachine.ferramentas.refazer_video') }} novas versões do vídeo.
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
            {{ $estado === 'a_enquadrar' ? 'A criar as imagens… costuma levar 1 a 2 minutos.' : 'A criar o vídeo… costuma levar 3 a 6 minutos. Pode fechar esta página e voltar mais tarde.' }}
        </div>

    @elseif ($estado === 'a_aprovar')
        <div class="mt-6 grid gap-5 md:grid-cols-2 items-start">
            @foreach ([['inicio', 'Início · '.$tipo['inicio'], $iaInicio], ['fim', 'Fim · '.$tipo['fim'], ! $iaInicio]] as [$qual, $rotulo, $ia])
                <figure class="rounded-2xl overflow-hidden bg-white border border-[#E4E1DA]">
                    <figcaption class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="font-semibold text-ivory">{{ $rotulo }}</span>
                        @if ($ia) <span class="px-2 py-0.5 rounded bg-cyan text-navy text-[11px] font-bold tracking-wider">IA</span> @endif
                    </figcaption>
                    <img src="{{ $ficheiro($qual) }}" alt="{{ $rotulo }}" class="w-full aspect-video object-cover bg-[#EDEAE3]">
                    @if ($ia && count($v->versoes ?? []) > 1)
                        <div class="flex flex-wrap gap-2 px-4 py-3">
                            @foreach ($v->versoes as $n => $versao)
                                <button type="button" wire:click="escolher({{ $n }})" class="px-3 py-1 rounded-md text-xs border {{ $versao === $v->{$qual} ? 'border-coral text-coral font-semibold' : 'border-[#D9D5CC] text-[#4A5263]' }}">Versão {{ $n + 1 }}</button>
                            @endforeach
                        </div>
                    @endif
                </figure>
            @endforeach
        </div>
        <form wire:submit="alterar" class="mt-6 rounded-2xl bg-white border border-[#E4E1DA] p-6 flex flex-col gap-3">
            <h2 class="font-display text-2xl text-ivory">Quer mudar alguma coisa na imagem IA?</h2>
            @if ($v->edicoesRestantes() > 0 || auth()->user()->is_admin)
                <textarea wire:model="pedido" maxlength="500" class="{{ $campo }}" placeholder="Ex.: um pouco mais alto, mostrar também o quintal"></textarea>
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
            <button type="button" wire:click="aprovar" wire:loading.attr="disabled" class="{{ $botao }}">Aprovar e criar vídeo →</button>
            <span class="text-xs text-[#6B7385]">Depois de aprovar, as imagens ficam fixas. O vídeo anima de uma para a outra, com som.</span>
        </div>

    @elseif ($estado === 'pronto')
        <video src="{{ $ficheiro('video') }}" poster="{{ $ficheiro('inicio') }}" controls playsinline class="mt-6 w-full rounded-2xl bg-navy aspect-video"></video>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ $ficheiro('video', null, ['baixar' => 1]) }}" class="inline-flex items-center {{ $botao }}">Descarregar vídeo</a>
            <a href="{{ $ficheiro('inicio', null, ['baixar' => 1]) }}" class="inline-flex items-center {{ $secundario }}">Imagem de início</a>
            <a href="{{ $ficheiro('fim', null, ['baixar' => 1]) }}" class="inline-flex items-center {{ $secundario }}">Imagem de fim</a>
        </div>
        <form wire:submit="refazer" class="mt-6 rounded-2xl bg-white border border-[#E4E1DA] p-6 flex flex-col gap-3">
            <h2 class="font-display text-2xl text-ivory">Não ficou como queria?</h2>
            @if ($v->refeitosRestantes() > 0 || auth()->user()->is_admin)
                <textarea wire:model="nota" maxlength="500" class="{{ $campo }}" placeholder="Opcional. Ex.: subir mais devagar"></textarea>
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
