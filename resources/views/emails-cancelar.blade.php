<x-layouts.site title="Imocena · Cancelar emails">
    <main class="min-h-screen flex items-center justify-center px-6">
        <div class="max-w-[520px] w-full rounded-[22px] bg-navy-2 border border-ivory/10 p-8 text-center">
            <a href="{{ route('home') }}" class="font-display text-[30px] text-ivory no-underline">Imocena</a>
            @if ($cancelado)
                <h1 class="mt-6 font-display text-[34px] leading-tight text-ivory">Já não vai receber emails promocionais.</h1>
                <p class="mt-4 text-sm leading-relaxed text-[#C9CED6]">O seu pedido foi registado. Se tiver vídeos em curso, continuamos a contactá-lo apenas sobre eles.</p>
            @else
                <h1 class="mt-6 font-display text-[34px] leading-tight text-ivory">Cancelar os emails promocionais?</h1>
                <p class="mt-4 text-sm leading-relaxed text-[#C9CED6]">Deixa de receber novidades, dicas e promoções da Imocena.</p>
                <form method="POST" action="{{ request()->fullUrl() }}" class="mt-8">
                    @csrf
                    <button type="submit" class="min-h-12 px-7 rounded-full bg-coral text-navy font-semibold hover:brightness-105">Cancelar emails</button>
                </form>
            @endif
        </div>
    </main>
</x-layouts.site>
