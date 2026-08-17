<div @if ($this->ocupado) wire:poll.4s @endif>
    <x-page-header
        eyebrow="Tomus · VI"
        title="Video Editor"
        cota="778.9 · ACM · '26"
        lead="Cuts a raw take down to what you meant to say — dead air and repeated lines gone, camera and screen feed cut identically." />

    @if (! $this->edicao)
        {{-- ═══════════════════ NEW EDIT ═══════════════════ --}}
        <x-panel eyebrow="New" title="Edit a take" glyph="✂">
            <form wire:submit="criar" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="eyebrow block">Camera recording <span class="text-bad">*</span></label>
                        <input type="file" wire:model="camera" accept="video/*"
                               class="w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-3 file:rounded-sm file:border file:border-ink-soft/20 file:bg-surface/40 file:text-ink-soft" />
                        <p class="font-mono text-[0.55rem] text-ink-faint">Carries the voice — this is what gets transcribed.</p>
                        @error('camera') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-2">
                        <label class="eyebrow block">Screen feed <span class="text-ink-faint">(optional)</span></label>
                        <input type="file" wire:model="screen" accept="video/*"
                               class="w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-3 file:rounded-sm file:border file:border-ink-soft/20 file:bg-surface/40 file:text-ink-soft" />
                        <p class="font-mono text-[0.55rem] text-ink-faint">Must start at the same moment — it's cut to the same ranges.</p>
                        @error('screen') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label class="eyebrow block">Title</label>
                        <input type="text" wire:model="titulo" placeholder="Tuesday's take"
                               class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none" />
                    </div>
                    <div class="space-y-2">
                        <label class="eyebrow block">Language</label>
                        <select wire:model="lingua" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none">
                            <option value="pt">Portuguese</option>
                            <option value="en">English</option>
                            <option value="es">Spanish</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="eyebrow block">Cut pauses longer than (s)</label>
                        <input type="number" wire:model="limiteSilencio" step="0.1" min="0.2" max="5"
                               class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none" />
                        @error('limiteSilencio') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="criar,camera,screen"
                        class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="criar,camera,screen">✦ Analyse the take</span>
                    <span wire:loading wire:target="criar,camera,screen">uploading…</span>
                </button>
            </form>
        </x-panel>

        @if ($this->edicoes->isNotEmpty())
            <div class="mt-8">
                <div class="eyebrow mb-4">Your edits · {{ $this->edicoes->count() }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach ($this->edicoes as $e)
                        <x-panel wire:key="edit-{{ $e->id() }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="font-display text-xl text-ink leading-tight">{{ $e->title }}</h2>
                                    <p class="font-mono text-[0.6rem] text-ink-faint mt-1">
                                        {{ $e->status }}@if ($e->get('duration')) · {{ gmdate('i:s', (int) $e->get('duration')) }}@endif
                                    </p>
                                    @if ($e->status === 'failed')
                                        <p class="text-bad text-xs mt-2">{{ \Illuminate\Support\Str::limit($e->error, 160) }}</p>
                                    @endif
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <button type="button" wire:click="abrir('{{ $e->id() }}')"
                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Open</button>
                                    <button type="button" wire:click="apagar('{{ $e->id() }}')" wire:confirm="Delete this edit and its files?"
                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕</button>
                                </div>
                            </div>
                        </x-panel>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        {{-- ═══════════════════ ONE EDIT ═══════════════════ --}}
        @php($e = $this->edicao)
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to edits</button>

        <x-panel eyebrow="Take" title="{{ $e->title }}" glyph="✂">
            @if (in_array($e->status, ['pending', 'analysing'], true))
                <div class="py-10 text-center">
                    <div class="h-1 w-32 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-teal/60 animate-pulse w-2/3"></div></div>
                    <p class="font-mono text-[0.62rem] text-ink-faint mt-3">Transcribing and looking for cuts… this takes a while on a long take.</p>
                </div>
            @elseif ($e->status === 'failed')
                <div class="py-6 text-center">
                    <div class="text-bad text-2xl">✕</div>
                    <p class="font-mono text-xs text-bad/90 mt-2">{{ $e->error }}</p>
                    <button type="button" wire:click="reanalisar" class="mt-4 font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">↻ Try again</button>
                </div>
            @else
                {{-- summary --}}
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mb-5 font-mono text-[0.65rem]">
                    <span class="text-ink-soft">original <span class="text-ink">{{ gmdate('i:s', (int) $this->resumo['total']) }}</span></span>
                    <span class="text-teal">after cuts <span class="text-ink">{{ gmdate('i:s', (int) $this->resumo['kept']) }}</span></span>
                    <span class="text-ink-faint">removed {{ gmdate('i:s', (int) $this->resumo['cut']) }}</span>
                </div>

                <div class="flex flex-wrap gap-2 mb-5">
                    @if (in_array($e->status, ['review', 'done'], true))
                        <button type="button" wire:click="aprovar"
                                class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                            ✓ {{ $e->status === 'done' ? 'Re-render with these cuts' : 'Approve & render' }}
                        </button>
                    @endif
                    <button type="button" wire:click="limparCortes"
                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-ink transition">↺ Keep everything</button>
                    <button type="button" wire:click="reanalisar"
                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">↻ Re-analyse</button>
                </div>

                @if ($e->status === 'rendering')
                    <p class="font-mono text-[0.62rem] text-teal animate-pulse mb-5">Cutting both tracks… this refreshes itself.</p>
                @endif

                {{-- the transcript IS the timeline --}}
                <div class="border border-ink-soft/15 rounded-sm bg-surface/20 p-4 max-h-[55vh] overflow-y-auto leading-relaxed">
                    @forelse ($this->linhas as $l)
                        @if ($l['gap'] > 0)
                            <span class="font-mono text-[0.55rem] text-teal/70 mx-1" title="{{ $l['gap'] }}s of silence removed">⋯{{ $l['gap'] }}s</span>
                        @endif
                        <button type="button" wire:click="alternarSegmento({{ $l['i'] }})" wire:key="seg-{{ $l['i'] }}"
                                title="{{ $l['removed'] ? 'Removed ('.$l['reason'].') — click to keep' : 'Click to cut' }}"
                                class="text-left transition rounded-sm px-0.5
                                       {{ $l['removed']
                                            ? 'line-through decoration-2 opacity-45 '.($l['reason'] === 'duplicate' ? 'decoration-gold text-gold/80' : 'decoration-ink-soft text-ink-faint')
                                            : 'text-ink hover:bg-teal/10' }}">{{ $l['text'] }}</button>
                    @empty
                        <p class="font-mono text-xs text-ink-faint">No transcript yet.</p>
                    @endforelse
                </div>
                <p class="font-mono text-[0.55rem] text-ink-faint mt-2">
                    click any sentence to cut or restore it · <span class="text-gold">gold</span> = repeated take · <span class="text-teal">⋯</span> = silence removed
                </p>

                {{-- results --}}
                @if ($e->status === 'done' && $e->get('outputs'))
                    <div class="mt-6 pt-5 border-t border-ink-soft/10">
                        <div class="eyebrow mb-3">Edited files</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ((array) $e->get('outputs') as $role => $path)
                                <a href="{{ route('video-editor.media', [$e->id(), $role]) }}?download=1"
                                   class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">⤓ {{ str_replace('_', ' ', $role) }}</a>
                            @endforeach
                        </div>

                        {{-- step 2 --}}
                        @if ($e->get('screen_path'))
                            <div class="mt-6">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="eyebrow">Effects on the screen feed</div>
                                        <p class="text-ink-soft text-sm mt-1">Picks the moments worth a graphic, builds each in the brand's style, and lays them over the screen recording.</p>
                                    </div>
                                    <button type="button" wire:click="gerarSfx" @disabled($e->get('sfx_status') === 'working')
                                            class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50 shrink-0">
                                        {{ $e->get('sfx_status') === 'working' ? 'working…' : '✦ Add effects' }}
                                    </button>
                                </div>

                                @if ($e->get('sfx_error'))
                                    <p class="text-bad text-xs mt-3">{{ $e->get('sfx_error') }}</p>
                                @elseif ($e->get('sfx_status') === 'none')
                                    <p class="font-mono text-[0.62rem] text-ink-faint mt-3">Nothing in this take called for a graphic.</p>
                                @elseif ($e->get('sfx'))
                                    <ul class="mt-3 space-y-2">
                                        @foreach ((array) $e->get('sfx') as $s)
                                            <li class="font-mono text-[0.62rem] text-ink-soft">
                                                <span class="text-teal">{{ gmdate('i:s', (int) $s['at']) }}</span>
                                                · {{ $s['brief'] }}
                                                <span class="text-ink-faint">— over “{{ \Illuminate\Support\Str::limit($s['over'], 60) }}”</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        </x-panel>
    @endif
</div>
