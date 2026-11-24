<x-layouts.site :title="'Imocena · Demonstração do vídeo do seu imóvel'" robots="noindex, nofollow" :og-image="$demo['poster'] ?: null">
    <main class="max-w-[1040px] mx-auto px-6 py-8 md:py-10">
        <nav class="flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>
        </nav>

        <div class="mt-8 md:mt-12 flex flex-col md:flex-row gap-8 md:gap-12 items-center md:items-start">
            <video src="{{ $demo['video'] }}" @if ($demo['poster']) poster="{{ $demo['poster'] }}" @endif controls playsinline preload="metadata"
                class="w-full max-w-[340px] md:max-w-none md:w-auto md:h-[min(78vh,720px)] aspect-[9/16] rounded-[14px] bg-navy border border-ivory/10"></video>

            <div class="max-w-[440px] md:pt-6">
                <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">DEMONSTRAÇÃO</span></div>
                <h1 class="mt-4 font-display text-[clamp(32px,4.4vw,48px)] leading-[1.05] font-normal text-ivory">O vídeo do imóvel que acabou de publicar</h1>
                @if ($demo['titulo'] !== '')
                    <p class="mt-3 text-[15px] text-[#C9CED6]">{{ $demo['titulo'] }}</p>
                @endif
                <p class="mt-5 text-[15px] leading-[1.7] font-light text-[#C9CED6]">Esta demonstração foi feita só com as fotografias do anúncio. Na versão final, a narração fala deste imóvel — a localização, as áreas e os pontos fortes — e no fim aparecem o seu nome, a sua fotografia e o seu contacto.</p>
                <a href="{{ $final }}" class="mt-7 inline-flex items-center min-h-12 px-7 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Fazer a versão final →</a>
                <p class="mt-4 text-[13px] text-[#8C96A6]">Fica pronta em poucos minutos.</p>
            </div>
        </div>
    </main>
</x-layouts.site>
