<div>
    <x-page-header
        eyebrow="Tomus · III"
        title="Gerador de Clips"
        cota="778.5 · IAT · '26"
        lead="Corte automático de vídeos longos em shorts legendados. Cada passo — cortar, legendar, regenerar — é independente e re-executável." />

    {{-- Operações locais (ffmpeg/IA) usam o particle loader — ver os botões abaixo. --}}

    @if (! $fonteAtual)
        {{-- ================= LISTA DE VÍDEOS LONGOS ================= --}}
        <div class="flex items-center gap-4 mb-6">
            <button wire:click="alternarNovaFonte"
                    class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                {{ $mostrarNovaFonte ? '× Cancelar' : '＋ Novo vídeo longo' }}
            </button>
            <span class="font-mono text-xs text-ink-faint">Motor: {{ $motor }}</span>
        </div>

        {{-- Formulário de novo vídeo (revelado pelo botão) --}}
        @if ($mostrarNovaFonte)
            <x-panel eyebrow="Origem" title="Novo vídeo longo" glyph="▶" class="mb-6">
                <form wire:submit="adicionarFonte" x-on:submit="window.CMLoader.busy('A adicionar o vídeo…')" class="space-y-4">
                    {{-- Arrastar e largar (até 2 GB) --}}
                    <div x-data="{ over: false }"
                         x-on:dragover.prevent="over = true"
                         x-on:dragleave.prevent="over = false"
                         x-on:drop.prevent="over = false; if ($event.dataTransfer.files.length) $wire.upload('novoVideo', $event.dataTransfer.files[0])"
                         :class="over ? 'border-teal bg-teal/5' : 'border-ink-soft/25'"
                         class="border border-dashed rounded-sm px-4 py-6 text-center transition">
                        @if ($novoVideo)
                            <p class="font-mono text-sm text-teal">✓ {{ $novoVideo->getClientOriginalName() }}</p>
                            <button type="button" wire:click="$set('novoVideo', null)"
                                    class="mt-1 font-mono text-[0.62rem] text-ink-faint hover:text-bad">remover</button>
                        @else
                            <p class="text-ink-soft text-sm">Arraste um vídeo (mp4 / mov) para aqui, ou
                                <label class="text-teal hover:underline cursor-pointer">escolha um ficheiro<input type="file" wire:model="novoVideo" accept="video/mp4,video/quicktime" class="hidden"></label>
                            </p>
                        @endif
                        <div wire:loading wire:target="novoVideo" class="mt-2 font-mono text-[0.62rem] text-ink-faint">a carregar…</div>
                        @error('novoVideo') <span class="block mt-1 text-bad font-mono text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="eyebrow block mb-1.5">…ou caminho local / URL</label>
                        <input type="text" wire:model="novaFonte" placeholder="/Users/.../video.mp4  ou  https://…"
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
                    <button type="submit" class="bg-teal text-papyrus font-display text-lg px-6 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                        Adicionar vídeo
                    </button>
                </form>
            </x-panel>
        @endif

        {{-- Lista de vídeos processados --}}
        @if ($fontes->isEmpty())
            <x-empty-state glyph="✂" title="Sem vídeos na bancada"
                note="Carregue em «Novo vídeo longo» para começar a gerar shorts." />
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
                        <x-badge :tone="$temTranscricao ? 'good' : 'neutral'">{{ $temTranscricao ? 'transcrita' : 'nova' }}</x-badge>
                        <span class="font-mono text-xs text-ink-faint">{{ $nClips }} {{ $nClips === 1 ? 'clip' : 'clips' }}@if ($nProntos) · <span class="text-good">{{ $nProntos }} prontos</span>@endif</span>
                        <div class="ml-auto flex items-center gap-2">
                            <button wire:click="abrirFonte('{{ $fonte->path }}')"
                                    class="bg-teal/90 text-papyrus font-mono text-xs uppercase tracking-wider px-4 py-1.5 rounded-sm hover:bg-teal-deep transition">Abrir</button>
                            <button wire:click="removerFonte('{{ $fonte->path }}')" wire:confirm="Remover este vídeo e os seus clips?"
                                    class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">✕</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- ================= DETALHE DE UM VÍDEO LONGO ================= --}}
        @php $temTranscricao = filled($fonteAtual->get('transcricao')); @endphp

        <button wire:click="voltarFontes" class="mb-4 font-mono text-xs text-teal hover:text-teal-deep uppercase tracking-wider">← Vídeos</button>

        <x-panel :eyebrow="'Vídeo · '.$fonteAtual->get('lingua', 'pt')" :title="$fonteAtual->title()" glyph="❦" class="mb-6">
            <div class="flex flex-wrap items-center gap-2 -mt-2 mb-4">
                <x-badge :tone="$temTranscricao ? 'good' : 'neutral'">{{ $temTranscricao ? 'transcrita' : $fonteAtual->get('estado', 'nova') }}</x-badge>
                <span class="font-mono text-xs text-ink-faint truncate max-w-md">{{ $fonteAtual->get('fonte') }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <button wire:click="transcrever('{{ $fonteAtual->path }}')" wire:loading.attr="disabled"
                            x-on:click="window.CMLoader.busy('A transcrever o vídeo…')"
                            class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition">
                        {{ $temTranscricao ? 'Re-transcrever' : 'Transcrever' }}
                    </button>
                    @if ($temIA)
                        <button wire:click="sugerirIA('{{ $fonteAtual->path }}')" wire:loading.attr="disabled" @disabled(! $temTranscricao)
                                x-on:click="window.CMLoader.busy('A escolher clips com IA…')"
                                class="bg-gold text-ink font-display text-base px-4 py-1.5 rounded-sm hover:bg-gold/80 transition shadow-engraved disabled:opacity-40 disabled:cursor-not-allowed">
                            ✦ Escolher clips com IA
                        </button>
                    @endif
                    <button wire:click="gerarPublicacao('{{ $fonteAtual->path }}')" @disabled(! $temTranscricao)
                            title="Usa o texto do vídeo como brief no gerador de publicações"
                            class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition disabled:opacity-40 disabled:cursor-not-allowed">
                        ✎ Gerar publicação
                    </button>
                    <button wire:click="removerFonte('{{ $fonteAtual->path }}')" wire:confirm="Remover este vídeo e os seus clips?"
                            class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">Remover</button>
                </div>
            </div>

            @if ($temIA)
                <div class="mb-4 border border-gold/30 bg-gold/5 text-ink-soft rounded-sm px-4 py-2.5 font-mono text-xs">
                    @if ($temTranscricao)
                        ✦ Carregue em <span class="text-gold">Escolher clips com IA</span> — a IA define o título, o início/fim e as tags de cada short automaticamente.
                    @else
                        1) <span class="text-teal">Transcreva</span> o vídeo · 2) depois <span class="text-gold">Escolher clips com IA</span> cria os shorts sozinha.
                    @endif
                </div>
            @endif

            {{-- Adicionar clip manualmente (opcional) --}}
            <details class="border border-ink-soft/15 rounded-sm p-4 bg-surface/30">
                <summary class="eyebrow cursor-pointer select-none">Adicionar clip manualmente (opcional)</summary>
                <div class="grid sm:grid-cols-4 gap-3 mt-3">
                    <input type="text" wire:model="clipTitulo.{{ $fonteAtual->slug() }}" placeholder="Título"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-body text-sm focus:border-teal focus:outline-none">
                    <input type="text" wire:model="clipInicio.{{ $fonteAtual->slug() }}" placeholder="Início (00:00:05 ou 5)"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    <input type="text" wire:model="clipFim.{{ $fonteAtual->slug() }}" placeholder="Fim (00:01:05 ou 65)"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    <input type="text" wire:model="clipTags.{{ $fonteAtual->slug() }}" placeholder="tags, separadas"
                           class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                </div>
                <button wire:click="adicionarClip('{{ $fonteAtual->path }}', '{{ $fonteAtual->slug() }}')"
                        class="mt-3 bg-teal/90 text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition">
                    Criar clip
                </button>
            </details>
        </x-panel>

        {{-- Clips deste vídeo --}}
        @if ($clipsDaFonte->isEmpty())
            <x-empty-state glyph="✂" title="Sem clips"
                note="Transcreva e use «Escolher clips com IA», ou adicione um clip manualmente acima." />
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
                        {{-- Cabeçalho (clicar recolhe/expande) --}}
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
                                        x-on:click="window.CMLoader.busy('A cortar o clip (ffmpeg)…')"
                                        class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal/10 transition">Cortar</button>
                                <button wire:click="regenerar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                        x-on:click="window.CMLoader.busy('A gravar o short com legendas…')"
                                        class="bg-teal text-papyrus font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-teal-deep transition shadow-engraved">Regenerar</button>
                                <button wire:click="removerClip('{{ $clip->path }}')" wire:confirm="Remover este clip?"
                                        class="border border-bad/30 text-bad font-mono text-xs uppercase tracking-wider px-3 py-1.5 rounded-sm hover:bg-bad/10 transition">✕</button>
                            </div>
                        </div>

                        {{-- Editor expandido: vídeo (esquerda) + detalhes (direita) + editor por baixo --}}
                        @if ($aberto)
                            <div class="border-t border-ink-soft/15 p-4 space-y-5">
                                <div class="grid lg:grid-cols-2 gap-6">
                                    {{-- Vídeo com/sem legendas --}}
                                    <div x-data="{ v: '{{ $tipo }}' }">
                                        @if ($temVideo)
                                            <video controls preload="metadata"
                                                   :src="'{{ $urlBase }}?t={{ $vv }}&v=' + v"
                                                   class="w-full max-w-xs mx-auto lg:mx-0 rounded-sm border border-ink-soft/20 bg-black aspect-[9/16] object-contain"></video>
                                            <div class="mt-2 flex items-center justify-center lg:justify-start gap-2 font-mono text-xs">
                                                @if ($ehFinal)
                                                    <button type="button" @click="v='final'" :class="v==='final' ? 'bg-teal text-papyrus' : 'border border-ink-soft/30 text-ink-soft'" class="px-3 py-1 rounded-sm uppercase tracking-wider">Com legendas</button>
                                                @endif
                                                <button type="button" @click="v='raw'" :class="v==='raw' ? 'bg-teal text-papyrus' : 'border border-ink-soft/30 text-ink-soft'" class="px-3 py-1 rounded-sm uppercase tracking-wider">Sem legendas</button>
                                            </div>
                                        @else
                                            <div class="w-full max-w-xs mx-auto lg:mx-0 aspect-[9/16] rounded-sm border border-dashed border-ink-soft/30 bg-surface/30 flex items-center justify-center text-center p-4">
                                                <span class="font-mono text-xs text-ink-faint">Ainda sem vídeo.<br>Carregue em <span class="text-teal">Regenerar</span> para cortar e legendar.</span>
                                            </div>
                                        @endif
                                    </div>
                                    {{-- Título / descrição / tags --}}
                                    <div class="space-y-3">
                                        <div>
                                            <label class="eyebrow block mb-1.5">Título</label>
                                            <input type="text" wire:model="clipTituloEdit"
                                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-display text-lg focus:border-teal focus:outline-none">
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between mb-1.5">
                                                <label class="eyebrow">Descrição</label>
                                                @if ($temIA)
                                                    <button type="button" wire:click="gerarDescricao('{{ $clip->path }}')" wire:loading.attr="disabled"
                                                            x-on:click="window.CMLoader.busy('A gerar descrição com IA…')"
                                                            class="font-mono text-[11px] text-gold hover:text-gold/80 uppercase tracking-wider">✦ Gerar com IA</button>
                                                @endif
                                            </div>
                                            <textarea wire:model="clipDescricao" rows="4" placeholder="Descrição do short (ou gere com IA)…"
                                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body text-sm focus:border-teal focus:outline-none"></textarea>
                                        </div>
                                        <div>
                                            <label class="eyebrow block mb-1.5">Tags (separadas por vírgula)</label>
                                            <input type="text" wire:model="clipTagsEdit" placeholder="ia, tutorial, n8n"
                                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                                        </div>
                                        <p class="font-mono text-[11px] text-ink-faint">Janela no vídeo original: {{ number_format((float) $clip->get('inicio'), 1) }}s → {{ number_format((float) $clip->get('fim'), 1) }}s</p>
                                    </div>
                                </div>

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
                                            <p class="font-mono text-xs text-ink-faint">Sem legendas. Adicione linhas ou transcreva o vídeo.</p>
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
                                            <input type="text" wire:model="estilo.line-color" placeholder="#5A7BFF" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="font-mono text-xs text-ink-faint block mb-1">Contorno (px)</label>
                                            <input type="number" wire:model="estilo.outline-width" class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                        </div>
                                    </div>
                                </div>

                                {{-- Música de fundo --}}
                                <div>
                                    <div class="eyebrow mb-2">Música de fundo</div>
                                    <div class="grid sm:grid-cols-4 gap-3">
                                        <select wire:model="musica"
                                                class="sm:col-span-3 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                            <option value="">Aleatória (da biblioteca)</option>
                                            <option value="nenhuma">Nenhuma (só áudio original)</option>
                                            @foreach ($musicas as $m)
                                                <option value="{{ $m['name'] }}">♪ {{ $m['name'] }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.05" min="0" max="1" wire:model="musicaVolume" placeholder="Volume 0-1"
                                               class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                                    </div>
                                    @if (empty($musicas))
                                        <p class="mt-1 font-mono text-[11px] text-ink-faint">Biblioteca vazia — carregue faixas em <a href="{{ route('ativos') }}" class="text-teal underline">Ativos</a>.</p>
                                    @endif
                                </div>

                                {{-- Acções do editor --}}
                                <div class="flex items-center gap-3 pt-1">
                                    <button wire:click="guardarLegendas" class="border border-teal/40 text-teal font-mono text-xs uppercase tracking-wider px-4 py-2 rounded-sm hover:bg-teal/10 transition">Guardar</button>
                                    <button wire:click="regenerar('{{ $clip->path }}')" wire:loading.attr="disabled"
                                            x-on:click="window.CMLoader.busy('A gravar o short com legendas…')"
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
    @endif
</div>
