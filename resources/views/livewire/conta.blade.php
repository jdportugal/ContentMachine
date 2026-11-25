<div class="font-sans max-w-3xl">
    <span class="eyebrow">A sua conta</span>
    <h1 class="mt-2 font-display text-5xl font-medium leading-none">Definições da conta</h1>

    <section class="mt-8 rounded-2xl bg-navy-2 border border-ivory/10 text-ivory p-7 md:p-8">
        <div class="flex items-center gap-3.5">
            <span class="w-10 h-px bg-cyan"></span>
            <span class="text-xs tracking-[.32em] text-cyan">PARTILHAR A CONTA</span>
        </div>
        <h2 class="mt-4 font-display text-3xl leading-tight text-ivory">Quem pode entrar nesta conta</h2>
        <p class="mt-2 text-sm leading-relaxed text-[#C9CED6]">Cada email desta lista entra nesta mesma conta, com os mesmos vídeos, imóveis e saldo. Útil para partilhar a conta com um colega ou assistente.</p>

        <ul class="mt-6 flex flex-col gap-2">
            <li class="flex items-center justify-between gap-3 min-h-12 px-4 rounded-xl bg-ivory/5">
                <span class="text-sm truncate">{{ auth()->user()->email }}</span>
                <span class="text-xs text-[#AEB5C1] shrink-0">Email principal</span>
            </li>
            @foreach ($emails as $e)
                <li wire:key="e-{{ $e }}" class="flex items-center justify-between gap-3 min-h-12 px-4 rounded-xl bg-ivory/5">
                    <span class="text-sm truncate">{{ $e }}</span>
                    <button type="button" wire:click="remover(@js($e))" wire:confirm="Retirar {{ $e }}? Deixa de conseguir entrar nesta conta."
                            class="shrink-0 min-h-9 px-3 rounded-full border border-ivory/30 text-xs font-semibold hover:bg-ivory/10">Retirar</button>
                </li>
            @endforeach
        </ul>

        <form wire:submit="adicionar" class="mt-6 flex flex-wrap gap-3" novalidate>
            <label for="novo-email" class="sr-only">Email a adicionar</label>
            <input id="novo-email" type="email" wire:model="email" placeholder="email@exemplo.pt" autocomplete="off"
                   class="flex-[1_1_280px] min-w-0 min-h-[52px] px-4 rounded-[10px] bg-ivory text-navy text-[15px] border-0 focus:outline-none focus:ring-2 focus:ring-cyan">
            <button type="submit" wire:loading.attr="disabled" wire:target="adicionar"
                    class="min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60">Adicionar email</button>
        </form>
        @error('email') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        <p class="mt-4 text-xs text-[#AEB5C1]">Para entrar, a pessoa usa o seu próprio email na página de entrada ou nas plantas. Pode retirar um email a qualquer momento.</p>
    </section>
</div>
