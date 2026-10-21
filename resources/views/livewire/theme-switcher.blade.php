<div x-data="{ aberto: false, aNovo: false }" class="relative">
    <button type="button" @click="aberto = ! aberto" :aria-expanded="aberto"
            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg border border-ink-soft/20 bg-surface/40 hover:border-teal/50 text-left">
        <span class="min-w-0">
            <span class="eyebrow block text-[0.6rem]">Tema</span>
            <span class="block truncate font-display text-lg leading-tight">{{ $atual->name }}</span>
        </span>
        <span aria-hidden="true" class="text-ink-faint text-xs">▾</span>
    </button>

    <div x-show="aberto" x-cloak @click.outside="aberto = false; aNovo = false"
         class="absolute z-50 left-0 right-0 mt-1 rounded-lg border border-ink-soft/20 bg-vellum shadow-engraved overflow-hidden">
        <ul class="max-h-64 overflow-y-auto py-1">
            @foreach ($temas as $tema)
                <li>
                    <button type="button" wire:click="mudar('{{ $tema->slug }}')"
                            @class(['w-full text-left px-3 min-h-11 flex items-center justify-between gap-2 hover:bg-surface',
                                    'text-teal' => $tema->slug === $atual->slug])>
                        <span class="truncate">{{ $tema->name }}</span>
                        @if ($tema->slug === $atual->slug) <span aria-hidden="true">✓</span> @endif
                    </button>
                </li>
            @endforeach
        </ul>
        <div class="border-t border-ink-soft/15 p-2">
            <button type="button" x-show="! aNovo" @click="aNovo = true; $nextTick(() => $refs.nome.focus())"
                    class="w-full text-left px-1 min-h-11 text-sm text-teal">＋ Novo tema</button>
            <form x-show="aNovo" wire:submit="criar" class="flex flex-col gap-2">
                <label class="text-xs text-ink-soft">Nome do novo tema
                    <input x-ref="nome" type="text" wire:model="novo" maxlength="60"
                           class="mt-1 w-full px-3 min-h-11 rounded-md bg-papyrus border border-ink-soft/25 text-ink text-sm focus:outline-none focus:border-teal">
                </label>
                @error('novo') <p class="text-xs text-bad">{{ $message }}</p> @enderror
                <button type="submit" class="min-h-11 rounded-full bg-teal-deep text-papyrus text-sm font-semibold">Criar e editar o design</button>
            </form>
        </div>
    </div>
</div>
