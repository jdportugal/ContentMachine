@props(['codigo', 'titulo', 'texto'])
{{-- Branded error page (404, 419, 500, 503): same look as the site, a way home and to the free video. --}}
<x-layouts.site :title="'Imocena · '.strip_tags($titulo)">
    <main class="min-h-screen flex flex-col">
        <header class="max-w-[1240px] w-full mx-auto px-6 pt-8">
            <a href="{{ url('/') }}" class="inline-flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>
        </header>
        <section class="flex-1 flex items-center">
            <div class="max-w-[760px] mx-auto px-6 py-20 text-center">
                <span class="text-xs tracking-[.32em] text-cyan">ERRO {{ $codigo }}</span>
                <h1 class="mt-5 font-display text-[clamp(44px,7vw,72px)] leading-[1.02] font-normal text-ivory">{!! $titulo !!}</h1>
                <p class="mt-6 mx-auto max-w-[520px] text-[17px] leading-[1.7] font-light text-[#C9CED6]">{{ $texto }}</p>
                <div class="mt-10 flex flex-wrap justify-center gap-3">
                    <a href="{{ url('/') }}#pedido" class="inline-flex items-center min-h-[56px] px-8 rounded-full bg-coral text-navy text-[16px] font-semibold hover:brightness-105">Pedir o vídeo grátis →</a>
                    <a href="{{ url('/') }}" class="inline-flex items-center min-h-[56px] px-8 rounded-full border border-ivory/35 text-ivory text-[16px] hover:bg-ivory/5">Página inicial</a>
                </div>
                <p class="mt-10 text-sm text-[#8B95A7]">Precisa de ajuda? <a href="mailto:{{ config('mail.from.address') }}" class="text-ivory underline underline-offset-2">{{ config('mail.from.address') }}</a></p>
            </div>
        </section>
    </main>
</x-layouts.site>
