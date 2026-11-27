{{-- Black Friday 2026 (config promocoes.black_friday): $parte = 'topo' (the opening: offer, the sample video playing, the
     packs), 'faixa' (a thin bar on the next steps) or 'resultado' (the free video done: only «este vídeo + N»). --}}
@php
    $bf = config('contentmachine.promocoes.black_friday');
    $C = \App\Services\Pagamentos\Creditos::class;
    $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
    $ate = \Illuminate\Support\Carbon::parse($bf['ate'], 'Europe/Lisbon')->locale('pt_PT');
    $reel = is_file(public_path('media/demo/moradia-lajeosa/reel.mp4')) ? asset('media/demo/moradia-lajeosa/reel.mp4') : null;
    $nomes = ['bf1' => 'Avulso', 'bf10' => 'Pack 10', 'bf20' => 'Pack 20'];
@endphp
@if ($parte === 'faixa')
    <div class="bg-coral text-navy text-center px-4 py-2 text-sm"><strong class="font-semibold">Black Friday:</strong> mais 25% de vídeos em todos os packs, até {{ $ate->translatedFormat('l') }} às {{ $ate->format('H:i') }}.</div>
@elseif ($parte === 'topo')
    <section class="w-full max-w-[1100px] mx-auto px-6 pt-4 pb-10 grid gap-8 md:grid-cols-[minmax(0,1fr)_minmax(0,340px)] items-center">
        <div class="flex flex-col gap-5">
            <span class="self-start px-3 py-1 rounded-full bg-coral text-navy text-xs font-bold tracking-[.2em]">BLACK FRIDAY · ATÉ {{ mb_strtoupper($ate->translatedFormat('l')) }}</span>
            <h1 class="font-display text-[clamp(36px,5.6vw,60px)] leading-[1.02] text-ivory">Mais 25% de vídeos em todos os packs.</h1>
            <p class="text-base md:text-lg font-light text-[#C9CED6]">Vídeos verticais dos seus imóveis, com narração em português de Portugal e o seu contacto no fim. Experimente primeiro com um imóvel seu: é grátis.</p>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($bf['packs'] as $chave => $pk)
                    <a href="{{ $stripe($chave, 'Black Friday · '.$nomes[$chave]) }}" class="rounded-[16px] bg-navy-2 border border-ivory/15 p-4 flex flex-col gap-1 hover:border-coral">
                        <span class="text-[11px] tracking-[.2em] text-cyan">{{ mb_strtoupper($nomes[$chave]) }}</span>
                        <span class="font-display text-[30px] leading-none text-ivory">{{ $eur($pk['preco']) }}</span>
                        <span class="text-sm text-ivory"><s class="text-[#8B95A7]">{{ $C::txt($pk['antes']) }}</s> → <strong>{{ $C::rotulo($pk['creditos']) }}</strong></span>
                    </a>
                @endforeach
            </div>
            <a href="#pedido" class="self-start inline-flex items-center min-h-11 text-sm font-semibold text-cyan underline underline-offset-4">Experimentar grátis com um imóvel meu ↓</a>
            <p class="text-xs text-[#8B95A7]">1 crédito = 1 vídeo. Os créditos servem também para o Imoplanta e as Ferramentas IA. IVA incluído. <a href="{{ route('termos') }}" class="underline">Termos</a>.</p>
        </div>
        @if ($reel)
            <video src="{{ $reel }}" autoplay muted loop playsinline preload="metadata" aria-label="Exemplo de um vídeo Imocena"
                   class="w-full max-w-[340px] mx-auto aspect-[9/16] rounded-[20px] bg-navy-3 border-[5px] border-navy-3 object-cover shadow-[0_30px_60px_-20px_rgba(0,0,0,.8)]"></video>
        @endif
    </section>
@elseif ($parte === 'resultado')
    {{-- only «this video + N»: each pack cleans this video (1 credit) and leaves the rest in the account --}}
    <span class="text-xs tracking-[.3em] text-coral">BLACK FRIDAY · ATÉ {{ mb_strtoupper($ate->translatedFormat('l')) }}</span>
    @foreach ($bf['packs'] as $chave => $pk)
        @php $resto = round($pk['creditos'] - 1, 1); $rotulo = 'Este vídeo + '.($resto >= 1 && $resto == (int) $resto ? (int) $resto.' vídeos' : $C::rotulo($resto)); @endphp
        @if (! empty($pedido['semContacto']))
            <button type="button" wire:click="$set('packBf', '{{ $chave }}'); $set('pedirContacto', true)" class="{{ $chave === 'bf10' ? $botao : 'inline-flex items-center justify-center gap-2 min-h-[52px] px-6 border-[1.5px] border-ivory/35 text-ivory font-semibold hover:bg-ivory/5' }} rounded-full flex-col !gap-0 py-2">
        @else
            <a href="{{ $stripe($chave, 'Black Friday · '.$nomes[$chave]) }}" class="{{ $chave === 'bf10' ? $botao : 'inline-flex items-center justify-center gap-2 min-h-[52px] px-6 border-[1.5px] border-ivory/35 text-ivory font-semibold hover:bg-ivory/5' }} rounded-full flex-col !gap-0 py-2">
        @endif
            <span>{{ $rotulo }} · {{ $eur($pk['preco']) }}</span>
            <span class="text-xs font-normal opacity-75">{{ $C::rotulo($pk['creditos']) }} em vez de {{ $C::txt($pk['antes']) }}{{ empty($pedido['semContacto']) ? '' : ' · primeiro o seu contacto' }}</span>
        @if (! empty($pedido['semContacto'])) </button> @else </a> @endif
    @endforeach
    <p class="text-xs text-[#8B95A7]">Este vídeo fica sem marca d’água; os outros créditos ficam na sua conta. IVA incluído.</p>
@endif
