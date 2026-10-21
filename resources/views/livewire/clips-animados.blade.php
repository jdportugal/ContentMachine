<div @if (($view === 'dashboard' && $this->hasActive) || ($view === 'backgrounds' && ($this->backgroundsBusy || $this->backgroundReelBusy)) || ($view === 'reviewImages' && $this->siteCollecting)) wire:poll.3s @endif>
    @php
        $estados = [
            'draft'        => ['label' => 'Draft',         'tone' => 'neutral', 'glyph' => '·'],
            'importing'    => ['label' => 'Importing listing', 'tone' => 'gold', 'glyph' => '⌂'],
            'choosing_voice' => ['label' => 'Pick the voice', 'tone' => 'gold', 'glyph' => '♪'],
            'analyzing'    => ['label' => 'Reading the walkthrough', 'tone' => 'gold', 'glyph' => '⌂'],
            'choosing_sections' => ['label' => 'Mark the sections', 'tone' => 'gold', 'glyph' => '◆'],
            'reviewing_rooms' => ['label' => 'Approve the rooms', 'tone' => 'gold', 'glyph' => '◆'],
            'animating'    => ['label' => 'Animating rooms', 'tone' => 'gold', 'glyph' => '❧'],
            'transcribing' => ['label' => 'Transcribing',  'tone' => 'gold',    'glyph' => '❧'],
            'planning'     => ['label' => 'Planning',      'tone' => 'gold',    'glyph' => '❧'],
            'collecting'   => ['label' => 'Your images',   'tone' => 'gold',    'glyph' => '◆'],
            'improving'    => ['label' => 'Improving with AI', 'tone' => 'gold', 'glyph' => '✨'],
            'rendering'    => ['label' => 'Rendering',     'tone' => 'gold',    'glyph' => '❧'],
            'done'         => ['label' => 'Ready',         'tone' => 'good',    'glyph' => '✔'],
            'failed'       => ['label' => 'Failed',        'tone' => 'bad',     'glyph' => '✕'],
        ];
        $tipos = [
            'animation' => ['label' => 'Animation',          'glyph' => '❈'],
            'overlay'   => ['label' => 'Video + Animations', 'glyph' => '❖'],
        ];
    @endphp

    <x-page-header
        eyebrow="Tomus · IV"
        title="Animated Clips"
        cota="741.5 · ACM · '26"
        lead="Animation studio: from voiceover to animated piece, with timestamps and assisted planning." />

    {{-- ============================================================ --}}
    {{-- DASHBOARD                                                    --}}
    {{-- ============================================================ --}}
    @if ($view === 'dashboard')
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="eyebrow">Clips generated · {{ $this->projects->count() }}</div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a href="{{ route('clips-animados.sfx') }}"
                        class="font-display text-lg px-5 py-2 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-ink-soft/50 transition">
                    ✷ SFX
                </a>
                <a href="{{ route('clips-animados.backgrounds') }}" wire:navigate
                        class="font-display text-lg px-5 py-2 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-ink-soft/50 transition">
                    ◆ Backgrounds
                </a>
                <button type="button" wire:click="novoClip"
                        class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                    ✦ New clip
                </button>
            </div>
        </div>

        {{-- One-step listing import: URL in, narrated clip out. --}}
        <form wire:submit="importarAnuncio" class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-4 mb-6 shadow-engraved">
            <label for="listingUrl" class="eyebrow block mb-2">⌂ From a listing</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <input id="listingUrl" type="url" wire:model="listingUrl" placeholder="https://www.remax.pt/pt/imoveis/…"
                       class="flex-1 min-w-0 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60">
                <button type="submit" wire:loading.attr="disabled" wire:target="importarAnuncio, listingAgentPhoto, listingVideo, listingAfterIntro"
                        class="font-display text-lg px-5 py-2 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-50">
                    Make the video
                </button>
            </div>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <span>Agent photo (optional — replaces the listing's):</span>
                <input type="file" accept="image/*" wire:model="listingAgentPhoto" class="text-xs">
                <span wire:loading wire:target="listingAgentPhoto" class="text-ink-faint">uploading…</span>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <span>🏠 <strong>Property video</strong> (optional) — the walkthrough of the house, minutes long; it is cut into the reel's footage:</span>
                <input type="file" accept="video/*" wire:model="listingVideo" class="text-xs">
                <span wire:loading wire:target="listingVideo" class="text-ink-faint">uploading…</span>
            </label>
            <label class="mt-1 ml-4 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <input type="checkbox" wire:model="listingMixPhotos" class="accent-teal">
                <span>Mix the video with the listing photos (off: the reel is all video)</span>
            </label>
            <label class="mt-1 ml-4 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <input type="checkbox" wire:model="listingBeatOpening" class="accent-teal">
                <span>🎵 <strong>Beat-synced opening</strong>: the best clips first, music only, switching on the beat until the drop — then the reel</span>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft">
                <span class="shrink-0">Music:</span>
                <select wire:model="listingMusic" class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60">
                    <option value="">The project's</option>
                    @foreach ($musicas as $m)
                        <option value="{{ $m['name'] }}">{{ $m['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <span>🎬 <strong>After-intro video</strong> (optional) — your few-second brand clip, shown right after the intro:</span>
                <input type="file" accept="video/*" wire:model="listingAfterIntro" class="text-xs">
                <span wire:loading wire:target="listingAfterIntro" class="text-ink-faint">uploading…</span>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft">
                <span class="shrink-0">After-intro line (said right after the intro, over the after-intro video):</span>
                <input type="text" wire:model="listingAfterIntroLine" placeholder="Se queres vender ou comprar casa, fala com…"
                       class="flex-1 min-w-0 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60">
            </label>
            @error('listingAfterIntroLine') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            <label class="mt-2 block text-sm text-ink-soft">
                <span>Notes for the script (optional — they override the listing page: a new price, a wrong area, what to stress or leave out):</span>
                <textarea wire:model="listingNotes" rows="2" placeholder="e.g. O preço baixou para 265.000 €. Não falar do sótão. Destacar a vista mar."
                          class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60"></textarea>
            </label>
            @error('listingNotes') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingUrl') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingAfterIntro') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingVideo') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingAgentPhoto') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            <p class="mt-2 font-mono text-[0.58rem] text-ink-faint">Scrapes the listing, writes and narrates the pt-PT voiceover, collects photos (first as main), the agent's photo and the agency logo, then plans with only those images, no subtitles and upbeat corporate music.</p>
        </form>

        @if ($this->projects->isEmpty())
            <x-empty-state glyph="❈" title="No clips" note="You haven't generated any piece yet. Start with «New clip»." />
        @else
            <div class="space-y-5">
                @foreach ($this->projects as $project)
                    @php
                        $e = $estados[$project->status] ?? $estados['draft'];
                        $t = $tipos[$project->type] ?? $tipos['animation'];
                        $sug = $project->meta['suggested'] ?? [];
                        $custo = $custos[$project->id] ?? 0.0;
                    @endphp
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-5 shadow-engraved" wire:key="clip-{{ $project->id }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- LEFT: video (the section picker takes the full width) --}}
                            <div class="flex items-center justify-center {{ $project->status === 'choosing_sections' ? 'md:col-span-2' : '' }}">
                                @if ($project->status === 'done' && $project->output_path)
                                    <div class="flex flex-col items-center gap-2">
                                        <video class="rounded-sm border border-ink-soft/15 bg-black max-h-[60vh] w-auto max-w-full" controls preload="metadata"
                                               src="{{ route('clips-animados.media', ['id' => $project->id, 'v' => @filemtime($project->output_path) ?: 0]) }}"></video>
                                        @if (! empty($project->plan['scenes']))
                                            {{-- Improve with AI: the clip reworked as asked — script, photos/footage, timing, effects, caption. No picture is generated. --}}
                                            <div class="self-stretch">
                                                <button type="button" wire:click="rerenderFinal('{{ $project->id }}')" title="Renders only the closing card again (e.g. after changing the agent card) — the rest of the clip stays as it is"
                                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:text-teal hover:border-teal/40 transition">↻ Re-render the ending</button>
                                                <button type="button" wire:click="abrirMelhoriaClip('{{ $project->id }}')"
                                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:text-gold hover:border-gold/40 transition">✨ Improve with AI</button>
                                                @if ($improveCardId === $project->id)
                                                    <div class="mt-2 space-y-1">
                                                        <textarea wire:model="improveCardPrompt" rows="2" maxlength="1000" placeholder="e.g. shorter; talk about the river beaches nearby; show more of the house and less of the land; faster cuts"
                                                                  class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-gold/50"></textarea>
                                                        @error('improveCardPrompt') <p class="text-bad text-xs">{{ $message }}</p> @enderror
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <button type="button" wire:click="melhorarClip" wire:loading.attr="disabled" wire:target="melhorarClip"
                                                                    class="font-mono text-[0.65rem] px-3 py-1.5 rounded-sm border border-gold/40 text-gold hover:bg-gold/10 transition">Improve</button>
                                                            <span class="font-mono text-[0.55rem] text-ink-faint">Rewrites the script as you ask (and records it again), picks the photos and footage, timing and effects anew, and updates the description — a few minutes. No image is generated.</span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                        @if (! empty($project->meta['property']['proxy']))
                                            @if (empty($project->meta['property']['photos']))
                                            <button type="button" wire:click="reselectSections('{{ $project->id }}')"
                                                    class="self-start font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition">↺ Change the sections</button>
                                            @endif
                                            @php
                                                $listing = $project->meta['listing'] ?? [];
                                                $info = $listing['info'] ?? collect(array_filter([
                                                    'Título' => $listing['title'] ?? null,
                                                    'Preço' => ! empty($listing['price']) ? number_format((float) $listing['price'], 0, ',', '.').' €' : null,
                                                ]) + (array) ($listing['details'] ?? []))->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
                                                $hasVoice = ! empty($project->meta['property']['voice']);
                                            @endphp
                                            {{-- The voiceover, written from the house details and narrated over the cut reel. wire:ignore: the dashboard polls. --}}
                                            <div class="w-full mt-2 border-t border-ink-soft/15 pt-3" wire:ignore wire:key="narrate-{{ $project->id }}" x-data="{ info: @js($info), url: @js($listing['url'] ?? ''), sending: false }">
                                                <p class="font-mono text-[0.62rem] text-ink-soft mb-1">Voiceover {{ $hasVoice ? '· this reel has one' : '· none yet' }}</p>
                                                <input type="url" x-model="url" placeholder="Listing link (RE/MAX) — the details are read from it" aria-label="Listing link"
                                                       class="w-full mb-2 bg-papyrus/60 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-xs focus:outline-none focus:border-teal/50">
                                                <textarea x-model="info" rows="6" maxlength="4000" aria-label="House details for the voiceover"
                                                          placeholder="…and/or the details, one per line (they win over the listing), e.g.&#10;Título: Moradia T3 em Braga&#10;Preço: 350 000 €&#10;Localização: Braga, perto do centro&#10;Área: 180 m²&#10;Quartos: 3&#10;…and anything else worth saying"
                                                          class="w-full bg-papyrus/60 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-xs font-mono focus:outline-none focus:border-teal/50"></textarea>
                                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                                    <button type="button" :disabled="sending || (!info.trim() && !url.trim())" @click="sending = true; $wire.narrateProperty('{{ $project->id }}', info, url).finally(() => sending = false)"
                                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40"
                                                            x-text="sending ? 'Sending…' : '{{ $hasVoice ? '↻ Write a new voiceover' : '🎙 Write & narrate the voiceover' }}'"></button>
                                                    @if ($hasVoice)
                                                        <button type="button" @click="$wire.removePropertyVoice('{{ $project->id }}')" class="font-mono text-[0.62rem] text-bad hover:underline">remove the voiceover</button>
                                                    @endif
                                                    <span class="font-mono text-[0.58rem] text-ink-faint">Sized to the reel; you pick the take, then it's cut again (animations reused).</span>
                                                </div>
                                            </div>
                                            @error('narrate.'.$project->id) <p class="text-xs text-bad mt-1 self-start">{{ $message }}</p> @enderror
                                        @endif
                                    </div>
                                @elseif ($project->status === 'failed')
                                    <p class="text-sm text-bad/90 self-start">{{ \Illuminate\Support\Str::limit($project->error, 200) }}</p>
                                @elseif ($project->status === 'choosing_voice')
                                    {{-- wire:ignore: the dashboard polls; a re-render must not reset a player mid-listen. --}}
                                    <div class="w-full self-start" wire:ignore wire:key="takes-{{ $project->id }}">
                                        <p class="font-mono text-[0.6rem] text-ink-faint mb-2">Listen and pick the take with the European Portuguese accent.</p>
                                        @foreach ($project->meta['voice_takes'] ?? [] as $n => $take)
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="font-mono text-[0.62rem] text-ink-soft w-12">Take {{ $n + 1 }}</span>
                                                <audio controls preload="none" class="flex-1 min-w-0 h-9" src="{{ route('clips-animados.take', ['id' => $project->id, 'n' => $n, 'v' => md5($take)]) }}"></audio>
                                                <button type="button" wire:click="escolherTake('{{ $project->id }}', {{ $n }})"
                                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Use this one</button>
                                            </div>
                                        @endforeach
                                        @if (!empty($project->meta['listing']['voiceover']))
                                            <details class="mt-3 border border-ink-soft/15 rounded-sm px-3 py-2 bg-papyrus/40">
                                                <summary class="cursor-pointer font-mono text-[0.62rem] text-ink-soft">✎ Script — to record or generate your own</summary>
                                                <p class="mt-2 text-sm text-ink-soft whitespace-pre-line">{{ $project->meta['listing']['voiceover'] }}</p>
                                                <div class="mt-2"><x-copy-btn :text="$project->meta['listing']['voiceover']" label="⧉ copy script" /></div>
                                            </details>
                                        @endif
                                        <label class="mt-3 flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft">
                                            <span class="w-12">None?</span>
                                            <span class="px-3 py-1.5 rounded-sm border border-ink-soft/30 hover:bg-ink/5 transition cursor-pointer">⇪ Upload my own voiceover</span>
                                            <input type="file" accept="audio/*" class="hidden" wire:model="takeUploads.{{ $project->id }}">
                                            <span wire:loading wire:target="takeUploads.{{ $project->id }}" class="text-ink-faint">uploading…</span>
                                        </label>
                                    </div>
                                    @error('takeUploads.'.$project->id) <p class="text-xs text-bad mt-1 self-start">{{ $message }}</p> @enderror
                                @elseif ($project->status === 'choosing_sections')
                                    @php
                                        // Each saved section's state from its room, so the picker says what's kept and what needs re-marking.
                                        $byKey = collect($project->meta['property']['rooms'] ?? [])->filter(fn ($r) => ! empty($r['anim']))
                                            ->keyBy(fn ($r) => round($r['anim']['start'], 2).'|'.round($r['anim']['end'], 2));
                                        $marked = collect($project->meta['property']['sections'] ?? [])->map(function ($s) use ($byKey) {
                                            $a = $byKey[round($s['start'], 2).'|'.round($s['end'], 2)]['anim'] ?? null;
                                            $s['state'] = match (true) {
                                                ! $a => '',
                                                ! empty($a['video']) && empty($a['clip_problems']) && ! empty($a['enabled']) => 'ok:✓ animated — kept as is',
                                                ! empty($a['clip_problems']) => 'redo:Kling warped this one — usually a camera turn: mark a straight push instead',
                                                ! empty($a['problems']) => 'redo:the modern still re-framed the room — end it on a wider view',
                                                default => '',
                                            };

                                            return $s;
                                        })->values()->all();
                                    @endphp
                                    {{-- Click and drag on the filmstrip to mark each section to animate; the player follows. --}}
                                    <div class="w-full self-start" wire:ignore wire:key="sections-{{ $project->id }}"
                                         x-data="sectionPicker(@js($project->meta['property']['duration'] ?? 0), @js($marked))">
                                        <video x-ref="video" class="w-full rounded-sm border border-ink-soft/15 bg-black max-h-[50vh]" muted playsinline preload="auto"
                                               src="{{ route('clips-animados.property', ['id' => $project->id, 'what' => 'footage']) }}"></video>
                                        <div class="mt-2 flex items-center justify-between font-mono text-[0.6rem] text-ink-faint">
                                            <span x-text="label(cursor)"></span>
                                            <span>Drag on the strip to mark up to 10 sections (1–10 s each) · drag its edges to adjust</span>
                                        </div>
                                        <div x-ref="strip" class="relative mt-1 h-16 rounded-sm border border-ink-soft/25 overflow-hidden cursor-crosshair select-none touch-none"
                                             style="background: url('{{ route('clips-animados.property', ['id' => $project->id, 'what' => 'strip']) }}') center/100% 100% no-repeat"
                                             @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up($event)" @pointerleave="up($event)">
                                            <template x-for="(s, i) in sections" :key="i">
                                                <div class="absolute inset-y-0" :style="`left:${pct(s.start)}%;width:${pct(s.end - s.start)}%;background:rgba(44,95,99,.45);border-left:2px solid #2C5F63;border-right:2px solid #2C5F63`">
                                                    <span class="absolute top-0.5 left-1 font-mono text-[0.6rem] text-papyrus drop-shadow" x-text="i + 1"></span>
                                                    <span class="absolute inset-y-0 -left-1.5 w-3 cursor-ew-resize" data-edge="start" :data-i="i"></span>
                                                    <span class="absolute inset-y-0 -right-1.5 w-3 cursor-ew-resize" data-edge="end" :data-i="i"></span>
                                                </div>
                                            </template>
                                            <div x-show="drag" class="absolute inset-y-0"
                                                 :style="drag && `left:${pct(Math.min(drag.from, drag.to))}%;width:${pct(Math.abs(drag.to - drag.from))}%;background:rgba(184,134,52,.45);border-left:2px solid #B88634;border-right:2px solid #B88634`"></div>
                                            <div class="absolute inset-y-0" :style="`left:${pct(cursor)}%;width:2px;background:#23201A`"></div>
                                        </div>
                                        <ol class="mt-3 space-y-2">
                                            <template x-for="(s, i) in sections" :key="i">
                                                <li class="flex flex-wrap items-center gap-2 font-mono text-[0.65rem] text-ink-soft">
                                                    <span class="w-5 text-teal" x-text="(i + 1) + '.'"></span>
                                                    <button type="button" class="hover:text-ink" @click="seek(s.start)" x-text="label(s.start)"></button>→
                                                    <button type="button" class="hover:text-ink" @click="seek(s.end)" x-text="label(s.end)"></button>
                                                    <span class="text-ink-faint" x-text="'(' + (s.end - s.start).toFixed(1) + ' s)'"></span>
                                                    <button type="button" class="px-2 py-0.5 rounded-sm border border-ink-soft/30 hover:bg-ink/5" @click="preview(s)">▶ preview</button>
                                                    <span x-show="s.state" class="basis-full pl-7 text-[0.6rem]" :class="s.state.startsWith('ok:') ? 'text-teal' : 'text-bad'" x-text="s.state.slice(s.state.indexOf(':') + 1)"></span>
                                                    <input type="text" x-model="s.note" maxlength="300" placeholder="optional: what you'd like (e.g. Scandinavian kitchen)" aria-label="Wish for this section"
                                                           class="flex-1 min-w-[12rem] bg-papyrus/60 border border-ink-soft/20 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-teal/50">
                                                    <button type="button" class="text-bad hover:underline" @click="sections.splice(i, 1)" :aria-label="'Remove section ' + (i + 1)">remove</button>
                                                </li>
                                            </template>
                                        </ol>
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <button type="button" :disabled="!sections.length || sending" @click="sending = true; $wire.stageSections('{{ $project->id }}', sections).finally(() => sending = false)"
                                                    class="font-display text-lg px-5 py-2 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-40"
                                                    x-text="sending ? 'Sending…' : 'Modernise these sections'">Modernise these sections</button>
                                            <span class="font-mono text-[0.6rem] text-ink-faint">Each section's last frame becomes a modern still you approve before anything is animated.</span>
                                            <label class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft">Final format
                                                <select @change="$wire.setPropertyAspect('{{ $project->id }}', $event.target.value)"
                                                        class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-teal/60">
                                                    @foreach (\App\Services\RealEstate\PropertyVideoEditor::ASPECTS as $value => $label)
                                                        <option value="{{ $value }}" @selected(($project->meta['property']['aspect'] ?? 'source') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label x-data="{ sent: false }" class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft cursor-pointer hover:text-ink">
                                                <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="sent">✓ Agent photo — replace</span>
                                                    <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="!sent">{{ collect($project->images ?? [])->contains(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato')) ? '✓ Agent photo — replace' : '+ Agent photo' }}</span>
                                                <input type="file" accept="image/*" class="hidden" @change="sent = true" wire:model="agentPhotos.{{ $project->id }}">
                                                <span wire:loading wire:target="agentPhotos.{{ $project->id }}" class="text-ink-faint">uploading…</span>
                                            </label>
                                        </div>
                                    </div>
                                    @error('agentPhotos.'.$project->id) <p class="text-sm text-bad mt-1 self-start">{{ $message }}</p> @enderror
                                    @error('sections.'.$project->id) <p class="text-sm text-bad mt-1 self-start">{{ $message }}</p> @enderror
                                @elseif ($project->status === 'reviewing_rooms')
                                    @php
                                        $rooms = $project->meta['property']['rooms'] ?? [];
                                        $staging = collect($rooms)->contains(fn ($r) => ! empty($r['anim']['staging']));
                                        $paid = collect($rooms)->filter(fn ($r) => ! empty($r['anim']['enabled']) && ! empty($r['anim']['staged']))->count();
                                        $plain = collect($rooms)->filter(fn ($r) => empty($r['anim']))->map(fn ($r) => $r['room'].' ('.$r['start'].'–'.$r['end'].' s)')->implode(' · ');
                                    @endphp
                                    <div class="w-full self-start space-y-4">
                                        @if (! empty($project->meta['property']['notice']))
                                            <p class="text-sm text-bad border border-bad/40 rounded-sm px-3 py-2 bg-bad/5" role="alert">{{ $project->meta['property']['notice'] }}</p>
                                        @endif
                                        <p class="font-mono text-[0.6rem] text-ink-faint">Real frame → modernised still. Approve, ask for another take (edit the prompt if you like) or skip a room — nothing is paid for until you animate.</p>
                                        @foreach ($rooms as $i => $room)
                                            @continue(empty($room['anim']))
                                            @php $anim = $room['anim']; $on = ! empty($anim['enabled']); @endphp
                                            <div wire:key="room-{{ $project->id }}-{{ $i }}" class="border border-ink-soft/15 rounded-sm p-3 {{ $on ? '' : 'opacity-50' }}">
                                                <div class="flex items-center justify-between gap-2 mb-2">
                                                    <span class="font-display text-lg text-ink">{{ $room['room'] }}</span>
                                                    <span class="font-mono text-[0.6rem] text-ink-soft">{{ ['unfurnish' => 'furniture out', 'furnish' => 'furniture in'][$anim['mode']] ?? 'modernise' }} · {{ $anim['start'] }}–{{ $anim['end'] }} s · {{ $anim['motion'] }}</span>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <img src="{{ route('clips-animados.room', ['id' => $project->id, 'i' => $i, 'which' => 'start', 'v' => md5($anim['start_frame'] ?? '')]) }}" alt="Real start frame of {{ $room['room'] }}" class="w-full rounded-sm border border-ink-soft/15 object-contain bg-black/5 max-h-72">
                                                    @if (! empty($anim['staging']))
                                                        <div class="flex items-center justify-center rounded-sm border border-dashed border-ink-soft/25 font-mono text-[0.62rem] text-ink-faint animate-pulse">staging…</div>
                                                    @elseif (! empty($anim['staged']))
                                                        <img src="{{ route('clips-animados.room', ['id' => $project->id, 'i' => $i, 'which' => 'staged', 'v' => md5($anim['staged'])]) }}" alt="Staged still of {{ $room['room'] }}" class="w-full rounded-sm border border-ink-soft/15 object-contain bg-black/5 max-h-72">
                                                    @else
                                                        <div class="flex items-center justify-center rounded-sm border border-dashed border-bad/40 p-2 text-center font-mono text-[0.62rem] text-bad">{{ $anim['error'] ?? 'No still yet' }}</div>
                                                    @endif
                                                </div>
                                                @if (count($anim['candidates'] ?? []) > 1 && empty($anim['staging']))
                                                    {{-- Every image the takes made: click one to use it. --}}
                                                    <p class="mt-2 font-mono text-[0.6rem] text-ink-faint">All {{ count($anim['candidates']) }} images, newest take first — click one to use it:</p>
                                                    <div class="mt-1 grid grid-cols-3 sm:grid-cols-4 gap-1.5">
                                                        @foreach ($anim['candidates'] as $n => $cand)
                                                            @php $chosen = ($anim['staged'] ?? null) === $cand['path']; @endphp
                                                            <button type="button" wire:click="chooseStill('{{ $project->id }}', {{ $i }}, {{ $n }})" @disabled($chosen)
                                                                    title="{{ $cand['problems'] ? implode(' · ', $cand['problems']) : 'Openings all kept' }}"
                                                                    class="relative rounded-sm overflow-hidden border-2 transition {{ $chosen ? 'border-teal' : 'border-transparent hover:border-ink-soft/40' }}">
                                                                <img src="{{ route('clips-animados.room', ['id' => $project->id, 'i' => $i, 'which' => 'c'.$n, 'v' => md5($cand['path'])]) }}" alt="Option {{ $n + 1 }} for {{ $room['room'] }}" loading="lazy" class="w-full aspect-video object-cover bg-black/5">
                                                                <span class="absolute bottom-0 inset-x-0 px-1 py-0.5 font-mono text-[0.55rem] text-left {{ $chosen ? 'bg-teal text-papyrus' : 'bg-ink/60 text-papyrus' }}">
                                                                    {{ $chosen ? '✓ in use' : ($cand['problems'] ? count($cand['problems']).' problem'.(count($cand['problems']) === 1 ? '' : 's') : 'clean') }}
                                                                </span>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if (isset($anim['alignment']) && empty($anim['staging']))
                                                    <p class="mt-1 font-mono text-[0.6rem] {{ $anim['alignment'] < \App\Services\RealEstate\RoomStager::DRIFT ? 'text-bad' : 'text-ink-faint' }}">
                                                        Lines up with the real frame: {{ round($anim['alignment'] * 100) }}%{{ $anim['alignment'] < \App\Services\RealEstate\RoomStager::DRIFT ? ' — the camera moved; Kling will morph the room. Take another or skip.' : '' }}
                                                    </p>
                                                @endif
                                                @if (! empty($anim['problems']) && empty($anim['staging']))
                                                    <ul class="mt-1 font-mono text-[0.6rem] text-bad list-disc pl-4">
                                                        @foreach ($anim['problems'] as $problem)
                                                            <li>{{ $problem }}</li>
                                                        @endforeach
                                                    </ul>
                                                @elseif (isset($anim['problems']) && empty($anim['staging']))
                                                    <p class="mt-1 font-mono text-[0.6rem] text-teal">Doors, windows and openings checked: all kept.</p>
                                                @endif
                                                <div wire:ignore wire:key="prompt-{{ $project->id }}-{{ $i }}-{{ md5($anim['prompt']) }}" class="mt-2">
                                                    <textarea id="rp-{{ $project->id }}-{{ $i }}" rows="2" aria-label="Staging prompt for {{ $room['room'] }}"
                                                              class="w-full bg-papyrus/60 border border-ink-soft/20 rounded-sm px-3 py-2 text-sm text-ink focus:outline-none focus:border-teal/50">{{ $anim['prompt'] }}</textarea>
                                                </div>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <button type="button" @disabled(! empty($anim['staging']))
                                                            @click="$wire.restageRoom('{{ $project->id }}', {{ $i }}, document.getElementById('rp-{{ $project->id }}-{{ $i }}').value)"
                                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40">↻ Another take</button>
                                                    <button type="button" wire:click="toggleRoom('{{ $project->id }}', {{ $i }})"
                                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition">{{ $on ? 'Skip — keep as footage' : 'Animate this room' }}</button>
                                                    @if (! empty($project->meta['property']['photos']))
                                                        <button type="button" wire:click="flipRoomMotion('{{ $project->id }}', {{ $i }})" title="Flip the camera's direction (makes this room's animation again)"
                                                                class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition">Camera: {{ ($anim['motion'] ?? 'forward') === 'backward' ? '← pulls back out' : '→ moves into the room' }} · flip</button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                        @if ($plain !== '')
                                            <p class="font-mono text-[0.6rem] text-ink-faint">Kept as footage: {{ $plain }}</p>
                                        @endif
                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="button" wire:click="animateProperty('{{ $project->id }}')" @disabled($staging)
                                                    class="font-display text-lg px-5 py-2 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-50">▶ Animate &amp; cut the reel</button>
                                            @if (empty($project->meta['property']['photos']))
                                            <button type="button" wire:click="reselectSections('{{ $project->id }}')" @disabled($staging)
                                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition disabled:opacity-40">↺ Change the sections</button>
                                            @endif
                                            <label class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft">Final format
                                                <select wire:change="setPropertyAspect('{{ $project->id }}', $event.target.value)"
                                                        class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-teal/60">
                                                    @foreach (\App\Services\RealEstate\PropertyVideoEditor::ASPECTS as $value => $label)
                                                        <option value="{{ $value }}" @selected(($project->meta['property']['aspect'] ?? 'source') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label x-data="{ sent: false }" class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft cursor-pointer hover:text-ink">
                                                <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="sent">✓ Agent photo — replace</span>
                                                    <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="!sent">{{ collect($project->images ?? [])->contains(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato')) ? '✓ Agent photo — replace' : '+ Agent photo' }}</span>
                                                <input type="file" accept="image/*" class="hidden" @change="sent = true" wire:model="agentPhotos.{{ $project->id }}">
                                                <span wire:loading wire:target="agentPhotos.{{ $project->id }}" class="text-ink-faint">uploading…</span>
                                            </label>
                                            <span class="font-mono text-[0.6rem] text-ink-faint">{{ $paid }} room{{ $paid === 1 ? '' : 's' }} to animate (Kling 3.0, 4–7 s each) on kie.ai</span>
                                        </div>
                                    </div>
                                @elseif ($project->status === 'collecting')
                                    <div class="w-full self-start text-center">
                                        <p class="font-mono text-[0.6rem] text-ink-faint mb-2">The plan is ready — upload your own images or let them be generated.</p>
                                        <button type="button" wire:click="revisarImagens('{{ $project->id }}')"
                                                class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">◆ Collect images</button>
                                    </div>
                                @else
                                    <div class="w-full self-start">
                                        <div class="h-1 w-full bg-surface/40 rounded-full overflow-hidden">
                                            <div class="h-full bg-teal/60 animate-pulse"
                                                 style="width: {{ ['importing'=>20,'analyzing'=>25,'animating'=>30,'transcribing'=>33,'planning'=>60,'rendering'=>85][$project->status] ?? 12 }}%"></div>
                                        </div>
                                        <p class="mt-2 font-mono text-[0.58rem] text-ink-faint">{{ $e['label'] }}…</p>
                                        @if (! empty($project->meta['step']))
                                            <p class="font-mono text-[0.58rem] text-ink-soft">{{ $project->meta['step'] }}…</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- RIGHT: suggested title, description, tags, status --}}
                            <div class="min-w-0 flex flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gold text-sm">{{ $t['glyph'] }}</span>
                                        <span class="font-mono text-[0.58rem] text-ink-faint uppercase tracking-wider">{{ $t['label'] }}</span>
                                    </div>
                                    <x-badge :tone="$e['tone']">{{ $e['glyph'] }} {{ $e['label'] }}</x-badge>
                                    @if ($custo > 0)
                                        <span class="font-mono text-[0.62rem] text-ink-soft border border-ink-soft/25 rounded-sm px-2 py-0.5"
                                              title="Provider spend recorded for this clip (images, LLM tokens, voiceover)">≈ ${{ number_format($custo, 2) }}</span>
                                    @endif
                                </div>

                                <div class="font-display text-xl text-ink mt-2">{{ $project->title ?? 'No title' }}</div>
                                <div class="font-mono text-[0.58rem] text-ink-faint">{{ $project->created_at ? \Illuminate\Support\Carbon::parse($project->created_at)->diffForHumans() : '' }}</div>

                                @if (!empty($sug['description']))
                                    <p class="mt-3 text-ink-soft text-sm leading-relaxed whitespace-pre-line">{{ $sug['description'] }}</p>
                                    {{-- Built here: a double quote inside the :text attribute ended it early. --}}
                                    @php $legenda = trim($sug['description'].PHP_EOL.PHP_EOL.collect($sug['tags'] ?? [])->map(fn ($t) => '#'.preg_replace('/\s+/u', '', ltrim($t, '#')))->implode(' ')); @endphp
                                    <x-copy-btn class="mt-2 font-mono text-[0.62rem] px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition"
                                                :text="$legenda" label="⧉ copy caption + hashtags" />
                                @endif

                                @if (!empty($sug['tags']))
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach ($sug['tags'] as $tag)
                                            <span class="font-mono text-[0.58rem] text-teal border border-teal/30 rounded-sm px-1.5 py-0.5">#{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                @php $anuncio = $project->meta['listing'] ?? null; @endphp
                                @if (!empty($anuncio['message']) || !empty($anuncio['flags']))
                                    <details wire:ignore.self class="mt-3 border border-ink-soft/15 rounded-sm px-3 py-2 bg-papyrus/40">
                                        <summary class="cursor-pointer font-mono text-[0.62rem] text-ink-soft">
                                            ⌂ Listing · {{ $anuncio['agent'] ?? '' }} · {{ $anuncio['phone'] ?? '' }}
                                            @if (!empty($anuncio['flags'])) <span class="text-warn">· {{ count($anuncio['flags']) }} to check</span> @endif
                                        </summary>
                                        @if (!empty($anuncio['flags']))
                                            <ul class="mt-2 list-disc pl-5 text-sm text-warn">
                                                @foreach ($anuncio['flags'] as $flag) <li>{{ $flag }}</li> @endforeach
                                            </ul>
                                        @endif
                                        @if (!empty($anuncio['message']))
                                            <p class="mt-2 text-sm text-ink-soft whitespace-pre-line">{{ $anuncio['message'] }}</p>
                                            <div class="mt-2 flex gap-2">
                                                <x-copy-btn :text="$anuncio['message']" label="⧉ copy message" />
                                                @if (!empty($anuncio['phone']))
                                                    <x-copy-btn :text="$anuncio['phone']" label="⧉ copy number" />
                                                @endif
                                                <a href="{{ $anuncio['url'] }}" target="_blank" rel="noopener" class="font-mono text-[0.7rem] px-3 py-1.5 text-teal hover:underline">↗ listing</a>
                                            </div>
                                        @endif
                                    </details>
                                @endif

                                {{-- actions --}}
                                <div class="mt-auto pt-4 flex flex-wrap items-center gap-x-4 gap-y-2 font-mono text-[0.62rem]">
                                    @if (!empty($project->plan['scenes']) && $project->status !== 'collecting')
                                        <button type="button" wire:click="editarClip('{{ $project->id }}')" class="text-teal hover:underline">✎ edit clip</button>
                                    @endif
                                    @if (!empty($project->transcript['text']))
                                        <button type="button" wire:click="editarTranscricao('{{ $project->id }}')" class="text-teal hover:underline">✎ edit transcript · regenerate</button>
                                    @endif
                                    @if ($project->status === 'done' && $project->output_path)
                                        <a href="{{ route('clips-animados.media', ['id' => $project->id, 'download' => 1]) }}" class="text-teal hover:underline">↓ download</a>
                                    @endif
                                    <button type="button" wire:click="apagar('{{ $project->id }}')"
                                            wire:confirm="Delete this clip and its files? This action is permanent."
                                            class="text-bad hover:underline ml-auto">✕ delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- COLLECT IMAGES (upload your own, or let it be generated)     --}}
    {{-- ============================================================ --}}
    @if ($view === 'reviewImages')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to dashboard</button>

        <x-panel eyebrow="Step · III — Images" title="Your images or generated" glyph="◆">
            <p class="text-ink-soft text-sm mb-5">The plan suggests these images, and the studio already went looking for the real ones — a photo, a logo, the actual page. For each one you can keep what was found, <span class="text-teal">upload</span> your own file (or pick one from the library), search <span class="text-teal">again</span>, let it be an <span class="text-teal">AI image</span>, or choose <span class="text-teal">no image</span> — that scene then becomes a text visual (card / list / diagram) like the non-image scenes. Green thumbnails came from your Assets library or from the web.</p>

            <div class="space-y-2 mb-6">
                @forelse ($this->imageRequests as $r)
                    <div class="flex flex-wrap items-center gap-3 bg-surface/30 border border-ink-soft/15 rounded-sm p-2" wire:key="req-{{ $r['key'] }}">
                        @if (!empty($r['path']) && !empty($r['video']))
                            <video src="{{ route('clips-animados.upload', basename($r['path'])) }}" muted playsinline
                                   class="w-12 h-12 object-cover rounded-sm border {{ !empty($r['fromLibrary']) ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40"></video>
                        @elseif (!empty($r['path']))
                            <img src="{{ route('clips-animados.upload', basename($r['path'])) }}"
                                 onerror="this.style.visibility='hidden'"
                                 onclick="window.dispatchEvent(new CustomEvent('abrir-lightbox', {detail: {imgs: ['{{ route('clips-animados.upload', basename($r['path'])) }}'], i: 0}}))"
                                 title="click to enlarge"
                                 class="w-12 h-12 object-cover rounded-sm border cursor-zoom-in {{ !empty($r['fromLibrary']) ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40" alt="" />
                        @else
                            <div class="w-12 h-12 rounded-sm border border-dashed border-ink-soft/30 flex items-center justify-center text-ink-faint text-lg bg-vellum/20">{{ $r['mode'] === 'text' ? '✎' : '✦' }}</div>
                        @endif

                        <span class="flex-1 min-w-0 text-sm text-ink">
                            {{ $r['label'] ?? (trim(\Illuminate\Support\Str::after($r['prompt'], 'Illustrate this moment:')) ?: $r['prompt']) }}
                            @if (!empty($r['fromLibrary']))
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· from library</span>
                            @elseif ($r['mode'] === 'upload' && !empty($r['fetched']))
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· found on the web</span>
                                @if (!empty($r['source']))
                                    <a href="{{ $r['source'] }}" target="_blank" rel="noopener noreferrer"
                                       class="ml-1 font-mono text-[0.55rem] text-ink-faint hover:text-teal underline"
                                       title="{{ $r['source'] }}">source ↗</a>
                                @endif
                            @elseif ($r['mode'] === 'upload' && !empty($r['gerado']))
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· AI image — ready</span>
                            @elseif ($r['mode'] === 'upload')
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· your image</span>
                            @elseif ($r['mode'] === 'text')
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· no image — becomes a text scene</span>
                            @elseif (!empty($r['animado']) && !empty($r['collecting']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· generating the image…</span>
                            @elseif (!empty($r['animado']) && empty($r['path']))
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· animated intro — generate the image first, then decide</span>
                            @elseif (!empty($r['animado']))
                                <span class="ml-1 font-mono text-[0.55rem] {{ ($r['videoMode'] ?? 'video') === 'video' ? 'text-teal' : 'text-ink-faint' }}">
                                    · animated intro — {{ ($r['videoMode'] ?? 'video') === 'video' ? 'the video is generated on approve' : 'kept as a still image' }}</span>
                            @elseif (!empty($r['collecting']) && !empty($r['site']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· filming {{ $r['site'] }}…</span>
                            @elseif (!empty($r['searching']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· searching the web for a real image…</span>
                            @elseif (!empty($r['collecting']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· generating the image…</span>
                            @elseif (!empty($r['site']))
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· films {{ $r['site'] }} on approve</span>
                            @else
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· will be generated</span>
                            @endif
                        </span>

                        {{-- What this suggestion becomes: your file · an AI image · no image at all. --}}
                        <div class="flex items-center gap-1 font-mono text-[0.6rem] whitespace-nowrap">
                            @php $on = 'border-teal/60 text-teal bg-teal/10'; $off = 'border-ink-soft/20 text-ink-faint hover:text-ink'; @endphp
                            <label class="px-2 py-1 rounded-sm border cursor-pointer {{ $r['mode'] === 'upload' ? $on : $off }}">
                                <span wire:loading.remove wire:target="reviewUploads.{{ $r['key'] }}">↑ upload</span>
                                <span wire:loading wire:target="reviewUploads.{{ $r['key'] }}">uploading…</span>
                                <input type="file" class="hidden" accept="image/*,video/mp4,video/quicktime,video/webm" wire:model="reviewUploads.{{ $r['key'] }}" />
                            </label>
                            @if (!empty($r['site']))
                                <button type="button" wire:click="coletarSite('{{ $r['key'] }}')" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'generate' && !$r['uploadedId'] ? $on : $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="film {{ $r['site'] }} now, to see it before approving">
                                    {{ !empty($r['collecting']) ? '⏺ filming…' : '⏺ collect now' }}
                                </button>
                            @elseif (!empty($r['animado']) && empty($r['path']))
                                <button type="button" wire:click="recriarImagemIntro" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $on }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="generate the start image — the video choice unlocks once you see it">
                                    {{ !empty($r['collecting']) ? '✦ generating…' : '✦ generate image' }}</button>
                            @elseif (!empty($r['animado']))
                                <button type="button" wire:click="modoVideoIntro('video')"
                                        class="px-2 py-1 rounded-sm border {{ ($r['videoMode'] ?? 'video') === 'video' && $r['mode'] === 'generate' ? $on : $off }}"
                                        title="animate this image into the intro video (and save it as a reusable asset)">▶ video</button>
                                <button type="button" wire:click="modoVideoIntro('image')"
                                        class="px-2 py-1 rounded-sm border {{ ($r['videoMode'] ?? 'video') === 'image' && $r['mode'] === 'generate' ? $on : $off }}"
                                        title="keep it as a still image — no video is generated">▣ image only</button>
                                <button type="button" wire:click="recriarImagemIntro" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="generate a different take of this image">{{ !empty($r['collecting']) ? '↻ generating…' : '↻ new image' }}</button>
                            @else
                                <button type="button" wire:click="procurarImagem('{{ $r['key'] }}')" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ !empty($r['fetched']) ? $on : $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="search the web for the REAL image this scene needs — a photo, a logo, a page">
                                    {{ !empty($r['searching']) ? '⌕ searching…' : (empty($r['path']) ? '⌕ find image' : '⌕ find another') }}</button>
                                <button type="button" wire:click="gerarImagemAgora('{{ $r['key'] }}')" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'generate' ? $on : $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="generate this image now — see it here before approving">
                                    {{ !empty($r['collecting']) && empty($r['searching']) ? '✦ generating…' : (empty($r['path']) ? '✦ generate now' : '↻ new image') }}</button>
                            @endif
                            @if (empty($r['site']))
                                <button type="button" wire:click="editarPrompt('{{ $r['key'] }}')"
                                        class="px-2 py-1 rounded-sm border {{ $promptKey === $r['key'] ? $on : $off }}"
                                        title="view and edit the image-generation prompt">✎ prompt</button>
                            @endif
                            <button type="button" wire:click="modoImagem('{{ $r['key'] }}', 'text')"
                                    class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'text' ? $on : $off }}"
                                    title="no image — this scene becomes a card/list/diagram like the others">✎ no image</button>
                            @if (!empty($this->libraryImages))
                                <button type="button" wire:click="abrirBibliotecaImagens('{{ $r['key'] }}')"
                                        class="px-2 py-1 text-teal hover:opacity-70">▦ library</button>
                            @endif
                        </div>
                        @error('reviewUploads.'.$r['key']) <p class="w-full text-bad text-xs">{{ $message }}</p> @enderror
                        @if (!empty($r['siteError'])) <p class="w-full text-bad text-xs">Could not film the page: {{ $r['siteError'] }}</p> @endif

                        {{-- Generation-prompt editor for this suggestion --}}
                        @if ($promptKey === $r['key'])
                            <div class="w-full mt-1 border-t border-ink-soft/15 pt-2">
                                <label class="font-mono text-[0.55rem] text-ink-faint uppercase tracking-wider block mb-1">Image-generation prompt (English)</label>
                                <textarea wire:model="promptEdits.{{ $r['key'] }}" rows="3"
                                          class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-xs focus:border-teal focus:outline-none"></textarea>
                                <div class="mt-1 flex items-center gap-2">
                                    <button type="button" wire:click="gerarImagemAgora('{{ $r['key'] }}')"
                                            class="px-3 py-1 rounded-sm border border-teal/60 text-teal bg-teal/10 font-mono text-[0.6rem]">✦ generate with this prompt</button>
                                    <button type="button" wire:click="editarPrompt('{{ $r['key'] }}')"
                                            class="font-mono text-[0.6rem] text-ink-faint hover:text-ink">close</button>
                                </div>
                            </div>
                        @endif

                        {{-- Library picker for this suggestion --}}
                        @if ($libraryPickerKey === $r['key'])
                            <div class="w-full mt-1 border-t border-ink-soft/15 pt-2">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-mono text-[0.55rem] text-ink-faint uppercase tracking-wider">Pick from library</span>
                                    <button type="button" wire:click="abrirBibliotecaImagens('{{ $r['key'] }}')" class="font-mono text-[0.55rem] text-ink-faint hover:text-ink">close</button>
                                </div>
                                <div class="flex gap-2 overflow-x-auto pb-1">
                                    @foreach ($this->libraryImages as $lib)
                                        <button type="button" wire:click="usarImagemBiblioteca('{{ $r['key'] }}', '{{ $lib['id'] }}')"
                                                class="shrink-0 w-16 group text-left" title="{{ $lib['description'] }}">
                                            @if (!empty($lib['video']))
                                                <video src="{{ route('clips-animados.library-image', $lib['id']) }}" muted playsinline
                                                       class="w-16 h-16 object-contain rounded-sm border border-ink-soft/20 bg-vellum/40 group-hover:border-teal transition"></video>
                                            @else
                                                <img src="{{ route('clips-animados.library-image', $lib['id']) }}"
                                                     class="w-16 h-16 object-contain rounded-sm border border-ink-soft/20 bg-vellum/40 group-hover:border-teal transition" alt="{{ $lib['name'] }}" />
                                            @endif
                                            <span class="block font-mono text-[0.5rem] text-ink-faint truncate mt-0.5">{{ $lib['description'] ?: $lib['name'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-ink-soft text-sm">No image suggestions for this clip.</p>
                @endforelse
            </div>

            {{-- Disabled while images are still arriving: continuing now would
                 settle the plan and throw away whatever landed a second later. --}}
            <button type="button" wire:click="finalizarImagens" @if ($this->siteCollecting) disabled @endif
                    x-on:click="window.CMLoader?.busy('Generating the animation…')"
                    class="font-display text-lg px-6 py-2.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition @if ($this->siteCollecting) opacity-50 cursor-wait hover:bg-transparent @endif">
                {{ $this->siteCollecting ? 'Still collecting images…' : '✦ Generate the rest & continue →' }}
            </button>
        </x-panel>
    @endif

    {{-- ============================================================ --}}
    {{-- CREATION                                                     --}}
    {{-- ============================================================ --}}
    @if ($view === 'create')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to dashboard</button>

        @if ($createType === null)
            {{-- step 1: choose type --}}
            <div class="eyebrow mb-4">Step · I — what kind of clip?</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <button type="button" wire:click="escolherTipo('animation')" class="text-left group">
                    <x-panel class="h-full transition group-hover:border-teal/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="eyebrow mb-2">Type · A</div>
                                <h2 class="font-display text-3xl text-ink">Animation</h2>
                                <p class="mt-2 text-ink-soft">From text or voiceover — fully animated video, with animation on every second.</p>
                            </div>
                            <span class="text-4xl text-gold/70 select-none">❈</span>
                        </div>
                        <div class="mt-6 font-mono text-[0.62rem] text-teal">choose →</div>
                    </x-panel>
                </button>
                <button type="button" wire:click="escolherTipo('overlay')" class="text-left group">
                    <x-panel class="h-full transition group-hover:border-teal/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="eyebrow mb-2">Type · B</div>
                                <h2 class="font-display text-3xl text-ink">Video + Animations</h2>
                                <p class="mt-2 text-ink-soft">From a ready-made video — overlaid animations only at the moments worth it.</p>
                            </div>
                            <span class="text-4xl text-gold/70 select-none">❖</span>
                        </div>
                        <div class="mt-6 font-mono text-[0.62rem] text-teal">choose →</div>
                    </x-panel>
                </button>
                <button type="button" wire:click="escolherTipo('property')" class="text-left group">
                    <x-panel class="h-full transition group-hover:border-teal/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="eyebrow mb-2">Type · C</div>
                                <h2 class="font-display text-3xl text-ink">Property animation</h2>
                                <p class="mt-2 text-ink-soft">From a walkthrough video and its listing — rooms cut together with zooms, furniture animated in or out, narrated with the agent's cards.</p>
                            </div>
                            <span class="text-4xl text-gold/70 select-none">⌂</span>
                        </div>
                        <div class="mt-6 font-mono text-[0.62rem] text-teal">choose →</div>
                    </x-panel>
                </button>
            </div>
        @elseif ($createType === 'animation')
            {{-- step 2A: animation form --}}
            <x-panel eyebrow="Step · II — Animation" title="New animation" glyph="❈">
                <form wire:submit="submitAnimation" x-on:submit="window.CMLoader.busy('Preparing the animated clip…')" class="space-y-5">
                    <div>
                        <label class="eyebrow block mb-2">Script / text for voiceover</label>
                        <textarea wire:model="text" rows="5"
                                  placeholder="Write the text. It will be converted to voiceover and transcribed to get the exact timings."
                                  class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none"></textarea>
                        @error('text') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="h-px flex-1 bg-ink-soft/15"></span>
                        <span class="font-mono text-[0.6rem] text-ink-faint uppercase tracking-widest">or</span>
                        <span class="h-px flex-1 bg-ink-soft/15"></span>
                    </div>

                    <div x-data="voiceRecorder" class="space-y-3">
                        <label class="eyebrow block">Record voice</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" x-show="!recording" @click="start()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-sm border border-leather/50 text-leather font-mono text-xs hover:bg-leather/10 transition">
                                <span class="text-base leading-none">●</span> Record
                            </button>
                            <button type="button" x-show="recording" x-cloak @click="stop()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-sm border border-bad/60 text-bad font-mono text-xs hover:bg-bad/10 transition">
                                <span class="text-base leading-none animate-pulse">■</span> Stop · <span x-text="elapsed + 's'"></span>
                            </button>
                            <span x-show="uploading" x-cloak class="font-mono text-[0.6rem] text-ink-faint">uploading recording…</span>
                            <span x-show="ready && !recording && !uploading" x-cloak class="font-mono text-[0.6rem] text-teal">recording ready ✔</span>
                            <span x-show="error" x-cloak class="font-mono text-[0.6rem] text-bad" x-text="error"></span>
                        </div>
                        <audio x-ref="player" x-show="ready" x-cloak controls class="w-full rounded-sm border border-ink-soft/15"></audio>
                    </div>

                    <div>
                        <label class="eyebrow block mb-2">Or upload voiceover (audio)</label>
                        <input type="file" wire:model="audio" accept="audio/*"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="audio" class="mt-1 font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                        @error('audio') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </div>

                    @include('livewire.partials.lead-field')

                    @include('livewire.partials.agent-extras-field')

                    @include('livewire.partials.instructions-field')


                    @include('livewire.partials.no-images')

                    @if (! $semImagens) @include('livewire.partials.only-reference-images') @endif
                    @if (! $semImagens && ! $soReferencias) @include('livewire.partials.fewer-images') @endif

                    @include('livewire.partials.free-sfx')

                    @include('livewire.partials.backdrop-picker')

                    @include('livewire.partials.musica-picker')
                    @include('livewire.partials.captions-picker')

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition" wire:loading.attr="disabled">
                            Generate animated clip
                        </button>
                        <span wire:loading wire:target="submitAnimation" class="font-mono text-[0.6rem] text-ink-faint">preparing…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">Dense mode — every second of the voiceover gets animation.</p>
                </form>
            </x-panel>
        @elseif ($createType === 'overlay')
            {{-- step 2B: video+animations form --}}
            <x-panel eyebrow="Step · II — Video + Animations" title="Video with animations" glyph="❖">
                <form wire:submit="submitOverlay" x-on:submit="window.CMLoader.busy('Preparing the clip…')" class="space-y-5">
                    @if ($videoPronto)
                        {{-- Came from the Shorts Generator: the cut is already staged. --}}
                        <div>
                            <label class="eyebrow block mb-2">Video</label>
                            <div class="flex flex-wrap items-center gap-3 rounded-sm border border-teal/40 bg-teal/5 px-3 py-2">
                                <span class="text-teal">✂</span>
                                <span class="min-w-0">
                                    <span class="block font-display text-lg text-ink leading-tight truncate">{{ $videoProntoNome }}</span>
                                    <span class="block font-mono text-[0.6rem] text-ink-faint">cut in the Shorts Generator — no burnt-in captions, no music</span>
                                </span>
                                <button type="button" wire:click="$set('videoPronto', null)"
                                        class="ml-auto font-mono text-[0.62rem] text-ink-soft hover:text-ink shrink-0">use another video</button>
                            </div>
                        </div>
                    @else
                        <div>
                            <label class="eyebrow block mb-2">Upload video (mp4 / mov)</label>
                            <input type="file" wire:model="video" accept="video/mp4,video/quicktime"
                                   class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                            <div wire:loading wire:target="video" class="mt-1 font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                            @error('video') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @include('livewire.partials.presents-picker')

                    @include('livewire.partials.format-picker')

                    @include('livewire.partials.lead-field')

                    @include('livewire.partials.instructions-field')


                    @include('livewire.partials.no-images')

                    @if (! $semImagens) @include('livewire.partials.only-reference-images') @endif
                    @if (! $semImagens && ! $soReferencias) @include('livewire.partials.fewer-images') @endif

                    @include('livewire.partials.free-sfx')

                    @include('livewire.partials.backdrop-picker')

                    @include('livewire.partials.musica-picker')
                    @include('livewire.partials.captions-picker')

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition" wire:loading.attr="disabled">
                            Generate clip
                        </button>
                        <span wire:loading wire:target="submitOverlay" class="font-mono text-[0.6rem] text-ink-faint">preparing…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">The AI automatically interleaves the chosen styles, according to the content.</p>
                </form>
            </x-panel>
        @elseif ($createType === 'property')
            {{-- step 2C: property walkthrough form --}}
            <x-panel eyebrow="Step · II — Property animation" title="Property walkthrough" glyph="⌂">
                <form wire:submit="submitProperty" x-on:submit="window.CMLoader.busy('Uploading the walkthrough…')" class="space-y-5">
                    <fieldset>
                        <legend class="eyebrow mb-2">Animate from</legend>
                        <div class="flex flex-wrap gap-4 text-sm text-ink">
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertySource" value="video"> a walkthrough video (you mark the moments)</label>
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertySource" value="photos"> photos (each one becomes a room)</label>
                        </div>
                    </fieldset>
                    @if ($propertySource === 'photos')
                    <div>
                        <label for="propertyPhotos" class="eyebrow block mb-2">Room photos (up to 15, in the order they play)</label>
                        <input id="propertyPhotos" type="file" wire:model="propertyPhotos" accept="image/*" multiple
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="propertyPhotos" class="mt-1 font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                        @if (is_array($propertyPhotos) && count($propertyPhotos))
                            <p class="mt-1 font-mono text-[0.6rem] text-ink-soft">{{ count($propertyPhotos) }} photo{{ count($propertyPhotos) === 1 ? '' : 's' }} ready.</p>
                        @endif
                        @error('propertyPhotos') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        @error('propertyPhotos.*') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Each photo is modernised and the camera moves slowly into the room or back out of it (you can flip it per room). The reel takes the first photo's shape.</p>
                    </div>
                    @else
                    <div>
                        <label for="propertyVideo" class="eyebrow block mb-2">Walkthrough video (mp4 / mov)</label>
                        <input id="propertyVideo" type="file" wire:model="video" accept="video/mp4,video/quicktime"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="video" class="mt-1 font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                        @error('video') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Slow, straight pushes into each room animate best.</p>
                    </div>
                    @endif
                    <div>
                        <label for="propertyAspect" class="eyebrow block mb-2">Final format</label>
                        <select id="propertyAspect" wire:model="propertyAspect"
                                class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                            @foreach (\App\Services\RealEstate\PropertyVideoEditor::ASPECTS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">The rooms are centre-cropped to it; the intro and outro cards are drawn at that size. Vertical from horizontal footage keeps the middle third of each frame — film in 4K for that.</p>
                    </div>
                    <div>
                        <label for="propertyListingUrl" class="eyebrow block mb-2">Listing URL (optional)</label>
                        <input id="propertyListingUrl" type="url" wire:model.blur="propertyListingUrl" placeholder="https://www.remax.pt/pt/imoveis/…"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60">
                        @error('propertyListingUrl') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Gives the agent's outro card and the house facts for the intro card.</p>
                    </div>
                    @if (trim($propertyListingUrl) === '')
                        <fieldset class="border border-ink-soft/15 rounded-sm p-3 space-y-3">
                            <legend class="eyebrow px-1">No listing URL? Type the agent and facts</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input type="text" wire:model="propertyAgent" placeholder="Agent's name" aria-label="Agent's name"
                                       class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                <input type="text" wire:model="propertyPhone" placeholder="Phone (+351 …)" aria-label="Agent's phone"
                                       class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                <input type="text" wire:model="propertyAgency" placeholder="Agency (e.g. RE/MAX Valor)" aria-label="Agency"
                                       class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                <select wire:model="propertyGender" aria-label="Title on the card"
                                        class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                    <option value="m">Consultor imobiliário</option>
                                    <option value="f">Consultora imobiliária</option>
                                </select>
                            </div>
                            <div>
                                <label for="propertyPhoto" class="font-mono text-[0.62rem] text-ink-soft">Agent's photo (for the outro card)</label>
                                <input id="propertyPhoto" type="file" wire:model="propertyPhoto" accept="image/*"
                                       class="mt-1 block w-full text-sm text-ink-soft file:mr-3 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                                @error('propertyPhoto') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                            </div>
                            <textarea wire:model="propertyFacts" rows="4" aria-label="House facts"
                                      placeholder="House facts, one per line — for the intro card&#10;Área útil: 152 m²&#10;Quartos: 3&#10;Preço: 350.000 €"
                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60"></textarea>
                        </fieldset>
                    @endif
                    <fieldset>
                        <legend class="eyebrow block mb-2">Voiceover</legend>
                        <div class="flex flex-wrap gap-4 text-sm text-ink-soft">
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertyVoice" value="none"> None — music only</label>
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertyVoice" value="listing"> Written and narrated from the listing (pt-PT)</label>
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertyVoice" value="upload"> Upload my own</label>
                        </div>
                        @if ($propertyVoice === 'upload')
                            <input type="file" wire:model="audio" accept="audio/*" aria-label="Voiceover audio"
                                   class="mt-2 block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                            <div wire:loading wire:target="audio" class="mt-1 font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                        @endif
                        @error('audio') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </fieldset>
                    @include('livewire.partials.agent-extras-field')
                    @include('livewire.partials.musica-picker')
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition" wire:loading.attr="disabled">
                            Make the property video
                        </button>
                        <span wire:loading wire:target="submitProperty" class="font-mono text-[0.6rem] text-ink-faint">preparing…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">Stabilizes and colour-corrects the footage, then you mark the sections to modernise and approve each modern still before any video is paid for. Each section transforms on screen with its own sounds, joined by whip pans, with your music and voiceover and the agent's outro at the end — under a minute.</p>
                </form>
            </x-panel>
        @endif

        {{-- Images (optional) — for the animation and video types --}}
        @if (in_array($createType, ['animation', 'overlay'], true))
            <x-panel class="mt-6" eyebrow="Optional" title="Images" glyph="▣">
                <p class="text-ink-soft text-sm mb-4">Upload images and describe what they show. The AI uses them animated in the scenes where they fit.</p>
                @include('livewire.partials.clip-images')
            </x-panel>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- EDIT CLIP (animations)                                       --}}
    {{-- ============================================================ --}}
    @if ($view === 'editPlan')
        <div class="flex items-center justify-between mb-6">
            <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">← back to dashboard</button>
            <div class="flex gap-1 font-mono text-[0.62rem]">
                <button type="button" wire:click="$set('editMode','cenas')"
                        class="px-3 py-1 rounded-sm border {{ $editMode === 'cenas' ? 'border-teal/50 text-teal' : 'border-ink-soft/20 text-ink-soft' }}">Scenes</button>
                <button type="button" wire:click="$set('editMode','json')"
                        class="px-3 py-1 rounded-sm border {{ $editMode === 'json' ? 'border-teal/50 text-teal' : 'border-ink-soft/20 text-ink-soft' }}">JSON (Remotion)</button>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-4 border border-bad/40 bg-bad/5 rounded-sm p-3">
                <ul class="text-bad text-sm space-y-0.5">
                    @foreach ($errors->all() as $err)
                        <li>• {{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-panel class="mb-6" eyebrow="Uploaded" title="Images" glyph="▣">
            <p class="text-ink-soft text-sm mb-4">Replace a picture (keeps its place in the video), or add/remove uploads. Changes re-render the current plan — a replaced image shows immediately; a newly added one only appears if a scene references it.</p>
            @include('livewire.partials.clip-images')
        </x-panel>

        @if ($editMode === 'json')
            <x-panel eyebrow="Edit clip" title="Remotion plan (JSON)" glyph="⌘">
                <form wire:submit="guardarJson" class="space-y-4">
                    <p class="text-ink-soft text-sm">Edit directly the plan that goes to Remotion (scenes, layers, params). Audio, karaoke and images are added at render.</p>
                    @include('livewire.partials.musica-picker')
                    @include('livewire.partials.captions-picker')
                    <textarea wire:model="editPlanJson" rows="22" spellcheck="false"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink font-mono text-xs leading-relaxed focus:border-teal/50 focus:outline-none" style="tab-size:2"></textarea>
                    @error('editPlanJson') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Save JSON and re-render</button>
                        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancel</button>
                    </div>
                </form>
            </x-panel>
        @else
        <x-panel eyebrow="Edit clip" title="Scenes" glyph="✎">
            <form wire:submit="guardarPlano" class="space-y-5">
                <div>
                    <label class="eyebrow block mb-2">Title</label>
                    <input type="text" wire:model="editTitle" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none" />
                </div>

                @include('livewire.partials.musica-picker')
                @include('livewire.partials.captions-picker')

                <div class="space-y-3">
                    @foreach ($editScenes as $i => $scene)
                        <div class="border border-ink-soft/15 rounded-sm p-3 bg-surface/20" wire:key="scene-{{ $i }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="eyebrow">Scene {{ $i + 1 }} · <span class="text-ink-faint normal-case tracking-normal">{{ $scene['layersSummary'] ?? '—' }}</span></span>
                                <button type="button" wire:click="removerCena({{ $i }})" class="text-bad font-mono text-sm hover:opacity-70" title="remove">✕</button>
                            </div>

                            {{-- Animation: its sample + a picker to swap it. --}}
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-14 aspect-[9/16] rounded-sm overflow-hidden bg-black/50 shrink-0 flex items-center justify-center">
                                    @if (! empty($scene['animacao']) && in_array($scene['animacao'], $sfxReady, true))
                                        <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $scene['animacao']) }}" autoplay loop muted playsinline onerror="this.style.display='none'"></video>
                                    @else
                                        <span class="font-mono text-[0.45rem] text-ink-faint text-center px-1">no sample</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-mono text-[0.55rem] text-ink-faint">animation</div>
                                    <div class="font-display text-ink truncate">{{ $scene['animacao'] ?? '—' }}</div>
                                </div>
                                @if (in_array($scene['animacao'] ?? null, $freeSfxSlugs, true))
                                    {{-- Written by Free SFX for this clip only: offer to keep it. --}}
                                    <button type="button" wire:click="guardarFreeSfx('{{ $scene['animacao'] }}')"
                                            class="font-mono text-[0.62rem] px-3 py-1.5 rounded-sm border border-gold/40 text-gold hover:bg-gold/10 transition shrink-0">✦ Add to SFX library</button>
                                @endif
                                <button type="button" wire:click="escolherAnimacao({{ $i }})"
                                        class="font-mono text-[0.62rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition shrink-0">⇆ Change</button>
                            </div>

                            @if ($animacaoPickerCena === $i)
                                <div class="mb-3 border border-teal/30 rounded-sm p-2 bg-vellum/40" @if (count($animacoes) > count($sfxReady)) wire:poll.4s @endif>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="eyebrow">Choose an animation</span>
                                        <span class="font-mono text-[0.55rem] text-ink-faint">charts &amp; timelines are written from this scene's words on save</span>
                                        <button type="button" wire:click="fecharAnimacoes" class="font-mono text-[0.6rem] text-ink-soft hover:text-ink">close</button>
                                    </div>
                                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 max-h-96 overflow-y-auto">
                                        @foreach ($animacoes as $a)
                                            <button type="button" wire:click="mudarAnimacao({{ $i }}, '{{ $a['slug'] }}')" wire:key="anim-{{ $i }}-{{ $a['slug'] }}"
                                                    class="rounded-sm border p-1 flex flex-col transition hover:border-teal/50 {{ ($scene['animacao'] ?? null) === $a['slug'] ? 'border-teal/60 bg-teal/10' : 'border-ink-soft/15' }}">
                                                <div class="aspect-[9/16] rounded-sm overflow-hidden bg-black/50 flex items-center justify-center">
                                                    @if (in_array($a['slug'], $sfxReady, true))
                                                        <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $a['slug']) }}" autoplay loop muted playsinline onerror="this.style.display='none'"></video>
                                                    @else
                                                        <span class="font-mono text-[0.45rem] text-ink-faint animate-pulse">rendering…</span>
                                                    @endif
                                                </div>
                                                <span class="font-mono text-[0.5rem] text-ink truncate mt-1 text-center w-full">{{ $a['label'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Scene image: preview + replace (upload or pick from library). --}}
                            @php $sceneImg = $this->sceneImages[$i] ?? null; @endphp
                            @if ($sceneImg)
                                @php $temImagem = !empty($sceneImg['id']); @endphp
                                <div class="flex items-center gap-3 mb-3">
                                    {{-- The placeholder sits BEHIND the preview: an empty slot shows it, and
                                         so does an image that cannot be previewed yet — one just added here
                                         is only on the record after Save, so its thumbnail 404s until then.
                                         Without this the row collapsed to a blank gap and read as broken. --}}
                                    <div class="relative w-14 aspect-[9/16] shrink-0 rounded-sm border border-dashed border-ink-soft/40 bg-vellum/20 flex items-center justify-center text-ink-faint text-lg">
                                        <span aria-hidden="true">+</span>
                                        @if ($temImagem)
                                            @if (!empty($sceneImg['video']))
                                                <video src="{{ route('clips-animados.clip-image', ['id' => $editingId, 'imageId' => $sceneImg['id']]) }}" muted playsinline
                                                       class="absolute inset-0 w-full h-full object-cover rounded-sm border {{ $sceneImg['library'] ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40"></video>
                                            @else
                                                <img src="{{ route('clips-animados.clip-image', ['id' => $editingId, 'imageId' => $sceneImg['id']]) }}"
                                                     onerror="this.style.display='none'"
                                                     class="absolute inset-0 w-full h-full object-cover rounded-sm border {{ $sceneImg['library'] ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40" alt="" />
                                            @endif
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-[0.55rem] text-ink-faint">
                                            @if (! $temImagem)
                                                no image yet
                                            @else
                                                {{ !empty($sceneImg['video']) ? 'video' : 'image' }}{{ $sceneImg['library'] ? ' · from library' : '' }}
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 font-mono text-[0.6rem] mt-1">
                                            @if (!empty($this->libraryImages))
                                                <button type="button" wire:click="abrirBibliotecaCena({{ $i }})" class="text-teal hover:opacity-70">▦ library</button>
                                            @endif
                                            <label class="text-teal hover:opacity-70 cursor-pointer">
                                                <span wire:loading.remove wire:target="sceneImageUploads.{{ $i }}">↑ {{ $temImagem ? 'replace' : 'add image' }}</span>
                                                <span wire:loading wire:target="sceneImageUploads.{{ $i }}">uploading…</span>
                                                <input type="file" class="hidden" accept="image/*,video/mp4,video/quicktime,video/webm" wire:model="sceneImageUploads.{{ $i }}" />
                                            </label>
                                        </div>
                                        @error('sceneImageUploads.'.$i) <p class="text-bad text-xs mt-0.5">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                @if ($sceneLibraryPicker === $i)
                                    <div class="mb-3 border border-teal/30 rounded-sm p-2 bg-vellum/40">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="eyebrow">Pick from library</span>
                                            <button type="button" wire:click="abrirBibliotecaCena({{ $i }})" class="font-mono text-[0.6rem] text-ink-soft hover:text-ink">close</button>
                                        </div>
                                        <div class="flex gap-2 overflow-x-auto pb-1">
                                            @foreach ($this->libraryImages as $lib)
                                                <button type="button" wire:click="usarImagemBibliotecaCena({{ $i }}, '{{ $lib['id'] }}')" wire:key="scenelib-{{ $i }}-{{ $lib['id'] }}"
                                                        class="shrink-0 w-16 group text-left" title="{{ $lib['description'] }}">
                                                    @if (!empty($lib['video']))
                                                        <video src="{{ route('clips-animados.library-image', $lib['id']) }}" muted playsinline
                                                               class="w-16 h-16 object-contain rounded-sm border border-ink-soft/20 bg-vellum/40 group-hover:border-teal transition"></video>
                                                    @else
                                                        <img src="{{ route('clips-animados.library-image', $lib['id']) }}"
                                                             class="w-16 h-16 object-contain rounded-sm border border-ink-soft/20 bg-vellum/40 group-hover:border-teal transition" alt="" />
                                                    @endif
                                                    <span class="block font-mono text-[0.5rem] text-ink-faint truncate mt-0.5">{{ $lib['description'] ?: $lib['name'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif

                            {{-- A listing given as video: pick the exact moment(s) of the property video this block shows. --}}
                            @if ($this->listingVideoLength && ($slots = $this->videoSlots($i)) !== [])
                                <div class="mb-3">
                                    <button type="button" wire:click="abrirMomentos({{ $i }})"
                                            class="font-mono text-[0.62rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">
                                        🎞 Choose from the property video{{ count($slots) > 1 ? ' ('.count($slots).' moments)' : '' }}
                                    </button>
                                    @if ($momentPicker === $i)
                                        @php $block = max(0.5, (float) $scene['end'] - (float) $scene['start']); @endphp
                                        <div class="mt-2 border border-teal/30 rounded-sm p-2 bg-vellum/40" wire:ignore wire:key="moments-{{ $i }}"
                                             x-data="sectionPicker(@js($this->listingVideoLength), @js(collect($slots)->filter(fn ($s) => $s['start'] !== null)->values()->all()), { max: {{ count($slots) }}, min: 0.5, maxLen: 10 })">
                                            <video x-ref="video" class="w-full rounded-sm border border-ink-soft/15 bg-black max-h-[40vh]" muted playsinline preload="auto"
                                                   src="{{ route('clips-animados.listing-video', ['id' => $editingId, 'what' => 'footage']) }}"></video>
                                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 font-mono text-[0.6rem] text-ink-faint">
                                                <span x-text="label(cursor)"></span>
                                                <span>Drag on the strip to mark {{ count($slots) > 1 ? 'up to '.count($slots).' moments, one per picture of this effect' : 'the moment' }} · this block lasts {{ number_format($block, 1) }} s — a shorter moment runs on to fill it</span>
                                            </div>
                                            <div x-ref="strip" class="relative mt-1 h-14 rounded-sm border border-ink-soft/25 overflow-hidden cursor-crosshair select-none touch-none"
                                                 style="background: url('{{ route('clips-animados.listing-video', ['id' => $editingId, 'what' => 'strip']) }}') center/100% 100% no-repeat"
                                                 @pointerdown="down($event)" @pointermove="move($event)" @pointerup="up($event)" @pointerleave="up($event)">
                                                <template x-for="(s, k) in sections" :key="k">
                                                    <div class="absolute inset-y-0" :style="`left:${pct(s.start)}%;width:${pct(s.end - s.start)}%;background:rgba(44,95,99,.45);border-left:2px solid #2C5F63;border-right:2px solid #2C5F63`">
                                                        <span class="absolute top-0.5 left-1 font-mono text-[0.6rem] text-papyrus drop-shadow" x-text="k + 1"></span>
                                                        <span class="absolute inset-y-0 -left-1.5 w-3 cursor-ew-resize" data-edge="start" :data-i="k"></span>
                                                        <span class="absolute inset-y-0 -right-1.5 w-3 cursor-ew-resize" data-edge="end" :data-i="k"></span>
                                                    </div>
                                                </template>
                                                <div x-show="drag" class="absolute inset-y-0"
                                                     :style="drag && `left:${pct(Math.min(drag.from, drag.to))}%;width:${pct(Math.abs(drag.to - drag.from))}%;background:rgba(184,134,52,.45);border-left:2px solid #B88634;border-right:2px solid #B88634`"></div>
                                                <div class="absolute inset-y-0" :style="`left:${pct(cursor)}%;width:2px;background:#23201A`"></div>
                                            </div>
                                            <ol class="mt-2 space-y-1">
                                                <template x-for="(s, k) in sections" :key="k">
                                                    <li class="flex flex-wrap items-center gap-2 font-mono text-[0.62rem] text-ink-soft">
                                                        <span class="w-5 text-teal" x-text="(k + 1) + '.'"></span>
                                                        <button type="button" class="hover:text-ink" @click="seek(s.start)" x-text="label(s.start)"></button>→
                                                        <button type="button" class="hover:text-ink" @click="seek(s.end)" x-text="label(s.end)"></button>
                                                        <span class="text-ink-faint" x-text="'(' + (s.end - s.start).toFixed(1) + ' s)'"></span>
                                                        <button type="button" class="px-2 py-0.5 rounded-sm border border-ink-soft/30 hover:bg-ink/5" @click="preview(s)">▶ preview</button>
                                                        <button type="button" class="text-bad hover:underline" @click="sections.splice(k, 1)">remove</button>
                                                    </li>
                                                </template>
                                            </ol>
                                            <div class="mt-2 flex items-center gap-3">
                                                <button type="button" :disabled="!sections.length || sending" @click="sending = true; $wire.usarMomentos({{ $i }}, sections).finally(() => sending = false)"
                                                        class="font-mono text-[0.65rem] px-3 py-1.5 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-40"
                                                        x-text="sending ? 'Cutting…' : 'Use {{ count($slots) > 1 ? 'these moments' : 'this moment' }}'">Use</button>
                                                <span class="font-mono text-[0.55rem] text-ink-faint">then Save below to re-render</span>
                                            </div>
                                        </div>
                                    @endif
                                    @error('moments.'.$i) <p class="text-bad text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endif


                            <div class="grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">start</label>
                                    <input type="number" step="any" min="0" wire:model="editScenes.{{ $i }}.start" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">end</label>
                                    <input type="number" step="any" min="0" wire:model="editScenes.{{ $i }}.end" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">background</label>
                                    <select wire:model="editScenes.{{ $i }}.background" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none">
                                        @foreach ($backgrounds as $bg)<option value="{{ $bg }}">{{ $bg }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">transition</label>
                                    <select wire:model="editScenes.{{ $i }}.transitionIn" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none">
                                        @foreach ($transitions as $tr)<option value="{{ $tr }}">{{ $tr }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-span-12">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">element text</label>
                                    <input type="text" wire:model="editScenes.{{ $i }}.layerText" placeholder="—" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                                </div>
                                <div class="col-span-12">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">subtitles spoken in this block</label>
                                    <textarea wire:model="editScenes.{{ $i }}.subtitles" rows="2" placeholder="—"
                                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm leading-relaxed focus:border-teal/50 focus:outline-none"></textarea>
                                    <p class="font-mono text-[0.5rem] text-ink-faint mt-0.5">Captions only — the voice still says the old words until you regenerate the voiceover.</p>
                                </div>
                                @if ($editOverlay)
                                    <div class="col-span-12">
                                        <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">how this block shows against the video</label>
                                        <select wire:model="editScenes.{{ $i }}.present" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none">
                                            <option value="">auto (planner's choice)</option>
                                            <option value="video">raw video only — no graphics</option>
                                            <option value="over">graphics over the video</option>
                                            <option value="split">split screen</option>
                                            <option value="animation">animation only</option>
                                        </select>
                                    </div>
                                @endif
                                <div class="col-span-7">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">punch word</label>
                                    <input type="text" wire:model="editScenes.{{ $i }}.punchWord" placeholder="—" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                                </div>
                                <label class="col-span-5 flex items-center gap-2 mt-4 font-mono text-[0.62rem] text-ink-soft">
                                    <input type="checkbox" wire:model="editScenes.{{ $i }}.karaoke" class="accent-teal" /> karaoke
                                </label>
                            </div>
                        </div>
                    @endforeach
                    @error('editScenes') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    @error('editScenes.*.end') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                </div>

                <button type="button" wire:click="adicionarCena" class="font-mono text-[0.62rem] text-teal hover:underline">+ add scene</button>

                <div class="flex items-center gap-3 pt-2 border-t border-ink-soft/15">
                    <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition mt-4">Save and re-render</button>
                    @if ($editSpeakable)
                        <button type="button" wire:click="guardarPlanoComVoz" wire:loading.attr="disabled"
                                class="font-display text-lg px-6 py-2 rounded-sm border border-gold/50 text-gold hover:bg-gold/10 transition mt-4"
                                title="Speaks the subtitles above again and re-renders. Your scenes are kept and their timings scaled to the new audio.">♺ Save and regenerate voiceover</button>
                    @endif
                    <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mt-4">cancel</button>
                </div>
                <p class="font-mono text-[0.6rem] text-ink-faint">Edit the pacing of the scenes (timings, background, transition, emphasis, karaoke). Each scene's layers are preserved. Gaps in dense mode are filled with «ambient».</p>
            </form>
        </x-panel>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- EDIT TRANSCRIPT                                              --}}
    {{-- ============================================================ --}}
    @if ($view === 'editTranscript')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to dashboard</button>
        <x-panel eyebrow="Edit transcript" title="Correct and regenerate" glyph="✎">
            <form wire:submit="regenerar" x-on:submit="window.CMLoader.busy('Regenerating the animations…')" class="space-y-5">
                <div>
                    <label class="eyebrow block mb-2">Transcript (correct recognition errors)</label>
                    <textarea wire:model="editTranscriptText" rows="6" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('editTranscriptText') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    @if ($this->editingSpokenAgain)
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">A changed transcript is spoken again (new voiceover), then the clip is planned on it.</p>
                    @endif
                </div>

                @if ($editListing)
                    @php
                        $editClip = $this->editingClip;
                        $em = $editClip?->meta ?? [];
                        $videoLen = $this->listingVideoLengthFor($editClip);
                        $temRetrato = collect($editClip?->images ?? [])->contains(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato'));
                    @endphp
                    {{-- The "From a listing" options, as this clip was made: what changes here is applied on Regenerate. --}}
                    <div class="border border-ink-soft/15 rounded-sm p-3 bg-surface/20 space-y-2">
                        <span class="eyebrow block">⌂ Listing</span>
                        <label class="block text-sm text-ink-soft">Listing link
                            <input type="url" wire:model="editListingUrl" class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60">
                        </label>
                        @error('editListingUrl') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                            <span>Agent photo — {{ $temRetrato ? '✓ has one; upload to replace' : 'none yet' }}:</span>
                            <input type="file" accept="image/*" wire:model="editAgentPhoto" class="text-xs">
                            <span wire:loading wire:target="editAgentPhoto" class="text-ink-faint">uploading…</span>
                        </label>
                        @error('editAgentPhoto') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                            <span>🏠 Property video — {{ $videoLen ? '✓ '.gmdate('i:s', (int) $videoLen).' long; upload to replace' : 'none (the reel uses the listing photos)' }}:</span>
                            <input type="file" accept="video/*" wire:model="editListingVideo" class="text-xs">
                            <span wire:loading wire:target="editListingVideo" class="text-ink-faint">uploading…</span>
                        </label>
                        @error('editListingVideo') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="ml-4 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                            <input type="checkbox" wire:model="editMixPhotos" class="accent-teal">
                            <span>Mix the video with the listing photos</span>
                        </label>
                        <label class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                            <span>🎬 After-intro video — {{ ! empty($em['after_intro']) ? '✓ this clip has its own; upload to replace' : (app(\App\Services\Clips\Store\EffectStore::class)->afterIntroPath() ? 'the project\'s (SFX studio)' : 'none') }}:</span>
                            <input type="file" accept="video/*" wire:model="editAfterIntro" class="text-xs">
                            <span wire:loading wire:target="editAfterIntro" class="text-ink-faint">uploading…</span>
                        </label>
                        @error('editAfterIntro') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="block text-sm text-ink-soft">After-intro line (said right after the intro, over the after-intro video)
                            <input type="text" wire:model="editAfterIntroLine" class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60">
                        </label>
                        <label class="block text-sm text-ink-soft">Notes for the script (they override the listing page)
                            <textarea wire:model="editListingNotes" rows="2" class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60"></textarea>
                        </label>
                        <p class="font-mono text-[0.58rem] text-ink-faint">Changing the link, the notes, the property video or the photo mix writes the script again from the listing and rebuilds the clip (new voiceover). A new after-intro line replaces the old one in the transcript; a new photo or after-intro video is swapped in.</p>
                    </div>
                @endif

                @if ($editOverlay)
                    @include('livewire.partials.presents-picker')

                    @include('livewire.partials.format-picker')
                @endif

                @include('livewire.partials.lead-field')

                @include('livewire.partials.instructions-field')


                    @include('livewire.partials.no-images')

                    @if (! $semImagens) @include('livewire.partials.only-reference-images') @endif
                    @if (! $semImagens && ! $soReferencias) @include('livewire.partials.fewer-images') @endif

                @include('livewire.partials.free-sfx')

                @include('livewire.partials.backdrop-picker')

                @include('livewire.partials.musica-picker')
                @include('livewire.partials.captions-picker')

                <div class="flex items-center gap-3">
                    <button type="submit" wire:loading.attr="disabled" wire:target="editAgentPhoto, editListingVideo, editAfterIntro" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40">Regenerate animations</button>
                    <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancel</button>
                </div>
                <p class="font-mono text-[0.6rem] text-ink-faint">The AI re-plans the animations from the corrected text and renders again.</p>
            </form>
        </x-panel>
    @endif

    {{-- ============================================================ --}}
    {{-- BACKGROUNDS STUDIO                                          --}}
    {{-- ============================================================ --}}
    @if ($view === 'backgrounds')
        @include('livewire.partials.studio-tabs')
        <a href="{{ route('clips-animados') }}" wire:navigate class="inline-block font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to Animated Clips</a>

        <x-panel eyebrow="Backgrounds library" title="Backgrounds" glyph="◆">
            <p class="text-ink-soft text-sm mb-5">
                The full-screen backdrop a clip renders behind every scene. Generate an animated one from a
                description (the AI writes a Remotion component that follows the design system and loops for any
                length), or upload a video. Enabled backgrounds can be picked automatically by the clip generator
                or chosen by hand when you create a clip.
            </p>

            <form wire:submit="gerarBackground" class="space-y-3 mb-6">
                <label class="eyebrow block">Generate an animated background</label>
                <textarea wire:model="bgPrompt" rows="3" placeholder="e.g. a slow aurora of teal and gold light drifting over deep ink, with faint floating particles"
                          class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                @error('bgPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <div class="flex items-center gap-3">
                    <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition"
                            wire:loading.attr="disabled" wire:target="gerarBackground">
                        ✦ Create background
                    </button>
                    <span class="font-mono text-[0.6rem] text-ink-faint">Colours are locked to the design system.</span>
                </div>
            </form>

            <form wire:submit="uploadBackground" class="space-y-3 border-t border-ink-soft/15 pt-5">
                <label class="eyebrow block">…or upload a video background (mp4 / mov)</label>
                <input type="text" wire:model="bgVideoName" placeholder="Name (e.g. City timelapse)"
                       class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none" />
                @error('bgVideoName') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <input type="file" wire:model="bgVideo" accept="video/mp4,video/quicktime"
                       class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                <div wire:loading wire:target="bgVideo" class="font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                @error('bgVideo') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition"
                        wire:loading.attr="disabled" wire:target="bgVideo,uploadBackground">
                    ✦ Add video background
                </button>
            </form>

            {{-- Export / import — move backgrounds (component or mp4) between installs. --}}
            <div class="flex flex-wrap items-center gap-3 border-t border-ink-soft/15 pt-5 mt-5">
                @if ($this->backgrounds->isNotEmpty())
                    <a href="{{ route('clips-animados.background-export', 'all') }}"
                       class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Export all backgrounds</a>
                @endif
                <form wire:submit="importarBackgrounds" class="flex items-center gap-2">
                    <input type="file" wire:model="importBackgroundFile" accept="application/json,.json"
                           class="block text-xs text-ink-soft file:mr-2 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-[0.6rem] file:cursor-pointer" />
                    <button type="submit" wire:loading.attr="disabled" wire:target="importBackgroundFile,importarBackgrounds"
                            class="font-mono text-xs px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40">⤒ Import</button>
                </form>
            </div>
            @error('importBackgroundFile') <p class="text-bad text-sm mt-2">{{ $message }}</p> @enderror
        </x-panel>

        {{-- Reel: one video cycling through every background, name centered --}}
        @if ($this->backgrounds->isNotEmpty())
            <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-4 mt-6">
                <div class="flex items-center justify-between gap-4 mb-3">
                    <div>
                        <div class="eyebrow">Preview all</div>
                        <p class="text-ink-soft text-sm mt-1">One video that plays every background in turn, each with its name in the middle.</p>
                    </div>
                    <button type="button" wire:click="gerarBackgroundReel" wire:loading.attr="disabled" wire:target="gerarBackgroundReel"
                            @if ($this->backgroundReelBusy) disabled @endif
                            class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50 shrink-0">
                        @if ($this->backgroundReelBusy) rendering… @elseif ($this->backgroundReelReady) ↻ Rebuild @else ✦ Build preview @endif
                    </button>
                </div>
                @if ($this->backgroundReelBusy)
                    <p class="font-mono text-[0.6rem] text-ink-faint">Rendering the reel — this can take a minute. It refreshes automatically.</p>
                @elseif ($this->backgroundReelReady)
                    <video class="rounded-sm border border-ink-soft/15 bg-black w-full max-h-[70vh]" controls preload="metadata"
                           src="{{ route('clips-animados.background-reel') }}?v={{ now()->timestamp }}"></video>
                @endif
            </div>
        @endif

        {{-- Refine panel for a code background --}}
        @if ($editingBgId)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <form wire:submit="guardarBackgroundEdicao" class="space-y-3">
                    <label class="eyebrow block">Refine this background</label>
                    <textarea wire:model="bgEditPrompt" rows="3"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('bgEditPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">✦ Regenerate</button>
                        <button type="button" wire:click="cancelarBackgroundEdicao" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancel</button>
                        <span class="font-mono text-[0.6rem] text-ink-faint">Same slug; the live version stays until the new one renders.</span>
                    </div>
                </form>
            </div>
        @endif

        {{-- Backgrounds grid --}}
        @if ($this->backgrounds->isNotEmpty())
            <div class="mt-8">
                <div class="flex items-baseline justify-between mb-4 gap-3">
                    <div class="eyebrow">Your backgrounds · {{ $this->backgrounds->count() }}</div>
                    <span class="font-mono text-[0.55rem] text-ink-faint">● on = pickable for clips · ○ off = kept but not used</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach ($this->backgrounds as $bg)
                        <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col transition {{ $bg->status === 'active' && ! $bg->enabled ? 'opacity-60' : '' }}" wire:key="bg-{{ $bg->id }}">
                            <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                                @if (isset($bgReady[$bg->id]) && in_array($bg->status, ['active', 'updating'], true))
                                    {{-- ?v= and the key carry the render's version: the URL is keyed by
                                         record id, so without it a re-rendered preview is served from
                                         the copy the browser already has. --}}
                                    <video class="w-full h-full object-cover" wire:key="bgprev-{{ $bg->id }}-{{ $bgReady[$bg->id] }}"
                                           src="{{ route('clips-animados.background-preview', ['id' => $bg->id, 'v' => $bgReady[$bg->id]]) }}" autoplay loop muted playsinline></video>
                                    @if ($bg->status === 'updating')
                                        <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                            <p class="font-mono text-[0.55rem] text-teal animate-pulse">Updating…</p>
                                        </div>
                                    @endif
                                    @if ($bg->kind === 'video')
                                        <div class="absolute top-1 left-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-ink/40 text-vellum border border-vellum/20">video</div>
                                    @endif
                                @elseif ($bg->status === 'failed')
                                    <div class="p-2 text-center">
                                        <div class="text-bad text-lg">✕</div>
                                        <p class="font-mono text-[0.52rem] text-bad/90 mt-1 line-clamp-4">{{ \Illuminate\Support\Str::limit($bg->error, 120) }}</p>
                                    </div>
                                @else
                                    <div class="text-center">
                                        <div class="h-1 w-16 mx-auto bg-surface/40 rounded-full overflow-hidden">
                                            <div class="h-full bg-teal/60 animate-pulse w-2/3"></div>
                                        </div>
                                        <p class="font-mono text-[0.52rem] text-ink-faint mt-2">{{ $bg->status === 'updating' ? 'Updating…' : 'Generating…' }}</p>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-start justify-between gap-1 mt-2">
                                <div class="min-w-0">
                                    <p class="font-display text-sm text-ink truncate">{{ $bg->display_name }}</p>
                                    <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ in_array($bg->status, ['active', 'updating'], true) ? $bg->slug : $bg->status }}</p>
                                    @if ($bg->status === 'active' && $bg->error)
                                        <p class="font-mono text-[0.5rem] text-bad/80 truncate" title="{{ $bg->error }}">⚠ {{ $bg->error }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @if ($bg->status === 'active')
                                        <button type="button" wire:click="alternarBackground('{{ $bg->id }}')"
                                                title="{{ $bg->enabled ? 'Pickable for clips — click to disable' : 'Disabled — click to enable' }}"
                                                class="font-mono text-[0.62rem] px-2 py-1 rounded-sm border {{ $bg->enabled ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">
                                            {{ $bg->enabled ? '● on' : '○ off' }}
                                        </button>
                                        @if ($bg->kind !== 'video')
                                            <button type="button" wire:click="editarBackground('{{ $bg->id }}')" title="Refine this background"
                                                    class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎</button>
                                        @endif
                                        <a href="{{ route('clips-animados.background-export', $bg->id) }}" title="Export this background"
                                           class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓</a>
                                    @endif
                                    <button type="button" wire:click="apagarBackground('{{ $bg->id }}')"
                                            wire:confirm="Delete this background? Clips already rendered keep their video." title="Delete this background"
                                            class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <x-empty-state glyph="◇" title="No backgrounds yet" note="Generate an animated backdrop or upload a video to get started." />
        @endif
    @endif

    @script
    <script>
        Alpine.data('voiceRecorder', () => ({
            recording: false, uploading: false, ready: false, elapsed: 0, error: null,
            recorder: null, chunks: [], timer: null,

            async start() {
                this.error = null;
                if (!navigator.mediaDevices?.getUserMedia) { this.error = 'Recording not supported in this browser.'; return; }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.chunks = [];
                    this.recorder = new MediaRecorder(stream);
                    this.recorder.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data); };
                    this.recorder.onstop = () => this.finish(stream);
                    this.recorder.start();
                    this.recording = true; this.ready = false; this.elapsed = 0;
                    this.timer = setInterval(() => this.elapsed++, 1000);
                } catch (e) { this.error = 'No microphone access.'; }
            },
            stop() {
                clearInterval(this.timer);
                this.recording = false;
                if (this.recorder && this.recorder.state !== 'inactive') { this.recorder.stop(); }
            },
            finish(stream) {
                stream.getTracks().forEach((t) => t.stop());
                const blob = new Blob(this.chunks, { type: 'audio/webm' });
                this.$refs.player.src = URL.createObjectURL(blob);
                this.ready = true;
                const file = new File([blob], 'gravacao.webm', { type: 'audio/webm' });
                this.uploading = true;
                this.$wire.upload('audio', file,
                    () => { this.uploading = false; },
                    () => { this.uploading = false; this.error = 'Failed to upload the recording.'; },
                    () => {}
                );
            },
        }));
    </script>
    @endscript
    @include('livewire.partials.lightbox')
</div>
