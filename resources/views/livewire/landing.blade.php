@php
    // A property photo for the reel mock-ups: drop one at public/images/landing/imovel.jpg.
    $foto = is_file(public_path('images/landing/imovel.jpg')) ? asset('images/landing/imovel.jpg') : null;
    $fotoStyle = $foto ? "background-image:url('{$foto}');background-size:cover;background-position:center" : 'background:linear-gradient(160deg,#2A3A52,#141F33)';

    $check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><path d="M5 12l5 5 9-10"/></svg>';

    // Prices are placeholders until set: [PREÇO] / [N].
    $planos = [
        ['nome' => 'GRÁTIS', 'para' => 'Para experimentar com um imóvel seu.', 'qtd' => '1 vídeo', 'unidade' => 'oferta', 'preco' => '0 €', 'cta' => 'Pedir o vídeo grátis', 'href' => '#pedido', 'nota' => 'Sem cartão de crédito', 'inclui' => 'Inclui:', 'itens' => ['Estilo premium', 'Voz em português de Portugal', 'Cartão final com AMI', 'Sem marca d’água'], 'destaque' => false, 'coral' => true],
        ['nome' => 'CONSULTOR', 'para' => 'Para quem angaria alguns imóveis por mês.', 'qtd' => '[N] vídeos', 'unidade' => '/mês', 'preco' => '[PREÇO] €/mês', 'cta' => 'Começar', 'href' => 'mailto:'.config('mail.from.address'), 'nota' => 'Cancela quando quiser', 'inclui' => 'Tudo no Grátis, mais:', 'itens' => ['Todos os estilos', 'A sua própria voz', 'Legenda e hashtags', 'Vídeo da visita guiada'], 'destaque' => false, 'coral' => false],
        ['nome' => 'PROFISSIONAL', 'para' => 'Para consultores com carteira grande.', 'qtd' => '[N] vídeos', 'unidade' => '/mês', 'preco' => '[PREÇO] €/mês', 'cta' => 'Começar', 'href' => 'mailto:'.config('mail.from.address'), 'nota' => 'Cancela quando quiser', 'inclui' => 'Tudo no Consultor, mais:', 'itens' => ['Cores e logótipo da agência', 'Processamento prioritário', 'Vídeos extra a [PREÇO] €'], 'destaque' => true, 'coral' => false],
        ['nome' => 'AGÊNCIA', 'para' => 'Para lojas e equipas da mesma rede.', 'qtd' => 'À medida', 'unidade' => '', 'preco' => 'Volume por loja', 'cta' => 'Falar connosco', 'href' => 'mailto:'.config('mail.from.address'), 'nota' => 'Resposta em 1 dia útil', 'inclui' => 'Tudo no Profissional, mais:', 'itens' => ['Vários consultores numa conta', 'Modelo com a marca da loja', 'Fatura única'], 'destaque' => false, 'coral' => false],
    ];

    $faq = [
        ['O primeiro vídeo é mesmo grátis?', 'Sim. Não pedimos cartão de crédito e o vídeo é seu, sem marca d’água, mesmo que não continue.'],
        ['Como recebo o vídeo?', 'Por email, com um link para ver e descarregar o MP4 vertical, pronto a publicar.'],
        ['De que sites leem os anúncios?', 'RE/MAX, iad e idealista. Basta copiar o link da barra de endereço do anúncio.'],
        ['A voz soa mesmo portuguesa?', 'A narração é em português de Portugal. Na sua conta pode também gravar o texto e usar a sua própria voz.'],
        ['Posso alterar o texto da narração?', 'Na sua conta, sim: pode deixar uma nota (preço novo, algo a destacar) e o texto muda antes de ser gravado.'],
        ['Que dados aparecem no fim do vídeo?', 'O nome, o telemóvel, a agência e o número AMI que constam do anúncio.'],
        ['O que fazem com os meus dados?', 'Usamo-los só para criar e enviar o seu vídeo, de acordo com o RGPD. Pode pedir que os apaguemos a qualquer momento.'],
        ['Posso pedir mais do que um vídeo grátis?', 'A oferta é de um vídeo por consultor. Para mais vídeos, crie uma conta e escolha um plano.'],
    ];
@endphp

