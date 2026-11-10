{{-- Cookie consent: measurement (Meta, Google) only after "Aceitar". The choice is kept a year;
     any link with data-cookies reopens it ("Preferências de cookies" in the footer). --}}
<div id="cookies" role="dialog" aria-live="polite" aria-label="Cookies" hidden
     class="fixed inset-x-3 bottom-3 sm:inset-x-auto sm:right-5 sm:bottom-5 sm:max-w-[420px] z-50 rounded-[18px] border border-ivory/15 bg-navy-2/95 backdrop-blur p-5 shadow-[0_20px_60px_rgba(0,0,0,.45)]">
    <p class="text-sm leading-relaxed text-[#C9CED6]">
        Usamos cookies de medição (Meta e Google) para saber que anúncios trazem visitas e melhorar a Imocena.
        Só com a sua autorização. <a href="{{ route('privacidade') }}#cookies" class="text-ivory underline underline-offset-2">Saber mais</a>
    </p>
    <div class="mt-4 flex gap-2">
        <button type="button" data-escolha="nao" class="flex-1 min-h-11 rounded-full border border-ivory/30 text-sm text-ivory">Rejeitar</button>
        <button type="button" data-escolha="sim" class="flex-1 min-h-11 rounded-full bg-coral text-navy text-sm font-semibold">Aceitar</button>
    </div>
</div>
<script>
(function () {
    const caixa = document.getElementById('cookies');
    const escolha = (document.cookie.split('; ').find(c => c.startsWith('imocena_cookies=')) || '').split('=')[1];
    if (!escolha) caixa.hidden = false;
    caixa.querySelectorAll('[data-escolha]').forEach(b => b.addEventListener('click', () => {
        const v = b.dataset.escolha;
        document.cookie = 'imocena_cookies=' + v + '; Max-Age=' + (365 * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        caixa.hidden = true;
        if (v === 'sim') window.imocenaMedicao && imocenaMedicao.carregar();
        else if (window.imocenaMedicao && escolha === 'sim') location.reload();   // withdrawn: stop the tags now
    }));
    document.addEventListener('click', e => { if (e.target.closest('[data-cookies]')) { e.preventDefault(); caixa.hidden = false; } });
})();
</script>
