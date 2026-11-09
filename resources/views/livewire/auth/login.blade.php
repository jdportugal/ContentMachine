<div>
    <div class="text-center mb-6">
        <span class="font-display text-4xl text-ink">Imocena</span>
        <span class="block eyebrow mt-1 text-[0.6rem]">Vídeos de imóveis</span>
    </div>

    <form wire:submit="entrar"
          class="bg-vellum/40 border border-ink-soft/15 rounded-sm shadow-engraved px-6 py-6 space-y-4">
        <div>
            <label for="email" class="eyebrow block mb-1.5">Email</label>
            <input id="email" type="email" wire:model="email" required autofocus autocomplete="username"
                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
            @error('email') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
        </div>

        @if ($linkEnviado)
            <p class="font-mono text-xs text-teal leading-relaxed">Se existir uma conta com este email, enviámos um link de acesso. Abra o email e clique no botão (válido 30 minutos).</p>
        @endif
        <button type="button" wire:click="enviarLink"
                class="w-full font-mono text-xs uppercase tracking-wider px-3 py-2.5 rounded-sm bg-teal text-papyrus hover:brightness-110 transition disabled:opacity-50"
                wire:loading.attr="disabled" wire:target="enviarLink">
            <span wire:loading.remove wire:target="enviarLink">Enviar link de acesso por email</span>
            <span wire:loading wire:target="enviarLink">A enviar…</span>
        </button>
        <p class="text-center font-mono text-[0.6rem] text-ink-faint">Clientes: sem palavra-passe, entre com o link que enviamos para o seu email.</p>

        <details class="pt-2 border-t border-ink-soft/15">
            <summary class="cursor-pointer font-mono text-[0.65rem] text-ink-soft py-2">Entrar com palavra-passe (equipa Imocena)</summary>
        <div class="space-y-4 pt-2">
        <div>
            <label for="password" class="eyebrow block mb-1.5">Palavra-passe</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password"
                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
            @error('password') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 font-mono text-[0.65rem] text-ink-soft select-none">
            <input type="checkbox" wire:model="remember" class="accent-teal">
            Manter sessão iniciada
        </label>

        <button type="submit"
                class="w-full font-mono text-xs uppercase tracking-wider px-3 py-2.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50"
                wire:loading.attr="disabled" wire:target="entrar">
            <span wire:loading.remove wire:target="entrar">Entrar</span>
            <span wire:loading wire:target="entrar">A entrar…</span>
        </button>
        </div>
        </details>

        <p class="text-center font-mono text-[0.65rem] text-ink-faint">
            <a href="{{ route('home') }}" class="text-teal underline underline-offset-2">Voltar ao site</a>
        </p>
    </form>
</div>
