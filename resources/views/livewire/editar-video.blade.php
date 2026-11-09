@php
    $restam = max(0, $permitidas - $feitas);
    $passo = fn (int $n, string $titulo, string $sub) => '<div class="flex items-start gap-3"><span class="shrink-0 w-8 h-8 rounded-full bg-cyan/15 border border-cyan/50 text-cyan text-sm font-semibold flex items-center justify-center">'.$n.'</span><div><h2 class="font-display text-[26px] leading-tight text-ivory">'.e($titulo).'</h2><p class="mt-1 text-sm text-[#AEB5C1]">'.e($sub).'</p></div></div>';
@endphp
<main class="max-w-[760px] mx-auto px-4 sm:px-6 pt-6 pb-40">
    <nav class="flex items-center justify-between gap-3">
        <a href="{{ route('conta') }}" class="inline-flex items-center min-h-11 -ml-2 px-2 text-sm text-[#C9CED6]">← Os meus vídeos</a>
        <span class="text-[11px] tracking-[.24em] text-cyan">EDITAR VÍDEO</span>
    </nav>
    <h1 class="mt-3 font-display text-[clamp(28px,7vw,40px)] leading-[1.08] text-ivory">{{ $clip->title }}</h1>

    @if ($aFazer)
        <p class="mt-6 rounded-[14px] border border-cyan/40 bg-cyan/5 p-4 text-sm text-ivory">Este vídeo está a ser feito. Pode editá-lo quando terminar.</p>
    @endif

    <form wire:submit="guardar" class="mt-8 flex flex-col gap-12" novalidate>
        {{-- 1 · Narração --}}
        <section class="flex flex-col gap-4">
            {!! $passo(1, 'Narração', 'O texto que a voz lê. Se o mudar, a voz é gravada de novo.') !!}
            <textarea wire:model="narracao" rows="10" maxlength="1500"
                      class="w-full rounded-[14px] bg-navy border border-ivory/20 p-4 text-[16px] leading-relaxed text-ivory focus:border-cyan focus:outline-none"></textarea>
            <div class="flex justify-between text-xs text-[#AEB5C1]" x-data><span>Cerca de 1 minuto no máximo.</span><span x-text="$wire.narracao.length + ' / 1500'"></span></div>
            @error('narracao') <p class="text-sm text-coral">{{ $message }}</p> @enderror
        </section>

        {{-- 2 · Cenas --}}
        <section class="flex flex-col gap-4">
            {!! $passo(2, 'Cenas', 'A imagem e o efeito de cada cena. Toque numa imagem para a trocar.') !!}
            <ol class="flex flex-col gap-4">
                @foreach ($cenas as $c)
                    <li class="rounded-[16px] border border-ivory/10 bg-navy-3/40 p-3" wire:key="cena-{{ $c['indice'] }}">
                        <div class="flex items-center justify-between gap-3 px-1">
                            <span class="text-[11px] tracking-[.22em] text-[#AEB5C1]">CENA {{ $c['indice'] + 1 }}</span>
                            <span class="text-xs text-cyan text-right">{{ $c['efeito'] }}</span>
                        </div>
                        <div class="mt-3 grid {{ ['grid-cols-1', 'grid-cols-1', 'grid-cols-2', 'grid-cols-3'][min(3, count($c['fotos']))] }} gap-2">
                            @forelse ($fotos[$c['indice']] ?? [] as $onde => $id)
                                @php $slot = \App\Livewire\EditarVideo::chave($c['indice'], $onde); @endphp
                                <div class="flex flex-col gap-2">
                                    <button type="button" wire:click="$set('aTrocar', '{{ $c['indice'] }}|{{ $onde }}')"
                                            class="relative block w-full {{ count($fotos[$c['indice']] ?? []) === 1 ? 'aspect-[16/10]' : 'aspect-[4/5]' }} rounded-[10px] overflow-hidden bg-navy border {{ $aTrocar === $c['indice'].'|'.$onde ? 'border-cyan' : 'border-ivory/15' }}">
                                        @if (! empty($novasFotos[$slot]))
                                            <img src="{{ $novasFotos[$slot]->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                                        @else
                                            <img src="{{ $url($id) }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover object-[50%_30%]">
                                        @endif
                                        <span class="absolute inset-x-0 bottom-0 py-1.5 bg-navy/75 text-[11px] text-ivory">Trocar</span>
                                    </button>
                                </div>
                            @empty
                                <div class="col-span-full aspect-[16/9] rounded-[10px] bg-navy border border-ivory/10 flex items-center justify-center text-sm text-[#AEB5C1]">{{ $c['video'] ? 'Vídeo do imóvel' : 'Cena sem imagem' }}</div>
                            @endforelse
                        </div>

                        @foreach ($fotos[$c['indice']] ?? [] as $onde => $id)
                            @if ($aTrocar === $c['indice'].'|'.$onde)
                                @php $slot = \App\Livewire\EditarVideo::chave($c['indice'], $onde); @endphp
                                <div class="mt-3 rounded-[12px] border border-cyan/40 bg-navy p-3">
                                    <p class="text-sm text-ivory">Escolha outra foto do anúncio</p>
                                    <div class="mt-2 flex gap-2 overflow-x-auto snap-x pb-2 -mx-1 px-1">
                                        @foreach ($galeria as $g)
                                            <button type="button" wire:click="escolherFoto({{ $c['indice'] }}, '{{ $onde }}', '{{ $g }}')"
                                                    class="snap-start shrink-0 w-[96px] aspect-[4/5] rounded-[8px] overflow-hidden border {{ $g === $id ? 'border-cyan' : 'border-ivory/15' }}">
                                                <img src="{{ $url($g) }}" alt="" loading="lazy" class="w-full h-full object-cover">
                                            </button>
                                        @endforeach
                                    </div>
                                    <label class="mt-2 flex items-center justify-center min-h-[48px] rounded-full border border-ivory/30 text-sm text-ivory cursor-pointer">
                                        <input type="file" accept="image/*" wire:model="novasFotos.{{ $slot }}" class="sr-only"> Enviar outra foto
                                    </label>
                                    <div wire:loading wire:target="novasFotos.{{ $slot }}" class="mt-2 text-xs text-[#AEB5C1]">A enviar…</div>
                                    <button type="button" wire:click="$set('aTrocar', null)" class="mt-2 w-full min-h-11 text-sm text-[#AEB5C1]">Fechar</button>
                                </div>
                            @endif
                        @endforeach
                    </li>
                @endforeach
            </ol>
            @error('novasFotos.*') <p class="text-sm text-coral">{{ $message }}</p> @enderror
        </section>

        {{-- 3 · Agente --}}
        <section class="flex flex-col gap-4">
            {!! $passo(3, 'Os seus dados', 'O que aparece no cartão final do vídeo.') !!}
            <label class="flex flex-col gap-1.5">
                <span class="text-sm text-ivory">Nome</span>
                <input type="text" wire:model="nome" autocomplete="name" class="min-h-[52px] rounded-[12px] bg-navy border border-ivory/20 px-4 text-[16px] text-ivory focus:border-cyan focus:outline-none">
                @error('nome') <span class="text-sm text-coral">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-sm text-ivory">Telefone</span>
                <input type="tel" wire:model="telefone" autocomplete="tel" inputmode="tel" class="min-h-[52px] rounded-[12px] bg-navy border border-ivory/20 px-4 text-[16px] text-ivory focus:border-cyan focus:outline-none">
                @error('telefone') <span class="text-sm text-coral">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-sm text-ivory">Licença AMI <span class="text-[#AEB5C1]">(só o número)</span></span>
                <input type="text" wire:model="licenca" inputmode="numeric" placeholder="ex.: 11220" class="min-h-[52px] rounded-[12px] bg-navy border border-ivory/20 px-4 text-[16px] text-ivory focus:border-cyan focus:outline-none">
                @error('licenca') <span class="text-sm text-coral">{{ $message }}</span> @enderror
            </label>
            <div class="flex flex-col gap-1.5">
                <span class="text-sm text-ivory">Logótipo da agência</span>
                <div class="flex items-center gap-3">
                    <div class="shrink-0 w-[72px] h-[72px] rounded-[12px] bg-ivory/90 overflow-hidden flex items-center justify-center">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" alt="" class="max-w-full max-h-full object-contain">
                        @elseif ($logoAtual)
                            <img src="{{ $url($logoAtual) }}" alt="" class="max-w-full max-h-full object-contain">
                        @else
                            <span class="text-[10px] text-navy">sem logo</span>
                        @endif
                    </div>
                    <label class="flex-1 flex items-center justify-center min-h-[52px] rounded-full border border-ivory/30 text-sm text-ivory cursor-pointer">
                        <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="logo" class="sr-only"> {{ $logoAtual ? 'Trocar logótipo' : 'Enviar logótipo' }}
                    </label>
                </div>
                <div wire:loading wire:target="logo" class="text-xs text-[#AEB5C1]">A enviar…</div>
                @error('logo') <span class="text-sm text-coral">{{ $message }}</span> @enderror
            </div>
        </section>

        {{-- 4 · Música --}}
        <section class="flex flex-col gap-4">
            {!! $passo(4, 'Música', 'Agora: '.$musicaAtual.'. Envie a sua (MP3, M4A ou WAV) ou retire a música.') !!}
            <label class="flex items-center justify-center min-h-[56px] rounded-[14px] border border-dashed border-ivory/30 text-sm text-ivory cursor-pointer {{ $semMusica ? 'opacity-40 pointer-events-none' : '' }}">
                <input type="file" accept="audio/*" wire:model="musica" class="sr-only">
                {{ $musica ? '♪ '.\Illuminate\Support\Str::limit($musica->getClientOriginalName(), 34) : 'Escolher ficheiro de música' }}
            </label>
            <div wire:loading wire:target="musica" class="text-xs text-[#AEB5C1]">A enviar…</div>
            @error('musica') <span class="text-sm text-coral">{{ $message }}</span> @enderror
            <label class="flex items-center gap-3 min-h-11 text-sm text-ivory">
                <input type="checkbox" wire:model.live="semMusica" class="w-5 h-5 accent-cyan"> Sem música
            </label>
            @unless ($semMusica)
                <label class="flex flex-col gap-2">
                    <span class="text-sm text-ivory">Volume da música</span>
                    <input type="range" min="0" max="0.4" step="0.02" wire:model="volume" class="w-full h-8 accent-cyan">
                    <span class="text-xs text-[#AEB5C1]">A narração fica sempre por cima.</span>
                </label>
            @endunless
            <p class="text-xs text-[#AEB5C1]">Use só música que tem direito a usar: as redes sociais silenciam vídeos com música protegida.</p>
        </section>
    </form>

    {{-- Save bar, fixed at the bottom (thumb reach on phones) --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-ivory/10 bg-navy/95 backdrop-blur px-4 py-3 pb-[max(12px,env(safe-area-inset-bottom))]">
        <div class="max-w-[760px] mx-auto flex flex-col gap-2">
            @error('guardar') <p class="text-sm text-coral">{{ $message }}</p> @enderror
            @if ($restam > 0)
                <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar,logo,musica,novasFotos" @disabled($aFazer)
                        class="w-full min-h-[56px] rounded-full bg-coral text-navy font-semibold text-[16px] disabled:opacity-50">
                    <span wire:loading.remove wire:target="guardar">Guardar e refazer o vídeo</span>
                    <span wire:loading wire:target="guardar">A guardar…</span>
                </button>
                <p class="text-center text-xs text-[#C9CED6]">Edição {{ $feitas + 1 }} de {{ $permitidas }} · {{ $restam === 1 ? 'esta é a última edição grátis' : 'restam '.$restam.' edições grátis' }}</p>
            @else
                <p class="text-center text-sm text-ivory">Já usou as {{ $permitidas }} edições deste vídeo.</p>
                @if ($creditos > 0)
                    <button type="button" wire:click="comprarEdicoes" class="w-full min-h-[56px] rounded-full bg-coral text-navy font-semibold">Usar 1 vídeo da conta para mais 2 edições</button>
                @else
                    <a href="{{ $comprar }}" class="w-full min-h-[56px] rounded-full bg-coral text-navy font-semibold flex items-center justify-center">Comprar 1 vídeo (20 €) para mais 2 edições</a>
                @endif
            @endif
        </div>
    </div>
</main>
