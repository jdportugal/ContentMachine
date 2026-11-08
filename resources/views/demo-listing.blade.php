<x-layouts.site :title="'Imocena · Exemplo · '.$listing['title']">
    <main class="max-w-[1240px] mx-auto px-6 py-10">
        <nav class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>
            <a href="{{ route('home') }}#pedido" class="inline-flex items-center min-h-11 px-5 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Fazer o vídeo do meu imóvel →</a>
        </nav>

        <div class="mt-10 flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-[720px]">
                <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">IMÓVEL DE DEMONSTRAÇÃO</span></div>
                <h1 class="mt-4 font-display text-[clamp(36px,4.6vw,56px)] leading-[1.02] font-normal text-ivory">{{ $listing['title'] }}</h1>
                <p class="mt-4 text-[15px] leading-[1.7] font-light text-[#C9CED6]">Um anúncio fictício, com imagens ilustrativas, que usamos para mostrar como fica um vídeo Imocena. Cole o link desta página no formulário e veja o resultado — ou, melhor, cole o link de um anúncio seu.</p>
            </div>
            <div class="text-right">
                <span class="block text-[9px] tracking-[.34em] text-cyan">PREÇO</span>
                <span class="font-display text-[44px] leading-none text-ivory">{{ $listing['details']['Preço'] }}</span>
            </div>
        </div>

        @if ($reel)
            <div class="mt-10 flex flex-wrap gap-8 items-start">
                <video src="{{ $reel }}" controls playsinline preload="metadata" class="w-[270px] aspect-[9/16] rounded-[14px] bg-navy border border-ivory/10"></video>
                <div class="max-w-[420px] pt-2">
                    <span class="text-xs tracking-[.32em] text-cyan">O VÍDEO DESTE ANÚNCIO</span>
                    <p class="mt-3 text-[15px] leading-[1.7] font-light text-[#C9CED6]">Feito pela Imocena a partir desta página, sem intervenção manual: texto, narração em português de Portugal, montagem e cartão final.</p>
                </div>
            </div>
        @endif

        <div class="mt-12 grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-4">
            @foreach ($listing['photos'] as $foto)
                <img src="{{ $foto }}" alt="" loading="lazy" class="w-full aspect-[3/2] object-cover rounded-[10px] border border-ivory/10">
            @endforeach
        </div>

        <div class="mt-12 grid md:grid-cols-[1fr_320px] gap-10">
            <div>
                <span class="text-xs tracking-[.32em] text-cyan">DESCRIÇÃO</span>
                <p class="mt-4 whitespace-pre-line text-[15px] leading-[1.75] font-light text-[#C9CED6]">{{ $listing['description'] }}</p>
            </div>
            <dl class="border border-ivory/10 rounded-[14px] p-6 h-fit">
                @foreach ($listing['details'] as $k => $v)
                    <div class="flex justify-between gap-4 items-baseline py-2.5 {{ $loop->last ? '' : 'border-b border-ivory/10' }}"><dt class="text-[11px] font-light tracking-[.14em] text-[#AEB5C1]">{{ mb_strtoupper($k) }}</dt><dd class="text-right text-sm text-ivory">{{ $v }}</dd></div>
                @endforeach
            </dl>
        </div>
        <p class="mt-10 text-xs text-[#AEB5C1]">Imóvel fictício. Imagens geradas, ilustrativas. Contacto: info@imocena.com</p>
    </main>
</x-layouts.site>
