<div>
    <div class="text-center mb-6">
        <span class="font-display text-2xl text-teal tracking-wide" style="letter-spacing:.06em">Brand Machine</span>
        <span class="block eyebrow mt-1 text-[0.55rem]">
            {{ $this->primeiroUtilizador ? 'Create the first account' : 'Create an account' }}
        </span>
    </div>

    @if (! $this->aberto)
        <div class="bg-vellum/40 border border-ink-soft/15 rounded-sm shadow-engraved px-6 py-6 text-center">
            <p class="text-ink-soft font-mono text-sm">Registration is closed on this install.</p>
            <p class="text-ink-faint font-mono text-[0.65rem] mt-2">
                Set <span class="text-teal">REGISTRATION_CODE</span> to invite someone.
            </p>
            <a href="{{ route('login') }}" class="inline-block mt-4 font-mono text-[0.7rem] text-teal underline underline-offset-2">Back to sign in</a>
        </div>
    @else
        <form wire:submit="registar"
              class="bg-vellum/40 border border-ink-soft/15 rounded-sm shadow-engraved px-6 py-6 space-y-4">
            @if ($this->primeiroUtilizador)
                <p class="text-ink-soft font-mono text-[0.65rem] leading-relaxed">
                    No account exists yet. This one becomes the owner of the install.
                </p>
            @endif

            <div>
                <label for="name" class="eyebrow block mb-1.5">Name</label>
                <input id="name" type="text" wire:model="name" required autofocus autocomplete="name"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                @error('name') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="eyebrow block mb-1.5">Email</label>
                <input id="email" type="email" wire:model="email" required autocomplete="username"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                @error('email') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="eyebrow block mb-1.5">Password</label>
                <input id="password" type="password" wire:model="password" required autocomplete="new-password" placeholder="at least 12 characters"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                @error('password') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="eyebrow block mb-1.5">Confirm password</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
            </div>

            @if ($this->exigeCodigo)
                <div>
                    <label for="codigo" class="eyebrow block mb-1.5">Registration code</label>
                    <input id="codigo" type="text" wire:model="codigo" required autocomplete="off"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    @error('codigo') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
                </div>
            @endif

            <button type="submit"
                    class="w-full font-mono text-xs uppercase tracking-wider px-3 py-2.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50"
                    wire:loading.attr="disabled" wire:target="registar">
                <span wire:loading.remove wire:target="registar">Create account</span>
                <span wire:loading wire:target="registar">Creating…</span>
            </button>

            <p class="text-center font-mono text-[0.65rem] text-ink-faint">
                Already have an account?
                <a href="{{ route('login') }}" class="text-teal underline underline-offset-2">Sign in</a>
            </p>
        </form>
    @endif
</div>
