<div @if ($this->sfxBusy || $this->showreelBusy) wire:poll.3s @endif>
    <x-page-header
        eyebrow="Tomus · IV"
        title="SFX Studio"
        cota="741.5 · ACM · '26"
        lead="The motion vocabulary the renderer can produce — create effects, attach sounds, and mark which ones open a video." />

    @include('livewire.partials.studio-tabs')

    @if ($this->detail)
        {{-- ══════════════════════════ DETAIL VIEW (one effect) ══════════════════════════ --}}
        @php($d = $this->detail)
        <a href="{{ route('clips-animados.sfx') }}" wire:navigate class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6 inline-block">← back to SFX</a>

        <x-panel eyebrow="{{ $d['kind'] === 'builtin' ? 'Built-in effect' : 'Effect' }}" title="{{ $d['label'] }}" glyph="✷">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- big preview, in the box you pick. One component serves all three
                     formats — this renders the SAME effect in each frame so you can
                     see how it lays itself out (half = top half of an overlay clip). --}}
                <div>
                <div class="flex flex-wrap gap-1.5 mb-2">
                    @foreach (\App\Services\Clips\EffectLibrary::FORMATS as $fmt => $rotulo)
                        <button type="button" wire:click="verFormato('{{ $fmt }}')"
                                class="font-mono text-[0.6rem] px-2.5 py-1 rounded-sm border transition
                                       {{ $formato === $fmt ? 'border-teal/50 text-teal bg-teal/10' : 'border-ink-soft/20 text-ink-faint hover:text-ink' }}">
                            {{ $rotulo }}@unless ($d['formatos'][$fmt] ?? false)<span class="opacity-60"> ○</span>@endunless
                        </button>
                    @endforeach
                </div>
                <div class="relative {{ ['portrait' => 'aspect-[9/16]', 'half' => 'aspect-[9/8]', 'landscape' => 'aspect-video'][$formato] ?? 'aspect-[9/16]' }} max-h-[60vh] mx-auto rounded-sm overflow-hidden bg-black/60 flex items-center justify-center w-full">
                    @if (! ($d['formatos'][$formato] ?? false) && ($d['status'] ?? 'active') !== 'failed')
                        <div class="text-center">
                            <div class="h-1 w-20 mx-auto bg-surface/40 rounded-full overflow-hidden">
                                <div class="h-full bg-teal/60 animate-pulse w-2/3"></div>
                            </div>
                            <p class="font-mono text-[0.6rem] text-ink-faint mt-3">Rendering the {{ strtolower(\App\Services\Clips\EffectLibrary::FORMATS[$formato]) }} preview…</p>
                        </div>
                    @elseif (($d['status'] ?? null) === 'failed')
                        <div class="p-3 text-center">
                            <div class="text-bad text-2xl">✕</div>
                            <p class="font-mono text-[0.6rem] text-bad/90 mt-2">{{ \Illuminate\Support\Str::limit($d['error'] ?? 'Generation failed', 200) }}</p>
                        </div>
                    @elseif (isset($sfxReady[$d['slug']]) && ($d['status'] ?? 'active') !== 'pending')
                        {{-- ?v= and the key both carry the render's version: same slug + same
                             design = same URL, so without it the browser keeps the video it
                             already has and a rewritten effect looks unchanged. --}}
                        <video class="w-full h-full object-cover" wire:key="prev-{{ $d['slug'] }}-{{ $formato }}-{{ $d['formatos'][$formato] ?? 0 }}"
                               src="{{ route('clips-animados.sfx-preview', ['slug' => $d['slug'], 'format' => $formato, 'v' => $d['formatos'][$formato] ?? 0]) }}" autoplay loop muted playsinline></video>
                        @if (in_array($d['status'] ?? null, ['updating'], true) || in_array($d['override'] ?? null, ['pending', 'updating'], true))
                            <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                <p class="font-mono text-xs text-teal animate-pulse">Updating…</p>
                            </div>
                        @endif
                    @else
                        <div class="text-center">
                            <div class="h-1 w-20 mx-auto bg-surface/40 rounded-full overflow-hidden">
                                <div class="h-full bg-teal/60 animate-pulse w-2/3"></div>
                            </div>
                            <p class="font-mono text-[0.6rem] text-ink-faint mt-3">Rendering preview…</p>
                        </div>
                    @endif
                </div>
                </div>

                {{-- meta + actions --}}
                <div class="space-y-5">
                    <div>
                        <p class="font-mono text-[0.6rem] text-ink-faint">{{ $d['slug'] }}{{ $d['kind'] === 'builtin' ? ' · built-in' : '' }}</p>
                        @if (!empty($d['description']))
                            <p class="text-ink-soft text-sm mt-2">{{ $d['description'] }}</p>
                        @endif
                        @if (($d['kind'] === 'builtin') && $d['override'] === 'active')
                            <p class="font-mono text-[0.55rem] text-teal mt-2">● you have a custom version of this built-in</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($d['kind'] === 'custom')
                            @if ($d['status'] === 'active')
                                <button type="button" wire:click="alternarSfx('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['enabled'] ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['enabled'] ? '● allowed' : '○ off' }}</button>
                                <button type="button" wire:click="alternarIntro('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['intro'] ? 'border-gold/50 text-gold' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['intro'] ? '★ intro' : '☆ intro' }}</button>
                                <button type="button" wire:click="editarSfx('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎ Refine with AI</button>
                                <button type="button" wire:click="abrirAudio('{{ $d['slug'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ in_array($d['slug'], $sfxAudio, true) ? 'border-teal/40 text-teal' : 'border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40' }} transition">{{ in_array($d['slug'], $sfxAudio, true) ? '🔊 Sound' : '♪ Add sound' }}</button>
                                @if ($d['versions'])
                                    <button type="button" wire:click="abrirHistorico('{{ $d['id'] }}')"
                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⟲ History ({{ $d['versions'] }})</button>
                                @endif
                            @elseif ($d['status'] === 'failed')
                                <button type="button" wire:click="editarSfx('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎ Try again</button>
                                {{-- A failed effect is exactly the one you need to roll back — the
                                     previous version is still on the record, so offer it here too. --}}
                                @if ($d['versions'])
                                    <button type="button" wire:click="abrirHistorico('{{ $d['id'] }}')"
                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/40 text-teal hover:bg-teal/10 transition">⟲ Restore a working version ({{ $d['versions'] }})</button>
                                @endif
                            @else
                                <span class="font-mono text-[0.62rem] text-ink-faint self-center">{{ $d['status'] === 'updating' ? 'Updating…' : 'Generating…' }}</span>
                            @endif
                            <a href="{{ route('clips-animados.sfx-export', $d['id']) }}"
                               class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Export</a>
                            <button type="button" wire:click="apagarSfx('{{ $d['id'] }}')"
                                    wire:confirm="Delete this effect? Clips already rendered keep their video."
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕ Delete</button>
                        @else
                            <button type="button" wire:click="alternarBuiltin('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['allowed'] ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['allowed'] ? '● allowed' : '○ off' }}</button>
                            <button type="button" wire:click="alternarIntroBuiltin('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['intro'] ? 'border-gold/50 text-gold' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['intro'] ? '★ intro' : '☆ intro' }}</button>
                            <button type="button" wire:click="editarBuiltin('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎ {{ $d['override'] ? 'Edit custom version' : 'Customize with AI' }}</button>
                            <button type="button" wire:click="abrirAudio('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ in_array($d['slug'], $sfxAudio, true) ? 'border-teal/40 text-teal' : 'border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40' }} transition">{{ in_array($d['slug'], $sfxAudio, true) ? '🔊 Sound' : '♪ Add sound' }}</button>
                            @if ($d['override'] && $d['versions'])
                                <button type="button" wire:click="abrirHistorico('{{ $d['overrideId'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⟲ History ({{ $d['versions'] }})</button>
                            @endif
                            <a href="{{ route('clips-animados.sfx-export', $d['slug']) }}"
                               class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Export</a>
                            @if ($d['override'])
                                <button type="button" wire:click="resetBuiltin('{{ $d['slug'] }}')"
                                        wire:confirm="Reset «{{ $d['slug'] }}» to the default built-in? Your custom version is deleted."
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">↺ Reset to default</button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </x-panel>

        {{-- Refine / customize panel --}}
        @if ($editingSfxId || $sfxOverrideSlug)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <form wire:submit="guardarSfxEdicao" class="space-y-3">
                    <label class="eyebrow block">{{ $sfxOverrideSlug ? 'Customize built-in · '.$sfxOverrideSlug : 'Refine this effect' }}</label>
                    <textarea wire:model="sfxEditPrompt" rows="3"
                              placeholder="{{ $sfxOverrideSlug ? 'Describe how this built-in effect should look and behave…' : 'Describe the change you want…' }}"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('sfxEditPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                            ✦ {{ $sfxOverrideSlug ? 'Create override' : 'Regenerate' }}
                        </button>
                        <button type="button" wire:click="cancelarSfxEdicao" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancel</button>
                        <span class="font-mono text-[0.6rem] text-ink-faint">The live version stays until the new one renders.</span>
                    </div>
                </form>
            </div>
        @endif

        {{-- Sound panel --}}
        @if ($audioEditingSlug)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <label class="eyebrow block">Effect sound · {{ $audioEditingSlug }}</label>
                    <button type="button" wire:click="fecharAudio" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">close</button>
                </div>
                @if (in_array($audioEditingSlug, $sfxAudio, true))
                    <div class="flex items-center gap-3 mb-2">
                        <audio controls src="{{ route('clips-animados.sfx-audio', $audioEditingSlug) }}" class="h-9 max-w-xs"></audio>
                        <button type="button" wire:click="apagarAudio('{{ $audioEditingSlug }}')"
                                wire:confirm="Remove this sound? Clips already rendered keep it."
                                class="font-mono text-[0.62rem] px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">remove</button>
                    </div>
                @endif
                <p class="font-mono text-[0.55rem] text-ink-faint mb-4">Plays once each time this effect appears in a clip.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <form wire:submit="uploadAudio" class="space-y-2">
                        <label class="eyebrow block">Upload a sound</label>
                        <input type="file" wire:model="audioUpload" accept="audio/*"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-ink-soft/20 file:bg-surface/40 file:text-ink-soft" />
                        <div wire:loading wire:target="audioUpload" class="font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                        @error('audioUpload') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="uploadAudio,audioUpload"
                                class="font-display text-base px-4 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">↥ Attach</button>
                    </form>
                    <form wire:submit="gerarAudio" class="space-y-2">
                        <label class="eyebrow block">Or generate with AI</label>
                        <textarea wire:model="audioGenPrompt" rows="2" placeholder="e.g. a short glassy chime with a soft tail"
                                  class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                        @error('audioGenPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="gerarAudio"
                                class="font-display text-base px-4 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                            <span wire:loading.remove wire:target="gerarAudio">✦ Generate</span>
                            <span wire:loading wire:target="gerarAudio">generating…</span>
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Version history panel --}}
        @if ($historyId && $this->historyEffect)
            @php($histEffect = $this->historyEffect)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <label class="eyebrow block">Version history · {{ $histEffect->display_name }}</label>
                    <button type="button" wire:click="fecharHistorico" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">close</button>
                </div>
                <p class="font-mono text-[0.55rem] text-ink-faint mb-3">Restore a previous version. The current one is kept, so you can move back and forth. The preview re-renders after restoring.</p>
                <div class="space-y-2">
                    @foreach (array_reverse($histEffect->versions ?? [], true) as $i => $v)
                        <div class="flex items-center gap-3 bg-surface/30 border border-ink-soft/15 rounded-sm p-2" wire:key="ver-{{ $histEffect->id }}-{{ $i }}">
                            <span class="font-mono text-[0.55rem] text-ink-faint shrink-0">v{{ $i + 1 }}</span>
                            <span class="flex-1 min-w-0 text-sm text-ink truncate" title="{{ $v['prompt'] ?? '' }}">{{ \Illuminate\Support\Str::limit($v['prompt'] ?? '—', 90) }}</span>
                            @if (!empty($v['created_at']))
                                <span class="font-mono text-[0.5rem] text-ink-faint shrink-0">{{ \Illuminate\Support\Carbon::parse($v['created_at'])->diffForHumans() }}</span>
                            @endif
                            <button type="button" wire:click="reverterSfx('{{ $histEffect->id }}', {{ $i }})"
                                    wire:confirm="Restore this version? The current version is kept in history."
                                    class="font-mono text-[0.6rem] px-2 py-1 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition shrink-0">↩ restore</button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        {{-- ══════════════════════════ GALLERY VIEW ══════════════════════════ --}}
        <a href="{{ route('clips-animados') }}" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6 inline-block">← back to Animated Clips</a>

        <x-panel eyebrow="Effects library" title="SFX" glyph="✷">
            <p class="text-ink-soft text-sm mb-5">
                The motion vocabulary the renderer can already produce — plus your own. Describe a new effect and
                the AI writes a Remotion component that follows the design system. Click any effect to manage it —
                refine, add a sound, mark it as an <span class="text-gold">★ intro</span>, or delete it.
            </p>
            <form wire:submit="gerarSfx" class="space-y-3 mb-2">
                <label class="eyebrow block">Describe a new effect</label>
                <textarea wire:model="sfxPrompt" rows="3" placeholder="e.g. a glitch flicker that snaps the headline into place, with a quick chromatic-aberration split"
                          class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                @error('sfxPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition"
                            wire:loading.attr="disabled" wire:target="gerarSfx">
                        ✦ Create effect
                    </button>
                    <span class="font-mono text-[0.6rem] text-ink-faint">Colours &amp; fonts are locked to the design system.</span>
                </div>
            </form>

            {{-- Export / import — move a whole effect set (component + sound) between installs. --}}
            <div class="mt-4 pt-4 border-t border-ink-soft/10 flex flex-wrap items-center gap-x-4 gap-y-2">
                <a href="{{ route('clips-animados.sfx-export', 'all') }}"
                   class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Export all effects</a>
                <form wire:submit="importarSfx" class="flex items-center gap-2">
                    <input type="file" wire:model="importFile" accept="application/json,.json"
                           class="text-xs text-ink-soft file:mr-2 file:py-1 file:px-2 file:rounded-sm file:border file:border-ink-soft/20 file:bg-surface/40 file:text-ink-soft" />
                    <button type="submit" wire:loading.attr="disabled" wire:target="importFile,importarSfx"
                            class="font-mono text-xs px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition whitespace-nowrap">
                        <span wire:loading.remove wire:target="importFile,importarSfx">⤒ Import</span>
                        <span wire:loading wire:target="importFile,importarSfx">importing…</span>
                    </button>
                </form>
                @error('importFile') <span class="text-bad text-xs">{{ $message }}</span> @enderror
            </div>
        </x-panel>

        {{-- Showreel --}}
        <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-4 mt-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <div class="eyebrow">Showreel</div>
                    <p class="text-ink-soft text-sm mt-1">One video that plays every effect in turn, each with its name in the middle.</p>
                </div>
                <button type="button" wire:click="gerarShowreel" wire:loading.attr="disabled" wire:target="gerarShowreel"
                        @if ($this->showreelBusy) disabled @endif
                        class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50 shrink-0">
                    @if ($this->showreelBusy) rendering… @elseif ($this->showreelReady) ↻ Rebuild @else ✦ Build showreel @endif
                </button>
            </div>
            @if ($this->showreelBusy)
                <p class="font-mono text-[0.6rem] text-ink-faint">Rendering the reel — this can take a minute. It refreshes automatically.</p>
            @elseif ($this->showreelReady)
                <video wire:ignore class="rounded-sm border border-ink-soft/15 bg-black w-full max-h-[70vh]" controls preload="metadata"
                       src="{{ route('clips-animados.showreel') }}?v={{ $this->showreelVersion }}"></video>
            @endif
        </div>

        {{-- Rewrite every effect for the three frames --}}
        @if ($this->effects->isNotEmpty())
            <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-4 mt-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="eyebrow">Frames</div>
                        <p class="text-ink-soft text-sm mt-1">
                            An effect has to work in three boxes: full portrait, half portrait (over-video scenes,
                            where the video takes the bottom half) and landscape. Effects written before this
                            sized themselves from the whole frame, so in a half-frame scene they overflow and get cut.
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        <button type="button" wire:click="tornarResponsivos" wire:loading.attr="disabled" wire:target="tornarResponsivos"
                                wire:confirm="Rewrite every live effect for the three frames? Each keeps its description; the previous version stays in its history."
                                class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50">
                            ⌗ Rewrite all
                        </button>
                        @if ($this->recuperaveis)
                            <button type="button" wire:click="recuperarFalhados" wire:loading.attr="disabled" wire:target="recuperarFalhados"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-gold/50 text-gold hover:bg-gold/10 transition disabled:opacity-50">
                                ⟲ Bring back {{ $this->recuperaveis }} failed
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Custom (generated) effects --}}
        @if ($this->effects->isNotEmpty())
            <div class="mt-8">
                <div class="flex items-baseline justify-between mb-4 gap-3">
                    <div class="eyebrow">Your effects · {{ $this->effects->count() }}</div>
                    <span class="font-mono text-[0.55rem] text-ink-faint">click to manage · ● on = allowed · ★ intro = may open a video</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach ($this->effects as $effect)
                        <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col relative transition hover:border-teal/40 {{ $effect->status === 'active' && ! $effect->enabled ? 'opacity-60' : '' }}" wire:key="sfx-{{ $effect->id }}">
                            <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                                @if (in_array($effect->status, ['active', 'updating'], true) && isset($sfxReady[$effect->slug]))
                                    <video class="w-full h-full object-cover" wire:key="thumb-{{ $effect->slug }}-{{ $sfxReady[$effect->slug] }}"
                                           src="{{ route('clips-animados.sfx-preview', ['slug' => $effect->slug, 'v' => $sfxReady[$effect->slug]]) }}" autoplay loop muted playsinline></video>
                                    @if ($effect->status === 'updating')
                                        <div class="absolute inset-0 bg-black/55 flex items-center justify-center"><p class="font-mono text-[0.55rem] text-teal animate-pulse">Updating…</p></div>
                                    @endif
                                @elseif ($effect->status === 'failed')
                                    <div class="p-2 text-center"><div class="text-bad text-lg">✕</div><p class="font-mono text-[0.52rem] text-bad/90 mt-1 line-clamp-3">{{ \Illuminate\Support\Str::limit($effect->error, 90) }}</p></div>
                                @else
                                    <div class="text-center">
                                        <div class="h-1 w-16 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-teal/60 animate-pulse w-2/3"></div></div>
                                        <p class="font-mono text-[0.52rem] text-ink-faint mt-2">{{ $effect->status === 'updating' ? 'Updating…' : 'Generating…' }}</p>
                                    </div>
                                @endif
                                @if ($effect->status === 'active' && $effect->intro)
                                    <div class="absolute top-1 left-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-gold/20 text-gold border border-gold/30">★ intro</div>
                                @endif
                                @if (in_array($effect->slug, $sfxAudio, true))
                                    <div class="absolute top-1 right-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-teal/20 text-teal border border-teal/30">🔊</div>
                                @endif
                                {{-- On/off right here (z-20, above the overlay link) — no need to open the effect. --}}
                                @if ($effect->status === 'active')
                                    <button type="button" wire:click="alternarSfx('{{ $effect->id }}')"
                                            title="{{ $effect->enabled ? 'Allowed in clips — click to turn off' : 'Off — click to allow in clips' }}"
                                            class="absolute bottom-1 left-1 z-20 font-mono text-[0.5rem] px-1.5 py-0.5 rounded-sm border backdrop-blur-sm transition {{ $effect->enabled ? 'bg-teal/25 text-teal border-teal/40 hover:bg-teal/40' : 'bg-black/60 text-ink-faint border-ink-soft/30 hover:text-ink' }}">
                                        {{ $effect->enabled ? '● on' : '○ off' }}
                                    </button>
                                @endif
                            </div>
                            <div class="mt-2 min-w-0">
                                <p class="font-display text-sm text-ink truncate">{{ $effect->display_name }}</p>
                                <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ in_array($effect->status, ['active', 'updating'], true) ? $effect->slug : $effect->status }}</p>
                            </div>

                            {{-- Full-card link to open the effect (z-10, below the toggle). --}}
                            <a href="{{ route('clips-animados.sfx.detail', $effect->id) }}" wire:navigate
                               class="absolute inset-0 z-10 rounded-sm" aria-label="Open {{ $effect->display_name }}"></a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Built-in effects --}}
        <div class="mt-8">
            <div class="flex items-baseline justify-between mb-4 gap-3">
                <div class="eyebrow">Built-in · {{ count($builtins) }}</div>
                <span class="font-mono text-[0.55rem] text-ink-faint">click to manage or customize</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach ($builtins as $b)
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col relative transition hover:border-teal/40 {{ $b['allowed'] ? '' : 'opacity-60' }}" wire:key="builtin-{{ $b['slug'] }}">
                        <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                            @if (isset($sfxReady[$b['slug']]))
                                <video class="w-full h-full object-cover" wire:key="thumb-{{ $b['slug'] }}-{{ $sfxReady[$b['slug']] }}"
                                       src="{{ route('clips-animados.sfx-preview', ['slug' => $b['slug'], 'v' => $sfxReady[$b['slug']]]) }}" autoplay loop muted playsinline></video>
                            @else
                                <div class="text-center">
                                    <div class="h-1 w-16 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-ink-soft/40 animate-pulse w-1/2"></div></div>
                                    <p class="font-mono text-[0.52rem] text-ink-faint mt-2">Rendering…</p>
                                </div>
                            @endif
                            @if ($b['intro'])
                                <div class="absolute top-1 right-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-gold/20 text-gold border border-gold/30">★</div>
                            @endif
                            @if ($b['override'] === 'active')
                                <div class="absolute top-1 left-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-teal/20 text-teal border border-teal/30">custom</div>
                            @endif
                            {{-- On/off right here (z-20, above the overlay link) — no need to open the effect. --}}
                            <button type="button" wire:click="alternarBuiltin('{{ $b['slug'] }}')"
                                    title="{{ $b['allowed'] ? 'Allowed in clips — click to turn off' : 'Off — click to allow in clips' }}"
                                    class="absolute bottom-1 left-1 z-20 font-mono text-[0.5rem] px-1.5 py-0.5 rounded-sm border backdrop-blur-sm transition {{ $b['allowed'] ? 'bg-teal/25 text-teal border-teal/40 hover:bg-teal/40' : 'bg-black/60 text-ink-faint border-ink-soft/30 hover:text-ink' }}">
                                {{ $b['allowed'] ? '● on' : '○ off' }}
                            </button>
                        </div>
                        <div class="mt-2 min-w-0">
                            <p class="font-display text-sm text-ink truncate">{{ $b['label'] }}</p>
                            <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ $b['slug'] }}</p>
                        </div>

                        {{-- Full-card link to open the effect (z-10, below the toggle). --}}
                        <a href="{{ route('clips-animados.sfx.detail', $b['slug']) }}" wire:navigate
                           class="absolute inset-0 z-10 rounded-sm" aria-label="Open {{ $b['label'] }}"></a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
