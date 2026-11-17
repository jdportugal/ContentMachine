@php
    $data = fn (string $d) => \Illuminate\Support\Carbon::parse($d)->locale('pt_PT')->translatedFormat('j \d\e F \d\e Y');
@endphp
<x-layouts.site
    :title="$guia['titulo'].' · Imocena'"
    :description="$guia['descricao']"
    :canonical="route('guias.ver', $guia['slug'])"
    :og-image="asset('images/og/'.$guia['slug'].'.png')"
    og-type="article"
    :schema="\App\Support\Esquema::guia($guia)">
    <header class="border-b border-ivory/10">
        <nav class="max-w-[1240px] mx-auto px-6 py-3 md:py-5 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
            <div class="flex items-center gap-6 text-sm">
                <a href="{{ route('guias') }}" class="inline-flex items-center min-h-11 text-[#D6D9DF] hover:text-ivory">Guias</a>
                <a href="{{ route('home') }}" class="inline-flex items-center min-h-11 px-5 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Vídeo grátis →</a>
            </div>
        </nav>
    </header>

    <main class="max-w-[760px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
        <nav aria-label="Caminho" class="text-[13px] text-[#8B95A7]">
            <a href="{{ route('home') }}" class="hover:text-ivory">Imocena</a>
            <span class="mx-1.5">›</span>
            <a href="{{ route('guias') }}" class="hover:text-ivory">Guias</a>
        </nav>

        <article>
            <h1 class="mt-5 font-display text-[clamp(34px,5.6vw,52px)] leading-[1.06] text-ivory">{{ $guia['titulo'] }}</h1>
            <p class="mt-4 text-[14px] text-[#8B95A7]">
                Por Imocena · <time datetime="{{ $guia['atualizado'] }}">Atualizado a {{ $data($guia['atualizado']) }}</time> · {{ max(1, (int) round($guia['palavras'] / 220)) }} min de leitura
            </p>

            @if ($guia['resumo'])
                <section aria-label="Resposta curta" class="mt-8 p-6 rounded-2xl bg-navy-2 border border-cyan/40">
                    <span class="text-xs font-medium tracking-[.28em] text-cyan">RESPOSTA CURTA</span>
                    <p class="mt-3 text-[17px] leading-relaxed text-ivory">{{ $guia['resumo'] }}</p>
                </section>
            @endif

            <div class="guia mt-4">{!! $guia['html'] !!}</div>
        </article>

        <aside class="mt-14 p-7 sm:p-8 rounded-2xl bg-ivory text-navy">
            <span class="text-xs font-medium tracking-[.28em] text-cyan-deep">EXPERIMENTE GRÁTIS</span>
            <p class="mt-3 font-display text-[30px] leading-tight">Cole o link do seu anúncio. Daqui a minutos tem o vídeo.</p>
            <p class="mt-3 text-[15px] leading-relaxed text-[#4B5563]">Vídeo vertical com narração em português de Portugal e o seu contacto no fim. Para anúncios da RE/MAX, iad Portugal, eXp Portugal, KW Portugal e Century 21.</p>
            <a href="{{ route('home') }}?utm_source=guias&utm_medium=organico&utm_campaign={{ $guia['slug'] }}" class="mt-6 inline-flex items-center min-h-[50px] px-7 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Pedir o vídeo grátis →</a>
        </aside>

        @if ($outros !== [])
            <section class="mt-14">
                <h2 class="font-display text-[28px] text-ivory">Outros guias</h2>
                <ul class="mt-5 grid gap-3">
                    @foreach ($outros as $o)
                        <li><a href="{{ route('guias.ver', $o['slug']) }}" class="block p-5 rounded-xl bg-navy-2 border border-ivory/10 hover:border-cyan/60">
                            <span class="block text-[17px] text-ivory">{{ $o['titulo'] }}</span>
                            <span class="mt-1 block text-[14px] leading-relaxed text-[#AEB5C1]">{{ $o['descricao'] }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>

    @include('guias.rodape')
</x-layouts.site>
