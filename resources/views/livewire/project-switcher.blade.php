<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-sm border border-ink-soft/20 bg-surface/40 hover:border-ink-soft/40 transition text-left">
        <span class="min-w-0">
            <span class="block eyebrow text-[0.5rem] text-ink-faint">Project</span>
            <span class="block font-display text-sm text-ink truncate">{{ $current->name }}</span>
        </span>
        <span class="font-mono text-[0.6rem] text-ink-faint">▾</span>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity
         class="absolute left-0 right-0 mt-1 z-50 rounded-sm border border-ink-soft/20 bg-vellum shadow-engraved overflow-hidden">
        @foreach ($projects as $p)
            <button type="button" wire:click="trocar('{{ $p->slug }}')" @click="open = false"
                    class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left transition
                           {{ $p->slug === $current->slug ? 'bg-surface/70 text-ink' : 'text-ink-soft hover:bg-surface/40 hover:text-ink' }}">
                <span class="font-display text-sm truncate">{{ $p->name }}</span>
                <span class="font-mono text-[0.55rem] uppercase text-ink-faint shrink-0">{{ $p->language }}{{ $p->slug === $current->slug ? ' ●' : '' }}</span>
            </button>
        @endforeach

        <div class="border-t border-ink-soft/15">
            @if ($creating)
                <form wire:submit="criar" @click.stop class="p-3 space-y-2">
                    <input type="text" wire:model="newName" placeholder="Project name" autofocus
                           class="w-full bg-surface/50 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-sm text-ink focus:border-teal/50 focus:outline-none">
                    @error('newName') <p class="text-bad text-[0.6rem]">{{ $message }}</p> @enderror
                    <select wire:model="newLanguage"
                            class="w-full bg-surface/50 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-sm text-ink focus:border-teal/50 focus:outline-none">
                        <option value="en">English</option>
                        <option value="pt">Português</option>
                    </select>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="font-display text-sm px-3 py-1 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Create</button>
                        <button type="button" wire:click="$set('creating', false)" class="font-mono text-[0.6rem] text-ink-soft hover:text-ink">cancel</button>
                    </div>
                </form>
            @else
                <button type="button" wire:click="$set('creating', true)" @click.stop
                        class="w-full px-3 py-2 text-left font-mono text-[0.62rem] text-teal hover:bg-surface/40 transition">＋ New project</button>
            @endif
        </div>
    </div>
</div>