<div>
    {{-- Barra de oferta --}}
    <div class="bg-coral text-navy text-center px-4 py-2.5 text-sm">
        <strong class="font-semibold">Oferta de lançamento:</strong> o vídeo do seu próximo imóvel é por nossa conta.
        <a href="#pedido" class="font-semibold underline underline-offset-2 text-navy">Pedir agora →</a>
    </div>

    {{-- Navegação --}}
    <header class="border-b border-ivory/10">
        <nav class="max-w-[1240px] mx-auto px-6 py-5 flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
            <div class="flex flex-wrap items-center gap-x-8 gap-y-3 text-sm">
                <a href="#como" class="hidden md:inline text-[#D6D9DF] hover:text-ivory">Como funciona</a>
                <a href="#exemplos" class="hidden md:inline text-[#D6D9DF] hover:text-ivory">Exemplos</a>
                <a href="#precos" class="hidden md:inline text-[#D6D9DF] hover:text-ivory">Preços</a>
                <a href="#faq" class="hidden md:inline text-[#D6D9DF] hover:text-ivory">Perguntas</a>
                <a href="{{ route('login') }}" class="text-ivory font-medium">Entrar</a>
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
                    <span class="text-xs tracking-[.32em] text-cyan">PARA CONSULTORES IMOBILIÁRIOS</span>
                </div>
                <h1 class="mt-6 font-display text-[clamp(52px,7vw,84px)] leading-[.98] font-normal text-ivory">O vídeo do seu imóvel, <em class="text-coral">grátis.</em></h1>
                <p class="mt-7 max-w-[540px] text-lg leading-relaxed font-light text-[#C9CED6]">Envie-nos o link do anúncio. Devolvemos um reel vertical com narração em português de Portugal, as fotos do imóvel e o seu contacto no fim, pronto a publicar no Instagram e no TikTok.</p>
                <ul class="mt-8 flex flex-wrap gap-x-7 gap-y-3 text-sm text-[#E4E6EA]">
                    <li class="flex items-center gap-2.5">{!! $check !!}Sem cartão de crédito</li>
                    <li class="flex items-center gap-2.5">{!! $check !!}Voz pt-PT, não brasileira</li>
                    <li class="flex items-center gap-2.5">{!! $check !!}O vídeo é seu, sem marca d'água</li>
                </ul>

                <div id="pedido" class="mt-10 max-w-[580px] scroll-mt-6 rounded-[18px] bg-ivory text-navy p-7 shadow-[0_30px_80px_-30px_rgba(0,0,0,.6)]">
                    @if ($pedido)
                        <div role="status" class="flex flex-col gap-3">
                            <span class="font-display text-[32px] font-semibold leading-tight">Pedido recebido.</span>
                            <p class="text-[15px] leading-relaxed text-ink-soft">Já estamos a ler o anúncio e a preparar o seu vídeo. Enviamos o link para <strong class="text-navy">{{ $email ?: 'o seu email' }}</strong> assim que estiver pronto.</p>
                            <p class="text-sm text-ink-soft">Não recebeu? Veja a pasta de spam ou promoções.</p>
                        </div>
                    @else
                        <form wire:submit="pedir" class="flex flex-col gap-3.5" novalidate>
                            <div class="flex flex-wrap justify-between items-baseline gap-3">
                                <span class="font-display text-[28px] font-semibold">Peça o seu vídeo grátis</span>
                                <span class="text-xs font-medium text-ink-soft">Leva 1 minuto</span>
                            </div>
                            <label class="flex flex-col gap-1.5 text-[13px] font-medium">Link do anúncio
                                <input type="url" wire:model="anuncio" placeholder="https://www.remax.pt/pt/imoveis/…" required
                                       class="min-h-[50px] px-4 rounded-[10px] border border-[#C8C3B8] bg-white text-[15px] font-normal text-navy focus:outline-none focus:border-cyan-deep focus:ring-2 focus:ring-cyan/30">
                            </label>
                            @error('anuncio') <p class="-mt-1.5 text-[13px] text-bad">{{ $message }}</p> @enderror
                            <div class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-3">
                                <label class="flex flex-col gap-1.5 text-[13px] font-medium">Nome
                                    <input type="text" wire:model="nome" autocomplete="name" required class="min-h-[50px] px-4 rounded-[10px] border border-[#C8C3B8] bg-white text-[15px] font-normal text-navy focus:outline-none focus:border-cyan-deep focus:ring-2 focus:ring-cyan/30">
                                </label>
                                <label class="flex flex-col gap-1.5 text-[13px] font-medium">Email
                                    <input type="email" wire:model="email" autocomplete="email" required class="min-h-[50px] px-4 rounded-[10px] border border-[#C8C3B8] bg-white text-[15px] font-normal text-navy focus:outline-none focus:border-cyan-deep focus:ring-2 focus:ring-cyan/30">
                                </label>
                                <label class="flex flex-col gap-1.5 text-[13px] font-medium">Telemóvel
                                    <input type="tel" wire:model="telefone" autocomplete="tel" placeholder="9XX XXX XXX" required class="min-h-[50px] px-4 rounded-[10px] border border-[#C8C3B8] bg-white text-[15px] font-normal text-navy focus:outline-none focus:border-cyan-deep focus:ring-2 focus:ring-cyan/30">
                                </label>
                            </div>
                            @error('nome') <p class="-mt-1.5 text-[13px] text-bad">{{ $message }}</p> @enderror
                            @error('email') <p class="-mt-1.5 text-[13px] text-bad">{{ $message }}</p> @enderror
                            @error('telefone') <p class="-mt-1.5 text-[13px] text-bad">{{ $message }}</p> @enderror
                            {{-- Honeypot: invisible to people, tempting to bots. --}}
                            <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden">
                                <label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label>
                            </div>
                            <label class="flex gap-2.5 items-start text-xs leading-normal text-ink-soft">
                                <input type="checkbox" wire:model="consentimento" class="mt-0.5 w-[18px] h-[18px] shrink-0 accent-navy">
                                <span>Aceito que a Imocena use estes dados para criar e enviar o vídeo, de acordo com a <a href="#" class="text-navy underline">Política de Privacidade</a> (RGPD).</span>
                            </label>
                            @error('consentimento') <p class="-mt-1.5 text-[13px] text-bad">{{ $message }}</p> @enderror
                            <button type="submit" wire:loading.attr="disabled" wire:target="pedir"
                                    class="min-h-14 rounded-full bg-navy text-ivory text-base font-semibold cursor-pointer hover:bg-navy-3 disabled:opacity-60">
                                <span wire:loading.remove wire:target="pedir">Quero o meu vídeo grátis →</span>
                                <span wire:loading wire:target="pedir">A enviar…</span>
                            </button>
                            <p class="text-xs text-ink-soft text-center">Recebe o vídeo por email. Lemos anúncios da RE/MAX, iad e idealista.</p>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Leque de reels --}}
            <div class="flex-[1_1_480px] min-w-0 relative h-[700px] hidden md:block" aria-hidden="true">
                <div class="absolute left-0 top-[90px] w-[236px] h-[420px] rounded-[26px] overflow-hidden bg-navy border-[6px] border-navy-3 -rotate-[7deg] shadow-[0_30px_60px_-20px_rgba(0,0,0,.7)]">
                    <div class="absolute inset-x-0 top-0 h-[190px] saturate-[.85] brightness-90" style="{{ $fotoStyle }}"></div>
                    <div class="absolute inset-x-0 top-[140px] h-[50px] bg-gradient-to-b from-navy/0 to-navy"></div>
                    <div class="absolute inset-x-4 top-[230px] text-center flex flex-col items-center gap-2.5">
                        <span class="text-[8px] tracking-[.34em] text-cyan">PREÇO</span>
                        <span class="font-display text-[44px] leading-none">167.000 €</span>
                        <span class="w-8 h-0.5 bg-coral"></span>
                        <span class="text-[8px] font-light tracking-[.2em]">PRONTO A UTILIZAR</span>
                    </div>
                </div>
                <div class="absolute right-0 top-[110px] w-[236px] h-[420px] rounded-[26px] overflow-hidden bg-navy border-[6px] border-navy-3 rotate-[7deg] shadow-[0_30px_60px_-20px_rgba(0,0,0,.7)] flex flex-col items-center px-[18px] pt-14 pb-6 gap-3.5 text-center">
                    <div class="w-[116px] h-[146px] bg-[#26324A] border border-cyan"></div>
                    <span class="text-[7px] tracking-[.34em] text-cyan">VISITAS POR MARCAÇÃO</span>
                    <span class="font-display text-[26px] leading-none">O seu nome</span>
                    <span class="px-3 py-1.5 border border-cyan text-[10px] tracking-[.14em]">+351 9XX XXX XXX</span>
                    <span class="mt-auto text-[8px] tracking-[.3em] opacity-70">AMI 00000</span>
                </div>
                <div class="absolute left-1/2 top-0 -ml-[150px] w-[300px] h-[534px] rounded-[32px] overflow-hidden bg-navy border-[7px] border-navy-3 shadow-[0_40px_90px_-20px_rgba(0,0,0,.8)]">
                    <div class="absolute inset-0 scale-[1.04] saturate-[.85] brightness-[.92]" style="{{ $fotoStyle }}"></div>
                    <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(14,22,38,.55)_0%,rgba(14,22,38,0)_28%,rgba(14,22,38,0)_46%,rgba(14,22,38,.94)_100%)]"></div>
                    <div class="absolute top-[22px] inset-x-5 flex justify-between text-[7px] tracking-[.32em]"><span class="font-medium">A SUA AGÊNCIA</span><span class="font-light">REF. 171469</span></div>
                    <span class="absolute top-[46px] left-5 px-2.5 py-1 bg-coral text-navy text-[8px] font-semibold tracking-[.24em]">EXCLUSIVO</span>
                    <div class="absolute inset-x-5 bottom-12 flex flex-col gap-2.5">
                        <div class="flex items-center gap-2"><span class="w-[22px] h-px bg-cyan"></span><span class="text-[8px] tracking-[.3em] text-cyan">CALDAS DA RAINHA · CENTRO</span></div>
                        <span class="font-display text-[34px] leading-[1.02]">Escritório<br><em>remodelado</em> no centro</span>
                        <span class="text-[8px] font-light tracking-[.18em] opacity-85">50 M² · CLASSE B- · ELEVADOR</span>
                    </div>
                    <div class="absolute inset-x-5 bottom-[22px] h-0.5 bg-ivory/25"><div class="w-[38%] h-full bg-cyan"></div></div>
                </div>
                <div class="absolute left-1/2 bottom-[60px] -translate-x-1/2 flex items-center gap-3 px-[18px] py-3 rounded-full bg-navy-3 border border-cyan/35 whitespace-nowrap">
                    <span class="w-2.5 h-2.5 rounded-full bg-cyan"></span>
                    <span class="text-[13px] text-[#E4E6EA]">Narração: <em class="font-display text-base">«Em pleno centro das Caldas…»</em></span>
                </div>
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

    {{-- Como funciona --}}
    <section id="como" class="bg-ivory text-navy">
        <div class="max-w-[1240px] mx-auto px-6 py-28">
            <div class="text-center max-w-[760px] mx-auto">
                <span class="text-xs tracking-[.32em] text-cyan-deep">COMO FUNCIONA</span>
                <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal">Do anúncio ao reel, <em>sem editar nada.</em></h2>
            </div>
            <div class="mt-[72px] grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-8">
                @foreach ([
                    ['01', 'Cole o link', 'Lemos o anúncio: fotos, preço, tipologia, área, certificado energético e os seus dados de contacto.'],
                    ['02', 'Escrevemos e narramos', 'Um texto curto, como um consultor o diria, gravado em português de Portugal.'],
                    ['03', 'Publique', 'Recebe o MP4 vertical. Publique no Instagram, no TikTok ou envie por WhatsApp ao proprietário.'],
                ] as [$n, $titulo, $texto])
                    <div class="px-8 py-9 bg-white rounded border-t-[3px] border-coral">
                        <span class="font-display text-[72px] leading-[.8]">{{ $n }}</span>
                        <h3 class="mt-7 mb-2.5 font-sans text-xl font-semibold tracking-normal">{{ $titulo }}</h3>
                        <p class="text-[15px] leading-[1.7] text-ink-soft">{{ $texto }}</p>
                    </div>
                @endforeach
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
                <p class="max-w-[380px] text-[15px] leading-[1.7] font-light text-[#C9CED6]">Câmara lenta, tipografia editorial e transições discretas. Menos de 60 segundos, em 9:16.</p>
            </div>
            <div class="mt-16 grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-5" aria-hidden="true">
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10">
                        <div class="absolute inset-0 saturate-[.85] brightness-90" style="{{ $fotoStyle }}"></div>
                        <div class="absolute inset-0 bg-gradient-to-b from-navy/0 from-45% to-navy/95"></div>
                        <span class="absolute inset-x-4 bottom-[22px] font-display text-[30px] leading-none">Escritório <em>remodelado</em></span>
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">01 · ABERTURA</figcaption>
                </figure>
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10 flex flex-col gap-2 p-[18px]">
                        <div class="flex-1 border border-cyan saturate-[.85] brightness-[.92]" style="{{ $fotoStyle }}"></div>
                        <div class="flex-1 border border-cyan saturate-[.85] brightness-75" style="{{ $fotoStyle }}"></div>
                        <span class="pt-1.5 text-[9px] tracking-[.3em] text-cyan text-center">LUZ NATURAL · DUAS FRENTES</span>
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">02 · DÍPTICO</figcaption>
                </figure>
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10 px-[22px] py-7 flex flex-col justify-center gap-[18px]">
                        <span class="text-[9px] tracking-[.34em] text-cyan">FICHA DO IMÓVEL</span>
                        @foreach (['ÁREA' => '50 m²', 'CERTIFICADO' => 'B-', 'PISO' => '2.º', 'ELEVADOR' => 'Sim'] as $k => $v)
                            <div class="flex justify-between items-baseline {{ $loop->last ? '' : 'border-b border-ivory/20 pb-2.5' }}"><span class="text-[11px] font-light tracking-[.14em]">{{ $k }}</span><span class="font-display text-[26px] leading-none">{{ $v }}</span></div>
                        @endforeach
                    </div>
                    <figcaption class="mt-3 text-xs tracking-[.24em] text-[#AEB5C1]">03 · FICHA</figcaption>
                </figure>
                <figure>
                    <div class="relative aspect-[9/16] overflow-hidden border border-ivory/10">
                        <div class="absolute inset-x-0 top-0 h-[44%] saturate-[.85] brightness-90" style="{{ $fotoStyle }}"></div>
                        <div class="absolute inset-x-0 top-[30%] h-[14%] bg-gradient-to-b from-navy/0 to-navy"></div>
                        <div class="absolute inset-x-3 top-[54%] flex flex-col items-center gap-3 text-center">
                            <span class="text-[9px] tracking-[.34em] text-cyan">PREÇO</span>
                            <span class="font-display text-[44px] leading-none">167.000 €</span>
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
        </div>
    </section>

    {{-- Feito para Portugal --}}
    <section class="bg-ivory text-navy">
        <div class="max-w-[1240px] mx-auto px-6 py-28 flex flex-wrap gap-16">
            <div class="flex-[1_1_360px] min-w-0">
                <span class="text-xs tracking-[.32em] text-cyan-deep">FEITO PARA PORTUGAL</span>
                <h2 class="mt-4 font-display text-[clamp(40px,4.6vw,56px)] leading-[1.02] font-normal">Fala como os seus clientes. <em>Não como uma novela.</em></h2>
                <p class="mt-6 text-base leading-[1.75] text-ink-soft">Cada detalhe foi pensado para o mercado português: da forma como se diz o preço ao número AMI no cartão final.</p>
                <a href="#pedido" class="mt-8 inline-flex items-center min-h-[52px] px-6 rounded-full bg-navy text-ivory text-[15px] font-semibold hover:bg-navy-3">Experimentar grátis →</a>
            </div>
            <div class="flex-[1.4_1_560px] min-w-0 grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-px bg-[#D6D1C5] border border-[#D6D1C5]">
                @foreach ([
                    ['Voz em português de Portugal', 'Diz «T3», «m²» e «duzentos e noventa e cinco mil euros» como se diz cá.', '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>'],
                    ['AMI e agência no cartão final', 'Nome, telemóvel, agência e número de licença AMI no fecho de cada vídeo.', '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4M7 11h10"/>'],
                    ['Texto revisto por si', 'O preço baixou? A área do anúncio está errada? Na sua conta, escreve uma nota e o texto muda.', '<path d="M4 20h4L19 9l-4-4L4 16z"/>'],
                    ['Vertical, abaixo de 60 segundos', 'No formato certo para Reels, TikTok, Shorts e estados de WhatsApp.', '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>'],
                    ['As cores da sua agência', 'Logótipo e cores usados em todos os vídeos, nos planos pagos.', '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>'],
                    ['Sem marca d’água', 'O vídeo é seu. Publique onde quiser, quantas vezes quiser.', '<path d="M4 12v7h16v-7M12 3v12M7 8l5-5 5 5"/>'],
                ] as [$titulo, $texto, $icone])
                    <div class="p-7 bg-ivory">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#076E8A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icone !!}</svg>
                        <h3 class="mt-4 mb-1.5 font-sans text-[17px] font-semibold tracking-normal">{{ $titulo }}</h3>
                        <p class="text-sm leading-[1.65] text-ink-soft">{{ $texto }}</p>
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
                <h2 class="mt-4 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] font-normal text-ivory">O primeiro é grátis. <em>Depois, escolha o ritmo.</em></h2>
            </div>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                @foreach (['Sem fidelização', 'Fatura com NIF', 'Cancela quando quiser'] as $pill)
                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-ivory/20 text-[13px] text-[#E4E6EA]">{!! $check !!}{{ $pill }}</span>
                @endforeach
            </div>
            <div class="mt-12 grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-5 items-stretch">
                @foreach ($planos as $p)
                    <div @class([
                        'relative px-7 py-8 rounded-2xl flex flex-col',
                        'bg-ivory text-navy border-2 border-cyan' => $p['destaque'],
                        'bg-navy-2 text-ivory border border-ivory/10' => ! $p['destaque'],
                    ])>
                        @if ($p['destaque'])
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3.5 py-1 rounded-full bg-coral text-navy text-[11px] font-semibold tracking-[.16em] whitespace-nowrap">MAIS ESCOLHIDO</span>
                        @endif
                        <span @class(['text-xs font-medium tracking-[.28em]', 'text-cyan-deep' => $p['destaque'], 'text-cyan' => ! $p['destaque']])>{{ $p['nome'] }}</span>
                        <p @class(['mt-2.5 text-sm leading-normal min-h-11', 'text-ink-soft' => $p['destaque'], 'text-[#C9CED6]' => ! $p['destaque']])>{{ $p['para'] }}</p>
                        <div class="mt-5 flex items-baseline gap-2"><span class="font-display text-[52px] leading-none">{{ $p['qtd'] }}</span><span class="text-sm opacity-80">{{ $p['unidade'] }}</span></div>
                        <div class="mt-2.5 text-sm opacity-80">{{ $p['preco'] }}</div>
                        <a href="{{ $p['href'] }}" @class([
                            'mt-6 flex items-center justify-center min-h-[50px] rounded-full text-[15px] font-semibold',
                            'bg-coral text-navy' => $p['coral'],
                            'bg-navy text-ivory' => $p['destaque'],
                            'border border-ivory/35 text-ivory hover:bg-ivory/5' => ! $p['coral'] && ! $p['destaque'],
                        ])>{{ $p['cta'] }}</a>
                        <span class="mt-2.5 text-xs text-center opacity-80">{{ $p['nota'] }}</span>
                        <div @class(['mt-6 pt-5 text-[13px] font-semibold border-t', 'border-[#D6D1C5]' => $p['destaque'], 'border-ivory/15' => ! $p['destaque']])>{{ $p['inclui'] }}</div>
                        <ul @class(['mt-3 flex flex-col gap-2.5 text-sm', 'text-ink-soft' => $p['destaque'], 'text-[#C9CED6]' => ! $p['destaque']])>
                            @foreach ($p['itens'] as $item)
                                <li class="flex gap-2.5 items-start">{!! $check !!}<span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
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
                        <p class="px-6 pb-5 text-sm leading-[1.7] text-ink-soft">{{ $a }}</p>
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
                <h2 class="font-display text-[clamp(44px,6vw,72px)] leading-none font-normal text-ivory">O seu próximo imóvel merece <em class="text-coral">mais do que fotos.</em></h2>
                <p class="mt-6 mx-auto max-w-[560px] text-[17px] leading-[1.7] font-light text-[#C9CED6]">Cole o link do anúncio e receba o primeiro vídeo sem pagar nada.</p>
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
            <nav aria-label="Produto" class="flex-[1_1_160px] flex flex-col gap-3 text-sm">
                <span class="text-xs tracking-[.24em] text-ivory">PRODUTO</span>
                <a href="#como" class="text-[#AEB5C1] hover:text-ivory">Como funciona</a>
                <a href="#exemplos" class="text-[#AEB5C1] hover:text-ivory">Exemplos</a>
                <a href="#precos" class="text-[#AEB5C1] hover:text-ivory">Preços</a>
                <a href="#pedido" class="text-[#AEB5C1] hover:text-ivory">Vídeo grátis</a>
            </nav>
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
