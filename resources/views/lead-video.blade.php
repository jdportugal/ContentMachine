<x-layouts.site title="O seu vídeo · Imocena">
    <header class="border-b border-ivory/10">
        <div class="max-w-[1240px] mx-auto px-6 py-5 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory">
                <span class="font-display text-[30px] font-medium">Imocena</span>
                <span class="w-1.5 h-1.5 rounded-full bg-cyan"></span>
            </a>
        </div>
    </header>

    <main class="max-w-[1080px] mx-auto px-6 py-16 flex flex-wrap gap-14 items-center">
        <div class="flex-[0_1_340px] mx-auto">
            <video src="{{ $ficheiro }}" controls playsinline preload="metadata"
                   class="w-full aspect-[9/16] rounded-[28px] border-[7px] border-navy-3 bg-black shadow-[0_40px_90px_-20px_rgba(0,0,0,.8)]"></video>
        </div>
        <div class="flex-[1_1_420px] min-w-0">
            <div class="flex items-center gap-3.5">
                <span class="w-10 h-px bg-cyan"></span>
                <span class="text-xs tracking-[.32em] text-cyan">O SEU VÍDEO ESTÁ PRONTO</span>
            </div>
            <h1 class="mt-5 font-display text-[clamp(44px,6vw,64px)] leading-none font-normal text-ivory">Obrigado, {{ $nome }}. <em class="text-coral">Publique-o já.</em></h1>
            <p class="mt-6 max-w-[480px] text-base leading-[1.7] font-light text-[#C9CED6]">Vertical, em 9:16 e com menos de 60 segundos: pronto para Reels, TikTok, Shorts e estados de WhatsApp. O vídeo é seu, sem marca d'água.</p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ $descarregar }}" class="inline-flex items-center gap-2 min-h-14 px-7 rounded-full bg-coral text-navy text-base font-semibold hover:brightness-105">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
                    Descarregar MP4
                </a>
                <a href="mailto:{{ config('mail.from.address') }}?subject=Mais%20v%C3%ADdeos" class="inline-flex items-center min-h-14 px-7 rounded-full border border-ivory/35 text-ivory text-base font-semibold hover:bg-ivory/5">Quero mais vídeos</a>
            </div>
        </div>
    </main>
</x-layouts.site>
