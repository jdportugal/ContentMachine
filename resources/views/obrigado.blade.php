<x-layouts.site title="Imocena · Obrigado">
    @if (! $compra)
        {{-- Stripe sends the buyer here a moment before its webhook lands: check again shortly. --}}
        <meta http-equiv="refresh" content="3">
    @elseif ($lead && ! $video)
        <meta http-equiv="refresh" content="8">
    @endif
    <main class="max-w-[760px] mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>
            @auth <a href="{{ route('conta') }}" class="inline-flex items-center min-h-11 px-4 rounded-full border border-ivory/30 text-ivory text-sm">A minha conta</a> @endauth
        </div>

        @if (! $compra)
            <div class="mt-12 flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">A CONFIRMAR O PAGAMENTO</span></div>
            <h1 class="mt-5 font-display text-[clamp(36px,5vw,54px)] leading-[1.05] text-ivory">Só um momento…</h1>
            <p class="mt-6 text-lg font-light text-[#C9CED6]">Estamos a receber a confirmação do pagamento. Esta página atualiza sozinha.</p>
        @else
            <div class="mt-12 flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">PAGAMENTO RECEBIDO</span></div>
            <h1 class="mt-5 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] text-ivory">Obrigado.</h1>

            @if ($lead)
                @if ($video)
                    <p class="mt-6 text-lg leading-relaxed font-light text-[#C9CED6]">O seu vídeo, sem marca d’água:</p>
                    <video src="{{ URL::signedRoute('lead.video', ['lead' => $lead->id]) }}" controls playsinline autoplay muted class="mt-6 w-full sm:max-w-[360px] aspect-[9/16] rounded-[16px] bg-navy border border-ivory/10 object-contain"></video>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ URL::signedRoute('lead.video', ['lead' => $lead->id, 'download' => 1]) }}" class="inline-flex items-center min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Descarregar MP4</a>
                        @auth <a href="{{ route('conta') }}" class="inline-flex items-center min-h-[52px] px-6 rounded-full border border-ivory/30 text-ivory">Ir para a minha conta →</a> @endauth
                    </div>
                @else
                    <p class="mt-6 text-lg leading-relaxed font-light text-[#C9CED6]">A terminar a versão sem marca d’água — fica aqui dentro de instantes (a página atualiza sozinha) e também na sua conta.</p>
                @endif
            @else
                <p class="mt-6 text-lg leading-relaxed font-light text-[#C9CED6]">{{ $compra->videos > 1 ? 'O seu pack de '.$compra->videos.' vídeos' : 'O seu vídeo' }} está na sua conta. Cole o link de um anúncio e o vídeo fica pronto em poucos minutos, sem marca d’água.</p>
                @auth <a href="{{ route('conta') }}" class="mt-6 inline-flex items-center min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Fazer o primeiro vídeo →</a> @endauth
            @endif

            @guest
                <p class="mt-8 text-sm text-[#AEB5C1]">Enviámos para {{ $compra->email }} um link para entrar na sua conta, sem palavra-passe. Pode pedir outro em <a href="{{ route('login') }}" class="text-cyan underline">imocena.com/login</a>.</p>
            @endguest
            <p class="mt-4 text-sm text-[#AEB5C1]">Fatura com o NIF indicado por email. Dúvidas: <a href="mailto:{{ config('mail.from.address') }}" class="text-cyan underline">{{ config('mail.from.address') }}</a>.</p>
        @endif
    </main>
</x-layouts.site>
