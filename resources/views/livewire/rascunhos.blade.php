<div>
    <x-page-header
        eyebrow="Tomus · VI"
        title="Rascunhos e Agendamento"
        cota="655.5 · IAT · '26"
        lead="Conteúdo pronto a agendar — publicações marcadas como prontas, shorts e clips animados concluídos." />

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach (['todos' => 'Todos', 'pronto' => 'Prontos', 'agendado' => 'Agendados'] as $chave => $label)
            <button wire:click="$set('filtro', '{{ $chave }}')"
                    class="px-4 py-1.5 rounded-sm border font-mono text-xs transition
                           {{ $filtro === $chave ? 'bg-surface/70 border-ink-soft/30 text-ink' : 'border-ink-soft/15 text-ink-soft hover:text-ink' }}">
                {{ $label }} <span class="text-ink-faint">· {{ $contagem[$chave] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    @forelse ($itens as $item)
        <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-5 mb-4 shadow-engraved" wire:key="{{ $item['id'] }}">
            <div class="flex items-start justify-between gap-4">
                @if ($item['cover'])
                    <img src="{{ $item['cover'] }}" alt="capa" class="hidden sm:block w-16 h-20 object-cover rounded-sm border border-ink-soft/20 shrink-0">
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                        <x-badge tone="teal">{{ $item['kind'] }}</x-badge>
                        @if ($item['scheduled'])
                            <x-badge tone="good">✓ agendado · {{ \Illuminate\Support\Carbon::parse($item['scheduled'])->translatedFormat('d M Y') }}</x-badge>
                        @else
                            <x-badge tone="good">✓ pronto</x-badge>
                        @endif
                    </div>
                    <h3 class="font-display text-2xl text-ink leading-tight">{{ $item['title'] }}</h3>
                    @if ($item['excerpt'] !== '')
                        <p class="mt-1 text-ink-soft line-clamp-2">{{ $item['excerpt'] }}</p>
                    @endif
                    <div class="mt-1.5 font-mono text-[0.6rem] text-ink-faint">{{ $item['ref'] }}</div>
                </div>

                <button wire:click="remover('{{ $item['source'] }}', '{{ $item['ref'] }}')"
                        wire:confirm="Remover este item?"
                        class="shrink-0 text-ink-faint hover:text-bad px-2 text-lg" title="Remover">🗑</button>
            </div>

            {{-- Agendamento --}}
            <div class="mt-4 pt-4 border-t border-ink-soft/10 flex items-end gap-3 flex-wrap">
                @if ($item['scheduled'])
                    <button wire:click="desagendar('{{ $item['source'] }}', '{{ $item['ref'] }}')"
                            class="border border-ink-soft/25 text-ink-soft hover:text-ink rounded-sm px-4 py-1.5 font-mono text-xs transition">
                        Cancelar agendamento
                    </button>
                @else
                    <div>
                        <label class="eyebrow block mb-1">Publicar a</label>
                        <input type="date" wire:model="datas.{{ $item['id'] }}"
                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        @error('datas.'.$item['id']) <span class="block text-bad text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <button wire:click="agendar('{{ $item['source'] }}', '{{ $item['ref'] }}')"
                            class="bg-teal text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition">
                        Agendar
                    </button>
                @endif
            </div>
        </div>
    @empty
        <x-empty-state glyph="⌛" title="Nada pronto ainda"
            note="Marque publicações como prontas, ou gere shorts e clips animados — aparecem aqui prontos a agendar." />
    @endforelse
</div>
