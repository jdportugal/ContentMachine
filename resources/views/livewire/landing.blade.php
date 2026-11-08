@php
    // The reel mock-ups use the demo listing's photos (public/media/demo/moradia-lajeosa,
    // generated, illustrative), falling back to public/images/landing/imovel.jpg, then a gradient.
    $demoSlug = 'moradia-lajeosa';
    $demoFoto = fn (string $f) => is_file(public_path("media/demo/{$demoSlug}/{$f}")) ? asset("media/demo/{$demoSlug}/{$f}")
        : (is_file(public_path('images/landing/imovel.jpg')) ? asset('images/landing/imovel.jpg') : null);
    $estilo = fn (?string $url) => $url ? "background-image:url('{$url}');background-size:cover;background-position:center" : 'background:linear-gradient(160deg,#2A3A52,#141F33)';
    $fotoStyle = $estilo($demoFoto('01-fachada.png'));
    $salaStyle = $estilo($demoFoto('03-sala.png'));
    $cozinhaStyle = $estilo($demoFoto('06-cozinha.png'));
    $jardimStyle = $estilo($demoFoto('14-jardim-terraco.png'));
    $demoReel = is_file(public_path("media/demo/{$demoSlug}/reel.mp4")) ? asset("media/demo/{$demoSlug}/reel.mp4") : null;
    $demoUrl = route('demo', ['slug' => $demoSlug]);
    // Stills taken from the reel at different moments: media/demo/<slug>/frames/frames.json lists
    // [{file, time, label}, …] and is written by scripts/demo-frames.sh after each render.
    $framesJson = public_path("media/demo/{$demoSlug}/frames/frames.json");
    $frames = is_file($framesJson) ? (json_decode(file_get_contents($framesJson), true) ?: []) : [];
    $frames = array_values(array_filter($frames, fn ($f) => is_file(public_path("media/demo/{$demoSlug}/frames/".($f['file'] ?? '')))));
    $frameUrl = fn (array $f) => asset("media/demo/{$demoSlug}/frames/{$f['file']}");
    $poster = $frames ? $frameUrl($frames[0]) : null;

    $check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><path d="M5 12l5 5 9-10"/></svg>';

    // Prices include VAT. Paid videos are sold by email until there is a checkout.
    $comprar = fn (string $o) => 'mailto:'.config('mail.from.address').'?subject='.rawurlencode($o);
    $limpo = 'Versão limpa, sem marca d’água';
    $planos = [
        ['nome' => 'GRÁTIS', 'para' => 'Para experimentar com um imóvel seu.', 'preco' => '0 €', 'unidade' => '1 vídeo', 'porVideo' => 'Com marca d’água', 'cta' => 'Pedir o vídeo grátis', 'href' => '#pedido', 'itens' => ['Basta colar o link do anúncio', 'Voz em português de Portugal', 'Cartão final com AMI', 'Com marca d’água Imocena'], 'selo' => null, 'coral' => true],
        ['nome' => 'VÍDEO AVULSO', 'para' => 'Um imóvel, quando precisar.', 'preco' => '20 €', 'unidade' => '1 vídeo', 'porVideo' => '20 € por vídeo', 'cta' => 'Comprar', 'href' => $comprar('Vídeo avulso (20 €)'), 'itens' => [$limpo, 'Voz em português de Portugal', 'Cartão final com AMI', 'Pronto para Reels e TikTok'], 'selo' => null, 'coral' => false],
        ['nome' => 'PACK 10', 'para' => 'Para quem angaria todas as semanas.', 'preco' => '100 €', 'unidade' => '10 vídeos', 'porVideo' => '10 € por vídeo', 'cta' => 'Comprar pack', 'href' => $comprar('Pack 10 (100 €)'), 'itens' => [$limpo, 'Válido 12 meses', 'Use quando angariar'], 'selo' => 'MAIS ESCOLHIDO', 'coral' => false],
        ['nome' => 'PACK 20', 'para' => 'Para carteiras grandes e equipas.', 'preco' => '175 €', 'unidade' => '20 vídeos', 'porVideo' => '8,75 € por vídeo', 'cta' => 'Comprar pack', 'href' => $comprar('Pack 20 (175 €)'), 'itens' => [$limpo, 'Válido 12 meses', 'O preço mais baixo por vídeo'], 'selo' => null, 'coral' => false],
    ];

    // What the free video includes. Its value is the price of a paid video (20 €).
    $stack = [
        ['Reel vertical com menos de 60 segundos', 'Em 9:16, pronto para Reels, TikTok, Shorts e estados de WhatsApp.'],
        ['Texto escrito a partir do anúncio', 'Preço, tipologia, área e certificado energético, ditos como um consultor os diria.'],
        ['Narração em português de Portugal', 'Diz «T3», «m²» e «duzentos e noventa e cinco mil euros» como se diz cá.'],
        ['Cartão final com os seus dados', 'Nome, telemóvel, agência e número AMI no fecho do vídeo.'],
        ['Montagem com ar de agência premium', 'Tipografia editorial, câmara lenta e transições discretas.'],
        ['MP4 para descarregar', 'Publique onde quiser ou envie ao proprietário.'],
    ];

    $faq = [
        ['O primeiro vídeo é mesmo grátis?', 'Sim. Não pedimos cartão de crédito. O vídeo grátis leva a marca d’água Imocena; a versão limpa custa 20 €, ou a partir de 8,75 € com um pack.'],
        ['Quanto tempo demora?', 'Poucos minutos. Mantenha a página aberta: o vídeo aparece no mesmo sítio onde colou o link.'],
        ['A voz soa mesmo portuguesa?', 'A narração é em português de Portugal. Nos vídeos pagos pode também gravar o texto e usar a sua própria voz.'],
        ['De que sites leem os anúncios?', 'RE/MAX, iad e idealista. Basta copiar o link da barra de endereço do anúncio.'],
        ['Como recebo o vídeo?', 'Aparece nesta página assim que estiver pronto, com o botão para descarregar o MP4 vertical.'],
        ['Posso alterar o texto da narração?', 'Na sua conta, sim: pode deixar uma nota (preço novo, algo a destacar) e o texto muda antes de ser gravado.'],
        ['Que dados aparecem no fim do vídeo?', 'O nome, o telemóvel, a agência e o número AMI que constam do anúncio.'],
        ['O que fazem com os meus dados?', 'Usamos os dados do anúncio só para criar o seu vídeo e o contactar sobre ele, de acordo com o RGPD. Pode pedir que os apaguemos a qualquer momento.'],
        ['Posso pedir mais do que um vídeo grátis?', 'A oferta é de um vídeo grátis por anúncio, para experimentar. Depois, compre vídeos avulso (20 €) ou um pack de 10 ou 20 vídeos.'],
        ['Os packs expiram?', 'Os vídeos de um pack podem ser usados durante 12 meses a contar da compra.'],
        ['Os preços incluem IVA?', 'Sim, todos os preços incluem IVA. Emitimos fatura com NIF.'],
    ];
