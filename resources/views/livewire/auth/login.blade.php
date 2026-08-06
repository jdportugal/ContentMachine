<div>
    <div class="text-center mb-6">
        <span class="font-display text-2xl text-teal tracking-wide" style="letter-spacing:.06em">Brand Machine</span>
        <span class="block eyebrow mt-1 text-[0.55rem]">AI Content Machines</span>
    </div>

    <form wire:submit="entrar"
          class="bg-vellum/40 border border-ink-soft/15 rounded-sm shadow-engraved px-6 py-6 space-y-4">
        <div>
            <label for="email" class="eyebrow block mb-1.5">Email</label>
            <input id="email" type="email" wire:model="email" required autofocus autocomplete="username"
                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
            @error('email') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="eyebrow block mb-1.5">Password</label>
            <input id="password" type="password" wire:model="password" required autocomplete="current-password"
                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
            @error('password') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 font-mono text-[0.65rem] text-ink-soft select-none">
            <input type="checkbox" wire:model="remember" class="accent-teal">
            Keep me signed in
        </label>

        <button type="submit"
                class="w-full font-mono text-xs uppercase tracking-wider px-3 py-2.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50"
                wire:loading.attr="disabled" wire:target="entrar">
            <span wire:loading.remove wire:target="entrar">Sign in</span>
            <span wire:loading wire:target="entrar">Signing in…</span>
        </button>

        <p class="text-center font-mono text-[0.65rem] text-ink-faint">
            <a href="{{ route('register') }}" class="text-teal underline underline-offset-2">Create an account</a>
        </p>
    </form>
</div>
