<div @if ($this->busy) wire:poll.3s @endif>
    <x-page-header
        eyebrow="Tomus · V"
        title="VFX Lab"
        cota="741.6 · ACM · '26"
        lead="Describe an animation, pick its proportions, get a finished video — in the brand's style — to drop into a long-form edit." />

    @include('livewire.partials.studio-tabs')

    <x-panel eyebrow="New" title="Generate a VFX" glyph="✧">
        <form wire:submit="gerarVfx" class="space-y-4">
            <div class="space-y-2">
                <label class="eyebrow block">Describe the animation</label>
                <textarea wire:model="prompt" rows="3"
                          placeholder="e.g. the words CHAPTER ONE slam in from the left with a gold light sweep, then settle with a slow drift"
                          class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                @error('prompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <p class="font-mono text-[0.6rem] text-ink-faint">Include any on-screen text here — it is baked into the render.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-2">
                    <label class="eyebrow block">Proportions</label>
                    <select wire:model="aspect"
                            class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none">
                        @foreach ($aspects as $key => $a)
                            <option value="{{ $key }}">{{ $a['label'] }} · {{ $a['width'] }}×{{ $a['height'] }}</option>
                        @endforeach
                    </select>
                    @error('aspect') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label class="eyebrow block">Length (seconds)</label>
                    <input type="number" wire:model="duration" min="1" max="{{ $maxDuration }}" step="0.5"
                           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none" />
                    @error('duration') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label class="eyebrow block">Background</label>
                    <label class="flex items-center gap-2 px-3 py-2 rounded-sm border border-ink-soft/20 bg-surface/40 cursor-pointer">
                        <input type="checkbox" wire:model="transparent" class="accent-teal" />
                        <span class="font-mono text-[0.68rem] text-ink-soft">transparent (overlay)</span>
                    </label>
                    <p class="font-mono text-[0.55rem] text-ink-faint">On → ProRes 4444 .mov with alpha. Off → .mp4 on the brand backdrop.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition"
                        wire:loading.attr="disabled" wire:target="gerarVfx">
                    ✦ Generate
                </button>
                <span class="font-mono text-[0.6rem] text-ink-faint">Colours &amp; fonts are locked to the design system. Takes a couple of minutes.</span>
            </div>
        </form>
    </x-panel>

    @if ($this->vfx->isNotEmpty())
        <div class="mt-8">
            <div class="flex items-baseline justify-between mb-4 gap-3">
                <div class="eyebrow">Your VFX · {{ $this->vfx->count() }}</div>
                <span class="font-mono text-[0.55rem] text-ink-faint">download and drop into your editor</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($this->vfx as $v)
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-3 flex flex-col" wire:key="vfx-{{ $v->id() }}">
                        <div class="relative rounded-sm overflow-hidden bg-black/60 flex items-center justify-center"
                             style="aspect-ratio: {{ $v->get('width', 1920) }} / {{ $v->get('height', 1080) }}">
                            @if ($v->status === 'active')
                                {{-- wire:ignore so the 3s poll never restarts playback mid-watch. --}}
                                <video wire:ignore class="w-full h-full object-contain" controls loop muted playsinline preload="metadata"
                                       src="{{ route('clips-animados.vfx-media', $v->id()) }}"></video>
                            @elseif ($v->status === 'failed')
                                <div class="p-3 text-center">
                                    <div class="text-bad text-lg">✕</div>
                                    <p class="font-mono text-[0.55rem] text-bad/90 mt-1">{{ \Illuminate\Support\Str::limit($v->error, 160) }}</p>
                                </div>
                            @else
                                <div class="text-center">
                                    <div class="h-1 w-20 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-teal/60 animate-pulse w-2/3"></div></div>
                                    <p class="font-mono text-[0.55rem] text-ink-faint mt-2">Generating &amp; rendering…</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 min-w-0 flex-1">
                            <p class="text-ink text-sm line-clamp-2">{{ $v->prompt }}</p>
                            <p class="font-mono text-[0.55rem] text-ink-faint mt-1">
                                {{ $v->get('aspect', '—') }} · {{ $v->get('width') }}×{{ $v->get('height') }} · {{ $v->get('duration') }}s
                                @if ($v->get('transparent')) · <span class="text-teal">alpha</span> @endif
                            </p>
                            {{-- Which page it filmed. An AI-guessed URL is the likeliest
                                 thing to be wrong, so it is always shown. --}}
                            @if ($v->get('site_url'))
                                <p class="font-mono text-[0.55rem] text-teal mt-1 truncate" title="{{ $v->get('site_url') }}">🎞 {{ $v->get('site_url') }}</p>
                            @elseif ($v->get('site_error'))
                                <p class="font-mono text-[0.55rem] text-bad/80 mt-1">site capture failed — built without it</p>
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($v->status === 'active')
                                <a href="{{ route('clips-animados.vfx-media', $v->id()) }}?download=1"
                                   class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">⤓ Download .{{ $v->get('ext', 'mp4') }}</a>
                            @endif
                            <button type="button" wire:click="apagarVfx('{{ $v->id() }}')"
                                    wire:confirm="Delete this VFX and its video?"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕ Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <x-empty-state class="mt-8" glyph="✧" title="No VFX yet"
                       note="Describe an animation above and it will show up here, ready to download." />
    @endif
</div>
