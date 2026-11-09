<div class="font-sans">
    <span class="eyebrow">Contas</span>
    <h1 class="mt-2 font-display text-5xl font-medium leading-none">Clientes</h1>

    {{-- Give videos to an email --}}
    <form wire:submit="adicionar" class="mt-8 rounded-2xl bg-navy-2 border border-ivory/10 text-ivory p-6 md:p-8" novalidate>
        <div class="flex items-center gap-3.5">
            <span class="w-10 h-px bg-cyan"></span>
            <span class="text-xs tracking-[.32em] text-cyan">ADICIONAR VÍDEOS</span>
        </div>
        <p class="mt-3 text-sm text-[#C9CED6]">Para um email. Se ainda não tiver conta, fica criada. Os vídeos gastam-se como os comprados.</p>
        <div class="mt-5 grid gap-3 md:grid-cols-[2fr_120px]">
            <label class="flex flex-col gap-1.5 text-sm text-[#C9CED6]">Email do cliente
                <input type="email" wire:model="email" autocomplete="off" placeholder="nome@agencia.pt"
                       class="min-h-[52px] px-4 rounded-[10px] bg-ivory text-navy text-[15px] border-0 focus:outline-none focus:ring-2 focus:ring-cyan">
            </label>
            <label class="flex flex-col gap-1.5 text-sm text-[#C9CED6]">Vídeos
                <input type="number" min="1" max="200" wire:model="quantidade" inputmode="numeric"
                       class="min-h-[52px] px-4 rounded-[10px] bg-ivory text-navy text-[15px] border-0 focus:outline-none focus:ring-2 focus:ring-cyan">
            </label>
        </div>
        @error('email') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        @error('quantidade') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        <label class="mt-3 flex flex-col gap-1.5 text-sm text-[#C9CED6]">Nota (só para si)
            <input type="text" wire:model="nota" maxlength="200" placeholder="Ex.: oferta de lançamento, reembolso, pago por transferência"
                   class="min-h-[48px] px-4 rounded-[10px] bg-navy-3 text-ivory text-[15px] border border-ivory/15 focus:outline-none focus:border-cyan">
        </label>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <label class="flex items-center gap-3 min-h-11 text-sm text-ivory">
                <input type="checkbox" wire:model="enviarLink" class="w-5 h-5 accent-cyan"> Enviar ao cliente um email com o link de entrada
            </label>
            <button type="submit" wire:loading.attr="disabled" wire:target="adicionar"
                    class="min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60">Adicionar vídeos</button>
        </div>
    </form>

    @if (session('feito'))
        <p class="mt-6 rounded-xl border border-cyan/40 bg-cyan/10 p-4 text-sm text-ink">{{ session('feito') }}</p>
    @endif

    {{-- The customers --}}
    <section class="mt-10">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="font-display text-3xl">Contas de clientes</h2>
            <input type="search" wire:model.live.debounce.300ms="procura" placeholder="Procurar email"
                   class="w-full sm:w-72 min-h-11 px-4 rounded-full bg-surface/60 border border-ink-soft/20 text-ink text-sm focus:outline-none focus:border-teal">
        </div>
        <div class="mt-4 flex flex-col gap-2">
            @forelse ($clientes as $c)
                <div class="grid gap-3 rounded-xl border border-ink-soft/15 bg-surface/40 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center" wire:key="cliente-{{ $c->id }}">
                    <div class="min-w-0">
                        <p class="truncate text-ink">{{ $c->email }}</p>
                        <p class="mt-0.5 font-mono text-[0.7rem] text-ink-faint">
                            desde {{ $c->desde?->format('d/m/Y') }} · {{ $c->videos }} {{ $c->videos === 1 ? 'vídeo feito' : 'vídeos feitos' }}
                            · pagou {{ number_format($c->pago / 100, 2, ',', ' ') }} €@if ($c->oferecidos) · {{ $c->oferecidos }} oferecidos @endif
                        </p>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="font-display text-3xl leading-none text-ink">{{ $c->creditos }}</span>
                        <span class="text-xs text-ink-faint">{{ $c->creditos === 1 ? 'vídeo disponível' : 'vídeos disponíveis' }}</span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('email', @js($c->email))" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })" class="min-h-11 px-4 rounded-full border border-ink-soft/30 text-sm text-ink hover:bg-surface/60">+ Adicionar</button>
                        @if ($c->creditos > 0)
                            <button type="button" wire:click="retirar({{ $c->id }})" wire:confirm="Retirar 1 vídeo a {{ $c->email }}?"
                                    class="min-h-11 px-4 rounded-full border border-ink-soft/30 text-sm text-ink-soft hover:bg-surface/60">− 1</button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-ink-soft">{{ $procura !== '' ? 'Nenhuma conta com esse email.' : 'Ainda não há contas de clientes.' }}</p>
            @endforelse
        </div>
    </section>
</div>
