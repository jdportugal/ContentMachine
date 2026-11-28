@php
    // Ferramentas IA's landing page, built like Imoplanta's (imoplanta.landing): each tool with its example clip
    // (public/exemplos-ia/<tool>.mp4, made once with the tool itself) and its price in video credits, the credit packs.
    $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
    $packs = collect(config('contentmachine.ferramentas.packs'))->values();
    $porCredito = $packs->last()['preco'] / $packs->last()['creditos'];   // the cheapest credit, in the biggest pack
    $menos = min(array_column(\App\Services\Ferramentas\Ferramentas::lista(), 'creditos'));
    $ferramentas = \App\Services\Ferramentas\Ferramentas::lista();
    $botao = 'inline-flex items-center justify-center min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105';
    $sobre = 'text-[11px] tracking-[.3em] text-[#0478A0]';
    $entrar = route('login');
    $comecar = auth()->check() ? route('ferramentas') : route('login');
@endphp
<x-layouts.site title="Ferramentas IA · Vídeos de imóveis a partir de uma fotografia">
<x-imoplanta.pagina>
    <x-slot:direita>
        <a href="{{ $entrar }}" class="hover:text-cyan">Entrar</a>
    </x-slot:direita>

    {{-- hero --}}
    <section class="grid gap-8 lg:gap-14 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] items-center">
        <div class="flex flex-col gap-5">
            <div class="flex items-center gap-3"><span class="w-8 h-0.5 bg-coral"></span><span class="{{ $sobre }}">FERRAMENTAS IA</span></div>
            <h1 class="font-display text-[44px] lg:text-[64px] font-medium leading-[1.02] text-navy">Uma fotografia, um vídeo que vende</h1>
            <p class="max-w-lg text-[16px] lg:text-[18px] leading-relaxed text-[#4A5263]">Vista de drone, movimentos de câmara, remodelações, ruínas recuperadas, construção num terreno, planta animada e fotografias limpas. Envie uma fotografia do imóvel, aprove as imagens e recebe o resultado em minutos.</p>
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ $comecar }}" class="{{ $botao }}">Experimentar com uma fotografia →</a>
                <a href="#ferramentas" class="min-h-11 inline-flex items-center text-sm font-semibold text-navy underline underline-offset-4">Ver as ferramentas</a>
            </div>
            <p class="text-sm text-[#4A5263]">Paga em créditos de vídeo: cada ferramenta usa os seus, a partir de {{ \App\Services\Pagamentos\Creditos::txt($menos) }}. Um crédito a partir de <strong class="text-navy">{{ $eur((int) round($porCredito)) }}</strong> num pack. Sem subscrição.</p>
        </div>
        @php $capa = \App\Services\Ferramentas\Ferramentas::exemplo('drone'); @endphp
        <figure class="flex flex-col gap-3">
            @if ($capa['video'])
                <video src="{{ $capa['video'] }}" poster="{{ $capa['poster'] }}" autoplay muted loop playsinline aria-label="Exemplo de vista drone"
                       class="w-full aspect-video object-cover rounded-[20px] bg-white shadow-[0_18px_40px_rgba(14,22,38,0.18)]"></video>
            @else
                <div class="w-full aspect-video rounded-[20px] bg-navy shadow-[0_18px_40px_rgba(14,22,38,0.18)]"></div>
            @endif
            <figcaption class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] font-medium text-[#4A5263]">
                <span>A sua fotografia</span><span aria-hidden="true" class="text-coral">→</span><span>Imagens aprovadas por si</span><span aria-hidden="true" class="text-coral">→</span><span>Vídeo</span>
            </figcaption>
        </figure>
    </section>

    {{-- the tools: one demo each (the remodel one per type of works), 3 × 3 on a computer; each clip plays only on screen --}}
    <section id="ferramentas" class="mt-20 lg:mt-28 scroll-mt-6">
        <div class="max-w-2xl">
            <div class="flex items-center gap-3"><span class="w-8 h-0.5 bg-coral"></span><span class="{{ $sobre }}">AS FERRAMENTAS</span></div>
            <h2 class="mt-3 font-display text-[34px] lg:text-[46px] font-medium leading-tight text-navy">Veja o que cada ferramenta faz</h2>
            <p class="mt-3 text-[15px] lg:text-[17px] text-[#4A5263]">Cada exemplo foi feito com a própria ferramenta, a partir de uma única fotografia. Os vídeos duram {{ $ferramentas['lateral']['segundos'] }} a {{ $ferramentas['construcao']['segundos'] }} segundos, em alta definição, no formato da sua fotografia.</p>
        </div>

        <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (\App\Services\Ferramentas\Ferramentas::demos() as $chave => $d)
                @php $ex = \App\Services\Ferramentas\Ferramentas::exemplo($chave); $f = $ferramentas[$d['ferramenta']]; @endphp
                <li class="rounded-[20px] overflow-hidden bg-white border border-[#E4E1DA] shadow-[0_12px_32px_rgba(14,22,38,0.08)] flex flex-col">
                    <div class="relative">
                        @if ($ex['video'])
                            <video src="{{ $ex['video'] }}" poster="{{ $ex['poster'] }}" muted loop playsinline preload="none"
                                   x-data x-intersect:enter="$el.play().catch(() => {})" x-intersect:leave="$el.pause()"
                                   class="w-full aspect-video object-cover bg-navy" aria-label="Exemplo: {{ $d['nome'] }}"></video>
                        @else
                            <div class="w-full aspect-video bg-navy"></div>
                        @endif
                        <span class="absolute top-3 left-3 px-3 py-1 rounded-full bg-navy/85 text-ivory text-[12px] font-semibold backdrop-blur">{{ \App\Services\Ferramentas\Ferramentas::formato($d['ferramenta']) }}</span>
                    </div>
                    <div class="p-5 lg:p-6 flex flex-col gap-2 grow">
                        <h3 class="font-display text-[24px] lg:text-[26px] font-medium leading-tight text-navy">{{ $d['nome'] }}</h3>
                        <p class="text-[15px] leading-relaxed text-[#4A5263] grow">{{ $d['texto'] }}</p>
                        <p class="pt-2 text-[13px] text-[#4A5263]">Envia: <strong class="text-navy">{{ mb_strtolower($f['envio']) }}</strong> · <strong class="text-navy">{{ \App\Services\Pagamentos\Creditos::rotulo(\App\Services\Ferramentas\Ferramentas::creditosDemo($chave)) }}</strong></p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- prices --}}
    <section class="mt-20 lg:mt-28">
        <div class="flex items-center gap-3"><span class="w-8 h-0.5 bg-coral"></span><span class="{{ $sobre }}">PREÇOS</span></div>
        <h2 class="mt-3 font-display text-[34px] lg:text-[46px] font-medium leading-tight text-navy">Paga em créditos de vídeo</h2>
        <p class="mt-2 text-[15px] text-[#4A5263]">IVA incluído. Os mesmos créditos dos vídeos Imocena e do Imoplanta. Cada ferramenta usa os seus créditos, com as alterações às imagens e as novas versões do vídeo incluídas. Os créditos ficam na sua conta até os usar.</p>
        <p class="mt-2 text-[13px] text-[#6B7385]">Os resultados são feitos por inteligência artificial e variam: não garantimos um resultado específico, e os créditos usados não são devolvidos por o resultado não ser o esperado (só se não conseguirmos fazer o pedido). <a href="{{ route('termos') }}#creditos" class="underline">Termos e condições</a>.</p>
        <div class="mt-8 grid gap-5 sm:grid-cols-3">
            @foreach ($packs as $pack)
                <div class="rounded-[20px] border {{ $loop->last ? 'border-coral bg-white' : 'border-[#E4E1DA] bg-white/60' }} p-6 flex flex-col gap-1">
                    <span class="text-sm font-semibold text-navy">{{ $pack['creditos'] === 1 ? '1 crédito de vídeo' : $pack['creditos'].' créditos de vídeo' }}</span>
                    <span class="font-display text-[40px] leading-none text-navy">{{ $eur($pack['preco']) }}</span>
                    @if ($pack['creditos'] > 1)
                        <span class="text-sm text-[#4A5263]">{{ $eur((int) round($pack['preco'] / $pack['creditos'])) }} por crédito</span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- closing call --}}
    <section class="mt-20 lg:mt-28 rounded-[24px] bg-navy text-ivory px-6 py-10 lg:px-14 lg:py-14 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
        <div>
            <h2 class="font-display text-[32px] lg:text-[42px] font-medium leading-tight">Tem uma fotografia? Comece agora.</h2>
            <p class="mt-2 text-[15px] text-[#C9CED6]">Precisa só do seu email. As imagens ficam prontas em poucos minutos.</p>
        </div>
        <a href="{{ $comecar }}" class="{{ $botao }} shrink-0">Experimentar com uma fotografia →</a>
    </section>
</x-imoplanta.pagina>
</x-layouts.site>
