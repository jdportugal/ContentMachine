<div>
    <x-page-header
        eyebrow="Tomus · VI"
        title="Rascunhos e Agendamento"
        cota="655.5 · IAT · '26"
        lead="Tudo o que é gerado nas oficinas aparece aqui como rascunho, pronto a agendar." />

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach (['todos' => 'Todos', 'rascunho' => 'Rascunhos', 'agendado' => 'Agendados'] as $chave => $label)
            <button wire:click="$set('filtro', '{{ $chave }}')"
                    class="px-4 py-1.5 rounded-sm border font-mono text-xs transition
                           {{ $filtro === $chave ? 'bg-surface/70 border-ink-soft/30 text-ink' : 'border-ink-soft/15 text-ink-soft hover:text-ink' }}">
                {{ $label }} <span class="text-ink-faint">· {{ $contagem[$chave] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    @forelse ($rascunhos as $nota)
        @php
            $tipo = $nota->get('tipo', 'peça');
            $tipoLabel = config('contentmachine.publicacoes.tipos.'.$tipo.'.label', $tipo);
            $cartoes = (int) $nota->get('cartoes', 0);
            $imagens = (array) $nota->get('imagens', []);
            $capa = $imagens[0] ?? null;
            $plataforma = $nota->get('plataforma');
            $meta = $plataforma ? config('contentmachine.plataformas_meta.'.$plataforma) : null;
            $agendado = $nota->get('estado') === 'agendado';
        @endphp
        <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-5 mb-4 shadow-engraved" wire:key="{{ $nota->slug() }}">
            <div class="flex items-start justify-between gap-4">
                @if ($capa)
                    <img src="{{ asset($capa) }}" alt="capa" class="hidden sm:block w-16 h-20 object-cover rounded-sm border border-ink-soft/20 shrink-0">
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                        <x-badge tone="teal">{{ $tipoLabel }}</x-badge>
                        @if ($cartoes > 1)<x-badge tone="leather">{{ $cartoes }} cartões</x-badge>@endif
                        @if ($meta)<x-badge tone="leather" style="color: {{ $meta['cor'] }}">{{ $meta['label'] }}</x-badge>@endif
                        @if ($agendado)
                            <x-badge tone="good">✓ agendado · {{ \Illuminate\Support\Carbon::parse($nota->get('agendado_para'))->translatedFormat('d M Y') }}</x-badge>
                        @else
                            <x-badge tone="warn">rascunho</x-badge>
                        @endif
                    </div>
                    <h3 class="font-display text-2xl text-ink leading-tight">{{ $nota->title() }}</h3>
                    <p class="mt-1 text-ink-soft line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags($nota->html()), 160) }}</p>
                    <div class="mt-1.5 font-mono text-[0.6rem] text-ink-faint">{{ $nota->path }}</div>
                </div>

                <button wire:click="remover('{{ $nota->path }}')"
                        wire:confirm="Remover este rascunho do vault?"
                        class="shrink-0 text-ink-faint hover:text-bad px-2 text-lg" title="Remover">🗑</button>
            </div>

            {{-- Agendamento --}}
            <div class="mt-4 pt-4 border-t border-ink-soft/10 flex items-end gap-3 flex-wrap">
                @if ($agendado)
                    <button wire:click="desagendar('{{ $nota->path }}')"
                            class="border border-ink-soft/25 text-ink-soft hover:text-ink rounded-sm px-4 py-1.5 font-mono text-xs transition">
                        Cancelar agendamento
                    </button>
                @else
                    <div>
                        <label class="eyebrow block mb-1">Publicar a</label>
                        <input type="date" wire:model="datas.{{ $nota->slug() }}"
                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        @error('datas.'.$nota->slug()) <span class="block text-bad text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <button wire:click="agendar('{{ $nota->path }}')"
                            class="bg-teal text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition">
                        Agendar
                    </button>
                @endif
            </div>
        </div>
    @empty
        <x-empty-state glyph="⌛" title="Nenhum rascunho ainda"
            note="Crie peças em Publicações — aparecem aqui automaticamente, prontas a agendar." />
    @endforelse
</div>
