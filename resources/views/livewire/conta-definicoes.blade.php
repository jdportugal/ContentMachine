<div class="font-sans max-w-3xl">
    <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">A SUA CONTA</span></div>
    <h1 class="mt-4 font-display text-[clamp(36px,5vw,52px)] leading-[1.02] text-ink">Definições da conta</h1>

    <section class="mt-8 rounded-[18px] bg-white border border-[#E4E1DA] p-6 md:p-8">
        <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">PARTILHAR A CONTA</span></div>
        <h2 class="mt-4 font-display text-3xl leading-tight text-ink">Quem pode entrar nesta conta</h2>
        <p class="mt-2 text-sm leading-relaxed text-ink-soft">Cada email desta lista entra nesta mesma conta, com os mesmos vídeos, imóveis e saldo. Útil para partilhar a conta com um colega ou assistente.</p>

        <ul class="mt-6 flex flex-col gap-2">
            <li class="flex items-center justify-between gap-3 min-h-12 px-4 rounded-xl bg-[#F6F4EF] border border-[#E4E1DA]">
                <span class="text-sm text-ink truncate">{{ auth()->user()->email }}</span>
                <span class="text-xs text-ink-faint shrink-0">Email principal</span>
            </li>
            @foreach ($emails as $e)
                <li wire:key="e-{{ $e }}" class="flex items-center justify-between gap-3 min-h-12 px-4 rounded-xl bg-[#F6F4EF] border border-[#E4E1DA]">
                    <span class="text-sm text-ink truncate">{{ $e }}</span>
                    <button type="button" wire:click="remover(@js($e))" wire:confirm="Retirar {{ $e }}? Deixa de conseguir entrar nesta conta."
                            class="shrink-0 min-h-9 px-3 rounded-full border border-[#D9D5CC] text-xs font-semibold text-navy hover:bg-[#EDEAE3]">Retirar</button>
                </li>
            @endforeach
        </ul>

        <form wire:submit="adicionar" class="mt-6 flex flex-wrap gap-3" novalidate>
            <label for="novo-email" class="sr-only">Email a adicionar</label>
            <input id="novo-email" type="email" wire:model="email" placeholder="email@exemplo.pt" autocomplete="off"
                   class="flex-[1_1_280px] min-w-0 min-h-[52px] px-4 rounded-[10px] bg-white text-navy text-[15px] border border-[#D9D5CC] focus:outline-none focus:ring-2 focus:ring-cyan">
            <button type="submit" wire:loading.attr="disabled" wire:target="adicionar"
                    class="min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60">Adicionar email</button>
        </form>
        @error('email') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        <p class="mt-4 text-xs text-ink-faint">Para entrar, a pessoa usa o seu próprio email na página de entrada ou nas plantas. Pode retirar um email a qualquer momento.</p>
    </section>
</div>
