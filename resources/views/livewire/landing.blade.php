@php
    // The closing band uses the demo listing's facade (public/media/demo/moradia-lajeosa,
    // generated, illustrative), falling back to public/images/landing/imovel.jpg, then a gradient.
    $demoSlug = 'moradia-lajeosa';
    $demoFoto = fn (string $f) => is_file(public_path("media/demo/{$demoSlug}/{$f}")) ? asset("media/demo/{$demoSlug}/{$f}")
        : (is_file(public_path('images/landing/imovel.jpg')) ? asset('images/landing/imovel.jpg') : null);
    $estilo = fn (?string $url) => $url ? "background-image:url('{$url}');background-size:cover;background-position:center" : 'background:linear-gradient(160deg,#2A3A52,#141F33)';
    $fotoStyle = $estilo($demoFoto('01-fachada.png'));
    $demoUrl = route('demo', ['slug' => $demoSlug]);
    // Stills taken from the reel at different moments: media/demo/<slug>/frames/frames.json lists
    // [{file, time, label}, …] and is written by scripts/demo-frames.sh after each render.
    $framesJson = public_path("media/demo/{$demoSlug}/frames/frames.json");
    $frames = is_file($framesJson) ? (json_decode(file_get_contents($framesJson), true) ?: []) : [];
    $frames = array_values(array_filter($frames, fn ($f) => is_file(public_path("media/demo/{$demoSlug}/frames/".($f['file'] ?? '')))));
    $frameUrl = fn (array $f) => asset("media/demo/{$demoSlug}/frames/{$f['file']}");
    $poster = $frames ? $frameUrl($frames[0]) : null;

    $check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><path d="M5 12l5 5 9-10"/></svg>';

    // Prices include VAT. Buying opens a Stripe Payment Link (config: contentmachine.stripe.links)
    // carrying the visitor's free-video lead as client_reference_id, so a single video is
    // delivered as that lead's clean render. Without a link, the button is an email.
    $comprar = fn (string $o) => 'mailto:'.config('mail.from.address').'?subject='.rawurlencode($o);
    $leadAtual = session('lead_id');
    $stripe = function (string $plano, string $assunto) use ($comprar, $leadAtual) {
        $link = (string) config("contentmachine.stripe.links.$plano");
        if ($link === '') {
            return $comprar($assunto);
        }
        $params = array_filter(['client_reference_id' => $leadAtual ? 'lead-'.$leadAtual : null, 'locale' => 'pt']);

        return $link.($params ? '?'.http_build_query($params) : '');
    };
    $limpo = 'Versão limpa, sem marca d’água';
    $planos = [
        ['nome' => 'GRÁTIS', 'para' => 'Para experimentar com um imóvel seu.', 'preco' => '0 €', 'unidade' => '1 vídeo', 'porVideo' => 'Com marca d’água', 'cta' => 'Pedir o vídeo grátis', 'href' => '#pedido', 'itens' => ['Basta colar o link do anúncio', 'Voz em português de Portugal', 'Cartão final com AMI', 'Com marca d’água Imocena'], 'selo' => null, 'coral' => true],
        ['nome' => 'VÍDEO AVULSO', 'para' => 'Um imóvel, quando precisar.', 'preco' => '20 €', 'unidade' => '1 vídeo', 'porVideo' => '20 € por vídeo', 'cta' => 'Comprar', 'href' => $stripe('avulso', 'Vídeo avulso (20 €)'), 'itens' => [$limpo, 'Voz em português de Portugal', 'Cartão final com AMI', 'Pronto para Reels e TikTok'], 'selo' => null, 'coral' => false],
        ['nome' => 'PACK 10', 'para' => 'Para quem angaria todas as semanas.', 'preco' => '100 €', 'unidade' => '10 vídeos', 'porVideo' => '10 € por vídeo', 'cta' => 'Comprar pack', 'href' => $stripe('pack10', 'Pack 10 (100 €)'), 'itens' => [$limpo, 'Válido 12 meses', 'Use quando angariar'], 'selo' => 'MAIS ESCOLHIDO', 'coral' => false],
        ['nome' => 'PACK 20', 'para' => 'Para carteiras grandes e equipas.', 'preco' => '175 €', 'unidade' => '20 vídeos', 'porVideo' => '8,75 € por vídeo', 'cta' => 'Comprar pack', 'href' => $stripe('pack20', 'Pack 20 (175 €)'), 'itens' => [$limpo, 'Válido 12 meses', 'O preço mais baixo por vídeo'], 'selo' => null, 'coral' => false],
    ];

    $faq = [
        ['O primeiro vídeo é mesmo grátis?', 'Sim. Não pedimos cartão de crédito. O vídeo grátis leva a marca d’água Imocena; a versão limpa desse vídeo custa 10 € (oferta de lançamento). Os seguintes: 20 €, ou a partir de 8,75 € com um pack.'],
        ['Quanto tempo demora?', 'Poucos minutos. Mantenha a página aberta: o vídeo aparece no mesmo sítio onde colou o link.'],
        ['A voz soa mesmo portuguesa?', 'A narração é em português de Portugal. Nos vídeos pagos pode também gravar o texto e usar a sua própria voz.'],
        ['De que sites leem os anúncios?', 'RE/MAX, iad Portugal, eXp Portugal (incluindo os sites dos consultores eXp), KW Portugal, Century 21, Predimed, Bússola Haus e My Best Houses. Basta copiar o link da barra de endereço do anúncio.'],
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
    @php
        // The page asks the agency first; after that (or with a request under way, or a link already
        // filled in from an alert email) it is the short page: that agency's name, its video, the form.
        $passo2 = $ag !== null || $pedido !== null || $jaTeve || $anuncio !== '';
        $lemos = $ag['lemos'] ?? true;
        $reelAg = \App\Livewire\Landing::demoReel($agencia, $demoSlug);
        $nomeAg = $ag && $agencia !== 'outra' ? $ag['nome'] : null;
    @endphp
    @if (! $passo2)
    {{-- Passo 1: só a pergunta. Uma agência por mosaico, com o logótipo quando o temos
         (public/images/agencias/<chave>.svg ou .png), senão o nome. Nada mais no ecrã. --}}
    <main class="min-h-dvh flex flex-col" x-data="{ mais: false }">
        <header class="px-6 py-5 flex items-center justify-between">
            <span class="flex items-baseline gap-2.5 text-ivory">
                <span class="font-display text-[26px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </span>
            <span class="flex items-center gap-6">
                <a href="{{ route('guias') }}" class="inline-flex items-center min-h-11 text-sm text-[#AEB5C1] hover:text-ivory">Guias</a>
                <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 text-sm text-[#AEB5C1] hover:text-ivory">Entrar</a>
            </span>
        </header>
        <div class="flex-1 flex items-center">
            <div id="pedido" class="w-full max-w-[880px] mx-auto px-6 pb-16">
                <h1 class="font-display text-[clamp(34px,5.4vw,56px)] leading-[1.05] font-normal text-ivory">Em que agência trabalha?</h1>
                <p class="mt-3 text-base md:text-lg font-light text-[#C9CED6]">Escolha a sua para ver o vídeo do seu imóvel com a imagem dela.</p>
                @php
                    $mosaico = 'group relative h-[104px] md:h-[124px] px-4 rounded-[16px] bg-ivory border-2 border-transparent flex items-center justify-center cursor-pointer transition hover:border-cyan hover:-translate-y-0.5 focus:outline-none focus-visible:border-cyan focus-visible:ring-4 focus-visible:ring-cyan/30 disabled:opacity-60';
                    $paginas = collect(\App\Livewire\Landing::AGENCIAS)->groupBy(fn ($a) => empty($a['mais']) ? 'principais' : 'mais', preserveKeys: true);
                @endphp
                @foreach (['principais', 'mais'] as $pagina)
                <div @if ($pagina === 'mais') x-show="mais" x-cloak @else x-show="! mais" @endif class="mt-8">
                    @if ($pagina === 'mais')
                        <button type="button" @click="mais = false" class="mb-4 inline-flex items-center gap-2 min-h-11 text-sm text-cyan hover:text-ivory cursor-pointer">← Voltar</button>
                    @endif
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach ($paginas[$pagina] as $chave => $a)
                        @php
                            $logo = collect(['svg', 'png', 'webp'])->map(fn ($x) => "images/agencias/{$chave}.{$x}")->first(fn ($p) => is_file(public_path($p)));
                            $rotulo = $a['nome'];
                        @endphp
                        <button type="button" wire:click="escolher('{{ $chave }}')" wire:loading.attr="disabled" wire:target="escolher" aria-label="{{ $rotulo }}" class="{{ $mosaico }}">
                            @if ($logo)
                                <img src="{{ asset($logo) }}" alt="{{ $rotulo }}" class="max-h-[52px] md:max-h-[60px] max-w-[78%] object-contain">
                            @else
                                <span class="text-center text-[19px] md:text-[21px] leading-tight font-semibold tracking-tight text-navy">{{ $rotulo }}</span>
                            @endif
                        </button>
                    @endforeach
                    @if ($pagina === 'principais')
                        <button type="button" @click="mais = true" aria-label="Mais agências" class="{{ $mosaico }}">
                            <span class="text-center text-[19px] md:text-[21px] leading-tight font-semibold tracking-tight text-navy">Mais agências <span aria-hidden="true">→</span></span>
                        </button>
                    @endif
                    </div>
                </div>
                @endforeach
                <p wire:loading wire:target="escolher" class="mt-5 text-sm text-cyan">A abrir…</p>
            </div>
        </div>
    </main>
    @else
    {{-- Barra de oferta --}}
    <div class="bg-coral text-navy text-center px-4 py-2.5 text-sm">
        @if ($convidadoPor)
        <strong class="font-semibold">{{ $convidadoPor }} ofereceu-lhe um vídeo grátis</strong> do seu imóvel, sem cartão de crédito.
        @else
        <strong class="font-semibold">Oferta de lançamento:</strong> 1 vídeo grátis por anúncio, sem cartão de crédito.
        @endif
        <a href="#pedido" class="font-semibold underline underline-offset-2 text-navy">Pedir agora →</a>
    </div>

    {{-- Navegação: só a marca e o pedido, sem links que tirem a pessoa da página --}}
    <header class="border-b border-ivory/10">
        <nav class="max-w-[1240px] mx-auto px-6 py-3 md:py-5 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3 text-sm">
                <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 text-[#D6D9DF] hover:text-ivory">Entrar</a>
                <a href="#pedido" class="hidden sm:inline-flex items-center min-h-11 px-5 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Vídeo grátis →</a>
            </div>
        </nav>
    </header>

    {{-- Hero: a página da agência, com o vídeo no modelo dela e o formulário --}}
    <section class="relative overflow-hidden">
        <div class="max-w-[1240px] mx-auto px-6 pt-8 pb-16 md:pt-16 md:pb-24 flex flex-wrap gap-x-14 gap-y-10 items-center">
            <div class="flex-[1_1_520px] min-w-0">
            @if ($lemos || $pedido !== null)
                {{-- Passo 2: a página da agência --}}
                <div class="flex items-center gap-3.5">
                    <span class="w-10 h-px bg-cyan"></span>
                    <span class="text-xs tracking-[.32em] text-cyan uppercase">{{ $nomeAg ? 'Consultores '.$nomeAg : 'Consultores imobiliários em Portugal' }}</span>
                </div>
                <h1 class="mt-5 font-display text-[clamp(34px,5.6vw,68px)] leading-[1] font-normal text-ivory">O vídeo do seu imóvel{{ $nomeAg ? ' '.$nomeAg : '' }}, pronto em minutos. <em class="text-coral">Grátis.</em></h1>
                <p class="mt-4 text-base leading-relaxed font-light text-[#C9CED6] md:hidden">Cole o link do seu anúncio{{ ! empty($ag['site']) ? ' em '.$ag['site'] : '' }} e receba o vídeo aqui.</p>
                <p class="hidden md:block mt-5 max-w-[540px] text-[17px] leading-relaxed font-light text-[#C9CED6]">Cole o link do seu anúncio{{ ! empty($ag['site']) ? ' em '.$ag['site'] : '' }}. Recebe um reel vertical com as fotos, narração em português de Portugal e o seu contacto no fim{{ $nomeAg ? ', com a imagem '.$ag['da'] : '' }}.</p>
                @if ($reelAg['url'] && $pedido === null)
                    <div class="mt-6 flex items-center gap-4 md:hidden" wire:key="reel-m-{{ $agencia ?: 'geral' }}">
                        <div class="relative shrink-0 w-[132px] aspect-[9/16] rounded-[18px] overflow-hidden bg-navy border-[4px] border-navy-3 shadow-[0_20px_40px_-16px_rgba(0,0,0,.8)]">
                            <video src="{{ $reelAg['url'] }}" @if ($poster && ! $reelAg['proprio']) poster="{{ $poster }}" @endif autoplay muted loop playsinline preload="metadata"
                                   class="absolute inset-0 w-full h-full object-cover bg-navy" aria-label="Vídeo de exemplo"
                                   onclick="this.muted=false;this.controls=true;this.requestFullscreen&&this.requestFullscreen()"></video>
                        </div>
                        <div class="min-w-0 flex flex-col gap-2.5 text-sm text-[#E4E6EA]">
                            <span class="text-[11px] tracking-[.22em] text-cyan uppercase">{{ $reelAg['proprio'] && $nomeAg ? 'Exemplo no modelo '.$nomeAg : 'Exemplo de vídeo' }}</span>
                            <span class="flex items-center gap-2">{!! $check !!}Pronto em minutos</span>
                            <span class="flex items-center gap-2">{!! $check !!}Voz em português de Portugal</span>
                            <span class="flex items-center gap-2">{!! $check !!}O seu contacto no fim</span>
                            <span class="text-xs text-[#8B95A7]">Toque no vídeo para ver com som</span>
                        </div>
                    </div>
                @endif
                <div id="pedido" @if (($pedido['estado'] ?? null) === 'a-fazer') wire:poll.5s @endif
                     class="mt-8 max-w-[600px] scroll-mt-6 rounded-[22px] p-[1px] bg-gradient-to-br from-cyan/60 via-ivory/10 to-coral/50 shadow-[0_40px_90px_-30px_rgba(5,184,230,.35)]">
                    <div class="rounded-[21px] bg-navy-2 px-6 py-6 sm:px-7">
                    @if (($pedido['estado'] ?? null) === 'pronto')
                        <div class="flex flex-col sm:flex-row gap-6 items-stretch sm:items-center">
                            {{-- Phones: the reel fills the card's width (it is a phone-shaped video, so that is its natural size); desktop: a thumbnail beside the text. --}}
                            <video src="{{ $pedido['video'] }}" controls playsinline preload="metadata" class="w-full sm:w-[170px] aspect-[9/16] rounded-[14px] bg-navy border border-ivory/10 object-contain"></video>
                            <div class="flex-[1_1_220px] flex flex-col gap-3">
                                <span class="text-xs tracking-[.28em] text-cyan">O SEU VÍDEO ESTÁ PRONTO</span>
                                <span class="font-display text-[34px] leading-tight text-ivory">Publique-o já.</span>
                                @if (! empty($pedido['oferta']))
                                <p class="text-sm leading-relaxed text-[#C9CED6]">Versão sem marca d’água, oferecida pelo convite ao seu colega. Obrigado!</p>
                                @elseif (! empty($pedido['pago']))
                                <p class="text-sm leading-relaxed text-[#C9CED6]">Versão sem marca d’água — já é sua. Também está na <a href="{{ route('conta') }}" class="text-cyan underline">sua conta</a>.</p>
                                @else
                                <p class="text-sm leading-relaxed text-[#C9CED6]">Esta versão leva a marca d’água Imocena. <strong class="text-ivory font-medium">Oferta de lançamento:</strong> a versão limpa deste vídeo por 10 €.</p>
                                @endif
                                <a href="{{ $pedido['descarregar'] }}" class="inline-flex items-center justify-center gap-2 min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
                                    Descarregar MP4
                                </a>
                                @if (empty($pedido['pago']))
                                {{-- Launch offer: this video clean for 10 € (the ad's price), with the pack beside it. --}}
                                <div class="flex flex-col sm:flex-row gap-2.5">
                                    <a href="{{ $stripe('primeiro', 'Versão limpa do 1.º vídeo (10 €)') }}" class="flex-1 inline-flex flex-col items-center justify-center min-h-[52px] px-5 py-2 rounded-[18px] bg-ivory text-navy font-semibold hover:brightness-95 text-center">
                                        <span>Versão limpa · 10 €</span>
                                        <span class="text-[11px] font-normal text-[#3A4456]">este vídeo, sem marca d’água</span>
                                    </a>
                                    <a href="{{ $stripe('pack10', 'Pack 10 (100 €)') }}" class="flex-1 inline-flex flex-col items-center justify-center min-h-[52px] px-5 py-2 rounded-[18px] border border-ivory/40 text-ivory font-semibold hover:bg-ivory/5 text-center">
                                        <span>Pack 10 · 10 €/vídeo</span>
                                        <span class="text-[11px] font-normal text-[#AEB5C1]">100 € · para os próximos imóveis</span>
                                    </a>
                                </div>
                                @endif
                            </div>
                        </div>
                        @if ($c = $pedido['convite'] ?? null)
                        {{-- Convide um colega: the watermark comes off when a colleague makes their free video by this link (Convites). --}}
                        <div class="mt-6 pt-6 border-t border-ivory/10 flex flex-col gap-3" wire:key="convite">
                            <span class="text-xs tracking-[.28em] text-coral">OU GRÁTIS: CONVIDE UM COLEGA</span>
                            <p class="text-sm leading-relaxed text-[#C9CED6]">Envie o seu link a um colega consultor. Quando ele fizer o vídeo grátis dele, a sua versão <strong class="text-ivory font-medium">sem marca d’água</strong> fica pronta e enviamo-la para o seu email. Ele também ganha um vídeo grátis.</p>
                            @if (! $c['ativo'])
                                <form wire:submit="ativarConvite" class="flex flex-col gap-2.5" novalidate>
                                    <div class="flex flex-col sm:flex-row gap-2.5">
                                        <input type="text" wire:model="meuNome" placeholder="O seu nome" autocomplete="name" maxlength="60"
                                               class="flex-1 min-w-0 min-h-[48px] px-4 rounded-full bg-navy border border-ivory/15 text-ivory placeholder:text-[#8B95A7] focus:border-cyan focus:outline-none">
                                        <input type="email" wire:model="meuEmail" placeholder="O seu email" autocomplete="email" inputmode="email"
                                               class="flex-1 min-w-0 min-h-[48px] px-4 rounded-full bg-navy border border-ivory/15 text-ivory placeholder:text-[#8B95A7] focus:border-cyan focus:outline-none">
                                    </div>
                                    @error('meuNome') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                                    @error('meuEmail') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                                    <button type="submit" class="self-start min-h-[48px] px-6 rounded-full bg-ivory text-navy font-semibold hover:brightness-95">Criar o meu link de convite</button>
                                    <p class="text-xs text-[#8B95A7]">O seu nome aparece no convite. O email serve só para lhe enviarmos o vídeo sem marca d’água.</p>
                                </form>
                            @else
                                <div class="flex flex-wrap gap-2.5" x-data="{ copiado: false }">
                                    <a href="{{ $c['whatsapp'] }}" target="_blank" rel="noopener" wire:click="partilhou('whatsapp')"
                                       class="inline-flex items-center justify-center gap-2 min-h-[48px] px-5 rounded-full bg-[#25D366] text-navy font-semibold hover:brightness-105">Enviar por WhatsApp</a>
                                    <button type="button" class="inline-flex items-center justify-center min-h-[48px] px-5 rounded-full border border-ivory/40 text-ivory font-semibold hover:bg-ivory/5"
                                            x-on:click="navigator.clipboard && navigator.clipboard.writeText(@js($c['link'])).then(() => { copiado = true; $wire.partilhou('copiado'); setTimeout(() => copiado = false, 2500) })"
                                            x-text="copiado ? 'Link copiado ✓' : 'Copiar o link'">Copiar o link</button>
                                </div>
                                <p class="truncate text-xs text-[#8B95A7] select-all">{{ $c['link'] }}</p>
                                <form wire:submit="convidar" class="flex flex-col sm:flex-row gap-2.5" novalidate>
                                    <input type="email" wire:model="emailColega" placeholder="Ou o email do colega: enviamos nós" inputmode="email"
                                           class="flex-1 min-w-0 min-h-[48px] px-4 rounded-full bg-navy border border-ivory/15 text-ivory placeholder:text-[#8B95A7] focus:border-cyan focus:outline-none">
                                    <button type="submit" class="min-h-[48px] px-5 rounded-full border border-cyan/60 text-cyan font-semibold hover:bg-cyan/10">Enviar convite</button>
                                </form>
                                @error('emailColega') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                                @if ($conviteEnviado !== '')
                                    <p class="text-sm text-cyan" role="status">Convite enviado para {{ $conviteEnviado }}. Pode convidar mais colegas.</p>
                                @endif
                                <p class="text-xs text-[#8B95A7]">Enviamos a versão limpa para {{ $c['email'] }} assim que um colega fizer o vídeo dele.</p>
                            @endif
                        </div>
                        @endif
                    @elseif (($pedido['estado'] ?? null) === 'a-fazer')
                        <div role="status" class="flex flex-col gap-4">
                            <span class="font-display text-[32px] leading-tight text-ivory">Estamos a fazer o seu vídeo.</span>
                            <div class="h-1.5 rounded-full bg-ivory/10 overflow-hidden"><div class="h-full bg-cyan transition-all duration-700" style="width: {{ $pedido['pct'] }}%"></div></div>
                            <p class="text-sm text-[#C9CED6]"><strong class="text-ivory font-medium">{{ $pedido['etapa'] }}…</strong> Leva poucos minutos. Mantenha esta página aberta: o vídeo aparece aqui{{ ! empty($pedido['email']) ? ' e enviamo-lo também para '.$pedido['email'] : '' }}.</p>
                        </div>
                    @elseif (($pedido['estado'] ?? null) === 'falhou')
                        <div role="alert" class="flex flex-col gap-3">
                            <span class="font-display text-[32px] leading-tight text-ivory">Não conseguimos fazer este vídeo.</span>
                            <p class="text-sm leading-relaxed text-[#C9CED6]">Tentámos duas vezes. Às vezes um anúncio não tem fotos ou dados suficientes, ou o portal não deixa lê-lo. Já fomos avisados e vamos ver o que se passou. Pode tentar outra vez com o mesmo link daqui a pouco, ou com outro anúncio.</p>
                            <button type="button" wire:click="outro" class="self-start min-h-11 px-5 rounded-full border border-ivory/40 text-ivory text-sm font-semibold hover:bg-ivory/5">Tentar com outro anúncio</button>
                        </div>
                    @elseif ($jaTeve)
                        <div class="flex flex-col gap-3">
                            <span class="text-xs tracking-[.28em] text-cyan">JÁ FEZ O SEU VÍDEO GRÁTIS</span>
                            <span class="font-display text-[32px] leading-tight text-ivory">O vídeo deste imóvel fica pronto em minutos.</span>
                            <p class="text-sm leading-relaxed text-[#C9CED6]">O vídeo grátis é um por consultor. Com um vídeo comprado, este sai sem marca d’água e pode escolher as fotos, a narração e a música: 20 €, ou 10 € por vídeo no pack de 10.</p>
                            <p class="truncate text-xs text-[#8B95A7]">{{ $anuncio }}</p>
                            <div class="flex flex-wrap gap-3">
                                <a href="{{ route('conta.novo', ['anuncio' => $anuncio]) }}" class="inline-flex items-center justify-center min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Fazer o vídeo na minha conta →</a>
                                <a href="#precos" class="inline-flex items-center justify-center min-h-[52px] px-6 rounded-full border border-ivory/40 text-ivory font-semibold hover:bg-ivory/5">Ver preços</a>
                            </div>
                            <button type="button" wire:click="outro" class="self-start text-sm text-cyan underline underline-offset-2">Não é o seu anúncio? Usar outro link</button>
                        </div>
                    @else
                        <form wire:submit="pedir" class="flex flex-col gap-4" novalidate>
                            <div class="flex flex-wrap justify-between items-center gap-3">
                                <label for="anuncio" class="font-display text-[26px] leading-tight text-ivory">Peça o seu vídeo grátis</label>
                                <span class="px-3 py-1 rounded-full bg-coral/15 border border-coral/40 text-xs text-coral">Valor <s>20 €</s> · <strong class="font-semibold">hoje 0 €</strong></span>
                            </div>
                            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 p-1.5 rounded-[18px] sm:rounded-full bg-navy border border-ivory/15 focus-within:border-cyan focus-within:ring-4 focus-within:ring-cyan/15 transition">
                                <svg class="hidden sm:block shrink-0 ml-4 text-[#8B95A7]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>
                                <input id="anuncio" type="url" wire:model="anuncio" placeholder="{{ ! empty($ag['site']) ? 'Link do anúncio em '.$ag['site'] : 'Cole aqui o link do anúncio' }}" required
                                       class="flex-1 min-w-0 w-full min-h-12 px-3 bg-transparent border-0 text-[15px] text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:ring-0">
                                <button type="submit" wire:loading.attr="disabled" wire:target="pedir"
                                        class="w-full sm:w-auto shrink-0 min-h-12 px-6 rounded-full bg-coral text-navy text-[15px] font-semibold whitespace-nowrap cursor-pointer hover:brightness-105 disabled:opacity-60">
                                    <span wire:loading.remove wire:target="pedir">Criar vídeo grátis →</span>
                                    <span wire:loading wire:target="pedir">A enviar…</span>
                                </button>
                            </div>
                            @error('anuncio') <p class="-mt-1 text-[13px] text-coral">{{ $message }}</p> @enderror
                            <div class="-mt-1">
                                <label for="emailVideo" class="sr-only">O seu email, para receber o vídeo</label>
                                <input id="emailVideo" type="email" wire:model="emailVideo" placeholder="O seu email, para lhe enviarmos o vídeo (opcional)" autocomplete="email" inputmode="email"
                                       class="w-full min-h-11 px-4 rounded-full bg-navy border border-ivory/15 text-sm text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:border-cyan focus:ring-4 focus:ring-cyan/15">
                                @error('emailVideo') <p class="mt-1.5 text-[13px] text-coral">{{ $message }}</p> @enderror
                            </div>
                            {{-- Honeypot: invisible to people, tempting to bots. --}}
                            <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden">
                                <label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label>
                            </div>
                            {{-- The one box: optional, unticked opt-in to new-listing alerts and news (RGPD / Lei 41/2004). --}}
                            <label class="flex gap-2.5 items-start text-[11px] leading-snug text-[#AEB5C1]">
                                <input type="checkbox" wire:model.live="marketing" class="mt-px w-3.5 h-3.5 shrink-0 accent-cyan">
                                <span>{{ \App\Livewire\Landing::MARKETING }} <span class="text-[#8B95A7]">(Opcional)</span></span>
                            </label>
                            @if ($marketing)
                                <div class="-mt-1 pl-6">
                                    <label for="email" class="sr-only">O seu email</label>
                                    <input id="email" type="email" wire:model="email" placeholder="O seu email (opcional: usamos o do seu anúncio)" autocomplete="email"
                                           class="w-full min-h-11 px-4 rounded-full bg-navy border border-ivory/15 text-sm text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:border-cyan focus:ring-4 focus:ring-cyan/15">
                                    @error('email') <p class="mt-1.5 text-[13px] text-coral">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <p class="text-[11px] leading-snug text-[#8B95A7]">Ao pedir, confirma que é o consultor deste anúncio e aceita os <a href="{{ route('termos') }}" target="_blank" class="text-ivory underline">termos</a> e a <a href="{{ route('privacidade') }}" target="_blank" class="text-ivory underline">política de privacidade</a>.</p>
                            <div class="pt-3 border-t border-ivory/10 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-[#C9CED6]">
                                <span class="flex items-center gap-2">{!! $check !!}Sem cartão de crédito</span>
                                <span class="flex items-center gap-2">{!! $check !!}Pronto em minutos</span>
                                <span class="text-[#8B95A7]">{{ ! empty($ag['lemos']) ? 'Vídeo com a imagem '.$ag['da'] : 'Lê anúncios da RE/MAX, iad, eXp, KW, Century 21, Predimed e mais' }}</span>
                            </div>
                        </form>
                    @endif
                    </div>
                </div>
                @if ($pedido === null && ! $jaTeve)
                    {{-- Sem o link à mão (quem vem do telemóvel): um email com esta página, para fazer depois --}}
                    <div class="mt-5 max-w-[600px]">
                        @if ($enviado === 'lembrete')
                            <p role="status" class="px-5 py-4 rounded-[14px] bg-cyan/10 border border-cyan/40 text-sm text-ivory">Enviámos-lhe o link. Veja o seu email quando estiver com o anúncio à frente.</p>
                        @else
                            <details class="group" @if ($errors->has('contacto')) open @endif>
                                <summary class="inline-flex items-center gap-2 min-h-11 text-sm font-medium text-cyan cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                                    <span class="underline underline-offset-2">Não tem o link do anúncio à mão?</span>
                                    <span aria-hidden="true" class="transition-transform group-open:rotate-90">›</span>
                                </summary>
                                <form wire:submit="lembrar" class="mt-2 flex flex-col gap-2.5" novalidate>
                                    <p class="text-sm leading-relaxed text-[#C9CED6]">Deixe o seu email: enviamos-lhe já esta página, para fazer o vídeo quando estiver com o anúncio aberto. É um email só.</p>
                                    <div class="flex flex-wrap sm:flex-nowrap gap-2">
                                        <label for="contacto" class="sr-only">Email para receber o link</label>
                                        <input id="contacto" type="email" wire:model="contacto" placeholder="Escreva o seu email" autocomplete="email" inputmode="email"
                                               class="flex-1 min-w-0 w-full min-h-12 px-4 rounded-full bg-navy border border-ivory/15 text-[15px] text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:border-cyan focus:ring-4 focus:ring-cyan/15">
                                        <button type="submit" wire:loading.attr="disabled" wire:target="lembrar" class="w-full sm:w-auto shrink-0 min-h-12 px-5 rounded-full border border-ivory/40 text-ivory text-[15px] font-semibold whitespace-nowrap cursor-pointer hover:bg-ivory/5 disabled:opacity-60">Enviar-me o link</button>
                                    </div>
                                    @error('contacto') <p class="text-[13px] text-coral">{{ $message }}</p> @enderror
                                </form>
                            </details>
                        @endif
                        @if ($ag !== null)
                            <button type="button" wire:click="trocar" class="mt-1 min-h-11 text-sm text-[#AEB5C1] underline underline-offset-2 cursor-pointer hover:text-ivory">Não é {{ $ag['da'] }}? Trocar de agência</button>
                        @endif
                    </div>
                @endif
            @else
                {{-- Passo 2, agência que ainda não lemos: o email, para avisar quando lermos --}}
                <div class="flex items-center gap-3.5">
                    <span class="w-10 h-px bg-cyan"></span>
                    <span class="text-xs tracking-[.32em] text-cyan uppercase">{{ $nomeAg ? 'Consultores '.$nomeAg : 'A sua agência' }}</span>
                </div>
                <h1 class="mt-5 font-display text-[clamp(34px,5.6vw,68px)] leading-[1] font-normal text-ivory">{{ $nomeAg ? 'Ainda não lemos anúncios '.$ag['da'].'.' : 'Diga-nos qual é a sua agência.' }} <em class="text-coral">{{ $nomeAg ? 'Falta pouco.' : 'Juntamo-la.' }}</em></h1>
                <p class="mt-5 max-w-[540px] text-[17px] leading-relaxed font-light text-[#C9CED6]">{{ $nomeAg ? 'Estamos a juntar a '.$nomeAg.'.' : 'Hoje lemos anúncios da RE/MAX, iad, eXp, KW, Century 21, Predimed, Bússola Haus e My Best Houses, e estamos a juntar mais agências.' }} Deixe o seu email e avisamos no dia em que puder fazer o seu vídeo grátis. Veja nesta página um exemplo do que vai receber.</p>
                <div id="pedido" class="mt-8 max-w-[600px] scroll-mt-6 rounded-[22px] p-[1px] bg-gradient-to-br from-cyan/60 via-ivory/10 to-coral/50 shadow-[0_40px_90px_-30px_rgba(5,184,230,.35)]">
                    <div class="rounded-[21px] bg-navy-2 px-6 py-6 sm:px-7">
                        @if ($enviado === 'aviso')
                            <div role="status" class="flex flex-col gap-2">
                                <span class="font-display text-[30px] leading-tight text-ivory">Fica combinado.</span>
                                <p class="text-sm leading-relaxed text-[#C9CED6]">Avisamos por email assim que lermos os anúncios {{ $ag['da'] }}. Não usamos o seu email para mais nada.</p>
                            </div>
                        @else
                            <form wire:submit="lembrar" class="flex flex-col gap-3.5" novalidate>
                                <label for="contacto" class="font-display text-[26px] leading-tight text-ivory">Avisem-me quando estiver pronto</label>
                                @if ($agencia === 'outra')
                                    <label for="nomeAgencia" class="sr-only">A sua agência</label>
                                    <input id="nomeAgencia" type="text" wire:model="nomeAgencia" placeholder="A sua agência (por exemplo, Decisões e Soluções)" maxlength="120"
                                           class="w-full min-h-12 px-4 rounded-full bg-navy border border-ivory/15 text-[15px] text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:border-cyan focus:ring-4 focus:ring-cyan/15">
                                @endif
                                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 p-1.5 rounded-[18px] sm:rounded-full bg-navy border border-ivory/15 focus-within:border-cyan focus-within:ring-4 focus-within:ring-cyan/15 transition">
                                    <input id="contacto" type="email" wire:model="contacto" placeholder="O seu email" autocomplete="email" inputmode="email" required
                                           class="flex-1 min-w-0 w-full min-h-12 px-3 sm:pl-5 bg-transparent border-0 text-[15px] text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:ring-0">
                                    <button type="submit" wire:loading.attr="disabled" wire:target="lembrar" class="w-full sm:w-auto shrink-0 min-h-12 px-6 rounded-full bg-coral text-navy text-[15px] font-semibold whitespace-nowrap cursor-pointer hover:brightness-105 disabled:opacity-60">Avisem-me →</button>
                                </div>
                                @error('contacto') <p class="-mt-1 text-[13px] text-coral">{{ $message }}</p> @enderror
                                <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden">
                                    <label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label>
                                </div>
                                <p class="text-[11px] leading-snug text-[#8B95A7]">Usamos o seu email só para este aviso, de acordo com a <a href="{{ route('privacidade') }}" target="_blank" class="text-ivory underline">política de privacidade</a>.</p>
                            </form>
                        @endif
                    </div>
                </div>
                <button type="button" wire:click="trocar" class="mt-4 min-h-11 text-sm text-cyan underline underline-offset-2 cursor-pointer">Tem anúncios na RE/MAX, iad, eXp, KW, Century 21, Predimed, Bússola Haus ou My Best Houses? Escolher outra agência</button>
            @endif
            </div>

            {{-- O vídeo: no modelo da agência quando o temos, senão o de demonstração --}}
            @if ($reelAg['url'])
                <div @class(['flex-[1_1_320px] min-w-0 flex-col items-center', 'flex' => ! ($ag !== null && $lemos && $pedido === null), 'hidden md:flex' => $ag !== null && $lemos && $pedido === null]) wire:key="reel-{{ $agencia ?: 'geral' }}">
                    <div class="relative w-full max-w-[320px] aspect-[9/19.5] rounded-[44px] overflow-hidden bg-navy border-[7px] border-navy-3 shadow-[0_40px_90px_-20px_rgba(0,0,0,.8)]">
                        <span class="absolute left-1/2 top-3 -translate-x-1/2 w-[88px] h-[25px] rounded-full bg-navy-3 z-10"></span>
                        <video src="{{ $reelAg['url'] }}" @if ($poster && ! $reelAg['proprio']) poster="{{ $poster }}" @endif autoplay muted loop playsinline controls preload="metadata"
                               class="absolute inset-0 w-full h-full object-contain bg-navy" aria-label="Vídeo de exemplo: Moradia T3 em Lajeosa, Viseu"></video>
                    </div>
                    <p class="mt-4 text-center text-[13px] text-[#C9CED6]">
                        {{ $reelAg['proprio'] && $nomeAg ? 'Exemplo no modelo '.$nomeAg : 'Exemplo feito pela Imocena em 7 minutos' }} ·
                        <a href="{{ $demoUrl }}" class="text-cyan underline underline-offset-2">ver o anúncio</a>
                    </p>
                </div>
            @endif
        </div>
    </section>

    {{-- Funciona com --}}
    <section class="border-y border-ivory/10">
        <div class="max-w-[1240px] mx-auto px-6 py-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-[13px] text-[#AEB5C1]">
            <span class="tracking-[.24em]">LÊ ANÚNCIOS DE</span>
            @foreach (\App\Services\RealEstate\ListingScraper::NOMES as $nome)
                <span class="text-lg font-semibold text-[#E4E6EA]">{{ $nome }}</span>
            @endforeach
        </div>
    </section>

    {{-- Como funciona --}}
    <section id="como" class="bg-ivory text-navy">
        <div class="max-w-[1240px] mx-auto px-6 py-28">
            <div class="text-center max-w-[760px] mx-auto">
                <span class="text-xs tracking-[.32em] text-cyan-deep">COMO FUNCIONA</span>
                <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal">Três passos. <em>Só um é seu.</em></h2>
                <p class="mt-5 text-[15px] text-[#4B5563]">Quer ver primeiro? <a href="{{ $demoUrl }}" class="text-cyan-deep underline underline-offset-2">Veja um anúncio de exemplo e o vídeo que fizemos dele</a>.</p>
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
            <p class="mt-8 text-center text-sm text-[#AEB5C1]">Todos os preços incluem IVA. Ao comprar, aceita os <a href="{{ route('termos') }}" class="text-cyan underline underline-offset-2">termos e condições</a> e pede que cada vídeo seja feito logo que o pedir. Equipas e agências com mais de 20 vídeos: <a href="{{ $comprar('Agência') }}" class="text-cyan underline underline-offset-2">fale connosco</a>.</p>
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
            <nav aria-label="Guias" class="flex-[1_1_160px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">GUIAS</span>
                @foreach (array_slice(app(\App\Services\Guias\Guias::class)->todos(), 0, 4) as $g)
                    <a href="{{ route('guias.ver', $g['slug']) }}" class="text-[#AEB5C1] hover:text-ivory">{{ $g['titulo'] }}</a>
                @endforeach
                <a href="{{ route('guias') }}" class="text-ivory hover:text-cyan">Todos os guias →</a>
            </nav>
            <nav aria-label="Empresa" class="flex-[1_1_160px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">EMPRESA</span>
                <a href="mailto:{{ config('mail.from.address') }}" class="text-[#AEB5C1] hover:text-ivory">Contactos</a>
                <a href="{{ route('termos') }}" class="text-[#AEB5C1] hover:text-ivory">Termos e condições</a>
                <a href="{{ route('privacidade') }}" class="text-[#AEB5C1] hover:text-ivory">Política de privacidade</a>
                <a href="#" data-cookies class="text-[#AEB5C1] hover:text-ivory">Preferências de cookies</a>
                <a href="https://www.livroreclamacoes.pt" rel="noopener" class="text-[#AEB5C1] hover:text-ivory">Livro de Reclamações</a>
                <a href="{{ route('termos') }}#reclamacoes" class="text-[#AEB5C1] hover:text-ivory">Resolução de litígios</a>
            </nav>
        </div>
        <div class="max-w-[1240px] mx-auto px-6 py-6 border-t border-ivory/10 flex flex-wrap justify-between gap-3 text-[13px] text-[#8B95A7]">
            @php($empresa = config('contentmachine.empresa'))
            <span>© {{ date('Y') }} {{ implode(' · ', array_filter(['Imocena', $empresa['nome'], $empresa['nif'] ? 'NIF '.$empresa['nif'] : null])) }}</span>
            <span>Feito em Portugal</span>
        </div>
    </footer>
    @endif
</div>
