<x-layouts.site title="Imocena · Obrigado">
    <main class="max-w-[760px] mx-auto px-6 py-16">
        <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>

        <div class="mt-12 flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">PAGAMENTO RECEBIDO</span></div>
        <h1 class="mt-5 font-display text-[clamp(40px,5vw,60px)] leading-[1.02] text-ivory">Obrigado.</h1>

        @if ($compra && $compra->videos === 1 && $lead)
            <p class="mt-6 text-lg leading-relaxed font-light text-[#C9CED6]">Estamos a fazer a versão limpa do seu vídeo, sem marca d’água. Leva poucos minutos e fica no mesmo link de sempre:</p>
            <a href="{{ URL::signedRoute('lead.video', ['lead' => $lead->id]) }}" class="mt-6 inline-flex items-center min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Abrir o meu vídeo →</a>
            <p class="mt-4 text-sm text-[#AEB5C1]">Se ainda vir a marca d’água, espere uns minutos e recarregue. Dúvidas: <a href="mailto:{{ config('mail.from.address') }}" class="text-cyan underline">{{ config('mail.from.address') }}</a>.</p>
        @elseif ($compra && $compra->videos > 1)
            <p class="mt-6 text-lg leading-relaxed font-light text-[#C9CED6]">O seu pack de <strong class="text-ivory font-medium">{{ $compra->videos }} vídeos</strong> está registado{{ $compra->email ? ' em '.$compra->email : '' }}. Para usar um vídeo, cole o link do anúncio em <a href="{{ route('home') }}#pedido" class="text-cyan underline">imocena.com</a> e responda ao email de confirmação com o link — entregamos a versão limpa.</p>
            <p class="mt-4 text-sm text-[#AEB5C1]">Válido 12 meses. Fatura com NIF por email. Dúvidas: <a href="mailto:{{ config('mail.from.address') }}" class="text-cyan underline">{{ config('mail.from.address') }}</a>.</p>
        @else
            <p class="mt-6 text-lg leading-relaxed font-light text-[#C9CED6]">Recebemos o seu pagamento. Vamos confirmar por email nos próximos minutos{{ $compra?->email ? ' para '.$compra->email : '' }}.</p>
            <p class="mt-4 text-sm text-[#AEB5C1]">Dúvidas: <a href="mailto:{{ config('mail.from.address') }}" class="text-cyan underline">{{ config('mail.from.address') }}</a>.</p>
        @endif

        <a href="{{ route('home') }}" class="mt-12 inline-block text-sm text-[#AEB5C1] hover:text-ivory">← Voltar à página inicial</a>
    </main>
</x-layouts.site>
