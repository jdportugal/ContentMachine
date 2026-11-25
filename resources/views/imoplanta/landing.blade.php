@php
    // Imoplanta's landing page: the steps, each shown with a GIF of the demo flat (made once from its real files:
    // public/imoplanta-demo/landing). The prices come from the config, so they stay in step with Checkout.
    $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
    $planos = collect(config('contentmachine.imoplanta.planos'))->values();
    $g = fn (string $f) => asset('imoplanta-demo/landing/'.$f);
    $passos = [
        ['Envie as plantas', 'Uma imagem por piso, onde se leiam as cotas. Lemos as paredes, as portas, as janelas e as divisões, com as medidas, e montamos a casa.', 'passo-1-planta.gif', 'A planta do apartamento e a casa montada a partir dela.'],
        ['Veja a casa em 3D e escolha as vistas', 'Rode o modelo, peça correções por escrito e escolha onde ficam as câmaras: até '.config('contentmachine.imoplanta.max_cameras').', já colocadas na sala, na cozinha, num quarto e na casa de banho.', 'passo-2-modelo.gif', 'O modelo 3D do apartamento a rodar, visto de cima e sem teto.'],
        ['Escolha os acabamentos', 'Um quadro de materiais e a planta 3D realista de cada piso. Pode mudar os dois por escrito, até '.config('contentmachine.imoplanta.refazer_referencias').' vezes.', 'passo-3-acabamentos.gif', 'O quadro de acabamentos, o modelo visto de cima e a planta 3D realista.'],
        ['Receba fotografias realistas', 'Cada câmara vira uma fotografia, com os acabamentos escolhidos. Se algo não estiver bem, peça a alteração: '.config('contentmachine.imoplanta.refazer_imagem').' por fotografia.', 'passo-4-fotos.gif', 'A vista do modelo 3D e a fotografia realista feita a partir dela, em três divisões.'],
        ['E o vídeo de visita', 'As fotografias ganham movimento e juntam-se num vídeo de visita, pela ordem de quem entra na casa.', 'passo-5-video.gif', 'Excerto do vídeo de visita do apartamento.'],
    ];
    $botao = 'inline-flex items-center justify-center min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105';
    $sobre = 'text-[11px] tracking-[.3em] text-[#0478A0]';