@endphp

<div>
    {{-- Barra de oferta --}}
    <div class="bg-coral text-navy text-center px-4 py-2.5 text-sm">
        <strong class="font-semibold">Oferta de lançamento:</strong> 1 vídeo grátis por anúncio, sem cartão de crédito.
        <a href="#pedido" class="font-semibold underline underline-offset-2 text-navy">Pedir agora →</a>
    </div>

    {{-- Navegação: só a marca e o pedido, sem links que tirem a pessoa da página --}}
    <header class="border-b border-ivory/10">
        <nav class="max-w-[1240px] mx-auto px-6 py-5 flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3 text-sm">
                <a href="{{ route('login') }}" class="text-[#D6D9DF] hover:text-ivory">Entrar</a>
                <a href="#pedido" class="inline-flex items-center min-h-11 px-5 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Vídeo grátis →</a>
            </div>
        </nav>
    </header>

    {{-- Hero + formulário --}}
    <section class="relative overflow-hidden">
        <div class="max-w-[1240px] mx-auto px-6 pt-16 pb-24 flex flex-wrap gap-14 items-center">
            <div class="flex-[1_1_520px] min-w-0">
                <div class="flex items-center gap-3.5">
                    <span class="w-10 h-px bg-cyan"></span>
                    <span class="text-xs tracking-[.32em] text-cyan">CONSULTORES IMOBILIÁRIOS EM PORTUGAL</span>
                </div>
                <h1 class="mt-6 font-display text-[clamp(48px,6.4vw,80px)] leading-[.98] font-normal text-ivory">O vídeo do seu imóvel, pronto em minutos. <em class="text-coral">Grátis.</em></h1>
                <p class="mt-7 max-w-[540px] text-lg leading-relaxed font-light text-[#C9CED6]">Cole o link do anúncio. Em poucos minutos recebe um reel vertical com as fotos do imóvel, narração em português de Portugal e o seu contacto no fim. Sem filmar, sem editar, sem pagar.</p>
                <div id="pedido" @if (($pedido['estado'] ?? null) === 'a-fazer') wire:poll.5s @endif
                     class="mt-10 max-w-[600px] scroll-mt-6 rounded-[22px] p-[1px] bg-gradient-to-br from-cyan/60 via-ivory/10 to-coral/50 shadow-[0_40px_90px_-30px_rgba(5,184,230,.35)]">
                    <div class="rounded-[21px] bg-navy-2 px-6 py-6 sm:px-7">
                    @if (($pedido['estado'] ?? null) === 'pronto')
                        <div class="flex flex-wrap gap-6 items-center">
                            <video src="{{ $pedido['video'] }}" controls playsinline preload="metadata" class="w-[170px] aspect-[9/16] rounded-[14px] bg-navy border border-ivory/10"></video>
                            <div class="flex-[1_1_220px] flex flex-col gap-3">
                                <span class="text-xs tracking-[.28em] text-cyan">O SEU VÍDEO ESTÁ PRONTO</span>
                                <span class="font-display text-[34px] leading-tight text-ivory">Publique-o já.</span>
                                <p class="text-sm leading-relaxed text-[#C9CED6]">Esta versão leva a marca d’água Imocena. A versão limpa custa 20 €, ou a partir de 8,75 € com um pack.</p>
                                <a href="{{ $pedido['descarregar'] }}" class="inline-flex items-center justify-center gap-2 min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
                                    Descarregar MP4
                                </a>
                                <a href="#precos" class="text-sm font-medium text-cyan underline underline-offset-2">Quero a versão limpa</a>
                            </div>
                        </div>
                    @elseif (($pedido['estado'] ?? null) === 'a-fazer')
                        <div role="status" class="flex flex-col gap-4">
                            <span class="font-display text-[32px] leading-tight text-ivory">Estamos a fazer o seu vídeo.</span>
                            <div class="h-1.5 rounded-full bg-ivory/10 overflow-hidden"><div class="h-full bg-cyan transition-all duration-700" style="width: {{ $pedido['pct'] }}%"></div></div>
                            <p class="text-sm text-[#C9CED6]"><strong class="text-ivory font-medium">{{ $pedido['etapa'] }}…</strong> Leva poucos minutos. Mantenha esta página aberta: o vídeo aparece aqui.</p>
                        </div>
                    @elseif (($pedido['estado'] ?? null) === 'falhou')
                        <div role="alert" class="flex flex-col gap-3">
                            <span class="font-display text-[32px] leading-tight text-ivory">Não conseguimos fazer este vídeo.</span>
                            <p class="text-sm leading-relaxed text-[#C9CED6]">Às vezes um anúncio não tem fotos ou dados suficientes. Já fomos avisados e vamos ver o que se passou.</p>
                            <button type="button" wire:click="outro" class="self-start min-h-11 px-5 rounded-full border border-ivory/40 text-ivory text-sm font-semibold hover:bg-ivory/5">Tentar com outro anúncio</button>
                        </div>
                    @else
                        <form wire:submit="pedir" class="flex flex-col gap-4" novalidate>
                            <div class="flex flex-wrap justify-between items-center gap-3">
                                <label for="anuncio" class="font-display text-[26px] leading-tight text-ivory">Peça o seu vídeo grátis</label>
                                <span class="px-3 py-1 rounded-full bg-coral/15 border border-coral/40 text-xs text-coral">Valor <s>20 €</s> · <strong class="font-semibold">hoje 0 €</strong></span>
                            </div>
                            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 p-1.5 rounded-[18px] sm:rounded-full bg-navy border border-ivory/15 focus-within:border-cyan focus-within:ring-4 focus-within:ring-cyan/15 transition">
                                <svg class="hidden sm:block shrink-0 ml-4 text-[#8B95A7]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>
                                <input id="anuncio" type="url" wire:model="anuncio" placeholder="Cole aqui o link do anúncio" required
                                       class="flex-1 min-w-0 w-full min-h-12 px-3 bg-transparent border-0 text-[15px] text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:ring-0">
                                <button type="submit" wire:loading.attr="disabled" wire:target="pedir"
                                        class="w-full sm:w-auto shrink-0 min-h-12 px-6 rounded-full bg-coral text-navy text-[15px] font-semibold whitespace-nowrap cursor-pointer hover:brightness-105 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="pedir">Criar vídeo grátis →</span>
                                    <span wire:loading wire:target="pedir">A enviar…</span>
                                </button>
                            </div>
                            @error('anuncio') <p class="-mt-1 text-[13px] text-coral">{{ $message }}</p> @enderror
                            {{-- Honeypot: invisible to people, tempting to bots. --}}
                            <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden">
                                <label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label>
                            </div>
                            <label class="flex gap-2.5 items-start text-[11px] leading-snug text-[#AEB5C1]">
                                <input type="checkbox" wire:model="consentimento" class="mt-px w-3.5 h-3.5 shrink-0 accent-cyan">
                                <span>Sou o consultor deste anúncio e aceito que a Imocena use os dados do anúncio (incluindo o meu nome e contacto) para criar o vídeo e me contactar sobre ele, de acordo com a <a href="#" class="text-ivory underline">Política de Privacidade</a> (RGPD).</span>
                            </label>
                            @error('consentimento') <p class="-mt-1 text-[13px] text-coral">{{ $message }}</p> @enderror
                            {{-- Promotional emails: a separate, optional, unticked opt-in (RGPD / Lei 41/2004). --}}
                            <label class="-mt-1.5 flex gap-2.5 items-start text-[11px] leading-snug text-[#AEB5C1]">
                                <input type="checkbox" wire:model.live="marketing" class="mt-px w-3.5 h-3.5 shrink-0 accent-cyan">
                                <span>{{ \App\Livewire\Landing::MARKETING }} <span class="text-[#8B95A7]">(Opcional)</span></span>
                            </label>
                            @if ($marketing)
                                <div class="-mt-1 pl-6">
                                    <label for="email" class="sr-only">O seu email</label>
                                    <input id="email" type="email" wire:model="email" placeholder="O seu email" autocomplete="email"
                                           class="w-full min-h-11 px-4 rounded-full bg-navy border border-ivory/15 text-sm text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:border-cyan focus:ring-4 focus:ring-cyan/15">
                                    @error('email') <p class="mt-1.5 text-[13px] text-coral">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <div class="pt-3 border-t border-ivory/10 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-[#C9CED6]">
                                <span class="flex items-center gap-2">{!! $check !!}Sem cartão de crédito</span>
                                <span class="flex items-center gap-2">{!! $check !!}Pronto em minutos</span>
                                <span class="text-[#8B95A7]">Lê anúncios da RE/MAX, iad e idealista</span>
                            </div>
                        </form>
                    @endif
                    </div>
                </div>
            </div>

            {{-- Leque de reels: o vídeo de demonstração a tocar, com dois momentos dele ao lado --}}
            <div class="flex-[1_1_480px] min-w-0 relative h-[760px] hidden md:block">
                @if ($demoReel)
                    @php /* side phones: an interior and the garden — two clearly different moments */ $lado = [$frames[2] ?? ($frames[1] ?? null), $frames[7] ?? ($frames[count($frames) - 2] ?? null)]; if ($lado[0] && $lado[1] && $lado[0]['file'] === $lado[1]['file']) { $lado[1] = null; } @endphp
                    @foreach ([['left-0 top-[100px] -rotate-[7deg]', $lado[0]], ['right-0 top-[120px] rotate-[7deg]', $lado[1]]] as [$pos, $frame])
                        <div class="absolute {{ $pos }} w-[230px] h-[498px] rounded-[38px] overflow-hidden bg-navy border-[6px] border-navy-3 shadow-[0_30px_60px_-20px_rgba(0,0,0,.7)]" aria-hidden="true">
                            <span class="absolute left-1/2 top-2.5 -translate-x-1/2 w-[68px] h-[20px] rounded-full bg-navy-3 z-10"></span>
                            @if ($frame)
                                <img src="{{ $frameUrl($frame) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                                <span class="absolute left-3 bottom-3 px-2 py-1 rounded-full bg-navy/70 text-[9px] tracking-[.2em] text-[#E4E6EA]">{{ $frame['time'] }}</span>
                            @else
                                <div class="absolute inset-0" style="{{ $loop->first ? $salaStyle : $jardimStyle }}"></div>
                            @endif
                        </div>
                    @endforeach
                    <div class="absolute left-1/2 top-0 -ml-[150px] w-[300px] h-[650px] rounded-[48px] overflow-hidden bg-navy border-[7px] border-navy-3 shadow-[0_40px_90px_-20px_rgba(0,0,0,.8)]">
                        <span class="absolute left-1/2 top-3 -translate-x-1/2 w-[90px] h-[26px] rounded-full bg-navy-3 z-10"></span>
                        <video src="{{ $demoReel }}" @if ($poster) poster="{{ $poster }}" @endif autoplay muted loop playsinline controls preload="metadata"
                               class="absolute inset-0 w-full h-full object-cover bg-navy" aria-label="Vídeo de demonstração: Moradia T3 em Lajeosa, Viseu"></video>
                    </div>
                    <div class="absolute left-1/2 bottom-[30px] -translate-x-1/2 flex items-center gap-3 px-[18px] py-3 rounded-full bg-navy-3 border border-cyan/35 whitespace-nowrap">
                        <span class="w-2.5 h-2.5 rounded-full bg-cyan"></span>
                        <span class="text-[13px] text-[#E4E6EA]">Feito pela Imocena em 7 minutos · <a href="{{ $demoUrl }}" class="text-cyan underline underline-offset-2">ver o anúncio</a></span>
                    </div>
                @else
                <div class="absolute left-0 top-[90px] w-[236px] h-[420px] rounded-[26px] overflow-hidden bg-navy border-[6px] border-navy-3 -rotate-[7deg] shadow-[0_30px_60px_-20px_rgba(0,0,0,.7)]" aria-hidden="true">
                    <div class="absolute inset-x-0 top-0 h-[190px] saturate-[.85] brightness-90" style="{{ $jardimStyle }}"></div>
                    <div class="absolute inset-x-0 top-[140px] h-[50px] bg-gradient-to-b from-navy/0 to-navy"></div>
                    <div class="absolute inset-x-4 top-[230px] text-center flex flex-col items-center gap-2.5">
                        <span class="text-[8px] tracking-[.34em] text-cyan">PREÇO</span>
                        <span class="font-display text-[44px] leading-none">295.000 €</span>
                        <span class="w-8 h-0.5 bg-coral"></span>
                        <span class="text-[8px] font-light tracking-[.2em]">PRONTO A UTILIZAR</span>
                    </div>
                </div>
                <div class="absolute right-0 top-[110px] w-[236px] h-[420px] rounded-[26px] overflow-hidden bg-navy border-[6px] border-navy-3 rotate-[7deg] shadow-[0_30px_60px_-20px_rgba(0,0,0,.7)] flex flex-col items-center px-[18px] pt-14 pb-6 gap-3.5 text-center" aria-hidden="true">
                    <div class="w-[116px] h-[146px] bg-[#26324A] border border-cyan"></div>
                    <span class="text-[7px] tracking-[.34em] text-cyan">VISITAS POR MARCAÇÃO</span>
                    <span class="font-display text-[26px] leading-none">O seu nome</span>
                    <span class="px-3 py-1.5 border border-cyan text-[10px] tracking-[.14em]">+351 9XX XXX XXX</span>
                    <span class="mt-auto text-[8px] tracking-[.3em] opacity-70">AMI 00000</span>
                </div>
                <div class="absolute left-1/2 top-0 -ml-[150px] w-[300px] h-[534px] rounded-[32px] overflow-hidden bg-navy border-[7px] border-navy-3 shadow-[0_40px_90px_-20px_rgba(0,0,0,.8)]" aria-hidden="true">
                    <div class="absolute inset-0 scale-[1.04] saturate-[.85] brightness-[.92]" style="{{ $fotoStyle }}"></div>
                    <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(14,22,38,.55)_0%,rgba(14,22,38,0)_28%,rgba(14,22,38,0)_46%,rgba(14,22,38,.94)_100%)]"></div>
                    <div class="absolute top-[22px] inset-x-5 flex justify-between text-[7px] tracking-[.32em]"><span class="font-medium">A SUA AGÊNCIA</span><span class="font-light">REF. 2019</span></div>
                    <span class="absolute top-[46px] left-5 px-2.5 py-1 bg-coral text-navy text-[8px] font-semibold tracking-[.24em]">NOVO IMÓVEL</span>
                    <div class="absolute inset-x-5 bottom-12 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2"><span class="w-[22px] h-px bg-cyan"></span><span class="text-[8px] tracking-[.3em] text-cyan">LAJEOSA · VISEU</span></div>
                        <span class="font-display text-[34px] leading-[1.02]">Moradia T3<br><em>com jardim</em> e garagem</span>
                        <span class="text-[8px] font-light tracking-[.18em] opacity-85">185 M² · CLASSE B · GARAGEM</span>
                    </div>
                    <div class="absolute inset-x-5 bottom-[22px] h-0.5 bg-ivory/25"><div class="w-[38%] h-full bg-cyan"></div></div>
                </div>
                <div class="absolute left-1/2 bottom-[60px] -translate-x-1/2 flex items-center gap-3 px-[18px] py-3 rounded-full bg-navy-3 border border-cyan/35 whitespace-nowrap" aria-hidden="true">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan"></span>
                    <span class="text-[13px] text-[#E4E6EA]">Narração: <em class="font-display text-base">«Moradia em Lajeosa com vista para a serra…»</em></span>
                </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Funciona com --}}
    <section class="border-y border-ivory/10">
        <div class="max-w-[1240px] mx-auto px-6 py-7 flex flex-wrap items-center justify-center gap-x-12 gap-y-4 text-[13px] text-[#AEB5C1]">
            <span class="tracking-[.24em]">LÊ ANÚNCIOS DE</span>
            <span class="text-xl font-semibold text-[#E4E6EA]">RE/MAX</span>
            <span class="text-xl font-semibold text-[#E4E6EA]">iad Portugal</span>
            <span class="text-xl font-semibold text-[#E4E6EA]">idealista</span>
        </div>
    </section>

    {{-- O problema: o caminho de hoje contra o nosso --}}
    <section class="bg-ivory text-navy">
        <div class="max-w-[1240px] mx-auto px-6 pt-28 pb-8">
            <div class="text-center max-w-[820px] mx-auto">
                <span class="text-xs tracking-[.32em] text-cyan-deep">O PROBLEMA</span>
                <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal">Fotos já não chegam. <em>E editar vídeo não é o seu trabalho.</em></h2>
            </div>
            <div class="mt-14 grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-5">
                <div class="px-8 py-9 rounded-2xl border border-[#D6D1C5]">
                    <span class="text-xs font-medium tracking-[.28em] text-[#4B5563]">SEM IMOCENA</span>
                    <ul class="mt-6 flex flex-col gap-4 text-[15px] leading-relaxed text-[#4B5563]">
                        @foreach (['Filmar, escolher música, montar e legendar: horas por imóvel.', 'Ou esperar dias por um videógrafo e pagar cada saída.', 'Entretanto, o anúncio fica só com fotos.', 'Ferramentas genéricas com voz brasileira ou robótica.'] as $item)
                            <li class="flex gap-3 items-start"><span aria-hidden="true" class="shrink-0 text-bad font-semibold">✕</span><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <div class="px-8 py-9 rounded-2xl bg-white border-2 border-cyan">
                    <span class="text-xs font-medium tracking-[.28em] text-cyan-deep">COM IMOCENA</span>
                    <ul class="mt-6 flex flex-col gap-4 text-[15px] leading-relaxed">
                        @foreach (['Cola o link do anúncio. Mais nada.', 'O reel fica pronto em poucos minutos.', 'Publica no mesmo dia em que angaria.', 'Voz em português de Portugal, com o seu contacto e AMI no fim.'] as $item)
                            <li class="flex gap-3 items-start">{!! $check !!}<span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- Como funciona --}}
    <section id="como" class="bg-ivory text-navy">
        <div class="max-w-[1240px] mx-auto px-6 py-28">
            <div class="text-center max-w-[760px] mx-auto">
                <span class="text-xs tracking-[.32em] text-cyan-deep">COMO FUNCIONA</span>
                <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal">Três passos. <em>Só um é seu.</em></h2>
            </div>
            <div class="mt-[72px] grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-8">
                @foreach ([
                    ['01', 'Cole o link', 'É a única coisa que faz. Lemos o anúncio: fotos, preço, tipologia, área, certificado e os seus dados de contacto.'],
                    ['02', 'Escrevemos, narramos e montamos', 'Um texto curto, como um consultor o diria, gravado em português de Portugal. Leva poucos minutos.'],
                    ['03', 'Publique', 'O vídeo aparece na página. Descarregue o MP4 e publique no Instagram, no TikTok ou envie ao proprietário.'],
                ] as [$n, $titulo, $texto])
                    <div class="px-8 py-9 bg-white rounded border-t-[3px] border-coral">
                        <span class="font-display text-[72px] leading-[.8]">{{ $n }}</span>
                        <h3 class="mt-7 mb-2.5 font-sans text-xl font-semibold tracking-normal">{{ $titulo }}</h3>
                        <p class="text-[15px] leading-[1.7] text-[#4B5563]">{{ $texto }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- A oferta, empilhada --}}
    <section>
        <div class="max-w-[900px] mx-auto px-6 py-28">
            <div class="text-center">
                <div class="inline-flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">O QUE RECEBE GRÁTIS</span><span class="w-10 h-px bg-cyan"></span></div>
                <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal text-ivory">Tudo isto, <em>a partir de um link.</em></h2>
            </div>
            <div class="mt-14 rounded-2xl border border-ivory/15 bg-navy-2 overflow-hidden">
                <ul class="divide-y divide-ivory/10">
                    @foreach ($stack as [$titulo, $texto])
                        <li class="px-7 py-5 flex gap-4 items-start">
                            <span class="mt-0.5">{!! $check !!}</span>
                            <div class="flex-1 min-w-0">
                                <span class="block text-[17px] font-semibold text-ivory">{{ $titulo }}</span>
                                <span class="block mt-1 text-sm leading-relaxed text-[#C9CED6]">{{ $texto }}</span>
                            </div>
                            <span class="shrink-0 text-xs tracking-[.2em] text-cyan">INCLUÍDO</span>
                        </li>
                    @endforeach
                </ul>
                <div class="px-7 py-8 bg-ivory text-navy flex flex-wrap items-center justify-between gap-6">
                    <div>
                        <span class="block text-sm text-[#4B5563]">Valor de um vídeo: <s>20 €</s></span>
                        <span class="block font-display text-[44px] leading-none mt-1">Hoje: 0 €</span>
                        <span class="block mt-2 text-xs text-[#4B5563]">1 vídeo grátis por anúncio, com marca d’água Imocena.</span>
                    </div>
                    <a href="#pedido" class="inline-flex items-center min-h-[56px] px-8 rounded-full bg-coral text-navy text-base font-semibold hover:brightness-105">Quero o meu vídeo grátis →</a>
                </div>
            </div>
        </div>
    </section>

    {{-- O que recebe --}}
    <section id="exemplos">
        <div class="max-w-[1240px] mx-auto px-6 py-28">
            <div class="flex flex-wrap justify-between items-end gap-6">
                <div class="max-w-[640px]">
                    <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">O QUE RECEBE</span></div>
                    <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal text-ivory">Um reel com ar de <em>agência premium.</em></h2>
                </div>
                <p class="max-w-[380px] text-[15px] leading-[1.7] font-light text-[#C9CED6]">Câmara lenta, tipografia editorial e transições discretas. Menos de 60 segundos, em 9:16. <a href="{{ $demoUrl }}" class="text-cyan underline underline-offset-2">Ver o anúncio de exemplo →</a></p>
            </div>
            @if (count($frames) >= 4)
                {{-- Momentos do vídeo de demonstração, tal como foi renderizado --}}
                <div class="mt-16 grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-5">
                    @foreach ($frames as $i => $f)
                        <figure>
                            <a href="{{ $demoUrl }}" class="group relative block aspect-[9/16] overflow-hidden border border-ivory/10 rounded-[6px]">
                                <img src="{{ $frameUrl($f) }}" alt="{{ $f['label'] }} — fotograma do vídeo de demonstração aos {{ $f['time'] }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700">
                                <span class="absolute left-3 top-3 px-2 py-1 rounded-full bg-navy/70 text-[10px] tracking-[.2em] text-[#E4E6EA]">{{ $f['time'] }}</span>
                            </a>
                            <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">{{ sprintf('%02d', $i + 1) }} · {{ mb_strtoupper($f['label']) }}</figcaption>
                        </figure>
                    @endforeach
                </div>
                <p class="mt-6 text-xs text-[#AEB5C1]">Fotogramas do vídeo real da Moradia T3 em Lajeosa (imóvel de demonstração, imagens ilustrativas).</p>
            @else
            <div class="mt-16 grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-5" aria-hidden="true">
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10">
                        <div class="absolute inset-0 saturate-[.85] brightness-90" style="{{ $fotoStyle }}"></div>
                        <div class="absolute inset-0 bg-gradient-to-b from-navy/0 from-45% to-navy/95"></div>
                        <span class="absolute inset-x-4 bottom-[22px] font-display text-[30px] leading-none">Moradia T3 <em>com jardim</em></span>
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">01 · ABERTURA</figcaption>
                </figure>
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10 flex flex-col gap-2 p-[18px]">
                        <div class="flex-1 border border-cyan saturate-[.85] brightness-[.92]" style="{{ $salaStyle }}"></div>
                        <div class="flex-1 border border-cyan saturate-[.85] brightness-75" style="{{ $cozinhaStyle }}"></div>
                        <span class="pt-1.5 text-[9px] tracking-[.3em] text-cyan text-center">SALA · COZINHA</span>
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">02 · DÍPTICO</figcaption>
                </figure>
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10 px-[22px] py-7 flex flex-col justify-center gap-[18px]">
                        <span class="text-[9px] tracking-[.34em] text-cyan">FICHA DO IMÓVEL</span>
                        @foreach (['ÁREA' => '185 m²', 'CERTIFICADO' => 'B', 'LOTE' => '620 m²', 'GARAGEM' => 'Sim'] as $k => $v)
                            <div class="flex justify-between items-baseline {{ $loop->last ? '' : 'border-b border-ivory/20 pb-2.5' }}"><span class="text-[11px] font-light tracking-[.14em]">{{ $k }}</span><span class="font-display text-[26px] leading-none">{{ $v }}</span></div>
                        @endforeach
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">03 · FICHA</figcaption>
                </figure>
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10">
                        <div class="absolute inset-x-0 top-0 h-[44%] saturate-[.85] brightness-90" style="{{ $jardimStyle }}"></div>
                        <div class="absolute inset-x-0 top-[30%] h-[14%] bg-gradient-to-b from-navy/0 to-navy"></div>
                        <div class="absolute inset-x-3 top-[54%] flex flex-col items-center gap-3 text-center">
                            <span class="text-[9px] tracking-[.34em] text-cyan">PREÇO</span>
                            <span class="font-display text-[44px] leading-none">295.000 €</span>
                            <span class="w-9 h-0.5 bg-coral"></span>
                        </div>
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">04 · PREÇO</figcaption>
                </figure>
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10 flex flex-col items-center justify-center gap-3.5 p-5 text-center">
                        <div class="w-24 h-[120px] bg-[#26324A] border border-cyan"></div>
                        <span class="font-display text-[26px] leading-none">O seu nome</span>
                        <span class="text-[9px] font-light tracking-[.24em] opacity-80">CONSULTOR IMOBILIÁRIO</span>
                        <span class="px-3 py-2 border border-cyan text-[11px] tracking-[.12em]">+351 9XX XXX XXX</span>
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">05 · CONTACTO</figcaption>
                </figure>
            </div>
            @endif
        </div>
    </section>

    {{-- Sem risco --}}
    <section class="bg-ivory text-navy">
        <div class="max-w-[900px] mx-auto px-6 py-28 text-center">
            <span class="text-xs tracking-[.32em] text-cyan-deep">SEM RISCO</span>
            <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal">Veja o vídeo primeiro. <em>Pague só se gostar.</em></h2>
            <p class="mt-6 mx-auto max-w-[620px] text-base leading-[1.75] text-[#4B5563]">O primeiro vídeo é grátis, sem cartão e sem compromisso. Se gostar, a versão limpa custa 20 €, ou desde 8,75 € com um pack. Se não gostar, não paga nada. E não há subscrição: os packs valem 12 meses e só os usa quando angariar.</p>
            <a href="#pedido" class="mt-8 inline-flex items-center min-h-[52px] px-6 rounded-full bg-navy text-ivory text-[15px] font-semibold hover:bg-navy-3">Experimentar grátis →</a>
        </div>
    </section>

    {{-- Preços --}}
    <section id="precos">
        <div class="max-w-[1240px] mx-auto px-6 py-28">
            <div class="text-center max-w-[760px] mx-auto">
                <div class="inline-flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">PREÇOS</span><span class="w-10 h-px bg-cyan"></span></div>
                <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal text-ivory">O primeiro é grátis. <em>Depois, paga só os vídeos que faz.</em></h2>
            </div>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                @foreach (['IVA incluído', 'Sem subscrição', 'Packs válidos 12 meses', 'Fatura com NIF'] as $pill)
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-ivory/20 text-[13px] text-[#E4E6EA]">{!! $check !!}{{ $pill }}</span>
                @endforeach
            </div>
            <div class="mt-12 grid grid-cols-[repeat(auto-fit,minmax(210px,1fr))] gap-4 items-stretch">
                @foreach ($planos as $p)
                    @php $destaque = $p['selo'] !== null; @endphp
                    <div @class([
                        'relative px-6 py-8 rounded-2xl flex flex-col',
                        'bg-ivory text-navy border-2 border-cyan' => $destaque,
                        'bg-navy-2 text-ivory border border-ivory/10' => ! $destaque,
                    ])>
                        @if ($destaque)
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3.5 py-1 rounded-full bg-coral text-navy text-[11px] font-semibold tracking-[.16em] whitespace-nowrap">{{ $p['selo'] }}</span>
                        @endif
                        <span @class(['text-xs font-medium tracking-[.28em]', 'text-cyan-deep' => $destaque, 'text-cyan' => ! $destaque])>{{ $p['nome'] }}</span>
                        <p @class(['mt-2.5 text-sm leading-normal min-h-11', 'text-[#4B5563]' => $destaque, 'text-[#C9CED6]' => ! $destaque])>{{ $p['para'] }}</p>
                        <div class="mt-5 flex flex-col gap-1.5"><span class="font-display text-[52px] leading-none whitespace-nowrap">{{ $p['preco'] }}</span><span class="text-sm opacity-80">{{ $p['unidade'] }}</span></div>
                        <div @class(['mt-2.5 text-sm font-medium', 'text-cyan-deep' => $destaque, 'text-cyan' => ! $destaque])>{{ $p['porVideo'] }}</div>
                        <a href="{{ $p['href'] }}" @class([
                            'mt-6 flex items-center justify-center min-h-[50px] rounded-full text-[15px] font-semibold',
                            'bg-coral text-navy' => $p['coral'],
                            'bg-navy text-ivory hover:bg-navy-3' => $destaque,
                            'border border-ivory/35 text-ivory hover:bg-ivory/5' => ! $p['coral'] && ! $destaque,
                        ])>{{ $p['cta'] }}</a>
                        <ul @class(['mt-6 pt-5 border-t flex flex-col gap-2.5 text-sm', 'text-[#4B5563] border-[#D6D1C5]' => $destaque, 'text-[#C9CED6] border-ivory/15' => ! $destaque])>
                            @foreach ($p['itens'] as $item)
                                <li class="flex gap-2.5 items-start">{!! $check !!}<span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="mt-8 text-center text-sm text-[#AEB5C1]">Todos os preços incluem IVA. Equipas e agências com mais de 20 vídeos: <a href="{{ $comprar('Agência') }}" class="text-cyan underline underline-offset-2">fale connosco</a>.</p>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="bg-ivory text-navy">
        <div class="max-w-[1240px] mx-auto px-6 py-28">
            <div class="text-center max-w-[760px] mx-auto">
                <span class="text-xs tracking-[.32em] text-cyan-deep">PERGUNTAS FREQUENTES</span>
                <h2 class="mt-4 font-display text-[clamp(40px,4.6vw,56px)] leading-[1.02] font-normal">Dúvidas? <em>Temos respostas.</em></h2>
            </div>
            <div class="mt-14 grid grid-cols-1 lg:grid-cols-2 gap-3.5 items-start">
                @foreach ($faq as [$q, $a])
                    <details class="group bg-white border border-[#DDD8CC] rounded-xl">
                        <summary class="min-h-16 px-6 flex items-center justify-between gap-4 text-[15px] font-medium cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            {{ $q }}
                            <span aria-hidden="true" class="shrink-0 w-7 h-7 rounded-full border border-navy flex items-center justify-center text-lg leading-none transition-transform group-open:rotate-45">+</span>
                        </summary>
                        <p class="px-6 pb-5 text-sm leading-[1.7] text-[#4B5563]">{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Chamada final --}}
    <section class="bg-ivory">
        <div class="relative overflow-hidden rounded-t-[64px] bg-navy">
            <div class="absolute inset-0 opacity-20 grayscale-[.4]" style="{{ $fotoStyle }}"></div>
            <div class="relative max-w-[900px] mx-auto px-6 py-28 text-center">
                <h2 class="font-display text-[clamp(44px,6vw,72px)] leading-none font-normal text-ivory">Cole o link agora. <em class="text-coral">Daqui a minutos tem o vídeo.</em></h2>
                <p class="mt-6 mx-auto max-w-[560px] text-[17px] leading-[1.7] font-light text-[#C9CED6]">Um vídeo grátis por anúncio, sem cartão de crédito. Se não gostar, não perdeu nada.</p>
                <a href="#pedido" class="mt-10 inline-flex items-center min-h-[60px] px-9 rounded-full bg-coral text-navy text-[17px] font-semibold hover:brightness-105">Quero o meu vídeo grátis →</a>
            </div>
        </div>
    </section>

    {{-- Rodapé --}}
    <footer class="bg-[#0A101C] border-t border-ivory/10">
        <div class="max-w-[1240px] mx-auto px-6 pt-[72px] pb-10 flex flex-wrap gap-12">
            <div class="flex-[1.4_1_260px] min-w-0">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <p class="mt-3.5 max-w-[280px] text-sm leading-[1.7] text-[#AEB5C1]">Vídeos de imóveis para consultores portugueses, a partir do link do anúncio.</p>
            </div>
            <nav aria-label="Conta" class="flex-[1_1_160px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">CONTA</span>
                <a href="{{ route('login') }}" class="text-[#AEB5C1] hover:text-ivory">Entrar</a>
            </nav>
            <nav aria-label="Empresa" class="flex-[1_1_160px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">EMPRESA</span>
                <a href="mailto:{{ config('mail.from.address') }}" class="text-[#AEB5C1] hover:text-ivory">Contactos</a>
                <a href="#" class="text-[#AEB5C1] hover:text-ivory">Termos e condições</a>
                <a href="#" class="text-[#AEB5C1] hover:text-ivory">Política de privacidade</a>
                <a href="https://www.livroreclamacoes.pt" rel="noopener" class="text-[#AEB5C1] hover:text-ivory">Livro de Reclamações</a>
            </nav>
        </div>
        <div class="max-w-[1240px] mx-auto px-6 py-6 border-t border-ivory/10 flex flex-wrap justify-between gap-3 text-[13px] text-[#8B95A7]">
            <span>© {{ date('Y') }} Imocena</span>
            <span>Feito em Portugal</span>
        </div>
    </footer>
</div>
