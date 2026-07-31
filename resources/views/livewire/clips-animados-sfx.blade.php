<div @if ($this->sfxBusy || $this->showreelBusy) wire:poll.3s @endif>
    <x-page-header
        eyebrow="Tomus · IV"
        title="SFX Studio"
        cota="741.5 · ACM · '26"
        lead="The motion vocabulary the renderer can produce — create effects, attach sounds, and mark which ones open a video." />

    @if ($this->detail)
        {{-- ══════════════════════════ DETAIL VIEW (one effect) ══════════════════════════ --}}
        @php($d = $this->detail)
        <a href="{{ route('clips-animados.sfx') }}" wire:navigate class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6 inline-block">← back to SFX</a>

        <x-panel eyebrow="{{ $d['kind'] === 'builtin' ? 'Built-in effect' : 'Effect' }}" title="{{ $d['label'] }}" glyph="✷">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- big preview --}}
                <div class="relative aspect-[9/16] max-h-[60vh] mx-auto rounded-sm overflow-hidden bg-black/60 flex items-center justify-center w-full">
                    @if (($d['status'] ?? null) === 'failed')
                        <div class="p-3 text-center">
                            <div class="text-bad text-2xl">✕</div>
                            <p class="font-mono text-[0.6rem] text-bad/90 mt-2">{{ \Illuminate\Support\Str::limit($d['error'] ?? 'Generation failed', 200) }}</p>
                        </div>
                    @elseif (in_array($d['slug'], $sfxReady, true) && ($d['status'] ?? 'active') !== 'pending')
                        <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $d['slug']) }}" autoplay loop muted playsinline></video>
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
                            @else
                                <span class="font-mono text-[0.62rem] text-ink-faint self-center">{{ $d['status'] === 'updating' ? 'Updating…' : 'Generating…' }}</span>
                            @endif
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

        {{-- Custom (generated) effects --}}
        @if ($this->effects->isNotEmpty())
            <div class="mt-8">
                <div class="flex items-baseline justify-between mb-4 gap-3">
                    <div class="eyebrow">Your effects · {{ $this->effects->count() }}</div>
                    <span class="font-mono text-[0.55rem] text-ink-faint">click to manage · ● on = allowed · ★ intro = may open a video</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach ($this->effects as $effect)
                        <a href="{{ route('clips-animados.sfx.detail', $effect->id) }}" wire:navigate
                           class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col transition hover:border-teal/40 {{ $effect->status === 'active' && ! $effect->enabled ? 'opacity-60' : '' }}" wire:key="sfx-{{ $effect->id }}">
                            <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                                @if (in_array($effect->status, ['active', 'updating'], true) && in_array($effect->slug, $sfxReady, true))
                                    <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $effect->slug) }}" autoplay loop muted playsinline></video>
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
                            </div>
                            <div class="mt-2 min-w-0">
                                <p class="font-display text-sm text-ink truncate">{{ $effect->display_name }}</p>
                                <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ in_array($effect->status, ['active', 'updating'], true) ? $effect->slug : $effect->status }}</p>
                            </div>
                        </a>
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
                    <a href="{{ route('clips-animados.sfx.detail', $b['slug']) }}" wire:navigate
                       class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col transition hover:border-teal/40 {{ $b['allowed'] ? '' : 'opacity-60' }}" wire:key="builtin-{{ $b['slug'] }}">
                        <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                            @if (in_array($b['slug'], $sfxReady, true))
                                <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $b['slug']) }}" autoplay loop muted playsinline></video>
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
                        </div>
                        <div class="mt-2 min-w-0">
                            <p class="font-display text-sm text-ink truncate">{{ $b['label'] }}</p>
                            <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ $b['slug'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
