@php
    $cartao = 'rounded-[20px] bg-navy text-ivory p-6 lg:p-8 shadow-[0_12px_32px_rgba(14,22,38,0.18)] flex flex-col gap-4';
    $rotulo = 'flex flex-col gap-1.5 text-[13px] text-[#C9CED6]';
    $campo = 'min-h-[52px] px-4 rounded-[10px] border-0 bg-ivory text-navy text-[15px] focus:outline-none focus:ring-2 focus:ring-cyan';
    $botao = 'min-h-[52px] rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60';
    $erro = '-mt-2 text-[13px] text-coral';
@endphp
<x-imoplanta.pagina>
    <x-slot:direita>
        <a href="{{ route('home') }}" class="hover:text-cyan">Voltar ao site</a>
    </x-slot:direita>

    <x-imoplanta.colunas>
        <x-slot:intro>
            <div class="flex flex-col gap-3.5 lg:pt-6">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-0.5 bg-coral"></span>
                    <span class="text-[11px] tracking-[.3em] text-[#0478A0]">A SUA CONTA</span>
                </div>
                <h1 class="font-display text-[40px] lg:text-[56px] font-medium leading-[1.05] text-navy">Entrar na Imocena</h1>
                <p class="max-w-md text-[15px] lg:text-[17px] leading-relaxed text-[#4A5263]">Os seus vídeos, modelos 3D e fotografias, num só sítio. Entre com o seu email, sem palavra-passe.</p>
            </div>
        </x-slot:intro>

        <div class="flex flex-col gap-4">
            @if ($aviso)
                <p role="status" class="rounded-xl bg-coral/10 border border-coral/40 px-4 py-3 text-sm">{{ $aviso }}</p>
            @endif

            @if ($enviadoPara)
                <section class="{{ $cartao }}">
                    <div class="w-14 h-14 rounded-full bg-cyan/15 flex items-center justify-center">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                    </div>
                    <h2 class="font-display text-[32px] font-medium leading-tight text-ivory">Veja o seu email</h2>
                    <p role="status" class="text-sm leading-relaxed text-[#C9CED6]">Se <strong class="font-semibold text-ivory">{{ $enviadoPara }}</strong> tiver conta, enviámos um link para entrar. O link vale 30 minutos e só funciona uma vez.</p>
                    <p class="text-[13px] text-[#AEB5C1]">Não chegou? Veja a pasta de spam ou promoções.</p>
                    <button type="button" wire:click="enviarLink" wire:loading.attr="disabled" class="min-h-12 rounded-full border-[1.5px] border-ivory/50 text-sm font-semibold hover:bg-ivory/5 disabled:opacity-60">Enviar outra vez</button>
                    <button type="button" wire:click="outroEmail" class="self-start text-[13px] text-[#C9CED6] underline">Usar outro email</button>
                </section>

            @elseif ($comPalavraPasse)
                <form wire:submit="entrar" class="{{ $cartao }}">
                    <h2 class="font-display text-[30px] font-medium leading-tight text-ivory">Entrar com palavra-passe</h2>
                    <label class="{{ $rotulo }}">Email
                        <input type="email" wire:model="email" required autocomplete="username" class="{{ $campo }}">
                    </label>
                    @error('email') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                    <label class="{{ $rotulo }}">Palavra-passe
                        <input type="password" wire:model="password" required autocomplete="current-password" class="{{ $campo }}">
                    </label>
                    @error('password') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                    <label class="flex items-center gap-2 text-xs text-[#AEB5C1] select-none">
                        <input type="checkbox" wire:model="remember" class="w-3.5 h-3.5 accent-cyan"> Manter sessão iniciada
                    </label>
                    <button type="submit" wire:loading.attr="disabled" wire:target="entrar" class="{{ $botao }}">
                        <span wire:loading.remove wire:target="entrar">Entrar</span>
                        <span wire:loading wire:target="entrar">A entrar…</span>
                    </button>
                    <button type="button" wire:click="$set('comPalavraPasse', false)" class="self-start text-[13px] text-[#C9CED6] underline">Receber antes um link por email</button>
                </form>

            @else
                <form wire:submit="enviarLink" class="{{ $cartao }}" novalidate>
                    <h2 class="font-display text-[30px] font-medium leading-tight text-ivory">Receba um link para entrar</h2>
                    <label class="{{ $rotulo }}">O seu email
                        <input type="email" wire:model="email" autocomplete="email" required autofocus class="{{ $campo }}">
                    </label>
                    @error('email') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="enviarLink" class="{{ $botao }}">
                        <span wire:loading.remove wire:target="enviarLink">Enviar link →</span>
                        <span wire:loading wire:target="enviarLink">A enviar…</span>
                    </button>
                    <p class="text-xs text-[#AEB5C1]">Ainda não tem conta? Escreva o seu email: criamos a conta e entra logo.</p>
                </form>
                <button type="button" wire:click="$set('comPalavraPasse', true)" class="self-start text-[13px] text-[#4A5263] underline">Entrar com palavra-passe</button>
            @endif
        </div>
    </x-imoplanta.colunas>
</x-imoplanta.pagina>
