<div @if ($view === 'dashboard' && $this->hasActive) wire:poll.3s @endif>
    @php
        $estados = [
            'draft'        => ['label' => 'Rascunho',      'tone' => 'neutral', 'glyph' => '·'],
            'transcribing' => ['label' => 'A transcrever', 'tone' => 'gold',    'glyph' => '❧'],
            'planning'     => ['label' => 'A planear',     'tone' => 'gold',    'glyph' => '❧'],
            'rendering'    => ['label' => 'A renderizar',  'tone' => 'gold',    'glyph' => '❧'],
            'done'         => ['label' => 'Pronto',        'tone' => 'good',    'glyph' => '✔'],
            'failed'       => ['label' => 'Falhou',        'tone' => 'bad',     'glyph' => '✕'],
        ];
        $tipos = [
            'animation' => ['label' => 'Animação',           'glyph' => '❈'],
            'overlay'   => ['label' => 'Vídeo + Animações',  'glyph' => '❖'],
        ];
    @endphp

    <x-page-header
        eyebrow="Tomus · IV"
        title="Clips Animados"
        cota="741.5 · IAT · '26"
        lead="Estúdio de animação: da locução à peça animada, com timestamps e planeamento assistido." />

    {{-- ============================================================ --}}
    {{-- DASHBOARD                                                    --}}
    {{-- ============================================================ --}}
    @if ($view === 'dashboard')
        <div class="flex items-center justify-between mb-6">
            <div class="eyebrow">Clipes gerados · {{ $this->projects->count() }}</div>
            <button type="button" wire:click="novoClip"
                    class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                ✦ Novo clip
            </button>
        </div>

        @if ($this->projects->isEmpty())
            <x-empty-state glyph="❈" title="Sem clipes" note="Ainda não gerou nenhuma peça. Comece com «Novo clip»." />
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
                            {{-- ESQUERDA: vídeo --}}
                            <div class="flex items-center justify-center">
                                @if ($project->status === 'done' && $project->output_path)
                                    <video class="rounded-sm border border-ink-soft/15 bg-black max-h-[60vh] w-auto max-w-full" controls preload="metadata"
                                           src="{{ route('clips-animados.media', $project) }}"></video>
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

                            {{-- DIREITA: título sugerido, descrição, tags, estado --}}
                            <div class="min-w-0 flex flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gold text-sm">{{ $t['glyph'] }}</span>
                                        <span class="font-mono text-[0.58rem] text-ink-faint uppercase tracking-wider">{{ $t['label'] }}</span>
                                    </div>
                                    <x-badge :tone="$e['tone']">{{ $e['glyph'] }} {{ $e['label'] }}</x-badge>
                                </div>

                                <div class="font-display text-xl text-ink mt-2">{{ $project->title ?? 'Sem título' }}</div>
                                <div class="font-mono text-[0.58rem] text-ink-faint">#{{ $project->id }} · {{ $project->created_at?->diffForHumans() }}</div>

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

                                {{-- acções --}}
                                <div class="mt-auto pt-4 flex flex-wrap items-center gap-x-4 gap-y-2 font-mono text-[0.62rem]">
                                    @if (!empty($project->plan['scenes']))
                                        <button type="button" wire:click="editarClip({{ $project->id }})" class="text-teal hover:underline">✎ editar clip</button>
                                    @endif
                                    @if (!empty($project->transcript['text']))
                                        <button type="button" wire:click="editarTranscricao({{ $project->id }})" class="text-teal hover:underline">✎ editar transcrição · regenerar</button>
                                    @endif
                                    @if ($project->status === 'done' && $project->output_path)
                                        <a href="{{ route('clips-animados.media', ['project' => $project, 'download' => 1]) }}" class="text-teal hover:underline">↓ descarregar</a>
                                    @endif
                                    <button type="button" wire:click="apagar({{ $project->id }})"
                                            wire:confirm="Apagar este clip e os seus ficheiros? Esta acção é definitiva."
                                            class="text-bad hover:underline ml-auto">✕ apagar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- CRIAÇÃO                                                      --}}
    {{-- ============================================================ --}}
    @if ($view === 'create')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← voltar ao painel</button>

        @if ($createType === null)
            {{-- passo 1: escolher tipo --}}
            <div class="eyebrow mb-4">Passo · I — que tipo de clip?</div>
            <div class="grid md:grid-cols-2 gap-6">
                <button type="button" wire:click="escolherTipo('animation')" class="text-left group">
                    <x-panel class="h-full transition group-hover:border-teal/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="eyebrow mb-2">Tipo · A</div>
                                <h2 class="font-display text-3xl text-ink">Animação</h2>
                                <p class="mt-2 text-ink-soft">De um texto ou locução — vídeo totalmente animado, com animação em cada segundo.</p>
                            </div>
                            <span class="text-4xl text-gold/70 select-none">❈</span>
                        </div>
                        <div class="mt-6 font-mono text-[0.62rem] text-teal">escolher →</div>
                    </x-panel>
                </button>
                <button type="button" wire:click="escolherTipo('overlay')" class="text-left group">
                    <x-panel class="h-full transition group-hover:border-teal/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="eyebrow mb-2">Tipo · B</div>
                                <h2 class="font-display text-3xl text-ink">Vídeo + Animações</h2>
                                <p class="mt-2 text-ink-soft">De um vídeo já pronto — animações sobrepostas apenas nos momentos que valem a pena.</p>
                            </div>
                            <span class="text-4xl text-gold/70 select-none">❖</span>
                        </div>
                        <div class="mt-6 font-mono text-[0.62rem] text-teal">escolher →</div>
                    </x-panel>
                </button>
            </div>
        @elseif ($createType === 'animation')
            {{-- passo 2A: formulário de animação --}}
            <x-panel eyebrow="Passo · II — Animação" title="Nova animação" glyph="❈">
                <form wire:submit="submitAnimation" class="space-y-5">
                    <div>
                        <label class="eyebrow block mb-2">Guião / texto para locução</label>
                        <textarea wire:model="text" rows="5"
                                  placeholder="Escreva o texto. Será convertido em locução e transcrito para obter os tempos exactos."
                                  class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none"></textarea>
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
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-sm border border-leather/50 text-leather font-mono text-xs hover:bg-leather/10 transition">
                                <span class="text-base leading-none">●</span> Gravar
                            </button>
                            <button type="button" x-show="recording" x-cloak @click="stop()"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-sm border border-bad/60 text-bad font-mono text-xs hover:bg-bad/10 transition">
                                <span class="text-base leading-none animate-pulse">■</span> Parar · <span x-text="elapsed + 's'"></span>
                            </button>
                            <span x-show="uploading" x-cloak class="font-mono text-[0.6rem] text-ink-faint">a enviar gravação…</span>
                            <span x-show="ready && !recording && !uploading" x-cloak class="font-mono text-[0.6rem] text-teal">gravação pronta ✔</span>
                            <span x-show="error" x-cloak class="font-mono text-[0.6rem] text-bad" x-text="error"></span>
                        </div>
                        <audio x-ref="player" x-show="ready" x-cloak controls class="w-full rounded-sm border border-ink-soft/15"></audio>
                    </div>

                    <div>
                        <label class="eyebrow block mb-2">Ou carregar locução (áudio)</label>
                        <input type="file" wire:model="audio" accept="audio/*"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="audio" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                        @error('audio') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </div>

                    @include('livewire.partials.musica-picker')

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition" wire:loading.attr="disabled">
                            Gerar clip animado
                        </button>
                        <span wire:loading wire:target="submitAnimation" class="font-mono text-[0.6rem] text-ink-faint">a preparar…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">Modo denso — cada segundo da locução recebe animação.</p>
                </form>
            </x-panel>
        @elseif ($createType === 'overlay')
            {{-- passo 2B: formulário de vídeo+animações --}}
            <x-panel eyebrow="Passo · II — Vídeo + Animações" title="Vídeo com animações" glyph="❖">
                <form wire:submit="submitOverlay" class="space-y-5">
                    <div>
                        <label class="eyebrow block mb-2">Carregar vídeo (mp4 / mov)</label>
                        <input type="file" wire:model="video" accept="video/mp4,video/quicktime"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="video" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                        @error('video') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="eyebrow block mb-2">Estilos permitidos</label>
                        @php
                            $styles = [
                                'video'     => ['t' => 'Só vídeo', 'd' => 'vídeo + legendas'],
                                'over'      => ['t' => 'Animação por cima', 'd' => 'gráfico sobre o vídeo'],
                                'split'     => ['t' => 'Ecrã dividido', 'd' => 'animação em cima, vídeo em baixo'],
                                'animation' => ['t' => 'Ecrã inteiro', 'd' => 'animação cobre o vídeo'],
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
                            Gerar clip
                        </button>
                        <span wire:loading wire:target="submitOverlay" class="font-mono text-[0.6rem] text-ink-faint">a preparar…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">A IA intercala automaticamente os estilos escolhidos, conforme o conteúdo.</p>
                </form>
            </x-panel>
        @endif

        {{-- Imagens (opcional) — para ambos os tipos --}}
        @if ($createType !== null)
            <x-panel class="mt-6" eyebrow="Opcional" title="Imagens" glyph="▣">
                <p class="text-ink-soft text-sm mb-4">Carregue imagens e descreva o que mostram. A IA usa-as animadas nas cenas onde encaixam.</p>

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
                        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">ficheiro</label>
                        <input type="file" wire:model="newImage" accept="image/*"
                               class="block w-full text-xs text-ink-soft file:mr-2 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-[0.6rem] file:cursor-pointer" />
                        <div wire:loading wire:target="newImage" class="mt-1 font-mono text-[0.55rem] text-ink-faint">a carregar…</div>
                    </div>
                    <div class="col-span-5">
                        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">descrição</label>
                        <input type="text" wire:model="newImageDesc" placeholder="ex.: logótipo da empresa"
                               class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                    </div>
                    <div class="col-span-2">
                        <button type="button" wire:click="adicionarImagem"
                                class="w-full font-mono text-[0.62rem] px-3 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">+ juntar</button>
                    </div>
                </div>
                @error('newImage') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                @error('newImageDesc') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
            </x-panel>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- EDITAR CLIP (animações)                                      --}}
    {{-- ============================================================ --}}
    @if ($view === 'editPlan')
        <div class="flex items-center justify-between mb-6">
            <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">← voltar ao painel</button>
            <div class="flex gap-1 font-mono text-[0.62rem]">
                <button type="button" wire:click="$set('editMode','cenas')"
                        class="px-3 py-1 rounded-sm border {{ $editMode === 'cenas' ? 'border-teal/50 text-teal' : 'border-ink-soft/20 text-ink-soft' }}">Cenas</button>
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
            <x-panel eyebrow="Editar clip" title="Plano Remotion (JSON)" glyph="⌘">
                <form wire:submit="guardarJson" class="space-y-4">
                    <p class="text-ink-soft text-sm">Edita directamente o plano que vai para o Remotion (cenas, camadas, params). O áudio, karaoke e imagens são acrescentados no render.</p>
                    <textarea wire:model="editPlanJson" rows="22" spellcheck="false"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink font-mono text-xs leading-relaxed focus:border-teal/50 focus:outline-none" style="tab-size:2"></textarea>
                    @error('editPlanJson') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Guardar JSON e re-renderizar</button>
                        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancelar</button>
                    </div>
                </form>
            </x-panel>
        @else
        <x-panel eyebrow="Editar clip" title="Cenas" glyph="✎">
            <form wire:submit="guardarPlano" class="space-y-5">
                <div>
                    <label class="eyebrow block mb-2">Título</label>
                    <input type="text" wire:model="editTitle" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none" />
                </div>

                @include('livewire.partials.musica-picker')

                <div class="space-y-3">
                    @foreach ($editScenes as $i => $scene)
                        <div class="border border-ink-soft/15 rounded-sm p-3 bg-surface/20" wire:key="scene-{{ $i }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="eyebrow">Cena {{ $i + 1 }} · <span class="text-ink-faint normal-case tracking-normal">{{ $scene['layersSummary'] ?? '—' }}</span></span>
                                <button type="button" wire:click="removerCena({{ $i }})" class="text-bad font-mono text-sm hover:opacity-70" title="remover">✕</button>
                            </div>
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">início</label>
                                    <input type="number" step="any" min="0" wire:model="editScenes.{{ $i }}.start" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">fim</label>
                                    <input type="number" step="any" min="0" wire:model="editScenes.{{ $i }}.end" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
                                </div>
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">fundo</label>
                                    <select wire:model="editScenes.{{ $i }}.background" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none">
                                        @foreach ($backgrounds as $bg)<option value="{{ $bg }}">{{ $bg }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">transição</label>
                                    <select wire:model="editScenes.{{ $i }}.transitionIn" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none">
                                        @foreach ($transitions as $tr)<option value="{{ $tr }}">{{ $tr }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-span-12">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">texto do elemento</label>
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

                <button type="button" wire:click="adicionarCena" class="font-mono text-[0.62rem] text-teal hover:underline">+ adicionar cena</button>

                <div class="flex items-center gap-3 pt-2 border-t border-ink-soft/15">
                    <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition mt-4">Guardar e re-renderizar</button>
                    <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mt-4">cancelar</button>
                </div>
                <p class="font-mono text-[0.6rem] text-ink-faint">Edita o ritmo das cenas (tempos, fundo, transição, ênfase, karaoke). As camadas de cada cena são preservadas. Lacunas em modo denso preenchem-se com «ambient».</p>
            </form>
        </x-panel>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- EDITAR TRANSCRIÇÃO                                           --}}
    {{-- ============================================================ --}}
    @if ($view === 'editTranscript')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← voltar ao painel</button>
        <x-panel eyebrow="Editar transcrição" title="Corrigir e regenerar" glyph="✎">
            <form wire:submit="regenerar" class="space-y-5">
                <div>
                    <label class="eyebrow block mb-2">Transcrição (corrija erros do reconhecimento)</label>
                    <textarea wire:model="editTranscriptText" rows="6" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('editTranscriptText') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Regenerar animações</button>
                    <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancelar</button>
                </div>
                <p class="font-mono text-[0.6rem] text-ink-faint">A IA volta a planear as animações a partir do texto corrigido e renderiza de novo.</p>
            </form>
        </x-panel>
    @endif

    @script
    <script>
        Alpine.data('voiceRecorder', () => ({
            recording: false, uploading: false, ready: false, elapsed: 0, error: null,
            recorder: null, chunks: [], timer: null,

            async start() {
                this.error = null;
                if (!navigator.mediaDevices?.getUserMedia) { this.error = 'Gravação não suportada neste navegador.'; return; }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.chunks = [];
                    this.recorder = new MediaRecorder(stream);
                    this.recorder.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data); };
                    this.recorder.onstop = () => this.finish(stream);
                    this.recorder.start();
                    this.recording = true; this.ready = false; this.elapsed = 0;
                    this.timer = setInterval(() => this.elapsed++, 1000);
                } catch (e) { this.error = 'Sem acesso ao microfone.'; }
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
                    () => { this.uploading = false; this.error = 'Falha ao enviar a gravação.'; },
                    () => {}
                );
            },
        }));
    </script>
    @endscript
</div>
