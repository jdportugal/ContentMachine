@php
    // Imoplanta for a signed-in account that hasn't bought it yet: what it is, the five steps (the demo flat's GIFs,
    // as on the public page), and the packs, each its Payment Link. No upload form: it needs a property to spend.
    $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
    $lista = collect($planos)->map(fn ($p, $k) => $p + ['chave' => $k])->values();
    $g = fn (string $f) => asset('imoplanta-demo/landing/'.$f);
    $primeiro = $lista->first();
    $sobre = 'flex items-center gap-3.5 text-xs tracking-[.32em] text-cyan';
@endphp
<div class="font-sans">
    {{-- what it is --}}
    <section class="grid gap-8 xl:gap-12 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] items-center">
        <div class="flex flex-col gap-5">
            <div class="{{ $sobre }}"><span class="w-10 h-px bg-cyan"></span>IMOPLANTA</div>
            <h1 class="font-display text-[clamp(40px,5vw,60px)] font-medium leading-[1.02] text-ink">Da planta ao vídeo do imóvel</h1>
            <p class="max-w-xl text-[16px] leading-relaxed text-ink-soft">Envie as plantas de uma casa ou de um apartamento. Recebe o modelo 3D, fotografias realistas e um vídeo de visita, mesmo antes de a casa estar construída.</p>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ \App\Services\Imoplanta\Imoplantas::link(auth()->user(), $primeiro['chave']) }}" class="inline-flex items-center min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105">Comprar {{ \App\Services\Pagamentos\Creditos::IMOPLANTA }} créditos · {{ $eur($primeiro['preco']) }} →</a>
                <a href="#precos" class="inline-flex items-center min-h-[52px] px-6 rounded-full border-[1.5px] border-ivory/30 text-ivory text-[15px] font-semibold hover:bg-ivory/5">Ver os packs</a>
            </div>
            <p class="text-sm text-ink-soft">A partir de <strong class="text-ink">{{ $eur($lista->last()['preco'] / $lista->last()['imoveis']) }}</strong> por imóvel. Sem subscrição. IVA incluído.</p>
        </div>
        <figure class="flex flex-col gap-3">
            <img src="{{ $g('capa.gif') }}" alt="A planta do apartamento, depois a casa em 3D vista de cima, o modelo 3D da sala, a fotografia realista feita a partir dele e o vídeo de visita." width="560" height="316"
                 class="w-full rounded-[20px] bg-white shadow-[0_18px_40px_rgba(0,0,0,0.35)]">
            <figcaption class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] font-medium text-ink-soft">
                <span>Planta</span><span aria-hidden="true" class="text-coral">→</span><span>Planta 3D</span><span aria-hidden="true" class="text-coral">→</span><span>Modelo 3D</span><span aria-hidden="true" class="text-coral">→</span><span>Fotografia IA</span><span aria-hidden="true" class="text-coral">→</span><span>Vídeo</span>
            </figcaption>
        </figure>
    </section>

    {{-- how it works --}}
    <section class="mt-16">
        <div class="{{ $sobre }}"><span class="w-10 h-px bg-cyan"></span>COMO FUNCIONA</div>
        <h2 class="mt-3 font-display text-[clamp(30px,3.5vw,42px)] font-medium leading-tight text-ink">Cinco passos, do papel ao vídeo</h2>
        <p class="mt-2 max-w-2xl text-[15px] text-ink-soft">Um apartamento T3 de demonstração, feito no Imoplanta tal como o recebe, sem retoques.</p>
        <ol class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach (\App\Services\Imoplanta\Imoplantas::passos() as $i => [$titulo, $texto, $gif, $alt])
                <li class="rounded-2xl overflow-hidden bg-white border border-[#E4E1DA] shadow-[0_12px_32px_rgba(14,22,38,0.06)] flex flex-col">
                    <div class="bg-white border-b border-[#E4E1DA]"><img src="{{ $g($gif) }}" alt="{{ $alt }}" loading="lazy" class="w-full aspect-[16/10] object-contain"></div>
                    <div class="p-5 flex flex-col gap-2">
                        <div class="flex items-center gap-3">
                            <span class="shrink-0 w-8 h-8 rounded-full bg-cyan/15 border border-cyan/50 text-cyan text-sm font-semibold flex items-center justify-center">{{ $i + 1 }}</span>
                            <h3 class="font-display text-[24px] leading-tight text-ivory">{{ $titulo }}</h3>
                        </div>
                        <p class="text-sm leading-relaxed text-[#C9CED6]">{{ $texto }}</p>
                    </div>
                </li>
            @endforeach
            <li class="rounded-2xl border border-dashed border-ivory/20 p-6 flex flex-col justify-center gap-2">
                <span class="self-start px-2.5 py-1 rounded-full bg-cyan/15 text-cyan text-[11px] tracking-[.18em]">EXTRAS OPCIONAIS</span>
                <p class="font-display text-[24px] leading-tight text-ivory">E, se quiser, mais dois vídeos</p>
                <p class="text-sm leading-relaxed text-[#C9CED6]">O vídeo da planta 3D, a casa a construir-se vista de cima, e o Vídeo Imocena, um reel vertical para as redes sociais. Pede-os no fim, quando o vídeo de visita estiver pronto.</p>
            </li>
        </ol>
    </section>

    {{-- the packs --}}
    <section id="precos" class="mt-16 scroll-mt-20">
        <div class="{{ $sobre }}"><span class="w-10 h-px bg-cyan"></span>PREÇOS</div>
        <h2 class="mt-3 font-display text-[clamp(30px,3.5vw,42px)] font-medium leading-tight text-ink">Créditos de vídeo</h2>
        <p class="mt-2 max-w-2xl text-[15px] text-ink-soft">IVA incluído. Cada imóvel inclui o modelo 3D com {{ config('contentmachine.imoplanta.refazer_3d') }} correções, as fotografias e o vídeo de visita. Cada imóvel usa {{ \App\Services\Pagamentos\Creditos::IMOPLANTA }} créditos de vídeo: os créditos de um pack ficam na sua conta e servem também para os vídeos Imocena e as Ferramentas IA.</p>
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach ($lista as $pl)
                <a href="{{ \App\Services\Imoplanta\Imoplantas::link(auth()->user(), $pl['chave']) }}"
                   class="rounded-[18px] border bg-white p-6 flex flex-col gap-1 {{ $loop->last ? 'border-coral hover:shadow-md' : 'border-[#E4E1DA] hover:border-cyan' }}">
                    <span class="text-[11px] tracking-[.24em] {{ $loop->last ? 'text-coral' : 'text-cyan' }}">{{ $pl['imoveis'] * \App\Services\Pagamentos\Creditos::IMOPLANTA }} CRÉDITOS DE VÍDEO{{ $loop->last ? ' · MAIS VANTAJOSO' : '' }}</span>
                    <span class="mt-1 font-display text-[40px] leading-none text-ivory">{{ $eur($pl['preco']) }}</span>
                    <span class="text-sm text-[#AEB5C1]">{{ $pl['imoveis'] > 1 ? $pl['imoveis'].' imóveis · '.$eur($pl['preco'] / $pl['imoveis']).' por imóvel' : '1 imóvel, agora.' }}</span>
                    <span class="mt-4 inline-flex items-center justify-center min-h-11 rounded-full {{ $loop->last ? 'bg-coral text-navy' : 'border border-ivory/30 text-ivory' }} text-sm font-semibold">Comprar →</span>
                </a>
            @endforeach
        </div>
        <p class="mt-4 text-sm text-ink-soft">Depois do pagamento, volta a esta página e carrega as plantas. Dúvidas? <a href="mailto:info@imocena.com" class="text-cyan underline underline-offset-2">info@imocena.com</a></p>
    </section>
</div>
