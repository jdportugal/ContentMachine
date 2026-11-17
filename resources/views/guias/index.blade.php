<x-layouts.site
    title="Guias de vídeo e marketing para consultores imobiliários · Imocena"
    description="Guias práticos para consultores imobiliários em Portugal: vídeo no idealista, Reels, TikTok, angariação e marketing, com fontes e números atuais."
    :canonical="route('guias')"
    :schema="$esquema">
    <header class="border-b border-ivory/10">
        <nav class="max-w-[1240px] mx-auto px-6 py-3 md:py-5 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
            <a href="{{ route('home') }}" class="inline-flex items-center min-h-11 px-5 rounded-full bg-coral text-navy text-sm font-semibold hover:brightness-105">Vídeo grátis →</a>
        </nav>
    </header>

    <main class="max-w-[880px] mx-auto px-5 sm:px-6 py-12 sm:py-16">
        <span class="text-xs font-medium tracking-[.32em] text-cyan">GUIAS</span>
        <h1 class="mt-4 font-display text-[clamp(36px,6vw,56px)] leading-[1.04] text-ivory">Vídeo e marketing para consultores imobiliários</h1>
        <p class="mt-5 max-w-[620px] text-[17px] leading-relaxed text-[#C9CED6]">Guias práticos para quem vende casas em Portugal: como pôr vídeo nos anúncios, o que publicar nas redes e como angariar mais. Com fontes e números atuais.</p>

        <ul class="mt-12 grid sm:grid-cols-2 gap-4">
            @foreach ($guias as $g)
                <li><a href="{{ route('guias.ver', $g['slug']) }}" class="h-full flex flex-col p-6 rounded-2xl bg-navy-2 border border-ivory/10 hover:border-cyan/60">
                    <span class="font-display text-[24px] leading-tight text-ivory">{{ $g['titulo'] }}</span>
                    <span class="mt-3 text-[15px] leading-relaxed text-[#AEB5C1]">{{ $g['descricao'] }}</span>
                    <span class="mt-auto pt-5 text-sm text-cyan">Ler o guia →</span>
                </a></li>
            @endforeach
        </ul>
    </main>

    @include('guias.rodape')
</x-layouts.site>
