<div @if (($view === 'dashboard' && $this->hasActive) || ($view === 'backgrounds' && ($this->backgroundsBusy || $this->backgroundReelBusy))) wire:poll.3s @endif>
    @php
        $estados = [
            'draft'        => ['label' => 'Draft',         'tone' => 'neutral', 'glyph' => '·'],
            'transcribing' => ['label' => 'Transcribing',  'tone' => 'gold',    'glyph' => '❧'],
            'planning'     => ['label' => 'Planning',      'tone' => 'gold',    'glyph' => '❧'],
            'collecting'   => ['label' => 'Your images',   'tone' => 'gold',    'glyph' => '◆'],
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
                <button type="button" wire:click="abrirBackgrounds"
                        class="font-display text-lg px-5 py-2 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-ink-soft/50 transition">
                    ◆ Backgrounds
                </button>
                <button type="button" wire:click="novoClip"
                        class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                    ✦ New clip
                </button>
            </div>
        </div>

        @if ($this->projects->isEmpty())
            <x-empty-state glyph="❈" title="No clips" note="You haven't generated any piece yet. Start with «New clip»." />
        @else
            <div class="space-y-5">
                @foreach ($this->projects as $project)
                    @php
                        $e = $estados[$project->status] ?? $estados['draft'];
                        $t = $tipos[$project->type] ?? $tipos['animation'];
                        $sug = $project->meta['suggested'] ?? [];
                    @endphp
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-5 shadow-engraved" wire:key="clip-{{ $project->id }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- LEFT: video --}}
                            <div class="flex items-center justify-center">
                                @if ($project->status === 'done' && $project->output_path)
                                    <video class="rounded-sm border border-ink-soft/15 bg-black max-h-[60vh] w-auto max-w-full" controls preload="metadata"
                                           src="{{ route('clips-animados.media', $project->id) }}"></video>
                                @elseif ($project->status === 'failed')
                                    <p class="text-sm text-bad/90 self-start">{{ \Illuminate\Support\Str::limit($project->error, 200) }}</p>
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
                                                 style="width: {{ ['transcribing'=>33,'planning'=>60,'rendering'=>85][$project->status] ?? 12 }}%"></div>
                                        </div>
                                        <p class="mt-2 font-mono text-[0.58rem] text-ink-faint">{{ $e['label'] }}…</p>
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
                                </div>

                                <div class="font-display text-xl text-ink mt-2">{{ $project->title ?? 'No title' }}</div>
                                <div class="font-mono text-[0.58rem] text-ink-faint">{{ $project->created_at ? \Illuminate\Support\Carbon::parse($project->created_at)->diffForHumans() : '' }}</div>

                                @if (!empty($sug['description']))
                                    <p class="mt-3 text-ink-soft text-sm leading-relaxed">{{ $sug['description'] }}</p>
                                @endif

                                @if (!empty($sug['tags']))
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach ($sug['tags'] as $tag)
                                            <span class="font-mono text-[0.58rem] text-teal border border-teal/30 rounded-sm px-1.5 py-0.5">#{{ $tag }}</span>
                                        @endforeach
                                    </div>
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
                                        @if ($project->finished)
                                            <span class="text-good">✓ in Finished</span>
                                        @else
                                            <button type="button" wire:click="promover('{{ $project->id }}')" class="text-teal hover:underline">→ send to Finished</button>
                                        @endif
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
            <p class="text-ink-soft text-sm mb-5">The plan suggests these images. For each one choose: <span class="text-teal">upload</span> your own file (or pick one from the library), let it be an <span class="text-teal">AI image</span>, or <span class="text-teal">no image</span> — that scene then becomes a text visual (card / list / diagram) like the non-image scenes. A green thumbnail was matched automatically from your Assets library.</p>

            <div class="space-y-2 mb-6">
                @forelse ($this->imageRequests as $r)
                    <div class="flex flex-wrap items-center gap-3 bg-surface/30 border border-ink-soft/15 rounded-sm p-2" wire:key="req-{{ $r['key'] }}">
                        @if (!empty($r['path']) && !empty($r['video']))
                            <video src="{{ route('clips-animados.upload', basename($r['path'])) }}" muted playsinline
                                   class="w-12 h-12 object-cover rounded-sm border {{ !empty($r['fromLibrary']) ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40"></video>
                        @elseif (!empty($r['path']))
                            <img src="{{ route('clips-animados.upload', basename($r['path'])) }}"
                                 onerror="this.style.visibility='hidden'"
                                 class="w-12 h-12 object-cover rounded-sm border {{ !empty($r['fromLibrary']) ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40" alt="" />
                        @else
                            <div class="w-12 h-12 rounded-sm border border-dashed border-ink-soft/30 flex items-center justify-center text-ink-faint text-lg bg-vellum/20">{{ $r['mode'] === 'text' ? '✎' : '✦' }}</div>
                        @endif

                        <span class="flex-1 min-w-0 text-sm text-ink">
                            {{ $r['label'] ?? (trim(\Illuminate\Support\Str::after($r['prompt'], 'Illustrate this moment:')) ?: $r['prompt']) }}
                            @if (!empty($r['fromLibrary']))
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· from library</span>
                            @elseif ($r['mode'] === 'upload')
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· your image</span>
                            @elseif ($r['mode'] === 'text')
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· no image — becomes a text scene</span>
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
                            <button type="button" wire:click="modoImagem('{{ $r['key'] }}', 'generate')"
                                    class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'generate' ? $on : $off }}"
                                    title="let the studio generate this image">✦ AI image</button>
                            <button type="button" wire:click="modoImagem('{{ $r['key'] }}', 'text')"
                                    class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'text' ? $on : $off }}"
                                    title="no image — this scene becomes a card/list/diagram like the others">✎ no image</button>
                            @if (!empty($this->libraryImages))
                                <button type="button" wire:click="abrirBibliotecaImagens('{{ $r['key'] }}')"
                                        class="px-2 py-1 text-teal hover:opacity-70">▦ library</button>
                            @endif
                        </div>
                        @error('reviewUploads.'.$r['key']) <p class="w-full text-bad text-xs">{{ $message }}</p> @enderror

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

            <button type="button" wire:click="finalizarImagens"
                    x-on:click="window.CMLoader?.busy('Generating the animation…')"
                    class="font-display text-lg px-6 py-2.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                ✦ Generate the rest &amp; continue →
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

                    @include('livewire.partials.backdrop-picker')

                    @include('livewire.partials.musica-picker')

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
                    <div>
                        <label class="eyebrow block mb-2">Upload video (mp4 / mov)</label>
                        <input type="file" wire:model="video" accept="video/mp4,video/quicktime"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="video" class="mt-1 font-mono text-[0.6rem] text-ink-faint">uploading…</div>
                        @error('video') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="eyebrow block mb-2">Allowed styles</label>
                        @php
                            $styles = [
                                'video'     => ['t' => 'Video only', 'd' => 'video + subtitles'],
                                'over'      => ['t' => 'Animation on top', 'd' => 'graphic over the video'],
                                'split'     => ['t' => 'Split screen', 'd' => 'animation on top, video below'],
                                'animation' => ['t' => 'Full screen', 'd' => 'animation covers the video'],
                            ];
                        @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($styles as $key => $s)
                                <label class="cursor-pointer flex items-start gap-2 rounded-sm border p-3 transition {{ in_array($key, $allowedPresents) ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
                                    <input type="checkbox" wire:model="allowedPresents" value="{{ $key }}" class="accent-teal mt-1" />
                                    <span>
                                        <span class="block font-display text-lg text-ink leading-tight">{{ $s['t'] }}</span>
                                        <span class="block text-ink-soft text-xs">{{ $s['d'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('allowedPresents') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </div>

                    @include('livewire.partials.backdrop-picker')

                    @include('livewire.partials.musica-picker')

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition" wire:loading.attr="disabled">
                            Generate clip
                        </button>
                        <span wire:loading wire:target="submitOverlay" class="font-mono text-[0.6rem] text-ink-faint">preparing…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">The AI automatically interleaves the chosen styles, according to the content.</p>
                </form>
            </x-panel>
        @endif

        {{-- Images (optional) — for both types --}}
        @if ($createType !== null)
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
                                <button type="button" wire:click="escolherAnimacao({{ $i }})"
                                        class="font-mono text-[0.62rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition shrink-0">⇆ Change</button>
                            </div>

                            @if ($animacaoPickerCena === $i)
                                <div class="mb-3 border border-teal/30 rounded-sm p-2 bg-vellum/40" @if (count($animacoes) > count($sfxReady)) wire:poll.4s @endif>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="eyebrow">Choose an animation</span>
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
                                <div class="flex items-center gap-3 mb-3">
                                    @if (!empty($sceneImg['video']))
                                        <video src="{{ route('clips-animados.clip-image', ['id' => $editingId, 'imageId' => $sceneImg['id']]) }}" muted playsinline
                                               class="w-14 aspect-[9/16] object-cover rounded-sm border {{ $sceneImg['library'] ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40 shrink-0"></video>
                                    @else
                                        <img src="{{ route('clips-animados.clip-image', ['id' => $editingId, 'imageId' => $sceneImg['id']]) }}"
                                             onerror="this.style.visibility='hidden'"
                                             class="w-14 aspect-[9/16] object-cover rounded-sm border {{ $sceneImg['library'] ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40 shrink-0" alt="" />
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-[0.55rem] text-ink-faint">{{ !empty($sceneImg['video']) ? 'video' : 'image' }}{{ $sceneImg['library'] ? ' · from library' : '' }}</div>
                                        <div class="flex items-center gap-3 font-mono text-[0.6rem] mt-1">
                                            @if (!empty($this->libraryImages))
                                                <button type="button" wire:click="abrirBibliotecaCena({{ $i }})" class="text-teal hover:opacity-70">▦ library</button>
                                            @endif
                                            <label class="text-teal hover:opacity-70 cursor-pointer">
                                                <span wire:loading.remove wire:target="sceneImageUploads.{{ $i }}">↑ replace</span>
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
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Regenerate animations</button>
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
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to dashboard</button>

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
                                @if (in_array($bg->id, $bgReady, true) && in_array($bg->status, ['active', 'updating'], true))
                                    <video class="w-full h-full object-cover" src="{{ route('clips-animados.background-preview', $bg->id) }}" autoplay loop muted playsinline></video>
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
</div>
