<div>
    <x-page-header
        eyebrow="Tomus · VI"
        title="Finished"
        cota="655.5 · ACM · '26"
        lead="Content promoted from the studio — publish or schedule it to your channels via Blotato." />

    @php
        $platLabels = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'threads' => 'Threads'];
    @endphp

    @if ($aviso)
        <div class="mb-6 rounded-sm border border-teal/40 bg-teal/10 px-4 py-3 text-sm text-ink" wire:key="aviso">
            {{ $aviso }}
        </div>
    @endif

    @unless ($blotatoReady)
        <div class="mb-6 rounded-sm border border-ink-soft/25 bg-vellum/50 px-4 py-3 text-sm text-ink-soft">
            No Blotato API key set — add it in <a href="{{ route('definicoes') }}" class="text-teal underline">Settings</a> to publish.
        </div>
    @endunless

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach (['unpublished' => 'Unpublished', 'scheduled' => 'Scheduled', 'calendar' => 'Calendar', 'posted' => 'Posted'] as $chave => $label)
            <button wire:click="$set('aba', '{{ $chave }}')"
                    class="px-4 py-1.5 rounded-sm border font-mono text-xs transition
                           {{ $aba === $chave ? 'bg-surface/70 border-ink-soft/30 text-ink' : 'border-ink-soft/15 text-ink-soft hover:text-ink' }}">
                {{ $label }}
                @isset($contagem[$chave])<span class="text-ink-faint">· {{ $contagem[$chave] }}</span>@endisset
            </button>
        @endforeach
    </div>

    {{-- ── Unpublished ─────────────────────────────────────────────── --}}
    @if ($aba === 'unpublished')
        @forelse ($unpublished as $item)
            <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-5 mb-4 shadow-engraved" wire:key="{{ $item['id'] }}">
                <div class="flex items-start justify-between gap-4">
                    @if ($item['cover'])
                        <img src="{{ $item['cover'] }}" alt="cover" class="hidden sm:block w-16 h-20 object-cover rounded-sm border border-ink-soft/20 shrink-0">
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                            <x-badge tone="teal">{{ $item['kind'] }}</x-badge>
                            <x-badge tone="good">✓ finished</x-badge>
                        </div>
                        <h3 class="font-display text-2xl text-ink leading-tight">{{ $item['title'] }}</h3>
                        @if ($item['excerpt'] !== '')
                            <p class="mt-1 text-ink-soft line-clamp-2">{{ $item['excerpt'] }}</p>
                        @endif
                    </div>
                    <button wire:click="remover('{{ $item['source'] }}', '{{ $item['ref'] }}')"
                            wire:confirm="Remove this item?"
                            class="shrink-0 text-ink-faint hover:text-bad px-2 text-lg" title="Remove">🗑</button>
                </div>

                {{-- Where --}}
                <div class="mt-4 pt-4 border-t border-ink-soft/10">
                    <label class="eyebrow block mb-2">Where to post</label>
                    <div class="flex flex-wrap gap-2">
                        @php $selecionadas = is_array($plataformas[$item['id']] ?? null) ? $plataformas[$item['id']] : []; @endphp
                        @foreach ($platLabels as $plat => $label)
                            <label class="flex items-center gap-2 px-3 py-1.5 rounded-sm border border-ink-soft/20 cursor-pointer text-sm
                                          {{ in_array($plat, $selecionadas, true) ? 'bg-teal/10 border-teal/40 text-ink' : 'text-ink-soft' }}">
                                <input type="checkbox" value="{{ $plat }}" wire:model.live="plataformas.{{ $item['id'] }}" class="accent-teal">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('plataformas.'.$item['id']) <span class="block text-bad text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                {{-- DM for link (Zernio) --}}
                <div class="mt-4 pt-4 border-t border-ink-soft/10">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="dm.{{ $item['id'] }}.ativo" class="accent-teal" @disabled(! $zernioReady)>
                        <span class="eyebrow">DM for link</span>
                        <span class="font-mono text-[0.6rem] text-ink-faint">
                            @if ($zernioReady)
                                Instagram · a comment or DM with the word gets the link
                            @else
                                needs the Zernio key & ids (Settings)
                            @endif
                        </span>
                    </label>

                    @if ($dm[$item['id']]['ativo'] ?? false)
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="eyebrow block mb-1.5 text-[0.55rem]">Keyword</label>
                                <input type="text" wire:model.live.debounce.400ms="dm.{{ $item['id'] }}.keyword" placeholder="GUIDE"
                                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                                @error('dm.'.$item['id'].'.keyword') <span class="block text-bad text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="eyebrow block mb-1.5 text-[0.55rem]">Link they get</label>
                                <input type="url" wire:model="dm.{{ $item['id'] }}.link" placeholder="https://…"
                                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                                @error('dm.'.$item['id'].'.link') <span class="block text-bad text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            {{-- Blank fields send the placeholder, so what's shown is what goes out. --}}
                            <div class="sm:col-span-2">
                                <label class="eyebrow block mb-1.5 text-[0.55rem]">Call to action (added to the caption)</label>
                                <input type="text" wire:model="dm.{{ $item['id'] }}.cta" placeholder="{{ $this->dmPadrao($item['id'], 'cta') }}"
                                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink text-sm focus:border-teal focus:outline-none">
                            </div>
                            <div>
                                <label class="eyebrow block mb-1.5 text-[0.55rem]">DM they receive</label>
                                <input type="text" wire:model="dm.{{ $item['id'] }}.mensagem" placeholder="{{ $this->dmPadrao($item['id'], 'mensagem') }}"
                                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink text-sm focus:border-teal focus:outline-none">
                            </div>
                            <div>
                                <label class="eyebrow block mb-1.5 text-[0.55rem]">Public reply to the comment</label>
                                <input type="text" wire:model="dm.{{ $item['id'] }}.resposta" placeholder="{{ $this->dmPadrao($item['id'], 'resposta') }}"
                                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink text-sm focus:border-teal focus:outline-none">
                            </div>
                        </div>
                    @endif
                </div>

                {{-- When --}}
                <div class="mt-4 flex flex-wrap items-end gap-4">
                    <div>
                        <label class="eyebrow block mb-1.5">When</label>
                        <div class="flex gap-3 text-sm text-ink-soft">
                            @foreach (['now' => 'Post now', 'time' => 'At a time', 'slot' => 'Next free slot'] as $modo => $label)
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="{{ $modo }}" wire:model.live="quando.{{ $item['id'] }}" class="accent-teal">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @if (($quando[$item['id']] ?? 'now') === 'time')
                        <div>
                            <input type="datetime-local" wire:model="datas.{{ $item['id'] }}"
                                   class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                            @error('datas.'.$item['id']) <span class="block text-bad text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <button wire:click="publicar('{{ $item['source'] }}', '{{ $item['ref'] }}')"
                            wire:loading.attr="disabled" @disabled(! $blotatoReady)
                            class="bg-teal text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition disabled:opacity-40">
                        <span wire:loading.remove wire:target="publicar">
                            {{ ($quando[$item['id']] ?? 'now') === 'now' ? 'Post now' : 'Schedule' }}
                        </span>
                        <span wire:loading wire:target="publicar">Working…</span>
                    </button>
                </div>
            </div>
        @empty
            <x-empty-state glyph="✓" title="Nothing waiting"
                note="Send finished posts and clips here from the studio to publish them." />
        @endforelse
    @endif

    {{-- ── Scheduled ───────────────────────────────────────────────── --}}
    @if ($aba === 'scheduled')
        @forelse ($scheduled as $item)
            <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-5 mb-4 shadow-engraved" wire:key="sch_{{ $item['id'] }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                            <x-badge tone="teal">{{ $item['kind'] }}</x-badge>
                            @if ($item['scheduled_for'])
                                <x-badge tone="good">◷ {{ \Illuminate\Support\Carbon::parse($item['scheduled_for'])->translatedFormat('d M Y · H:i') }}</x-badge>
                            @else
                                <x-badge tone="good">◷ next free slot</x-badge>
                            @endif
                            @foreach ($item['plataformas'] as $p)
                                <x-badge tone="neutral">{{ $platLabels[$p] ?? ucfirst($p) }}</x-badge>
                            @endforeach
                        </div>
                        <h3 class="font-display text-2xl text-ink leading-tight">{{ $item['title'] }}</h3>
                    </div>
                    <button wire:click="desagendar('{{ $item['source'] }}', '{{ $item['ref'] }}')"
                            class="shrink-0 border border-ink-soft/25 text-ink-soft hover:text-ink rounded-sm px-4 py-1.5 font-mono text-xs transition">
                        Cancel
                    </button>
                </div>
            </div>
        @empty
            <x-empty-state glyph="◷" title="Nothing scheduled" note="Scheduled posts show here until they go live." />
        @endforelse
    @endif

    {{-- ── Calendar ────────────────────────────────────────────────── --}}
    @if ($aba === 'calendar')
        <div class="flex items-center justify-between mb-4">
            <button wire:click="mesAnterior" class="border border-ink-soft/25 text-ink-soft hover:text-ink rounded-sm px-3 py-1 font-mono text-xs">← Prev</button>
            <div class="font-display text-xl text-ink">{{ \Illuminate\Support\Carbon::parse($mes.'-01')->translatedFormat('F Y') }}</div>
            <button wire:click="mesSeguinte" class="border border-ink-soft/25 text-ink-soft hover:text-ink rounded-sm px-3 py-1 font-mono text-xs">Next →</button>
        </div>
        <div class="grid grid-cols-7 gap-1">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                <div class="eyebrow text-center pb-1">{{ $d }}</div>
            @endforeach
            @foreach ($diasDoMes as $dia)
                <div class="min-h-[5rem] border border-ink-soft/10 rounded-sm p-1 {{ $dia ? 'bg-vellum/40' : '' }}">
                    @if ($dia)
                        <div class="font-mono text-[0.6rem] text-ink-faint">{{ (int) \Illuminate\Support\Carbon::parse($dia)->format('d') }}</div>
                        @foreach ($calendario[$dia] ?? [] as $item)
                            <div class="mt-0.5 truncate rounded-sm bg-teal/15 border border-teal/30 px-1 py-0.5 text-[0.6rem] text-ink"
                                 title="{{ $item['title'] }} · {{ \Illuminate\Support\Carbon::parse($item['scheduled_for'])->format('H:i') }}">
                                {{ \Illuminate\Support\Carbon::parse($item['scheduled_for'])->format('H:i') }} {{ $item['title'] }}
                            </div>
                        @endforeach
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Posted ──────────────────────────────────────────────────── --}}
    @if ($aba === 'posted')
        @forelse ($posted as $item)
            <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-5 mb-4" wire:key="pub_{{ $item['id'] }}">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <x-badge tone="teal">{{ $item['kind'] }}</x-badge>
                    @if ($item['scheduled_for'])
                        <x-badge tone="good">✓ posted · {{ \Illuminate\Support\Carbon::parse($item['scheduled_for'])->translatedFormat('d M Y · H:i') }}</x-badge>
                    @else
                        <x-badge tone="good">✓ posted</x-badge>
                    @endif
                    @foreach ($item['plataformas'] as $p)
                        <x-badge tone="neutral">{{ $platLabels[$p] ?? ucfirst($p) }}</x-badge>
                    @endforeach
                </div>
                <h3 class="font-display text-2xl text-ink leading-tight">{{ $item['title'] }}</h3>
            </div>
        @empty
            <x-empty-state glyph="✓" title="Nothing posted yet" note="Published posts show here." />
        @endforelse
    @endif
</div>
