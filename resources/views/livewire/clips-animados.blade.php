<div @if ($this->hasActive) wire:poll.3s @endif>
    <x-page-header
        eyebrow="Tomus · IV"
        title="Clips Animados"
        cota="741.5 · IAT · '26"
        lead="Estúdio de animação: da locução à peça animada, com timestamps e planeamento assistido." />

    {{-- ============ Separadores ============ --}}
    @php
        $tabs = [
            'animation' => ['label' => 'Animação', 'sub' => 'Texto ou locução → vídeo animado', 'glyph' => '❈'],
            'overlay'   => ['label' => 'Vídeo + Animações', 'sub' => 'Vídeo existente → animações sobrepostas', 'glyph' => '❖'],
        ];
    @endphp
    <div class="flex gap-1 border-b border-ink-soft/15 mb-8">
        @foreach ($tabs as $key => $tab)
            <button type="button" wire:click="trocarSeparador('{{ $key }}')"
                    class="group relative px-5 py-3 text-left transition
                           {{ $activeTab === $key ? 'text-ink' : 'text-ink-soft hover:text-ink' }}">
                <span class="flex items-center gap-2">
                    <span class="text-gold text-lg select-none">{{ $tab['glyph'] }}</span>
                    <span>
                        <span class="block font-display text-xl leading-tight">{{ $tab['label'] }}</span>
                        <span class="block font-mono text-[0.6rem] text-ink-faint">{{ $tab['sub'] }}</span>
                    </span>
                </span>
                <span class="absolute left-0 -bottom-px h-0.5 w-full transition-all"
                      style="background: {{ $activeTab === $key ? 'var(--color-teal)' : 'transparent' }};
                             box-shadow: {{ $activeTab === $key ? '0 0 8px var(--color-teal)' : 'none' }}"></span>
            </button>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-5 gap-6">
        {{-- ============ Formulário ============ --}}
        <div class="lg:col-span-3">
            @if ($activeTab === 'animation')
                <x-panel eyebrow="Passo · I" title="Nova animação" glyph="❈">
                    <form wire:submit="submitAnimation" class="space-y-5">
                        <div>
                            <label class="eyebrow block mb-2">Guião / texto para locução</label>
                            <textarea wire:model="text" rows="6"
                                      placeholder="Escreva o texto. Será convertido em locução e transcrito para obter os tempos exactos."
                                      class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink
                                             placeholder:text-ink-faint focus:border-teal/50 focus:outline-none"></textarea>
                            @error('text') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="h-px flex-1 bg-ink-soft/15"></span>
                            <span class="font-mono text-[0.6rem] text-ink-faint uppercase tracking-widest">ou</span>
                            <span class="h-px flex-1 bg-ink-soft/15"></span>
                        </div>

                        <div x-data="voiceRecorder" class="space-y-3">
                            <label class="eyebrow block">Gravar voz</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" x-show="!recording" @click="start()"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-sm border border-leather/50
                                               text-leather font-mono text-xs hover:bg-leather/10 transition">
                                    <span class="text-base leading-none">●</span> Gravar
                                </button>
                                <button type="button" x-show="recording" x-cloak @click="stop()"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-sm border border-bad/60
                                               text-bad font-mono text-xs hover:bg-bad/10 transition">
                                    <span class="text-base leading-none animate-pulse">■</span>
                                    Parar · <span x-text="elapsed + 's'"></span>
                                </button>
                                <span x-show="uploading" x-cloak class="font-mono text-[0.6rem] text-ink-faint">a enviar gravação…</span>
                                <span x-show="ready && !recording && !uploading" x-cloak class="font-mono text-[0.6rem] text-teal">gravação pronta ✔</span>
                                <span x-show="error" x-cloak class="font-mono text-[0.6rem] text-bad" x-text="error"></span>
                            </div>
                            <audio x-ref="player" x-show="ready" x-cloak controls
                                   class="w-full rounded-sm border border-ink-soft/15"></audio>
                        </div>

                        <div>
                            <label class="eyebrow block mb-2">Ou carregar locução (áudio)</label>
                            <input type="file" wire:model="audio" accept="audio/*"
                                   class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4
                                          file:rounded-sm file:border file:border-teal/40 file:bg-transparent
                                          file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                            <div wire:loading wire:target="audio" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                            @error('audio') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit"
                                    class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal
                                           hover:bg-teal/10 transition"
                                    wire:loading.attr="disabled">
                                Gerar clip animado
                            </button>
                            <span wire:loading wire:target="submitAnimation" class="font-mono text-[0.6rem] text-ink-faint">a preparar…</span>
                        </div>
                        <p class="font-mono text-[0.6rem] text-ink-faint">
                            Modo denso — cada segundo da locução recebe animação.
                        </p>
                    </form>
                </x-panel>
            @else
                <x-panel eyebrow="Passo · I" title="Vídeo com animações" glyph="❖">
                    <form wire:submit="submitOverlay" class="space-y-5">
                        <div>
                            <label class="eyebrow block mb-2">Carregar vídeo (mp4 / mov)</label>
                            <input type="file" wire:model="video" accept="video/mp4,video/quicktime"
                                   class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4
                                          file:rounded-sm file:border file:border-teal/40 file:bg-transparent
                                          file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                            <div wire:loading wire:target="video" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                            @error('video') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit"
                                    class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal
                                           hover:bg-teal/10 transition"
                                    wire:loading.attr="disabled">
                                Gerar animações sobrepostas
                            </button>
                            <span wire:loading wire:target="submitOverlay" class="font-mono text-[0.6rem] text-ink-faint">a preparar…</span>
                        </div>
                        <p class="font-mono text-[0.6rem] text-ink-faint">
                            Modo esparso — animações apenas nos momentos que valem a pena.
                        </p>
                    </form>
                </x-panel>
            @endif
        </div>

        {{-- ============ Peças / estado ============ --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="eyebrow">Peças recentes</div>

            @php
                $estados = [
                    'draft'        => ['label' => 'Rascunho',     'tone' => 'neutral', 'glyph' => '·'],
                    'transcribing' => ['label' => 'A transcrever', 'tone' => 'gold',   'glyph' => '❧'],
                    'planning'     => ['label' => 'A planear',    'tone' => 'gold',    'glyph' => '❧'],
                    'rendering'    => ['label' => 'A renderizar',  'tone' => 'gold',   'glyph' => '❧'],
                    'done'         => ['label' => 'Pronto',       'tone' => 'good',    'glyph' => '✔'],
                    'failed'       => ['label' => 'Falhou',       'tone' => 'bad',     'glyph' => '✕'],
                ];
            @endphp

            @forelse ($this->projects as $project)
                @php $e = $estados[$project->status] ?? $estados['draft']; @endphp
                <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-4 shadow-engraved" wire:key="clip-{{ $project->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-display text-lg text-ink truncate">{{ $project->title ?? 'Sem título' }}</div>
                            <div class="font-mono text-[0.58rem] text-ink-faint">#{{ $project->id }} · {{ $project->created_at?->diffForHumans() }}</div>
                        </div>
                        <x-badge :tone="$e['tone']">{{ $e['glyph'] }} {{ $e['label'] }}</x-badge>
                    </div>

                    @if ($project->status === 'done' && $project->output_path)
                        <video class="mt-3 w-full rounded-sm border border-ink-soft/15 bg-black" controls preload="metadata"
                               src="{{ route('clips-animados.media', $project) }}"></video>
                        <a href="{{ route('clips-animados.media', ['project' => $project, 'download' => 1]) }}"
                           class="mt-2 inline-block font-mono text-[0.6rem] text-teal">descarregar →</a>
                    @elseif ($project->status === 'failed')
                        <p class="mt-2 text-sm text-bad/90">{{ \Illuminate\Support\Str::limit($project->error, 160) }}</p>
                    @else
                        <div class="mt-3 h-1 w-full bg-surface/40 rounded-full overflow-hidden">
                            <div class="h-full bg-teal/60 animate-pulse"
                                 style="width: {{ ['transcribing'=>33,'planning'=>60,'rendering'=>85][$project->status] ?? 12 }}%"></div>
                        </div>
                    @endif
                </div>
            @empty
                <x-empty-state glyph="❈" title="Sem peças" note="Ainda não há clips neste separador. Gere o primeiro à esquerda." />
            @endforelse
        </div>
    </div>

    @script
    <script>
        Alpine.data('voiceRecorder', () => ({
            recording: false,
            uploading: false,
            ready: false,
            elapsed: 0,
            error: null,
            recorder: null,
            chunks: [],
            timer: null,

            async start() {
                this.error = null;
                if (!navigator.mediaDevices?.getUserMedia) {
                    this.error = 'Gravação não suportada neste navegador.';
                    return;
                }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.chunks = [];
                    this.recorder = new MediaRecorder(stream);
                    this.recorder.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data); };
                    this.recorder.onstop = () => this.finish(stream);
                    this.recorder.start();
                    this.recording = true;
                    this.ready = false;
                    this.elapsed = 0;
                    this.timer = setInterval(() => this.elapsed++, 1000);
                } catch (e) {
                    this.error = 'Sem acesso ao microfone.';
                }
            },

            stop() {
                clearInterval(this.timer);
                this.recording = false;
                if (this.recorder && this.recorder.state !== 'inactive') {
                    this.recorder.stop();
                }
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
                    () => { this.uploading = false; this.error = 'Falha ao enviar a gravação.'; },
                    () => {}
                );
            },
        }));
    </script>
    @endscript
</div>
