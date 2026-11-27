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
        ['nome' => 'VÍDEO AVULSO', 'para' => 'Um imóvel, quando precisar.', 'preco' => '20 €', 'unidade' => '1 crédito de vídeo', 'porVideo' => '1 crédito = 1 vídeo', 'cta' => 'Comprar', 'href' => $stripe('avulso', 'Vídeo avulso (20 €)'), 'itens' => [$limpo, 'Voz em português de Portugal', 'Cartão final com AMI', 'Pronto para Reels e TikTok'], 'selo' => null, 'coral' => false],
        ['nome' => 'PACK 10', 'para' => 'Para quem angaria todas as semanas.', 'preco' => '100 €', 'unidade' => '10 créditos de vídeo', 'porVideo' => '10 € por vídeo', 'cta' => 'Comprar pack', 'href' => $stripe('pack10', 'Pack 10 (100 €)'), 'itens' => [$limpo, 'Válido 12 meses', 'Use quando angariar'], 'selo' => 'MAIS ESCOLHIDO', 'coral' => false],
        ['nome' => 'PACK 20', 'para' => 'Para carteiras grandes e equipas.', 'preco' => '175 €', 'unidade' => '20 créditos de vídeo', 'porVideo' => '8,75 € por vídeo', 'cta' => 'Comprar pack', 'href' => $stripe('pack20', 'Pack 20 (175 €)'), 'itens' => [$limpo, 'Válido 12 meses', 'O preço mais baixo por vídeo'], 'selo' => null, 'coral' => false],
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
        $porNome = isset(\App\Services\RealEstate\ConsultoresPorNome::PORTAIS[$agencia ?? '']);   // RE/MAX, iad, Century 21: find the listing by the agent's name
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
    {{--
        Passo 2 em diante, uma pergunta de cada vez (estilo Typeform): "Qual é o imóvel?" com
        quatro respostas, depois um ecrã para a resposta, depois o vídeo. O mínimo de texto:
        quem vem de um anúncio está no telemóvel e cada caixa a mais era um sítio para se perder.
    --}}
    @php
        $estado = $pedido['estado'] ?? null;
        $pct = $estado !== null ? 100 : ($jaTeve ? 100 : ($modo === '' ? 50 : 75));
        $opcao = 'w-full min-h-[64px] px-5 rounded-[16px] bg-ivory text-navy flex items-center gap-4 text-left text-[17px] font-semibold tracking-tight cursor-pointer border-2 border-transparent transition hover:border-cyan hover:-translate-y-0.5 focus:outline-none focus-visible:border-cyan focus-visible:ring-4 focus-visible:ring-cyan/30 disabled:opacity-60';
        $letra = 'shrink-0 w-8 h-8 rounded-[8px] border-[1.5px] border-[#B9B3A6] flex items-center justify-center text-[13px] font-semibold text-[#6B7280]';
        $pergunta = 'font-display text-[clamp(36px,5.4vw,52px)] leading-[1.04] font-normal text-ivory';
        $linha = 'w-full min-w-0 bg-transparent border-0 border-b-2 border-cyan px-0 py-2.5 text-[20px] font-light text-ivory placeholder:text-[#5B6578] focus:outline-none focus:ring-0';
        $botao = 'inline-flex items-center justify-center gap-2 min-h-[54px] px-7 rounded-[14px] bg-coral text-navy text-[17px] font-semibold cursor-pointer hover:brightness-105 disabled:opacity-60';
        $voltar = 'inline-flex items-center gap-2 min-h-11 text-sm text-cyan hover:text-ivory cursor-pointer';
    @endphp
    <main class="min-h-dvh flex flex-col">
        <div class="h-[3px] bg-ivory/10" aria-hidden="true"><div class="h-full bg-cyan transition-all duration-500" style="width: {{ $pct }}%"></div></div>
        @if ($convidadoPor)
            <div class="bg-coral text-navy text-center px-4 py-2 text-sm"><strong class="font-semibold">{{ $convidadoPor }} ofereceu-lhe um vídeo grátis</strong> do seu imóvel, sem cartão de crédito.</div>
        @endif
        <header class="px-6 py-5 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline">
                <span class="font-display text-[26px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
            <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 text-sm text-[#AEB5C1] hover:text-ivory">Entrar</a>
        </header>

        <div class="flex-1 flex {{ $estado === 'pronto' ? 'items-start md:items-center' : 'items-center' }}">
        <div id="pedido" class="w-full {{ $estado === 'pronto' ? 'max-w-[1100px]' : 'max-w-[560px]' }} mx-auto px-6 pb-12 scroll-mt-6" @if ($estado === 'a-fazer') wire:poll.5s @endif>

        @if ($estado === 'pronto')
            {{-- O resultado: o vídeo é a página. Dois botões e links pequenos, nada mais. --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-center gap-6 md:gap-16">
                <video src="{{ $pedido['video'] }}" controls playsinline preload="metadata" class="w-full md:w-auto md:h-[min(78vh,720px)] max-h-[72vh] md:max-h-none aspect-[9/16] mx-auto md:mx-0 rounded-[20px] bg-navy-3 border-[5px] border-navy-3 object-contain shadow-[0_30px_60px_-20px_rgba(0,0,0,.8)]"></video>
                <div class="w-full md:w-[380px] flex flex-col gap-3.5">
                    <span class="hidden md:block text-xs tracking-[.3em] text-cyan">O SEU VÍDEO ESTÁ PRONTO</span>
                    <span class="hidden md:block font-display text-[44px] leading-none text-ivory">Publique-o já.</span>
                    @if (! empty($pedido['pago']))
                        <p class="text-sm text-[#C9CED6]">{{ ! empty($pedido['oferta']) ? 'Versão sem marca d’água, oferecida pelo convite ao seu colega. Obrigado!' : 'Versão sem marca d’água: já é sua.' }}</p>
                        <a href="{{ $pedido['descarregar'] }}" class="{{ $botao }} rounded-full">↓ Descarregar MP4</a>
                    @else
                        @if (! empty($pedido['semContacto']))
                            <button type="button" wire:click="$set('pedirContacto', true)" class="{{ $botao }} rounded-full flex-col !gap-0 py-2">
                                <span>Adicionar o meu contacto</span><span class="text-xs font-normal opacity-75">e tirar a marca d’água · 10 €</span>
                            </button>
                        @else
                            <a href="{{ $stripe('primeiro', 'Versão limpa do 1.º vídeo (10 €)') }}" class="{{ $botao }} rounded-full flex-col !gap-0 py-2">
                                <span>Tirar a marca d’água · 10 €</span><span class="text-xs font-normal opacity-75">este vídeo, sem marca</span>
                            </a>
                        @endif
                        <a href="{{ $pedido['descarregar'] }}" class="inline-flex items-center justify-center gap-2 min-h-[52px] px-6 rounded-full border-[1.5px] border-ivory/35 text-ivory font-semibold whitespace-nowrap hover:bg-ivory/5">↓ Descarregar MP4 <span class="hidden md:inline font-normal text-[#AEB5C1]">com marca d’água</span></a>
                        <div class="flex flex-wrap justify-center md:justify-start gap-x-5 gap-y-2 text-[13px]">
                            <a href="{{ $stripe('pack10', 'Pack 10 (100 €)') }}" class="text-cyan underline underline-offset-2">Pack 10 · 10 €/vídeo</a>
                            @if ($pedido['convite'] ?? null)
                                <button type="button" x-data x-on:click="document.getElementById('convite').toggleAttribute('open'); document.getElementById('convite').scrollIntoView({ behavior: 'smooth', block: 'start' })" class="text-cyan underline underline-offset-2 cursor-pointer">Grátis: convidar um colega</button>
                            @endif
                            <button type="button" wire:click="outro" class="text-[#AEB5C1] underline underline-offset-2 cursor-pointer">Outro vídeo</button>
                        </div>
                    @endif
                </div>
            </div>
            @if (! empty($pedido['semContacto']) && $pedirContacto)
                {{-- O contacto para o fim do vídeo (fotos carregadas): refeito com ele, e depois o pagamento. --}}
                <div class="fixed inset-0 z-40 bg-black/50 flex items-end md:items-center justify-center" wire:key="contacto">
                    <form wire:submit="guardarContacto" class="w-full md:max-w-[440px] bg-navy-2 border-t md:border border-ivory/15 rounded-t-[24px] md:rounded-[24px] p-6 flex flex-col gap-4" novalidate>
                        <div class="flex items-start justify-between gap-4">
                            <span class="font-display text-[32px] leading-none text-ivory">O seu contacto</span>
                            <button type="button" wire:click="$set('pedirContacto', false)" class="text-[#AEB5C1] text-2xl leading-none cursor-pointer" aria-label="Fechar">×</button>
                        </div>
                        <input type="text" wire:model="nomeContacto" placeholder="Nome" autocomplete="name" maxlength="60" class="{{ $linha }} !border-ivory/25 focus:!border-cyan">
                        @error('nomeContacto') <p class="-mt-2 text-[13px] text-coral">{{ $message }}</p> @enderror
                        <input type="tel" wire:model="telefoneContacto" placeholder="Telefone" autocomplete="tel" inputmode="tel" maxlength="30" class="{{ $linha }} !border-ivory/25 focus:!border-cyan">
                        @error('telefoneContacto') <p class="-mt-2 text-[13px] text-coral">{{ $message }}</p> @enderror
                        <label class="flex items-center gap-3 text-sm text-[#C9CED6] cursor-pointer">
                            <span class="w-11 h-11 rounded-full border-[1.5px] border-dashed border-ivory/35 overflow-hidden flex items-center justify-center">
                                @if ($fotoContacto && method_exists($fotoContacto, 'temporaryUrl'))<img src="{{ $fotoContacto->temporaryUrl() }}" alt="" class="w-full h-full object-cover">@endif
                            </span>
                            Foto (opcional)
                            <input type="file" accept="image/*" wire:model="fotoContacto" class="sr-only">
                        </label>
                        <button type="submit" wire:loading.attr="disabled" wire:target="guardarContacto,fotoContacto" class="{{ $botao }} rounded-full whitespace-nowrap">Continuar para pagar · 10 €</button>
                        <p class="text-xs text-center text-[#8B95A7]">O vídeo é refeito com o seu contacto no fim, sem marca d’água.</p>
                    </form>
                </div>
            @endif
            @if ($c = $pedido['convite'] ?? null)
                <details id="convite" class="mt-10 max-w-[600px] mx-auto">
                    <summary class="sr-only">Convidar um colega</summary>
                    @include('livewire.partials.landing-convite', ['c' => $c])
                </details>
            @endif

        @elseif ($estado === 'a-fazer')
            <div role="status" class="flex flex-col gap-4">
                <span class="{{ $pergunta }}">Estamos a fazer o seu vídeo.</span>
                <div class="h-1.5 rounded-full bg-ivory/10 overflow-hidden"><div class="h-full bg-cyan transition-all duration-700" style="width: {{ $pedido['pct'] }}%"></div></div>
                <p class="text-[15px] text-[#C9CED6]"><strong class="text-ivory font-medium">{{ $pedido['etapa'] }}…</strong> Fica pronto aqui, em poucos minutos{{ ! empty($pedido['email']) ? ', e enviamo-lo para '.$pedido['email'] : '' }}.</p>
                @if (empty($pedido['email']))
                    {{-- O email pede-se aqui, não no formulário: quando já viram que funciona. --}}
                    <form wire:submit="guardarEmailVideo" class="mt-3 p-5 rounded-[18px] bg-navy-2 border border-ivory/12 flex flex-col gap-3" novalidate>
                        <label for="emailVideo" class="font-display text-[24px] leading-tight text-ivory">Quer recebê-lo também por email?</label>
                        <div class="flex flex-wrap sm:flex-nowrap gap-2">
                            <input id="emailVideo" type="email" wire:model="emailVideo" placeholder="O seu email" autocomplete="email" inputmode="email"
                                   class="flex-1 min-w-0 w-full min-h-12 px-4 rounded-full bg-navy border border-ivory/20 text-[15px] text-ivory placeholder:text-[#8B95A7] focus:outline-none focus:border-cyan focus:ring-4 focus:ring-cyan/15">
                            <button type="submit" wire:loading.attr="disabled" wire:target="guardarEmailVideo" class="w-full sm:w-auto shrink-0 min-h-12 px-6 rounded-full bg-ivory text-navy text-[15px] font-semibold cursor-pointer disabled:opacity-60">Enviar</button>
                        </div>
                        @error('emailVideo') <p class="text-[13px] text-coral">{{ $message }}</p> @enderror
                        {{-- The one box: optional, unticked opt-in to new-listing alerts and news (RGPD / Lei 41/2004). --}}
                        <label class="flex gap-2.5 items-start text-[11px] leading-snug text-[#AEB5C1]">
                            <input type="checkbox" wire:model="marketing" class="mt-px w-3.5 h-3.5 shrink-0 accent-cyan">
                            <span>{{ \App\Livewire\Landing::MARKETING }} <span class="text-[#8B95A7]">(Opcional)</span></span>
                        </label>
                    </form>
                @endif
            </div>

        @elseif ($estado === 'falhou')
            <div role="alert" class="flex flex-col gap-4">
                <span class="{{ $pergunta }}">Não conseguimos fazer este vídeo.</span>
                <p class="text-[15px] text-[#C9CED6]">Já fomos avisados. Tente outra vez daqui a pouco, ou com outro anúncio.</p>
                <button type="button" wire:click="outro" class="{{ $botao }} self-start">Tentar com outro anúncio</button>
            </div>

        @elseif ($jaTeve)
            <div class="flex flex-col gap-4">
                <span class="{{ $pergunta }}">Já fez o seu vídeo grátis.</span>
                <p class="text-[15px] text-[#C9CED6]">O vídeo grátis é um por consultor. Este faz-se na sua conta, sem marca d’água: 20 €, ou 10 € no pack de 10.</p>
                <a href="{{ route('conta.novo', ['anuncio' => $anuncio]) }}" class="{{ $botao }} self-start">Fazer na minha conta →</a>
                <button type="button" wire:click="outro" class="{{ $voltar }} self-start">← Outro anúncio</button>
            </div>

        @elseif ($modo === 'fotos')
            <button type="button" wire:click="mudarModo('')" class="{{ $voltar }}">← Voltar</button>
            <h1 class="mt-3 {{ $pergunta }}">Fotos ou vídeos do imóvel</h1>
            <form wire:submit="criarComFotos" class="mt-7 flex flex-col gap-4" novalidate>
                <label class="flex flex-col items-center justify-center gap-1.5 h-[170px] rounded-[18px] border-2 border-dashed border-ivory/30 text-[#C9CED6] cursor-pointer hover:border-cyan">
                    <span class="text-[17px] font-semibold text-ivory" wire:loading.remove wire:target="novasFotos">{{ $fotos || $videos ? 'Juntar mais' : 'Escolher fotos ou vídeos' }}</span>
                    <span class="text-[17px] font-semibold text-cyan" wire:loading wire:target="novasFotos">A carregar…</span>
                    <span class="text-sm">{{ count($fotos) }} fotos{{ $videos ? ' · '.count($videos).' '.(count($videos) === 1 ? 'vídeo' : 'vídeos') : '' }}</span>
                    <input type="file" accept="image/*,video/mp4,video/quicktime" multiple wire:model="novasFotos" class="sr-only">
                </label>
                @if ($fotos || $videos)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($videos as $i => $v)
                            <button type="button" wire:key="v-{{ $i }}" wire:click="tirarVideo({{ $i }})" class="w-[60px] h-[60px] rounded-[10px] bg-navy-2 border border-ivory/20 flex flex-col items-center justify-center text-ivory text-[11px] cursor-pointer hover:border-coral" aria-label="Tirar este vídeo">
                                <span class="text-lg leading-none">▶</span>vídeo
                            </button>
                        @endforeach
                        @foreach ($fotos as $i => $f)
                            <button type="button" wire:key="f-{{ $i }}" wire:click="tirarFoto({{ $i }})" class="relative w-[60px] h-[60px] rounded-[10px] overflow-hidden cursor-pointer group" aria-label="Tirar esta foto">
                                <img src="{{ $f->temporaryUrl() }}" alt="" class="w-full h-full object-cover">
                                <span class="absolute inset-0 bg-navy/60 hidden group-hover:flex items-center justify-center text-ivory">×</span>
                            </button>
                        @endforeach
                    </div>
                @endif
                @error('novasFotos.*') <p class="text-[13px] text-coral">{{ $message }}</p> @enderror
                @error('fotos') <p class="text-[13px] text-coral">{{ $message }}</p> @enderror
                <input type="text" wire:model="sobreFotos" placeholder="O que é e onde? Ex.: T2 em Alvalade" maxlength="300" class="{{ $linha }} !text-[17px]">
                @error('sobreFotos') <p class="-mt-2 text-[13px] text-coral">{{ $message }}</p> @enderror
                <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden"><label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label></div>
                <button type="submit" wire:loading.attr="disabled" wire:target="criarComFotos,novasFotos" class="{{ $botao }} self-start">
                    <span wire:loading.remove wire:target="criarComFotos">Criar vídeo grátis →</span><span wire:loading wire:target="criarComFotos">A enviar…</span>
                </button>
                <p class="text-[11px] text-[#8B95A7]">Ao pedir, aceita os <a href="{{ route('termos') }}" target="_blank" class="underline">termos</a> e a <a href="{{ route('privacidade') }}" target="_blank" class="underline">privacidade</a>.</p>
            </form>

        @elseif (! $lemos || $modo === 'depois')
            {{-- Ainda não lemos os anúncios desta agência, ou "Lembrar-me mais tarde": só o email. --}}
            <button type="button" wire:click="{{ $lemos ? "mudarModo('')" : 'trocar' }}" class="{{ $voltar }}">← {{ $lemos ? 'Voltar' : 'Outra agência' }}</button>
            @if ($enviado !== '')
                <div role="status" class="mt-3 flex flex-col gap-3">
                    <span class="{{ $pergunta }}">{{ $enviado === 'aviso' ? 'Fica combinado.' : 'Enviámos-lhe o link.' }}</span>
                    <p class="text-[15px] text-[#C9CED6]">{{ $enviado === 'aviso' ? 'Avisamos por email assim que lermos os anúncios '.($ag['da'] ?? '').'.' : 'Veja o seu email quando tiver o anúncio à mão.' }}</p>
                </div>
            @else
                <h1 class="mt-3 {{ $pergunta }}">{{ $lemos ? 'O seu email?' : ($nomeAg ? 'Ainda não lemos anúncios '.$ag['da'].'.' : 'Diga-nos qual é a sua agência.') }}</h1>
                <form wire:submit="lembrar" class="mt-7 flex flex-col gap-4" novalidate>
                    @if (! $lemos)
                        <label for="contacto" class="text-[15px] text-[#C9CED6]">Avisem-me quando estiver pronto:</label>
                    @endif
                    @if ($agencia === 'outra')
                        <input id="nomeAgencia" type="text" wire:model="nomeAgencia" placeholder="A sua agência" maxlength="120" class="{{ $linha }}">
                    @endif
                    <input id="contacto" type="email" wire:model="contacto" placeholder="nome@agencia.pt" autocomplete="email" inputmode="email" class="{{ $linha }}">
                    @error('contacto') <p class="-mt-2 text-[13px] text-coral">{{ $message }}</p> @enderror
                    <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden"><label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label></div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="lembrar" class="{{ $botao }} self-start">{{ $lemos ? 'Enviar' : 'Avisem-me' }} →</button>
                </form>
                @if (! $lemos)
                    <button type="button" wire:click="mudarModo('fotos')" class="mt-8 {{ $opcao }} !bg-transparent !text-ivory border-[1.5px] !border-dashed !border-ivory/30">Ou faça já o vídeo com as suas fotos →</button>
                @endif
            @endif

        @elseif ($modo === 'nome' && $porNome)
            <button type="button" wire:click="mudarModo('')" class="{{ $voltar }}">← Voltar</button>
            <h1 class="mt-3 {{ $pergunta }}">O seu nome?</h1>
            <form wire:submit="procurarConsultor" class="mt-7 flex items-end gap-3" novalidate>
                <input id="nomeConsultor" type="text" wire:model="nomeConsultor" placeholder="Como aparece na {{ $nomeAg }}" autocomplete="name" maxlength="80" class="{{ $linha }} flex-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="procurarConsultor" class="{{ $botao }} !min-h-[48px] !px-5">
                    <span wire:loading.remove wire:target="procurarConsultor">Procurar</span><span wire:loading wire:target="procurarConsultor">…</span>
                </button>
            </form>
            @error('nomeConsultor') <p class="mt-2 text-[13px] text-coral">{{ $message }}</p> @enderror
            @if ($avisoProcura === 'sem-resultados')
                <p class="mt-3 text-[13px] text-[#C9CED6]">Não encontrámos. <button type="button" wire:click="mudarModo('link')" class="text-cyan underline cursor-pointer">Colar o link</button></p>
            @elseif ($avisoProcura === 'erro')
                <p class="mt-3 text-[13px] text-coral">Não conseguimos procurar agora. <button type="button" wire:click="mudarModo('link')" class="underline cursor-pointer">Colar o link</button></p>
            @endif
            @if ($consultores !== [] && $consultorEscolhido === '')
                <div class="mt-5 flex flex-col gap-2">
                    @foreach ($consultores as $c)
                        <button type="button" wire:key="c-{{ $c['id'] }}" wire:click="escolherConsultor('{{ $c['id'] }}')" wire:loading.attr="disabled" wire:target="escolherConsultor"
                                class="w-full min-h-[56px] px-4 rounded-[14px] bg-navy-2 border border-ivory/12 text-left hover:border-cyan cursor-pointer">
                            <span class="block text-[15px] text-ivory font-medium">{{ $c['nome'] }}</span><span class="block text-[12px] text-[#8B95A7]">{{ $c['agencia'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
            <p wire:loading wire:target="escolherConsultor,escolherImovel" class="mt-3 text-[13px] text-cyan">Um momento…</p>
            @if ($consultorEscolhido !== '' && $avisoProcura === '')
                @if ($imoveis === [])
                    <p class="mt-4 text-[13px] text-[#C9CED6]">Não encontrámos imóveis seus à venda. <button type="button" wire:click="mudarModo('link')" class="text-cyan underline cursor-pointer">Colar o link</button></p>
                @else
                    <div class="mt-5 flex flex-col gap-2">
                        @foreach ($imoveis as $i)
                            <button type="button" wire:key="i-{{ $i['id'] }}" wire:click="escolherImovel('{{ $i['id'] }}')" wire:loading.attr="disabled" wire:target="escolherImovel"
                                    class="w-full flex items-center gap-3 p-2 rounded-[14px] bg-navy-2 border border-ivory/12 text-left hover:border-cyan cursor-pointer">
                                @if ($i['foto'])
                                    <img src="{{ $i['foto'] }}" alt="" loading="lazy" class="shrink-0 w-14 h-14 rounded-[10px] object-cover">
                                @else
                                    <span class="shrink-0 w-14 h-14 rounded-[10px] bg-navy"></span>
                                @endif
                                <span class="min-w-0"><span class="block text-[15px] text-ivory truncate">{{ $i['titulo'] }}</span>
                                    @if (! empty($i['preco']))<span class="block text-[12px] text-[#8B95A7]">{{ number_format((int) $i['preco'], 0, ',', ' ') }} €</span>@endif</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
            @error('anuncio') <p class="mt-3 text-[13px] text-coral">{{ $message }}</p> @enderror

        @elseif ($modo === 'link')
            <button type="button" wire:click="mudarModo('')" class="{{ $voltar }}">← Voltar</button>
            <h1 class="mt-3 {{ $pergunta }}">Cole o link</h1>
            <form wire:submit="pedir" class="mt-7 flex flex-col gap-5" novalidate
                  x-data="{ colar() { navigator.clipboard && navigator.clipboard.readText().then(t => { if (t) { $wire.set('anuncio', t.trim()); } }).catch(() => document.getElementById('anuncio').focus()); } }"
                  x-on:focar-link.window="$nextTick(() => { const i = document.getElementById('anuncio'); if (i) { i.scrollIntoView({ block: 'center', behavior: 'smooth' }); i.focus(); } })">
                @if ($imovelEscolhido !== '')
                    <p class="-mb-2 text-[13px] text-cyan">{{ $imovelEscolhido }}</p>
                @endif
                <div class="flex items-end gap-3">
                    <input id="anuncio" type="url" wire:model="anuncio" placeholder="{{ ! empty($ag['site']) ? $ag['site'].'/…' : 'https://…' }}" autocomplete="off" inputmode="url"
                           class="{{ $linha }} flex-1 @error('anuncio') !border-coral @enderror">
                    <button type="button" x-on:click="colar()" class="shrink-0 min-h-11 px-4 rounded-[10px] bg-ivory/10 text-ivory text-sm font-semibold cursor-pointer hover:bg-ivory/15">Colar</button>
                </div>
                @error('anuncio') <p class="-mt-2 text-[13px] text-coral">{{ $message }}</p> @enderror
                <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden"><label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label></div>
                <button type="submit" wire:loading.attr="disabled" wire:target="pedir" class="{{ $botao }} self-start">
                    <span wire:loading.remove wire:target="pedir">Criar vídeo grátis →</span><span wire:loading wire:target="pedir">A enviar…</span>
                </button>
                <p class="text-[11px] text-[#8B95A7]">Ao pedir, confirma que é o consultor deste anúncio e aceita os <a href="{{ route('termos') }}" target="_blank" class="underline">termos</a> e a <a href="{{ route('privacidade') }}" target="_blank" class="underline">privacidade</a>.</p>
            </form>

        @else
            {{-- "Qual é o imóvel?": quatro respostas, sem explicações. --}}
            <button type="button" wire:click="trocar" class="{{ $voltar }}">← {{ $nomeAg ?? 'Agência' }}</button>
            <h1 class="mt-3 {{ $pergunta }}">Qual é o imóvel?</h1>
            <div class="mt-7 flex flex-col gap-3">
                <button type="button" wire:click="mudarModo('link')" class="{{ $opcao }}"><span class="{{ $letra }}">A</span>Tenho o link</button>
                @if ($porNome)
                    <button type="button" wire:click="mudarModo('nome')" class="{{ $opcao }}"><span class="{{ $letra }}">B</span>Procurar pelo meu nome</button>
                @endif
                <button type="button" wire:click="mudarModo('fotos')" class="{{ $opcao }}"><span class="{{ $letra }}">{{ $porNome ? 'C' : 'B' }}</span>Carregar fotos</button>
                <button type="button" wire:click="mudarModo('depois')" class="{{ $opcao }} !bg-transparent !text-ivory border-[1.5px] !border-dashed !border-ivory/30"><span class="{{ $letra }} !border-ivory/30 !text-[#8B95A7]">{{ $porNome ? 'D' : 'C' }}</span>Lembrar-me mais tarde</button>
            </div>
            <p class="mt-6 text-[13px] text-[#8B95A7]">Grátis · sem cartão</p>
        @endif

        </div>
        </div>

        <footer class="px-6 py-5 border-t border-ivory/10 text-[11px] text-[#8B95A7] flex flex-wrap justify-center gap-x-4 gap-y-1.5">
            @php($empresa = config('contentmachine.empresa'))
            <span>{{ implode(' · ', array_filter(['Imocena', $empresa['nome'], $empresa['nif'] ? 'NIF '.$empresa['nif'] : null])) }}</span>
            <span>Ao comprar, aceita os <a href="{{ route('termos') }}" class="underline">termos</a></span>
            <a href="{{ route('privacidade') }}" class="hover:text-ivory">Privacidade</a>
            <a href="https://www.livroreclamacoes.pt" rel="noopener" class="hover:text-ivory">Livro de Reclamações</a>
            <a href="{{ route('termos') }}#reclamacoes" class="hover:text-ivory">Resolução de litígios</a>
            <a href="#" data-cookies class="hover:text-ivory">Preferências de cookies</a>
        </footer>
    </main>
    @endif
</div>
