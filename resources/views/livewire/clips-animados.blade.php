<div @if (($view === 'dashboard' && $this->hasActive) || ($view === 'sfx' && ($this->sfxBusy || $this->showreelBusy))) wire:poll.3s @endif>
    @php
        $estados = [
            'draft'        => ['label' => 'Draft',         'tone' => 'neutral', 'glyph' => '·'],
            'transcribing' => ['label' => 'Transcribing',  'tone' => 'gold',    'glyph' => '❧'],
            'planning'     => ['label' => 'Planning',      'tone' => 'gold',    'glyph' => '❧'],
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
        cota="741.5 · IAT · '26"
        lead="Animation studio: from voiceover to animated piece, with timestamps and assisted planning." />

    {{-- ============================================================ --}}
    {{-- DASHBOARD                                                    --}}
    {{-- ============================================================ --}}
    @if ($view === 'dashboard')
        <div class="flex items-center justify-between mb-6">
            <div class="eyebrow">Clips generated · {{ $this->projects->count() }}</div>
            <div class="flex items-center gap-3">
                <button type="button" wire:click="abrirSfx"
                        class="font-display text-lg px-5 py-2 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-ink-soft/50 transition">
                    ✷ SFX
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
                        <div class="grid md:grid-cols-2 gap-5">
                            {{-- LEFT: video --}}
                            <div class="flex items-center justify-center">
                                @if ($project->status === 'done' && $project->output_path)
                                    <video class="rounded-sm border border-ink-soft/15 bg-black max-h-[60vh] w-auto max-w-full" controls preload="metadata"
                                           src="{{ route('clips-animados.media', $project->id) }}"></video>
                                @elseif ($project->status === 'failed')
                                    <p class="text-sm text-bad/90 self-start">{{ \Illuminate\Support\Str::limit($project->error, 200) }}</p>
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
                                    @if (!empty($project->plan['scenes']))
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
    {{-- CREATION                                                     --}}
    {{-- ============================================================ --}}
    @if ($view === 'create')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to dashboard</button>

        @if ($createType === null)
            {{-- step 1: choose type --}}
            <div class="eyebrow mb-4">Step · I — what kind of clip?</div>
            <div class="grid md:grid-cols-2 gap-6">
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
                        <div class="grid sm:grid-cols-2 gap-2">
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

                @if (!empty($images))
                    <div class="space-y-2 mb-4">
                        @foreach ($images as $i => $img)
                            <div class="flex items-center gap-3 bg-surface/30 border border-ink-soft/15 rounded-sm p-2" wire:key="img-{{ $i }}">
                                <img src="{{ route('clips-animados.upload', basename($img['path'])) }}"
                                     onerror="this.style.visibility='hidden'"
                                     class="w-12 h-12 object-cover rounded-sm border border-ink-soft/20 bg-vellum/40" alt="" />
                                <span class="flex-1 min-w-0 text-sm text-ink truncate">{{ $img['description'] }}</span>
                                <button type="button" wire:click="removerImagem({{ $i }})" class="text-bad font-mono text-sm hover:opacity-70">✕</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-12 gap-2 items-end">
                    <div class="col-span-5">
                        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">file</label>
                        <input type="file" wire:model="newImage" accept="image/*"
                               class="block w-full text-xs text-ink-soft file:mr-2 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-[0.6rem] file:cursor-pointer" />
                        <div wire:loading wire:target="newImage" class="mt-1 font-mono text-[0.55rem] text-ink-faint">uploading…</div>
                    </div>
                    <div class="col-span-5">
                        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">description</label>
                        <input type="text" wire:model="newImageDesc" placeholder="e.g.: company logo"
                               class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                    </div>
                    <div class="col-span-2">
                        <button type="button" wire:click="adicionarImagem"
                                class="w-full font-mono text-[0.62rem] px-3 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">+ add</button>
                    </div>
                </div>
                @error('newImage') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                @error('newImageDesc') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
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
    {{-- SFX STUDIO                                                   --}}
    {{-- ============================================================ --}}
    @if ($view === 'sfx')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← back to dashboard</button>

        <x-panel eyebrow="Effects library" title="SFX" glyph="✷">
            <p class="text-ink-soft text-sm mb-5">
                The motion vocabulary the renderer can already produce — plus your own. Describe a new effect and
                the AI writes a Remotion component that follows the design system; once it renders, the planner can
                use it on any clip.
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

        {{-- Showreel: one video cycling through every effect, name centered --}}
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
                <video class="rounded-sm border border-ink-soft/15 bg-black w-full max-h-[70vh]" controls preload="metadata"
                       src="{{ route('clips-animados.showreel') }}?v={{ now()->timestamp }}"></video>
            @endif
        </div>

        {{-- Refine panel: a custom effect OR a built-in override --}}
        @if ($editingSfxId || $sfxOverrideSlug)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <form wire:submit="guardarSfxEdicao" class="space-y-3">
                    <label class="eyebrow block">{{ $sfxOverrideSlug ? 'Customize built-in · '.$sfxOverrideSlug : 'Refine this effect' }}</label>
                    <textarea wire:model="sfxEditPrompt" rows="3"
                              placeholder="{{ $sfxOverrideSlug ? 'Describe how this built-in effect should look and behave…' : '' }}"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('sfxEditPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                            ✦ {{ $sfxOverrideSlug ? 'Create override' : 'Regenerate' }}
                        </button>
                        <button type="button" wire:click="cancelarSfxEdicao" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancel</button>
                        <span class="font-mono text-[0.6rem] text-ink-faint">{{ $sfxOverrideSlug ? 'Creates a custom version that replaces the built-in for this project.' : 'Same slug; the live version stays until the new one renders.' }}</span>
                    </div>
                </form>
            </div>
        @endif

        {{-- Custom (generated) effects --}}
        @if ($this->effects->isNotEmpty())
            <div class="mt-8">
                <div class="flex items-baseline justify-between mb-4 gap-3">
                    <div class="eyebrow">Your effects · {{ $this->effects->count() }}</div>
                    <span class="font-mono text-[0.55rem] text-ink-faint">● on = allowed in generated videos · ○ off = kept but not used</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach ($this->effects as $effect)
                        <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col transition {{ $effect->status === 'active' && ! $effect->enabled ? 'opacity-60' : '' }}" wire:key="sfx-{{ $effect->id }}">
                            <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                                @if (in_array($effect->status, ['active', 'updating'], true) && in_array($effect->slug, $sfxReady, true))
                                    <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $effect->slug) }}" autoplay loop muted playsinline></video>
                                    @if ($effect->status === 'updating')
                                        <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                            <p class="font-mono text-[0.55rem] text-teal animate-pulse">Updating…</p>
                                        </div>
                                    @endif
                                @elseif ($effect->status === 'failed')
                                    <div class="p-2 text-center">
                                        <div class="text-bad text-lg">✕</div>
                                        <p class="font-mono text-[0.52rem] text-bad/90 mt-1 line-clamp-4">{{ \Illuminate\Support\Str::limit($effect->error, 120) }}</p>
                                    </div>
                                @else
                                    <div class="text-center">
                                        <div class="h-1 w-16 mx-auto bg-surface/40 rounded-full overflow-hidden">
                                            <div class="h-full bg-teal/60 animate-pulse w-2/3"></div>
                                        </div>
                                        <p class="font-mono text-[0.52rem] text-ink-faint mt-2">{{ $effect->status === 'updating' ? 'Updating…' : 'Generating…' }}</p>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-start justify-between gap-1 mt-2">
                                <div class="min-w-0">
                                    <p class="font-display text-sm text-ink truncate">{{ $effect->display_name }}</p>
                                    <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ in_array($effect->status, ['active', 'updating'], true) ? $effect->slug : $effect->status }}</p>
                                    @if ($effect->status === 'active' && $effect->error)
                                        <p class="font-mono text-[0.5rem] text-bad/80 truncate" title="{{ $effect->error }}">⚠ {{ $effect->error }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @if ($effect->status === 'active')
                                        <button type="button" wire:click="alternarSfx('{{ $effect->id }}')"
                                                title="{{ $effect->enabled ? 'Allowed in generated videos — click to disallow' : 'Disallowed — click to allow' }}"
                                                class="font-mono text-[0.62rem] px-2 py-1 rounded-sm border {{ $effect->enabled ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">
                                            {{ $effect->enabled ? '● on' : '○ off' }}
                                        </button>
                                        <button type="button" wire:click="editarSfx('{{ $effect->id }}')" title="Refine this effect"
                                                class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎</button>
                                    @endif
                                    <button type="button" wire:click="apagarSfx('{{ $effect->id }}')"
                                            wire:confirm="Delete this effect? Clips already rendered keep their video." title="Delete this effect"
                                            class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Built-in effects --}}
        <div class="mt-8">
            <div class="flex items-baseline justify-between mb-4 gap-3">
                <div class="eyebrow">Built-in · {{ count($builtins) }}</div>
                <span class="font-mono text-[0.55rem] text-ink-faint">disallow any you don't want the planner to use</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach ($builtins as $b)
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col transition {{ $b['allowed'] ? '' : 'opacity-60' }}" wire:key="builtin-{{ $b['slug'] }}">
                        <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                            @if (in_array($b['slug'], $sfxReady, true))
                                <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $b['slug']) }}" autoplay loop muted playsinline></video>
                            @else
                                <div class="text-center">
                                    <div class="h-1 w-16 mx-auto bg-surface/40 rounded-full overflow-hidden">
                                        <div class="h-full bg-ink-soft/40 animate-pulse w-1/2"></div>
                                    </div>
                                    <p class="font-mono text-[0.52rem] text-ink-faint mt-2">Rendering…</p>
                                </div>
                            @endif
                            @if ($b['override'] === 'active')
                                <div class="absolute top-1 left-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-teal/20 text-teal border border-teal/30">custom</div>
                            @elseif (in_array($b['override'], ['pending', 'updating'], true))
                                <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                    <p class="font-mono text-[0.55rem] text-teal animate-pulse">Updating…</p>
                                </div>
                            @endif
                        </div>
                        <div class="flex items-start justify-between gap-1 mt-2">
                            <div class="min-w-0">
                                <p class="font-display text-sm text-ink truncate">{{ $b['label'] }}</p>
                                <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ $b['slug'] }}</p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" wire:click="alternarBuiltin('{{ $b['slug'] }}')"
                                        title="{{ $b['allowed'] ? 'Allowed in generated videos — click to disallow' : 'Disallowed — click to allow' }}"
                                        class="font-mono text-[0.6rem] px-1.5 py-1 rounded-sm border {{ $b['allowed'] ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">
                                    {{ $b['allowed'] ? '● on' : '○ off' }}
                                </button>
                                <button type="button" wire:click="editarBuiltin('{{ $b['slug'] }}')"
                                        title="{{ $b['override'] ? 'Edit your custom version' : 'Customize this built-in' }}"
                                        class="text-sm leading-none px-1.5 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎</button>
                                @if ($b['override'])
                                    <button type="button" wire:click="resetBuiltin('{{ $b['slug'] }}')"
                                            wire:confirm="Reset «{{ $b['slug'] }}» to the default built-in? Your custom version is deleted."
                                            title="Reset to the default built-in"
                                            class="text-sm leading-none px-1.5 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">↺</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
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