@endphp
<x-layouts.site title="Imoplanta · Da planta ao vídeo do imóvel">
<x-imoplanta.pagina>
    <x-slot:direita>
        <a href="{{ route('login') }}" class="hover:text-cyan">Entrar</a>
    </x-slot:direita>

    {{-- hero --}}
    <section class="grid gap-8 lg:gap-14 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] items-center">
        <div class="flex flex-col gap-5">
            <div class="flex items-center gap-3"><span class="w-8 h-0.5 bg-coral"></span><span class="{{ $sobre }}">IMOPLANTA</span></div>
            <h1 class="font-display text-[44px] lg:text-[64px] font-medium leading-[1.02] text-navy">Da planta ao vídeo do imóvel</h1>
            <p class="max-w-lg text-[16px] lg:text-[18px] leading-relaxed text-[#4A5263]">Envie as plantas de uma casa ou de um apartamento. Recebe o modelo 3D, fotografias realistas e um vídeo de visita, mesmo antes de a casa estar construída.</p>
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('plantas') }}" class="{{ $botao }}">Começar com as plantas →</a>
                <a href="#como" class="min-h-11 inline-flex items-center text-sm font-semibold text-navy underline underline-offset-4">Ver como funciona</a>
            </div>
            <p class="text-sm text-[#4A5263]">A partir de <strong class="text-navy">{{ $eur($planos->last()['preco'] / $planos->last()['imoveis']) }}</strong> por imóvel. Sem subscrição.</p>
        </div>
        {{-- the whole thing at a glance, in order: the plan, the 3D from above, a room in 3D, its AI photo, the video --}}
        <figure class="flex flex-col gap-3">
            <img src="{{ $g('capa.gif') }}" alt="A planta do apartamento, depois a casa em 3D vista de cima, o modelo 3D da sala, a fotografia realista feita a partir dele e o vídeo de visita." width="560" height="316"
                 class="w-full rounded-[20px] bg-white shadow-[0_18px_40px_rgba(14,22,38,0.18)]">
            <figcaption class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] font-medium text-[#4A5263]">
                <span>Planta</span><span aria-hidden="true" class="text-coral">→</span><span>Planta 3D</span><span aria-hidden="true" class="text-coral">→</span><span>Modelo 3D</span><span aria-hidden="true" class="text-coral">→</span><span>Fotografia IA</span><span aria-hidden="true" class="text-coral">→</span><span>Vídeo</span>
            </figcaption>
        </figure>
    </section>

    {{-- the steps --}}
    <section id="como" class="mt-20 lg:mt-28 scroll-mt-6">
        <div class="max-w-2xl">
            <div class="flex items-center gap-3"><span class="w-8 h-0.5 bg-coral"></span><span class="{{ $sobre }}">COMO FUNCIONA</span></div>
            <h2 class="mt-3 font-display text-[34px] lg:text-[46px] font-medium leading-tight text-navy">Um apartamento T3, do papel ao vídeo</h2>
            <p class="mt-3 text-[15px] lg:text-[17px] text-[#4A5263]">Todas as imagens abaixo são de um apartamento de demonstração com 126 m², feitas no Imoplanta tal como as recebe, sem retoques.</p>
        </div>

        <ol class="mt-12 flex flex-col gap-16 lg:gap-24">
            @foreach ($passos as $i => [$titulo, $texto, $gif, $alt])
                <li class="grid gap-6 lg:gap-14 lg:grid-cols-2 items-center">
                    <div class="flex flex-col gap-3 {{ $i % 2 ? 'lg:order-2' : '' }}">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-navy text-ivory font-display text-xl">{{ $i + 1 }}</span>
                        <h3 class="font-display text-[30px] lg:text-[38px] font-medium leading-tight text-navy">{{ $titulo }}</h3>
                        <p class="max-w-md text-[15px] lg:text-[17px] leading-relaxed text-[#4A5263]">{{ $texto }}</p>
                    </div>
                    <div class="rounded-[20px] overflow-hidden bg-white border border-[#E4E1DA] shadow-[0_12px_32px_rgba(14,22,38,0.10)]">
                        <img src="{{ $g($gif) }}" alt="{{ $alt }}" loading="lazy" class="w-full h-auto">
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- the add-ons: optional, bought on top of the property (no prices here) --}}
    <section class="mt-20 lg:mt-28">
        <div class="flex items-center gap-3"><span class="w-8 h-0.5 bg-coral"></span><span class="{{ $sobre }}">EXTRAS OPCIONAIS</span></div>
        <h2 class="mt-3 font-display text-[34px] lg:text-[46px] font-medium leading-tight text-navy">E, se quiser, mais dois vídeos</h2>
        <p class="mt-2 max-w-2xl text-[15px] lg:text-[17px] text-[#4A5263]">São extras: não fazem parte do imóvel. Ficam disponíveis quando o vídeo de visita estiver pronto e junta-os só se quiser.</p>
        <div class="mt-8 grid gap-6 lg:grid-cols-2 items-start">
            <article class="rounded-[20px] bg-navy text-ivory p-6 lg:p-8 flex flex-col gap-4">
                <div class="rounded-xl overflow-hidden bg-white"><img src="{{ asset('imoplanta-demo/demo-planta.gif') }}" alt="A planta 3D a construir-se: o terreno vazio, depois o chão e as paredes, e por fim a mobília." loading="lazy" class="w-full max-h-[420px] object-contain"></div>
                <span class="self-start px-2.5 py-1 rounded-full bg-cyan/15 text-cyan text-[11px] tracking-[.18em]">EXTRA</span>
                <h3 class="font-display text-[28px] leading-tight">Vídeo da planta 3D</h3>
                <p class="text-sm leading-relaxed text-[#C9CED6]">A casa vista de cima: começa num terreno vazio, depois o chão e as paredes, e por fim os móveis a entrar no lugar. Todos os pisos, com som.</p>
            </article>
            <article class="rounded-[20px] bg-navy text-ivory p-6 lg:p-8 flex flex-col gap-4">
                <video src="{{ $g('video-imocena.mp4') }}" poster="{{ $g('video-imocena.jpg') }}" controls playsinline preload="none"
                       class="w-full max-w-[260px] mx-auto aspect-[9/16] rounded-xl bg-navy-3 object-cover" aria-label="Exemplo do Vídeo Imocena do apartamento de demonstração"></video>
                <span class="self-start px-2.5 py-1 rounded-full bg-cyan/15 text-cyan text-[11px] tracking-[.18em]">EXTRA</span>
                <h3 class="font-display text-[28px] leading-tight">Vídeo Imocena</h3>
                <p class="text-sm leading-relaxed text-[#C9CED6]">O vídeo de visita feito reel vertical para as redes sociais: narração em português, música e os dados do imóvel no ecrã, com o seu contacto e o seu logótipo. Com o vídeo da planta, este também o inclui.</p>
            </article>
        </div>
    </section>

    {{-- prices --}}
    <section class="mt-20 lg:mt-28">
        <div class="flex items-center gap-3"><span class="w-8 h-0.5 bg-coral"></span><span class="{{ $sobre }}">PREÇOS</span></div>
        <h2 class="mt-3 font-display text-[34px] lg:text-[46px] font-medium leading-tight text-navy">Paga por imóvel</h2>
        <p class="mt-2 text-[15px] text-[#4A5263]">IVA incluído. Cada imóvel inclui o modelo 3D com {{ config('contentmachine.imoplanta.refazer_3d') }} correções, as fotografias e o vídeo de visita. Os imóveis de um pack ficam na sua conta até os usar.</p>
        <div class="mt-8 grid gap-5 sm:grid-cols-3">
            @foreach ($planos as $plano)
                <div class="rounded-[20px] border {{ $loop->last ? 'border-coral bg-white' : 'border-[#E4E1DA] bg-white/60' }} p-6 flex flex-col gap-1">
                    <span class="text-sm font-semibold text-navy">{{ $plano['imoveis'] === 1 ? '1 imóvel' : $plano['imoveis'].' imóveis' }}</span>
                    <span class="font-display text-[40px] leading-none text-navy">{{ $eur($plano['preco']) }}</span>
                    @if ($plano['imoveis'] > 1)
                        <span class="text-sm text-[#4A5263]">{{ $eur($plano['preco'] / $plano['imoveis']) }} por imóvel</span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- closing call --}}
    <section class="mt-20 lg:mt-28 rounded-[24px] bg-navy text-ivory px-6 py-10 lg:px-14 lg:py-14 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
            <h2 class="font-display text-[32px] lg:text-[42px] font-medium leading-tight">Tem as plantas? Comece agora.</h2>
            <p class="mt-2 text-[15px] text-[#C9CED6]">Precisa só do seu email. O modelo 3D fica pronto em poucos minutos.</p>
        </div>
        <a href="{{ route('plantas') }}" class="{{ $botao }} shrink-0">Começar com as plantas →</a>
    </section>
</x-imoplanta.pagina>
</x-layouts.site>
