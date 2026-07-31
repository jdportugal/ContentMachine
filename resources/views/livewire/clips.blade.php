<div>
    <x-page-header
        eyebrow="Tomus · III"
        title="Clip Generator"
        cota="778.5 · ACM · '26"
        lead="Automatic cutting of long videos into subtitled shorts. Each step — cut, subtitle, regenerate — is independent and re-runnable." />

    {{-- Local operations (ffmpeg/AI) use the particle loader — see the buttons below. --}}

    @if (! $fonteAtual)
        {{-- ================= LONG VIDEO LIST ================= --}}
        <div class="flex items-center gap-4 mb-6">
            <button wire:click="alternarNovaFonte"
                    class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                {{ $mostrarNovaFonte ? '× Cancel' : '＋ New long video' }}
            </button>
            <span class="font-mono text-xs text-ink-faint">Engine: {{ $motor }}</span>
        </div>

        {{-- New video form (revealed by the button) --}}
        @if ($mostrarNovaFonte)
            <x-panel eyebrow="Source" title="New long video" glyph="▶" class="mb-6">
                <form wire:submit="adicionarFonte" x-on:submit="window.CMLoader.busy('Adding the video…')" class="space-y-4">
                    {{-- Drag and drop (up to 2 GB) --}}
                    <div x-data="{ over: false }"
                         x-on:dragover.prevent="over = true"
                         x-on:dragleave.prevent="over = false"
                         x-on:drop.prevent="over = false; if ($event.dataTransfer.files.length) $wire.upload('novoVideo', $event.dataTransfer.files[0])"
                         :class="over ? 'border-teal bg-teal/5' : 'border-ink-soft/25'"
                         class="border border-dashed rounded-sm px-4 py-6 text-center transition">
                        @if ($novoVideo)
                            <p class="font-mono text-sm text-teal">✓ {{ $novoVideo->getClientOriginalName() }}</p>
                            <button type="button" wire:click="$set('novoVideo', null)"
                                    class="mt-1 font-mono text-[0.62rem] text-ink-faint hover:text-bad">remove</button>
                        @else
                            <p class="text-ink-soft text-sm">Drag a video (mp4 / mov) here, or
                                <label class="text-teal hover:underline cursor-pointer">choose a file<input type="file" wire:model="novoVideo" accept="video/mp4,video/quicktime" class="hidden"></label>
                            </p>
                        @endif
                        <div wire:loading wire:target="novoVideo" class="mt-2 font-mono text-[0.62rem] text-ink-faint">loading…</div>
                        @error('novoVideo') <span class="block mt-1 text-bad font-mono text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5">…or local path / URL</label>
                        <input type="text" wire:model="novaFonte" placeholder="/Users/.../video.mp4  ou  https://…"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        @error('novaFonte') <span class="text-bad font-mono text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="eyebrow block mb-1.5">Title (optional)</label>
                            <input type="text" wire:model="novaFonteTitulo" placeholder="Interview, lecture…"
                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                        </div>
                        <div>
                            <label class="eyebrow block mb-1.5">Language</label>
                            <input type="text" wire:model="novaFonteLingua" placeholder="pt"
                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        </div>
                    </div>
                    <button type="submit" class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                        Add video
                    </button>
                </form>
            </x-panel>
        @endif

        {{-- List of processed videos --}}
        @if ($fontes->isEmpty())
            <x-empty-state glyph="✂" title="No videos on the bench"
                note="Press «New long video» to start generating shorts." />
        @else
            <div class="space-y-3">
                @foreach ($fontes as $fonte)
                    @php
                        $temTranscricao = filled($fonte->get('transcricao'));
                        $nClips = ($clipsPorFonte[$fonte->path] ?? collect())->count();
                        $nProntos = ($clipsPorFonte[$fonte->path] ?? collect())->filter(fn ($c) => $c->get('estado') === 'pronto')->count();
                    @endphp
                    <div class="border border-ink-soft/15 rounded-sm bg-vellum/40 flex flex-wrap items-center gap-3 p-4">
                        <button wire:click="abrirFonte('{{ $fonte->path }}')" class="flex items-center gap-3 min-w-0 text-left group">
                            <span class="font-mono text-teal text-lg shrink-0">❦</span>
                            <span class="min-w-0">
                                <span class="block font-display text-xl text-ink leading-tight group-hover:text-teal transition">{{ $fonte->title() }}</span>
                                <span class="block font-mono text-xs text-ink-faint truncate max-w-lg">{{ $fonte->get('fonte') }}</span>
                            </span>
                        </button>
                        <x-badge :tone="$temTranscricao ? 'good' : 'neutral'">{{ $temTranscricao ? 'transcribed' : 'new' }}</x-badge>
                        <span class="font-mono text-xs text-ink-faint">{{ $nClips }} {{ $nClips === 1 ? 'clip' : 'clips' }}@if ($nProntos) · <span class="text-good">{{ $nProntos }} ready</span>@endif</span>
                        <div class="ml-auto flex items-center gap-2">
                            <button wire:click="abrirFonte('{{ $fonte->path }}')"
                                    class="bg-teal/90 text-papyrus font-mono text-xs uppercase tracking-wider px-4 py-1.5 rounded-sm hover:bg-teal-deep transition">Open</button>
                            <button wire:click="removerFonte('{{ $fonte->path }}')" wire:confirm="Remove this video and its clips?"
                                    class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">✕</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- ================= LONG VIDEO DETAIL ================= --}}
        @php $temTranscricao = filled($fonteAtual->get('transcricao')); @endphp

        <button wire:click="voltarFontes" class="mb-4 font-mono text-xs text-teal hover:text-teal-deep uppercase tracking-wider">← Videos</button>

        <x-panel :eyebrow="'Video · '.$fonteAtual->get('lingua', 'pt')" :title="$fonteAtual->title()" glyph="❦" class="mb-6">
            <div class="flex flex-wrap items-center gap-2 -mt-2 mb-4">
                <x-badge :tone="$temTranscricao ? 'good' : 'neutral'">{{ $temTranscricao ? 'transcribed' : $fonteAtual->get('estado', 'nova') }}</x-badge>
                <span class="font-mono text-xs text-ink-faint truncate max-w-md">{{ $fonteAtual->get('fonte') }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <button wire:click="transcrever('{{ $fonteAtual->path }}')" wire:loading.attr="disabled"
                            x-on:click="window.CMLoader.busy('Transcribing the video…')"
                            class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition">
                        {{ $temTranscricao ? 'Re-transcribe' : 'Transcribe' }}
                    </button>
                    @if ($temIA)
                        <button wire:click="sugerirIA('{{ $fonteAtual->path }}')" wire:loading.attr="disabled" @disabled(! $temTranscricao)
                                x-on:click="window.CMLoader.busy('Choosing clips with AI…')"
                                class="bg-gold text-ink font-display text-base px-4 py-1.5 rounded-sm hover:bg-gold/80 transition shadow-engraved disabled:opacity-40 disabled:cursor-not-allowed">
                            ✦ Choose clips with AI
                        </button>
                    @endif
                    <button wire:click="removerFonte('{{ $fonteAtual->path }}')" wire:confirm="Remove this video and its clips?"
                            class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">Remove</button>
                </div>
            </div>

            @if ($temIA)
                <div class="mb-4 border border-gold/30 bg-gold/5 text-ink-soft rounded-sm px-4 py-2.5 font-mono text-xs">
                    @if ($temTranscricao)
                        ✦ Press <span class="text-gold">Choose clips with AI</span> — in a single step the AI creates the shorts (title, window and tags) <em>and</em> suggests posts from the video.
                    @else
                        1) <span class="text-teal">Transcribe</span> the video · 2) then <span class="text-gold">Choose clips with AI</span> creates shorts and posts on its own.
                    @endif
                </div>
            @endif

            {{-- Posts suggested by the AI (from the video) --}}
            @php $pubsSugeridas = json_decode((string) $fonteAtual->get('publicacoes_sugeridas'), true) ?: []; @endphp
            @if ($pubsSugeridas)
                <x-panel eyebrow="From the video" title="Suggested posts" glyph="✎" class="mb-4">
                    <div class="space-y-2">
                        @foreach ($pubsSugeridas as $i => $pub)
                            <div class="flex items-start gap-3 py-2 border-b border-ink-soft/10 last:border-0" wire:key="pub-sug-{{ $i }}">
                                <div class="min-w-0 flex-1">
                                    <div class="font-display text-lg text-ink leading-tight">{{ $pub['titulo'] ?? 'Post' }}</div>
                                    @if (!empty($pub['angulo']))
                                        <p class="text-sm text-ink-soft">{{ $pub['angulo'] }}</p>
                                    @endif
                                </div>
                                <button wire:click="abrirPublicacao('{{ $fonteAtual->path }}', {{ $i }})"
                                        class="shrink-0 border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition">
                                    Open in Posts →
                                </button>
                            </div>
                        @endforeach
                    </div>
                </x-panel>
            @endif

            {{-- Add clip manually (optional) --}}
            <details class="border border-ink-soft/15 rounded-sm p-4 bg-surface/30">
                <summary class="eyebrow cursor-pointer select-none">Add clip manually (optional)</summary>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 mt-3">
                    <input type="text" wire:model="clipTitulo.{{ $fonteAtual->slug() }}" placeholder="Title"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-body text-sm focus:border-teal focus:outline-none">
                    <input type="text" wire:model="clipInicio.{{ $fonteAtual->slug() }}" placeholder="Start (00:00:05 or 5)"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    <input type="text" wire:model="clipFim.{{ $fonteAtual->slug() }}" placeholder="End (00:01:05 or 65)"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    <input type="text" wire:model="clipTags.{{ $fonteAtual->slug() }}" placeholder="tags, separated"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                </div>
                <button wire:click="adicionarClip('{{ $fonteAtual->path }}', '{{ $fonteAtual->slug() }}')"
                        class="mt-3 bg-teal/90 text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition">
                    Create clip
                </button>
            </details>
        </x-panel>

        {{-- Clips from this video --}}
        @if ($clipsDaFonte->isEmpty())
            <x-empty-state glyph="✂" title="No clips"
                note="Transcribe and use «Choose clips with AI», or add a clip manually above." />
        @else
            <div class="space-y-3">
                @foreach ($clipsDaFonte as $clip)
                    @php
                        $estado = $clip->get('estado', 'rascunho');
                        $tone = match ($estado) { 'pronto' => 'good', 'cortado' => 'teal', default => 'neutral' };
                        $temVideo = in_array($estado, ['cortado', 'pronto'], true);
                        $ehFinal = $estado === 'pronto';
                        $vv = substr(md5($estado.$clip->get('output_path').$clip->get('output_bytes').$clip->get('clip_path')), 0, 8);
                        $tipo = $ehFinal ? 'final' : 'raw';
                        $aberto = $clipAberto === $clip->path;
                        $urlBase = route('clips.video', $clip->slug());
                    @endphp
                    <div class="border border-ink-soft/15 rounded-sm bg-vellum/40">
                        {{-- Header (click collapses/expands) --}}
                        <div class="flex flex-wrap items-center gap-3 p-4">
                            <button type="button"
                                    @if ($aberto) wire:click="fechar" @else wire:click="abrir('{{ $clip->path }}')" @endif
                                    class="flex items-center gap-3 min-w-0 text-left group">
                                <span class="font-mono text-teal text-sm w-4 shrink-0">{{ $aberto ? '▾' : '▸' }}</span>
                                <span class="min-w-0">
                                    <span class="block font-display text-xl text-ink leading-tight group-hover:text-teal transition">{{ $clip->title() }}</span>
                                    <span class="block font-mono text-xs text-ink-faint">
                                        {{ number_format((float) $clip->get('inicio'), 1) }}s → {{ number_format((float) $clip->get('fim'), 1) }}s
                                        @foreach ((array) $clip->get('tags', []) as $tag) · <span class="text-ink-soft">#{{ $tag }}</span> @endforeach
                                    </span>
                                </span>
                            </button>
                            <x-badge :tone="$tone">{{ $estado }}</x-badge>
                            <div class="ml-auto flex items-center gap-2">
                                <button wire:click="cortar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                        x-on:click="window.CMLoader.busy('Cutting the clip (ffmpeg)…')"
                                        class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition">Cut</button>
                                <button wire:click="regenerar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                        x-on:click="window.CMLoader.busy('Rendering the short with subtitles…')"
                                        class="bg-teal text-papyrus font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal-deep transition shadow-engraved">Regenerate</button>
                                <button wire:click="removerClip('{{ $clip->path }}')" wire:confirm="Remove this clip?"
                                        class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">✕</button>
                            </div>
                        </div>

                        {{-- Expanded editor: video (left) + details (right) + editor below --}}
                        @if ($aberto)
                            <div class="border-t border-ink-soft/15 p-4 space-y-5">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    {{-- Video with/without subtitles --}}
                                    <div x-data="{ v: '{{ $tipo }}' }">
                                        @if ($temVideo)
                                            <video controls preload="metadata"
                                                   :src="'{{ $urlBase }}?t={{ $vv }}&v=' + v"
                                                   class="w-full max-w-xs mx-auto lg:mx-0 rounded-sm border border-ink-soft/20 bg-black aspect-[9/16] object-contain"></video>
                                            <div class="mt-2 flex items-center justify-center lg:justify-start gap-2 font-mono text-xs">
                                                @if ($ehFinal)
                                                    <button type="button" @click="v='final'" :class="v==='final' ? 'bg-teal text-papyrus' : 'border border-ink-soft/30 text-ink-soft'" class="px-3 py-1 rounded-sm uppercase tracking-wider">With subtitles</button>
                                                @endif
                                                <button type="button" @click="v='raw'" :class="v==='raw' ? 'bg-teal text-papyrus' : 'border border-ink-soft/30 text-ink-soft'" class="px-3 py-1 rounded-sm uppercase tracking-wider">Without subtitles</button>
                                            </div>
                                        @else
                                            <div class="w-full max-w-xs mx-auto lg:mx-0 aspect-[9/16] rounded-sm border border-dashed border-ink-soft/30 bg-surface/30 flex items-center justify-center text-center p-4">
                                                <span class="font-mono text-xs text-ink-faint">No video yet.<br>Press <span class="text-teal">Regenerate</span> to cut and subtitle.</span>
                                            </div>
                                        @endif
                                    </div>
                                    {{-- Title / description / tags --}}
                                    <div class="space-y-3">
                                        <div>
                                            <label class="eyebrow block mb-1.5">Title</label>
                                            <input type="text" wire:model="clipTituloEdit"
                                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-display text-lg focus:border-teal focus:outline-none">
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between mb-1.5">
                                                <label class="eyebrow">Description</label>
                                                @if ($temIA)
                                                    <button type="button" wire:click="gerarDescricao('{{ $clip->path }}')" wire:loading.attr="disabled"
                                                            x-on:click="window.CMLoader.busy('Generating description with AI…')"
                                                            class="font-mono text-[11px] text-gold hover:text-gold/80 uppercase tracking-wider">✦ Generate with AI</button>
                                                @endif
                                            </div>
                                            <textarea wire:model="clipDescricao" rows="4" placeholder="Short description (or generate with AI)…"
                                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body text-sm focus:border-teal focus:outline-none"></textarea>
                                        </div>
                                        <div>
                                            <label class="eyebrow block mb-1.5">Tags (comma-separated)</label>
                                            <input type="text" wire:model="clipTagsEdit" placeholder="ai, tutorial, n8n"
                                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                                        </div>
                                        <p class="font-mono text-[11px] text-ink-faint">Window in the original video: {{ number_format((float) $clip->get('inicio'), 1) }}s → {{ number_format((float) $clip->get('fim'), 1) }}s</p>
                                    </div>
                                </div>

                                {{-- Segments --}}
                                <div>
                                    <div class="eyebrow mb-2">Subtitles — text and times (seconds, relative to the clip)</div>
                                    <div class="space-y-2">
                                        @forelse ($segmentos as $i => $seg)
                                            <div class="grid grid-cols-12 gap-2 items-start">
                                                <input type="number" step="0.1" wire:model="segmentos.{{ $i }}.start"
                                                       class="col-span-2 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                <input type="number" step="0.1" wire:model="segmentos.{{ $i }}.end"
                                                       class="col-span-2 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                <textarea wire:model="segmentos.{{ $i }}.text" rows="1"
                                                          class="col-span-7 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-body text-sm focus:border-teal focus:outline-none"></textarea>
                                                <button wire:click="removerSegmento({{ $i }})" class="col-span-1 text-bad font-mono text-sm hover:text-bad/70">✕</button>
                                            </div>
                                        @empty
                                            <p class="font-mono text-xs text-ink-faint">No subtitles. Add lines or transcribe the video.</p>
                                        @endforelse
                                    </div>
                                    <button wire:click="adicionarSegmento" class="mt-2 font-mono text-xs text-teal hover:text-teal-deep">+ add line</button>
                                </div>

                                {{-- Style --}}
                                <div>
                                    <div class="eyebrow mb-2">Subtitle style</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="font-mono text-xs text-ink-faint block mb-1">Position</label>
                                            <select wire:model="estilo.position" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                @foreach ($posicoes as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="font-mono text-xs text-ink-faint block mb-1">Font</label>
                                            <select wire:model="estilo.font-family" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                @foreach (['Luckiest Guy','Anton','Impact','Times Bold Italic','Pixelify Sans','DejaVu-Sans-Bold'] as $f) <option value="{{ $f }}">{{ $f }}</option> @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="font-mono text-xs text-ink-faint block mb-1">Word mode</label>
                                            <select wire:model="modoPalavra" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                @foreach ($modosPalavra as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="font-mono text-xs text-ink-faint block mb-1">Size</label>
                                            <input type="number" wire:model="estilo.font-size" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="font-mono text-xs text-ink-faint block mb-1">Text color</label>
                                            <input type="text" wire:model="estilo.line-color" placeholder="#5A7BFF" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="font-mono text-xs text-ink-faint block mb-1">Outline (px)</label>
                                            <input type="number" wire:model="estilo.outline-width" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                        </div>
                                    </div>
                                </div>

                                {{-- Background music --}}
                                <div>
                                    <div class="eyebrow mb-2">Background music</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                        <select wire:model="musica"
                                                class="sm:col-span-3 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                            <option value="">Random (from library)</option>
                                            <option value="nenhuma">None (original audio only)</option>
                                            @foreach ($musicas as $m)
                                                <option value="{{ $m['name'] }}">♪ {{ $m['name'] }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.05" min="0" max="1" wire:model="musicaVolume" placeholder="Volume 0-1"
                                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                    </div>
                                    @if (empty($musicas))
                                        <p class="mt-1 font-mono text-[11px] text-ink-faint">Library empty — upload tracks in <a href="{{ route('ativos') }}" class="text-teal underline">Assets</a>.</p>
                                    @endif
                                </div>

                                {{-- Editor actions --}}
                                <div class="flex items-center gap-3 pt-1">
                                    <button wire:click="guardarLegendas" class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-4 py-2 rounded-sm hover:bg-teal/10 transition">Save</button>
                                    <button wire:click="regenerar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                            x-on:click="window.CMLoader.busy('Rendering the short with subtitles…')"
                                            class="bg-teal text-papyrus font-display text-base px-5 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                                        Save and regenerate short
                                    </button>
                                    <span class="font-mono text-xs text-ink-faint">Regenerate re-renders the subtitles onto the already-cut clip — without re-transcribing or re-cutting.</span>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
