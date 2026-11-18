@php
    // The home page: what Imocena is, an example, how it works, prices in short, and the blog.
    // Every "pedir" button goes to the free-video form (route 'video'), which is where the ads land.
    $check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><path d="M5 12l5 5 9-10"/></svg>';
    $form = route('video');
    $recentes = array_slice($guias, 0, 6);
    $aprender = $temas['Fotografia e apresentação'] ?? ($temas['Vídeo de imóveis'] ?? []);
    $og = fn (array $g) => is_file(public_path("images/og/{$g['slug']}.png")) ? asset("images/og/{$g['slug']}.png") : asset('images/og/imocena.png');
@endphp
<x-layouts.site
    title="Imocena · Vídeos de imóveis para consultores, a partir do link do anúncio"
    description="Imocena faz o vídeo vertical do seu imóvel a partir do link do anúncio, com narração em português de Portugal. Blog com guias de fotografia, vídeo e marketing imobiliário."
    :canonical="route('home')">

    <header class="border-b border-ivory/10">
        <nav class="max-w-[1240px] mx-auto px-6 py-4 md:py-5 flex flex-wrap items-center justify-between gap-x-8 gap-y-3" aria-label="Principal">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
            <div class="flex flex-wrap items-center gap-x-7 gap-y-2 text-sm">
                <a href="#como" class="hidden md:inline-flex items-center min-h-11 text-[#D6D9DF] hover:text-ivory">Como funciona</a>
                <a href="#blog" class="inline-flex items-center min-h-11 text-[#D6D9DF] hover:text-ivory">Blog</a>
                <a href="#precos" class="hidden md:inline-flex items-center min-h-11 text-[#D6D9DF] hover:text-ivory">Preços</a>
                <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 text-[#D6D9DF] hover:text-ivory">Entrar</a>
                <a href="{{ $form }}" class="inline-flex items-center min-h-11 px-5 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Vídeo grátis →</a>
            </div>
        </nav>
    </header>

    <main>
        {{-- Hero --}}
        <section class="relative overflow-hidden">
            <div class="max-w-[1240px] mx-auto px-6 pt-12 pb-16 md:pt-20 md:pb-24 flex flex-wrap gap-x-14 gap-y-12 items-center">
                <div class="flex-[1_1_520px] min-w-0">
                    <div class="flex items-center gap-3.5">
                        <span class="w-10 h-px bg-cyan"></span>
                        <span class="text-xs tracking-[.32em] text-cyan uppercase">Para consultores imobiliários em Portugal</span>
                    </div>
                    <h1 class="mt-5 font-display text-[clamp(38px,6vw,72px)] leading-[1] font-normal text-ivory">Cada imóvel com o seu vídeo. <em class="text-coral">Sem filmar, sem editar.</em></h1>
                    <p class="mt-6 max-w-[560px] text-[17px] leading-relaxed font-light text-[#C9CED6]">Cole o link do anúncio e a Imocena faz um reel vertical com as fotos, narração em português de Portugal e o seu contacto no fim. Pronto em minutos, para o Instagram, o TikTok, o WhatsApp e os portais.</p>
                    <div class="mt-9 flex flex-wrap items-center gap-4">
                        <a href="{{ $form }}" class="inline-flex items-center min-h-[56px] px-8 rounded-full bg-coral text-navy text-[16px] font-semibold hover:brightness-105">Fazer o primeiro vídeo grátis →</a>
                        <a href="{{ route('demo', ['slug' => 'moradia-lajeosa']) }}" class="inline-flex items-center min-h-[56px] px-7 rounded-full border border-ivory/35 text-ivory text-[15px] hover:bg-ivory/5">Ver um exemplo</a>
                    </div>
                    <ul class="mt-8 flex flex-wrap gap-x-7 gap-y-3 text-sm text-[#E4E6EA]">
                        <li class="flex items-center gap-2">{!! $check !!}Primeiro vídeo grátis</li>
                        <li class="flex items-center gap-2">{!! $check !!}Sem cartão de crédito</li>
                        <li class="flex items-center gap-2">{!! $check !!}Voz em português de Portugal</li>
                    </ul>
                </div>
                @if ($reel)
                    <div class="flex-[0_1_300px] mx-auto">
                        <div class="relative w-[260px] max-w-full aspect-[9/16] rounded-[28px] overflow-hidden bg-navy-2 border-[6px] border-navy-3 shadow-[0_40px_90px_-30px_rgba(5,184,230,.45)]">
                            <video src="{{ $reel }}" autoplay muted loop playsinline preload="metadata" class="absolute inset-0 w-full h-full object-cover"
                                   aria-label="Vídeo de exemplo feito pela Imocena" onclick="this.muted=false;this.controls=true"></video>
                        </div>
                        <p class="mt-3 text-center text-xs text-[#8B95A7]">Exemplo real. Toque para ouvir.</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- Funciona com --}}
        <section class="border-y border-ivory/10" aria-label="Agências">
            <div class="max-w-[1240px] mx-auto px-6 py-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-[13px] text-[#AEB5C1]">
                <span class="tracking-[.24em]">LÊ ANÚNCIOS DE</span>
                @foreach (\App\Services\RealEstate\ListingScraper::NOMES as $nome)
                    <span class="text-lg font-semibold text-[#E4E6EA]">{{ $nome }}</span>
                @endforeach
            </div>
        </section>

        {{-- Como funciona --}}
        <section id="como" class="bg-ivory text-navy scroll-mt-4">
            <div class="max-w-[1240px] mx-auto px-6 py-24">
                <div class="max-w-[760px]">
                    <span class="text-xs tracking-[.32em] text-cyan-deep">COMO FUNCIONA</span>
                    <h2 class="mt-4 font-display text-[clamp(38px,5vw,58px)] leading-[1.02] font-normal">Três passos. <em>Só um é seu.</em></h2>
                </div>
                <div class="mt-14 grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-6">
                    @foreach ([
                        ['Cole o link', 'É a única coisa que faz. Lemos o anúncio: fotos, preço, tipologia, área, certificado energético e os seus dados de contacto.'],
                        ['Escrevemos, narramos e montamos', 'Um texto curto, como um consultor o diria, gravado em português de Portugal, sobre as fotos do imóvel.'],
                        ['Publique', 'Descarregue o MP4 vertical e publique nos Reels, no TikTok e nos Shorts, ou envie-o ao proprietário e aos compradores.'],
                    ] as $i => [$titulo, $texto])
                        <div class="px-7 py-8 bg-white rounded border-t-[3px] border-coral">
                            <span class="font-display text-[64px] leading-[.8]">0{{ $i + 1 }}</span>
                            <h3 class="mt-6 mb-2.5 font-sans text-xl font-semibold">{{ $titulo }}</h3>
                            <p class="text-[15px] leading-[1.7] text-[#4B5563]">{{ $texto }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Para quê --}}
        <section>
            <div class="max-w-[1240px] mx-auto px-6 py-24 grid gap-12 lg:grid-cols-[1fr_1.2fr] items-start">
                <div>
                    <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">PARA QUÊ</span></div>
                    <h2 class="mt-4 font-display text-[clamp(36px,4.6vw,54px)] leading-[1.04] font-normal text-ivory">Um vídeo por imóvel, <em>em todo o lado onde os compradores estão.</em></h2>
                </div>
                <ul class="grid sm:grid-cols-2 gap-4">
                    @foreach ([
                        ['Redes sociais', 'Um Reel, um TikTok e um Short de cada imóvel novo, sem perder uma tarde a editar.'],
                        ['Portais', 'Junte o vídeo ao anúncio no idealista e nos outros portais que aceitam vídeo.'],
                        ['Angariação', 'Leve à reunião o vídeo de um imóvel seu e mostre ao proprietário como vai divulgar a casa dele.'],
                        ['WhatsApp', 'Envie o vídeo aos compradores da sua lista e ao proprietário, que o partilha com a família.'],
                    ] as [$t, $d])
                        <li class="p-6 rounded-2xl bg-navy-2 border border-ivory/10">
                            <span class="text-lg font-semibold text-ivory">{{ $t }}</span>
                            <p class="mt-2 text-[15px] leading-relaxed text-[#AEB5C1]">{{ $d }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Blog --}}
        <section id="blog" class="bg-ivory text-navy scroll-mt-4">
            <div class="max-w-[1240px] mx-auto px-6 py-24">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div class="max-w-[720px]">
                        <span class="text-xs tracking-[.32em] text-cyan-deep">BLOG</span>
                        <h2 class="mt-4 font-display text-[clamp(38px,5vw,58px)] leading-[1.02] font-normal">Aprenda a mostrar <em>melhor cada casa.</em></h2>
                        <p class="mt-4 text-[16px] leading-relaxed text-[#4B5563]">Guias práticos para consultores: fotografia, vídeo, home staging, redes sociais e angariação. Cada guia tem o seu vídeo curto.</p>
                    </div>
                    <a href="{{ route('guias') }}" class="inline-flex items-center min-h-11 px-5 rounded-full border border-navy/30 text-sm font-semibold hover:bg-navy/5">Ver os {{ count($guias) }} artigos →</a>
                </div>

                <nav aria-label="Temas do blog" class="mt-8 flex flex-wrap gap-2 text-sm">
                    @foreach ($temas as $tema => $lista)
                        <a href="{{ route('guias') }}#{{ \Illuminate\Support\Str::slug($tema) }}" class="px-4 py-2 rounded-full border border-navy/15 text-[#374151] hover:border-cyan-deep hover:text-navy">{{ $tema }} <span class="text-[#6B7280]">{{ count($lista) }}</span></a>
                    @endforeach
                </nav>

                <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recentes as $g)
                        <li>
                            <a href="{{ route('guias.ver', $g['slug']) }}" class="group h-full flex flex-col rounded-2xl overflow-hidden bg-white border border-[#DDD8CC] hover:border-cyan-deep">
                                <img src="{{ $og($g) }}" alt="" loading="lazy" width="1200" height="630" class="w-full aspect-[1200/630] object-cover">
                                <span class="p-6 flex flex-col flex-1">
                                    <span class="text-[11px] tracking-[.2em] text-cyan-deep uppercase">{{ $g['tema'] }}</span>
                                    <span class="mt-2 font-display text-[24px] leading-tight text-navy">{{ $g['titulo'] }}</span>
                                    <span class="mt-3 text-[15px] leading-relaxed text-[#4B5563]">{{ $g['descricao'] }}</span>
                                    <span class="mt-auto pt-5 text-sm font-semibold text-cyan-deep">Ler o artigo →</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if (count($aprender) > 0)
                    <div class="mt-16">
                        <h3 class="font-display text-[30px] leading-tight">Fotografia, vídeo e apresentação da casa</h3>
                        <ul class="mt-6 grid gap-x-10 gap-y-3 sm:grid-cols-2 text-[15px]">
                            @foreach (array_slice($aprender, 0, 10) as $g)
                                <li><a href="{{ route('guias.ver', $g['slug']) }}" class="text-[#1F2937] underline decoration-[#C9C3B4] underline-offset-4 hover:decoration-cyan-deep">{{ $g['titulo'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        {{-- Preços --}}
        <section id="precos" class="scroll-mt-4">
            <div class="max-w-[1240px] mx-auto px-6 py-24">
                <div class="max-w-[760px]">
                    <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">PREÇOS</span></div>
                    <h2 class="mt-4 font-display text-[clamp(36px,4.6vw,54px)] leading-[1.04] font-normal text-ivory">O primeiro é grátis. <em>Depois, paga só os vídeos que faz.</em></h2>
                </div>
                <div class="mt-12 grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
                    @foreach ([
                        ['GRÁTIS', '0 €', '1 vídeo, com marca d’água'],
                        ['VÍDEO AVULSO', '20 €', '1 vídeo, sem marca d’água'],
                        ['PACK 10', '100 €', '10 € por vídeo · 12 meses'],
                        ['PACK 20', '175 €', '8,75 € por vídeo · 12 meses'],
                    ] as [$nome, $preco, $nota])
                        <div class="px-6 py-7 rounded-2xl bg-navy-2 border border-ivory/10">
                            <span class="text-xs font-medium tracking-[.28em] text-cyan">{{ $nome }}</span>
                            <div class="mt-4 font-display text-[48px] leading-none text-ivory">{{ $preco }}</div>
                            <p class="mt-3 text-sm text-[#C9CED6]">{{ $nota }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-6 text-sm text-[#AEB5C1]">IVA incluído, sem subscrição, fatura com NIF. <a href="{{ $form }}#precos" class="text-cyan underline underline-offset-2">Ver os planos em pormenor</a>.</p>
            </div>
        </section>

        {{-- Chamada final --}}
        <section class="bg-ivory">
            <div class="rounded-t-[64px] bg-navy">
                <div class="max-w-[900px] mx-auto px-6 py-24 text-center">
                    <h2 class="font-display text-[clamp(40px,6vw,68px)] leading-none font-normal text-ivory">Experimente com um imóvel seu. <em class="text-coral">É grátis.</em></h2>
                    <p class="mt-6 mx-auto max-w-[560px] text-[17px] leading-[1.7] font-light text-[#C9CED6]">Um vídeo grátis por anúncio, sem cartão de crédito. Basta o link.</p>
                    <a href="{{ $form }}" class="mt-10 inline-flex items-center min-h-[60px] px-9 rounded-full bg-coral text-navy text-[17px] font-semibold hover:brightness-105">Quero o meu vídeo grátis →</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-[#0A101C] border-t border-ivory/10">
        <div class="max-w-[1240px] mx-auto px-6 pt-[72px] pb-10 flex flex-wrap gap-12">
            <div class="flex-[1.4_1_260px] min-w-0">
                <span class="font-display text-[30px] font-medium text-ivory">Imocena</span>
                <p class="mt-3.5 max-w-[280px] text-sm leading-[1.7] text-[#AEB5C1]">Vídeos de imóveis para consultores portugueses, a partir do link do anúncio.</p>
            </div>
            <nav aria-label="Produto" class="flex-[1_1_160px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">PRODUTO</span>
                <a href="{{ $form }}" class="text-[#AEB5C1] hover:text-ivory">Vídeo grátis</a>
                <a href="{{ route('demo', ['slug' => 'moradia-lajeosa']) }}" class="text-[#AEB5C1] hover:text-ivory">Exemplo</a>
                <a href="{{ route('login') }}" class="text-[#AEB5C1] hover:text-ivory">Entrar</a>
            </nav>
            <nav aria-label="Blog" class="flex-[1_1_200px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">BLOG</span>
                @foreach (array_slice($guias, 0, 4) as $g)
                    <a href="{{ route('guias.ver', $g['slug']) }}" class="text-[#AEB5C1] hover:text-ivory">{{ $g['titulo'] }}</a>
                @endforeach
                <a href="{{ route('guias') }}" class="text-ivory hover:text-cyan">Todos os artigos →</a>
            </nav>
            <nav aria-label="Empresa" class="flex-[1_1_160px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">EMPRESA</span>
                <a href="mailto:{{ config('mail.from.address') }}" class="text-[#AEB5C1] hover:text-ivory">Contactos</a>
                <a href="{{ route('termos') }}" class="text-[#AEB5C1] hover:text-ivory">Termos e condições</a>
                <a href="{{ route('privacidade') }}" class="text-[#AEB5C1] hover:text-ivory">Política de privacidade</a>
                <a href="#" data-cookies class="text-[#AEB5C1] hover:text-ivory">Preferências de cookies</a>
                <a href="https://www.livroreclamacoes.pt" rel="noopener" class="text-[#AEB5C1] hover:text-ivory">Livro de Reclamações</a>
            </nav>
        </div>
        <div class="max-w-[1240px] mx-auto px-6 py-6 border-t border-ivory/10 flex flex-wrap justify-between gap-3 text-[13px] text-[#8B95A7]">
            @php($empresa = config('contentmachine.empresa'))
            <span>© {{ date('Y') }} {{ implode(' · ', array_filter(['Imocena', $empresa['nome'] ?? null, ! empty($empresa['nif']) ? 'NIF '.$empresa['nif'] : null])) }}</span>
            <span>Feito em Portugal</span>
        </div>
    </footer>
</x-layouts.site>
