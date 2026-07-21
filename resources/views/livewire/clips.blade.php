<div>
    <x-page-header
        eyebrow="Tomus · III"
        title="Gerador de Clips"
        cota="778.5 · IAT · '26"
        lead="Corte automático de vídeos longos em shorts legendados. Cada passo — cortar, legendar, regenerar — é independente e re-executável." />

    {{-- Avisos --}}
    @if ($mensagem)
        <div class="mb-4 border border-good/40 bg-good/5 text-good rounded-sm px-4 py-2 font-mono text-sm">✓ {{ $mensagem }}</div>
    @endif
    @if ($erro)
        <div class="mb-4 border border-bad/40 bg-bad/5 text-bad rounded-sm px-4 py-2 font-mono text-sm">✕ {{ $erro }}</div>
    @endif

    {{-- Indicador de operação em curso (corte/legendas podem demorar) --}}
    <div wire:loading wire:target="transcrever, sugerirIA, cortar, regenerar" class="mb-4 border border-teal/40 bg-teal/5 text-teal rounded-sm px-4 py-2 font-mono text-sm">
        ⏳ A processar no ShortsCreator… (corte e legendagem demoram alguns segundos)
    </div>

    {{-- Nova fonte --}}
    <x-panel eyebrow="Origem" title="Novo vídeo longo" glyph="▶" class="mb-6">
        <form wire:submit="adicionarFonte" class="space-y-4">
            <div>
                <label class="eyebrow block mb-1.5">URL do vídeo (acessível por HTTP)</label>
                <input type="text" wire:model="novaFonte" placeholder="https://… ou http://localhost:5056/video.mp4"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                @error('novaFonte') <span class="text-bad font-mono text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="grid sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="eyebrow block mb-1.5">Título (opcional)</label>
                    <input type="text" wire:model="novaFonteTitulo" placeholder="Entrevista, aula…"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                </div>
                <div>
                    <label class="eyebrow block mb-1.5">Língua</label>
                    <input type="text" wire:model="novaFonteLingua" placeholder="pt"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button type="submit" class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                    Adicionar fonte
                </button>
                <span class="font-mono text-xs text-ink-faint">API: {{ $apiUrl }}</span>
            </div>
        </form>
    </x-panel>

    {{-- Sem fontes --}}
    @if ($fontes->isEmpty())
        <x-empty-state glyph="✂" title="Sem vídeos na bancada"
            note="Adicione um vídeo longo acima para começar a gerar shorts." />
    @endif

    {{-- Fontes + clips --}}
    <div class="space-y-6">
        @foreach ($fontes as $fonte)
            @php
                $estadoFonte = $fonte->get('estado', 'nova');
                $temTranscricao = filled($fonte->get('transcricao'));
                $clips = $clipsPorFonte[$fonte->path] ?? collect();
            @endphp
            <x-panel :eyebrow="'Fonte · '.$fonte->get('lingua', 'pt')" :title="$fonte->title()" glyph="❦">
                <div class="flex flex-wrap items-center gap-2 -mt-2 mb-4">
                    <x-badge :tone="$temTranscricao ? 'good' : 'neutral'">{{ $temTranscricao ? 'transcrita' : $estadoFonte }}</x-badge>
                    <span class="font-mono text-xs text-ink-faint truncate max-w-md">{{ $fonte->get('fonte') }}</span>
                    <div class="ml-auto flex items-center gap-2">
                        <button wire:click="transcrever('{{ $fonte->path }}')" wire:loading.attr="disabled"
                                class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition">
                            Transcrever
                        </button>
                        @if ($temOpenAI)
                            <button wire:click="sugerirIA('{{ $fonte->path }}')" wire:loading.attr="disabled"
                                    class="border border-gold/40 text-gold font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-gold/10 transition">
                                Sugerir com IA
                            </button>
                        @endif
                        <button wire:click="removerFonte('{{ $fonte->path }}')"
                                wire:confirm="Remover esta fonte?"
                                class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">
                            Remover
                        </button>
                    </div>
                </div>

                {{-- Adicionar clip manualmente --}}
                <div class="border border-ink-soft/15 rounded-sm p-4 bg-surface/30 mb-5">
                    <div class="eyebrow mb-3">Adicionar clip (janela do vídeo original)</div>
                    <div class="grid sm:grid-cols-4 gap-3">
                        <input type="text" wire:model="clipTitulo.{{ $fonte->slug() }}" placeholder="Título"
                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-body text-sm focus:border-teal focus:outline-none">
                        <input type="text" wire:model="clipInicio.{{ $fonte->slug() }}" placeholder="Início (00:00:05 ou 5)"
                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <input type="text" wire:model="clipFim.{{ $fonte->slug() }}" placeholder="Fim (00:01:05 ou 65)"
                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <input type="text" wire:model="clipTags.{{ $fonte->slug() }}" placeholder="tags, separadas"
                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <button wire:click="adicionarClip('{{ $fonte->path }}', '{{ $fonte->slug() }}')"
                            class="mt-3 bg-teal/90 text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition">
                        Criar clip
                    </button>
                    @unless ($temTranscricao)
                        <p class="mt-2 font-mono text-xs text-ink-faint">Sem transcrição: o clip fica sem legendas — pode escrevê-las à mão no editor.</p>
                    @endunless
                </div>

                {{-- Lista de clips desta fonte --}}
                @if ($clips->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($clips as $clip)
                            @php
                                $estado = $clip->get('estado', 'rascunho');
                                $tone = match ($estado) { 'pronto' => 'good', 'cortado' => 'teal', default => 'neutral' };
                            @endphp
                            <div class="border border-ink-soft/15 rounded-sm bg-vellum/40">
                                {{-- Cabeçalho do clip --}}
                                <div class="flex flex-wrap items-center gap-3 p-4">
                                    <div class="min-w-0">
                                        <div class="font-display text-xl text-ink leading-tight">{{ $clip->title() }}</div>
                                        <div class="font-mono text-xs text-ink-faint">
                                            {{ number_format((float) $clip->get('inicio'), 1) }}s → {{ number_format((float) $clip->get('fim'), 1) }}s
                                            @foreach ((array) $clip->get('tags', []) as $tag) · <span class="text-ink-soft">#{{ $tag }}</span> @endforeach
                                        </div>
                                    </div>
                                    <x-badge :tone="$tone">{{ $estado }}</x-badge>
                                    @if ($estado === 'pronto' && $clip->get('output_path'))
                                        <a href="{{ route('clips.video', $clip->slug()) }}" target="_blank"
                                           class="font-mono text-xs text-teal underline hover:text-teal-deep">▶ ver short</a>
                                    @endif
                                    <div class="ml-auto flex items-center gap-2">
                                        @if ($clipAberto === $clip->path)
                                            <button wire:click="fechar" class="border border-ink-soft/30 text-ink-soft font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-ink-soft/10 transition">Fechar</button>
                                        @else
                                            <button wire:click="abrir('{{ $clip->path }}')" class="border border-ink-soft/30 text-ink-soft font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-ink-soft/10 transition">Editar legendas</button>
                                        @endif
                                        <button wire:click="cortar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                                class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition">Cortar</button>
                                        <button wire:click="regenerar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                                class="bg-teal text-papyrus font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal-deep transition shadow-engraved">Regenerar</button>
                                        <button wire:click="removerClip('{{ $clip->path }}')" wire:confirm="Remover este clip?"
                                                class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">✕</button>
                                    </div>
                                </div>

                                {{-- Editor de legendas (clip aberto) --}}
                                @if ($clipAberto === $clip->path)
                                    <div class="border-t border-ink-soft/15 p-4 space-y-5">
                                        {{-- Segmentos --}}
                                        <div>
                                            <div class="eyebrow mb-2">Legendas — texto e tempos (segundos, relativos ao clip)</div>
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
                                                    <p class="font-mono text-xs text-ink-faint">Sem legendas. Adicione linhas ou transcreva a fonte.</p>
                                                @endforelse
                                            </div>
                                            <button wire:click="adicionarSegmento" class="mt-2 font-mono text-xs text-teal hover:text-teal-deep">+ adicionar linha</button>
                                        </div>

                                        {{-- Estilo --}}
                                        <div>
                                            <div class="eyebrow mb-2">Estilo das legendas</div>
                                            <div class="grid sm:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="font-mono text-xs text-ink-faint block mb-1">Posição</label>
                                                    <select wire:model="estilo.position" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                        @foreach ($posicoes as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="font-mono text-xs text-ink-faint block mb-1">Tipo de letra</label>
                                                    <select wire:model="estilo.font-family" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                        @foreach (['Luckiest Guy','Anton','Impact','Times Bold Italic','Pixelify Sans','DejaVu-Sans-Bold'] as $f) <option value="{{ $f }}">{{ $f }}</option> @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="font-mono text-xs text-ink-faint block mb-1">Modo de palavra</label>
                                                    <select wire:model="modoPalavra" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                        @foreach ($modosPalavra as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="font-mono text-xs text-ink-faint block mb-1">Tamanho</label>
                                                    <input type="number" wire:model="estilo.font-size" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                </div>
                                                <div>
                                                    <label class="font-mono text-xs text-ink-faint block mb-1">Cor do texto</label>
                                                    <input type="text" wire:model="estilo.line-color" placeholder="#2dbab4" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                </div>
                                                <div>
                                                    <label class="font-mono text-xs text-ink-faint block mb-1">Contorno (px)</label>
                                                    <input type="number" wire:model="estilo.outline-width" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Acções do editor --}}
                                        <div class="flex items-center gap-3 pt-1">
                                            <button wire:click="guardarLegendas" class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-4 py-2 rounded-sm hover:bg-teal/10 transition">Guardar</button>
                                            <button wire:click="regenerar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                                    class="bg-teal text-papyrus font-display text-base px-5 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                                                Guardar e regenerar short
                                            </button>
                                            <span class="font-mono text-xs text-ink-faint">Regenerar volta a gravar as legendas no clip já cortado — sem re-transcrever nem re-cortar.</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-panel>
        @endforeach
    </div>
</div>
