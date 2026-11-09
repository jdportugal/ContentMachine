<main class="max-w-[1100px] mx-auto px-4 sm:px-6 py-8 sm:py-12" @if ($aDecorrer) wire:poll.5s @endif>
    <nav class="flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>
        <div class="flex items-center gap-4 text-sm">
            <span class="hidden sm:inline text-[#AEB5C1]">{{ $user->email }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="min-h-11 px-4 rounded-full border border-ivory/30 text-ivory hover:bg-ivory/5">Sair</button></form>
        </div>
    </nav>

    <div class="mt-10 flex flex-wrap items-end justify-between gap-6">
        <div>
            <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">A MINHA CONTA</span></div>
            <h1 class="mt-4 font-display text-[clamp(36px,5vw,52px)] leading-[1.02] text-ivory">Os seus vídeos</h1>
        </div>
        <div class="text-right">
            <span class="block text-[10px] tracking-[.3em] text-cyan">VÍDEOS DISPONÍVEIS</span>
            <span class="font-display text-[48px] leading-none text-ivory">{{ $creditos }}</span>
        </div>
    </div>

    <section class="mt-8 rounded-[18px] border border-ivory/10 bg-navy-3/60 p-5 sm:p-6">
        @if ($creditos > 0)
            <form wire:submit="usarCredito" class="flex flex-col gap-3" novalidate>
                <label for="anuncio" class="font-display text-[24px] text-ivory">Fazer um vídeo (usa 1 dos seus {{ $creditos }})</label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input id="anuncio" type="url" wire:model="anuncio" placeholder="Cole aqui o link do anúncio" class="flex-1 min-h-[52px] rounded-full bg-navy border border-ivory/20 px-5 text-ivory placeholder:text-[#7C8696] focus:border-cyan focus:outline-none">
                    <button type="submit" class="min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105" wire:loading.attr="disabled">Criar vídeo →</button>
                </div>
                @error('anuncio') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                <p class="text-xs text-[#AEB5C1]">Sem marca d’água. Fica pronto em poucos minutos, aqui nesta página.</p>
            </form>
        @else
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <p class="text-[15px] text-[#C9CED6]">Não tem vídeos disponíveis. Compre um pack e faça vídeos sempre que angariar.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ $comprar('pack10') }}" class="inline-flex items-center min-h-11 px-5 rounded-full bg-coral text-navy font-semibold">Pack 10 · 100 €</a>
                    <a href="{{ $comprar('pack20') }}" class="inline-flex items-center min-h-11 px-5 rounded-full border border-ivory/30 text-ivory">Pack 20 · 175 €</a>
                </div>
            </div>
        @endif
    </section>

    <section class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($videos as $v)
            <article class="rounded-[18px] border border-ivory/10 bg-navy-3/40 p-4 flex flex-col gap-3">
                @if ($v->estado === 'pronto')
                    <video src="{{ $v->video }}" controls playsinline preload="metadata" class="w-full aspect-[9/16] rounded-[12px] bg-navy object-contain"></video>
                @else
                    <div class="w-full aspect-[9/16] rounded-[12px] bg-navy flex flex-col items-center justify-center gap-4 px-6 text-center">
                        @if ($v->estado === 'falhou')
                            <span class="text-sm text-coral">Não conseguimos fazer este vídeo. Já fomos avisados.</span>
                        @else
                            <span class="text-sm text-ivory">{{ $v->etapa }}…</span>
                            <div class="w-full h-1.5 rounded-full bg-ivory/10 overflow-hidden"><div class="h-full bg-cyan transition-all duration-700" style="width: {{ $v->pct }}%"></div></div>
                        @endif
                    </div>
                @endif
                <p class="text-sm text-ivory line-clamp-2">{{ $v->titulo }}</p>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-[11px] tracking-[.2em] {{ $v->limpo ? 'text-cyan' : 'text-[#AEB5C1]' }}">{{ $v->estado !== 'pronto' ? '' : ($v->limpo ? 'SEM MARCA D’ÁGUA' : 'VERSÃO GRÁTIS') }}</span>
                    @if ($v->descarregar)
                        <a href="{{ $v->descarregar }}" class="inline-flex items-center min-h-11 px-4 rounded-full bg-coral text-navy text-sm font-semibold">Descarregar MP4</a>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-[#C9CED6]">Ainda não tem vídeos.</p>
        @endforelse
    </section>
</main>
