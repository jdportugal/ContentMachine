<div @if (($view === 'dashboard' && $this->hasActive) || ($view === 'backgrounds' && ($this->backgroundsBusy || $this->backgroundReelBusy)) || ($view === 'reviewImages' && $this->siteCollecting)) wire:poll.3s @endif>
    @php
        $estados = [
            'draft'        => ['label' => 'Rascunho',         'tone' => 'neutral', 'glyph' => '·'],
            'importing'    => ['label' => 'A importar o anúncio', 'tone' => 'gold', 'glyph' => '⌂'],
            'choosing_voice' => ['label' => 'Escolha a voz', 'tone' => 'gold', 'glyph' => '♪'],
            'analyzing'    => ['label' => 'A ler o vídeo da visita', 'tone' => 'gold', 'glyph' => '⌂'],
            'choosing_sections' => ['label' => 'Marque os excertos', 'tone' => 'gold', 'glyph' => '◆'],
            'reviewing_rooms' => ['label' => 'Aprove as divisões', 'tone' => 'gold', 'glyph' => '◆'],
            'animating'    => ['label' => 'A animar as divisões', 'tone' => 'gold', 'glyph' => '❧'],
            'transcribing' => ['label' => 'A transcrever',  'tone' => 'gold',    'glyph' => '❧'],
            'planning'     => ['label' => 'A planear',      'tone' => 'gold',    'glyph' => '❧'],
            'collecting'   => ['label' => 'As suas imagens',   'tone' => 'gold',    'glyph' => '◆'],
            'improving'    => ['label' => 'A melhorar com IA', 'tone' => 'gold', 'glyph' => '✨'],
            'rendering'    => ['label' => 'A renderizar',     'tone' => 'gold',    'glyph' => '❧'],
            'done'         => ['label' => 'Pronto',         'tone' => 'good',    'glyph' => '✔'],
            'failed'       => ['label' => 'Falhou',        'tone' => 'bad',     'glyph' => '✕'],
        ];
        $tipos = [
            'animation' => ['label' => 'Animação',          'glyph' => '❈'],
            'overlay'   => ['label' => 'Vídeo + animações', 'glyph' => '❖'],
        ];
    @endphp

    <x-page-header
        eyebrow="Tomus · IV"
        title="Imóveis"
        cota="741.5 · ACM · '26"
        lead="Vídeos animados: da narração ao vídeo final, com tempos marcados e planeamento assistido." />

    {{-- ============================================================ --}}
    {{-- DASHBOARD                                                    --}}
    {{-- ============================================================ --}}
    @if ($view === 'dashboard')
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="eyebrow">Vídeos gerados · {{ $this->projects->count() }}</div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a href="{{ route('clips-animados.sfx') }}"
                        class="font-display text-lg px-5 py-2 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-ink-soft/50 transition">
                    ✷ SFX
                </a>
                <a href="{{ route('clips-animados.backgrounds') }}" wire:navigate
                        class="font-display text-lg px-5 py-2 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-ink-soft/50 transition">
                    ◆ Fundos
                </a>
                <button type="button" wire:click="novoClip"
                        class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                    ✦ Novo vídeo
                </button>
            </div>
        </div>

        {{-- One-step listing import: URL in, narrated clip out. --}}
        <form wire:submit="importarAnuncio" class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-4 mb-6 shadow-engraved">
            <label for="listingUrl" class="eyebrow block mb-2">⌂ A partir de um anúncio</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <input id="listingUrl" type="url" wire:model="listingUrl" placeholder="https://www.remax.pt/pt/imoveis/…"
                       class="flex-1 min-w-0 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60">
                <button type="submit" wire:loading.attr="disabled" wire:target="importarAnuncio, listingAgentPhoto, listingVideo, listingAfterIntro"
                        class="font-display text-lg px-5 py-2 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-50">
                    Fazer o vídeo
                </button>
            </div>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <span>Foto do consultor (opcional — substitui a do anúncio):</span>
                <input type="file" accept="image/*" wire:model="listingAgentPhoto" class="text-xs">
                <span wire:loading wire:target="listingAgentPhoto" class="text-ink-faint">a carregar…</span>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <span>🏠 <strong>Vídeo do imóvel</strong> (opcional) — a visita à casa, com alguns minutos; é cortado para as imagens do vídeo:</span>
                <input type="file" accept="video/*" wire:model="listingVideo" class="text-xs">
                <span wire:loading wire:target="listingVideo" class="text-ink-faint">a carregar…</span>
            </label>
            <label class="mt-1 ml-4 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <input type="checkbox" wire:model="listingMixPhotos" class="accent-teal">
                <span>Misturar o vídeo com as fotos do anúncio (desligado: o vídeo é só filmagem)</span>
            </label>
            <label class="mt-1 ml-4 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <input type="checkbox" wire:model="listingBeatOpening" class="accent-teal">
                <span>🎵 <strong>Abertura ao ritmo</strong>: os melhores planos primeiro, só com música, a mudar na batida até ao drop — depois o vídeo</span>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft">
                <span class="shrink-0">Música:</span>
                <select wire:model="listingMusic" class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60">
                    <option value="">A do projeto</option>
                    @foreach ($musicas as $m)
                        <option value="{{ $m['name'] }}">{{ $m['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                <span>🎬 <strong>Vídeo após a intro</strong> (opcional) — o seu vídeo de marca de poucos segundos, mostrado logo a seguir à intro:</span>
                <input type="file" accept="video/*" wire:model="listingAfterIntro" class="text-xs">
                <span wire:loading wire:target="listingAfterIntro" class="text-ink-faint">a carregar…</span>
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-ink-soft">
                <span class="shrink-0">Frase após a intro (dita logo a seguir à intro, sobre o vídeo após a intro):</span>
                <input type="text" wire:model="listingAfterIntroLine" placeholder="Se queres vender ou comprar casa, fala com…"
                       class="flex-1 min-w-0 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60">
            </label>
            @error('listingAfterIntroLine') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            <label class="mt-2 block text-sm text-ink-soft">
                <span>Notas para o guião (opcional — sobrepõem-se ao anúncio: um preço novo, uma área errada, o que destacar ou deixar de fora):</span>
                <textarea wire:model="listingNotes" rows="2" placeholder="ex.: O preço baixou para 265.000 €. Não falar do sótão. Destacar a vista mar."
                          class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60"></textarea>
            </label>
            @error('listingNotes') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingUrl') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingAfterIntro') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingVideo') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            @error('listingAgentPhoto') <p class="mt-2 text-sm text-bad">{{ $message }}</p> @enderror
            <p class="mt-2 font-mono text-[0.58rem] text-ink-faint">Lê o anúncio, escreve e grava a narração em pt-PT, recolhe as fotos (a primeira como principal), a foto do consultor e o logótipo da agência, e planeia só com essas imagens, sem legendas e com música animada.</p>
        </form>

        @if ($this->projects->isEmpty())
            <x-empty-state glyph="❈" title="Sem vídeos" note="Ainda não gerou nenhum vídeo. Comece com «Novo vídeo»." />
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
                                                <button type="button" wire:click="rerenderFinal('{{ $project->id }}')" title="Renderiza de novo só o cartão final (ex.: depois de mudar o cartão do consultor) — o resto do vídeo fica igual"
                                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:text-teal hover:border-teal/40 transition">↻ Renderizar de novo o final</button>
                                                <button type="button" wire:click="abrirMelhoriaClip('{{ $project->id }}')"
                                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:text-gold hover:border-gold/40 transition">✨ Melhorar com IA</button>
                                                @if ($improveCardId === $project->id)
                                                    <div class="mt-2 space-y-1">
                                                        <textarea wire:model="improveCardPrompt" rows="2" maxlength="1000" placeholder="ex.: mais curto; falar das praias fluviais perto; mostrar mais da casa e menos do terreno; cortes mais rápidos"
                                                                  class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-gold/50"></textarea>
                                                        @error('improveCardPrompt') <p class="text-bad text-xs">{{ $message }}</p> @enderror
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <button type="button" wire:click="melhorarClip" wire:loading.attr="disabled" wire:target="melhorarClip"
                                                                    class="font-mono text-[0.65rem] px-3 py-1.5 rounded-sm border border-gold/40 text-gold hover:bg-gold/10 transition">Melhorar</button>
                                                            <span class="font-mono text-[0.55rem] text-ink-faint">Reescreve o guião como pedir (e grava-o de novo), volta a escolher fotos e filmagens, tempos e efeitos, e atualiza a descrição — uns minutos. Não é gerada nenhuma imagem.</span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                        @if (! empty($project->meta['property']['proxy']))
                                            @if (empty($project->meta['property']['photos']))
                                            <button type="button" wire:click="reselectSections('{{ $project->id }}')"
                                                    class="self-start font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition">↺ Mudar os excertos</button>
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
                                                <p class="font-mono text-[0.62rem] text-ink-soft mb-1">Narração {{ $hasVoice ? '· este vídeo já tem' : '· ainda sem narração' }}</p>
                                                <input type="url" x-model="url" placeholder="Link do anúncio (RE/MAX) — os detalhes são lidos daqui" aria-label="Link do anúncio"
                                                       class="w-full mb-2 bg-papyrus/60 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-xs focus:outline-none focus:border-teal/50">
                                                <textarea x-model="info" rows="6" maxlength="4000" aria-label="Detalhes da casa para a narração"
                                                          placeholder="…e/ou os detalhes, um por linha (prevalecem sobre o anúncio), ex.:&#10;Título: Moradia T3 em Braga&#10;Preço: 350 000 €&#10;Localização: Braga, perto do centro&#10;Área: 180 m²&#10;Quartos: 3&#10;…e o que mais valer a pena dizer"
                                                          class="w-full bg-papyrus/60 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-xs font-mono focus:outline-none focus:border-teal/50"></textarea>
                                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                                    <button type="button" :disabled="sending || (!info.trim() && !url.trim())" @click="sending = true; $wire.narrateProperty('{{ $project->id }}', info, url).finally(() => sending = false)"
                                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40"
                                                            x-text="sending ? 'A enviar…' : '{{ $hasVoice ? '↻ Escrever uma nova narração' : '🎙 Escrever e gravar a narração' }}'"></button>
                                                    @if ($hasVoice)
                                                        <button type="button" @click="$wire.removePropertyVoice('{{ $project->id }}')" class="font-mono text-[0.62rem] text-bad hover:underline">remover a narração</button>
                                                    @endif
                                                    <span class="font-mono text-[0.58rem] text-ink-faint">À medida do vídeo; escolha a versão da narração e o vídeo é cortado de novo (as animações são reaproveitadas).</span>
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
                                        <p class="font-mono text-[0.6rem] text-ink-faint mb-2">Ouça e escolha a versão com sotaque de Portugal.</p>
                                        @foreach ($project->meta['voice_takes'] ?? [] as $n => $take)
                                            <div class="flex items-center gap-2 mb-2">
                                                <span class="font-mono text-[0.62rem] text-ink-soft w-12">Take {{ $n + 1 }}</span>
                                                <audio controls preload="none" class="flex-1 min-w-0 h-9" src="{{ route('clips-animados.take', ['id' => $project->id, 'n' => $n, 'v' => md5($take)]) }}"></audio>
                                                <button type="button" wire:click="escolherTake('{{ $project->id }}', {{ $n }})"
                                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Usar esta</button>
                                            </div>
                                        @endforeach
                                        @if (!empty($project->meta['listing']['voiceover']))
                                            <details class="mt-3 border border-ink-soft/15 rounded-sm px-3 py-2 bg-papyrus/40">
                                                <summary class="cursor-pointer font-mono text-[0.62rem] text-ink-soft">✎ Guião — para gravar ou gerar a sua própria narração</summary>
                                                <p class="mt-2 text-sm text-ink-soft whitespace-pre-line">{{ $project->meta['listing']['voiceover'] }}</p>
                                                <div class="mt-2"><x-copy-btn :text="$project->meta['listing']['voiceover']" label="⧉ copiar guião" /></div>
                                            </details>
                                        @endif
                                        <label class="mt-3 flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft">
                                            <span class="w-12">Nenhuma?</span>
                                            <span class="px-3 py-1.5 rounded-sm border border-ink-soft/30 hover:bg-ink/5 transition cursor-pointer">⇪ Carregar a minha narração</span>
                                            <input type="file" accept="audio/*" class="hidden" wire:model="takeUploads.{{ $project->id }}">
                                            <span wire:loading wire:target="takeUploads.{{ $project->id }}" class="text-ink-faint">a carregar…</span>
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
                                                ! empty($a['video']) && empty($a['clip_problems']) && ! empty($a['enabled']) => 'ok:✓ animado — fica como está',
                                                ! empty($a['clip_problems']) => 'redo:o Kling deformou este — normalmente por uma rotação da câmara: marque antes um avanço a direito',
                                                ! empty($a['problems']) => 'redo:a imagem modernizada reenquadrou a divisão — termine num plano mais aberto',
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
                                            <span>Arraste na faixa para marcar até 10 excertos (1–10 s cada) · arraste as pontas para ajustar</span>
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
                                                    <button type="button" class="px-2 py-0.5 rounded-sm border border-ink-soft/30 hover:bg-ink/5" @click="preview(s)">▶ pré-visualizar</button>
                                                    <span x-show="s.state" class="basis-full pl-7 text-[0.6rem]" :class="s.state.startsWith('ok:') ? 'text-teal' : 'text-bad'" x-text="s.state.slice(s.state.indexOf(':') + 1)"></span>
                                                    <input type="text" x-model="s.note" maxlength="300" placeholder="opcional: o que gostaria (ex.: cozinha escandinava)" aria-label="Pedido para este excerto"
                                                           class="flex-1 min-w-[12rem] bg-papyrus/60 border border-ink-soft/20 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-teal/50">
                                                    <button type="button" class="text-bad hover:underline" @click="sections.splice(i, 1)" :aria-label="'Remover excerto ' + (i + 1)">remover</button>
                                                </li>
                                            </template>
                                        </ol>
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <button type="button" :disabled="!sections.length || sending" @click="sending = true; $wire.stageSections('{{ $project->id }}', sections).finally(() => sending = false)"
                                                    class="font-display text-lg px-5 py-2 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-40"
                                                    x-text="sending ? 'A enviar…' : 'Modernizar estes excertos'">Modernizar estes excertos</button>
                                            <span class="font-mono text-[0.6rem] text-ink-faint">A última imagem de cada excerto torna-se uma imagem modernizada que aprova antes de animar.</span>
                                            <label class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft">Formato final
                                                <select @change="$wire.setPropertyAspect('{{ $project->id }}', $event.target.value)"
                                                        class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-teal/60">
                                                    @foreach (\App\Services\RealEstate\PropertyVideoEditor::ASPECTS as $value => $label)
                                                        <option value="{{ $value }}" @selected(($project->meta['property']['aspect'] ?? 'source') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label x-data="{ sent: false }" class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft cursor-pointer hover:text-ink">
                                                <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="sent">✓ Foto do consultor — substituir</span>
                                                    <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="!sent">{{ collect($project->images ?? [])->contains(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato')) ? '✓ Foto do consultor — substituir' : '+ Foto do consultor' }}</span>
                                                <input type="file" accept="image/*" class="hidden" @change="sent = true" wire:model="agentPhotos.{{ $project->id }}">
                                                <span wire:loading wire:target="agentPhotos.{{ $project->id }}" class="text-ink-faint">a carregar…</span>
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
                                        <p class="font-mono text-[0.6rem] text-ink-faint">Imagem real → imagem modernizada. Aprove, peça outra versão (pode editar o prompt) ou salte uma divisão — nada é pago até animar.</p>
                                        @foreach ($rooms as $i => $room)
                                            @continue(empty($room['anim']))
                                            @php $anim = $room['anim']; $on = ! empty($anim['enabled']); @endphp
                                            <div wire:key="room-{{ $project->id }}-{{ $i }}" class="border border-ink-soft/15 rounded-sm p-3 {{ $on ? '' : 'opacity-50' }}">
                                                <div class="flex items-center justify-between gap-2 mb-2">
                                                    <span class="font-display text-lg text-ink">{{ $room['room'] }}</span>
                                                    <span class="font-mono text-[0.6rem] text-ink-soft">{{ ['unfurnish' => 'sem mobília', 'furnish' => 'com mobília'][$anim['mode']] ?? 'modernizar' }} · {{ $anim['start'] }}–{{ $anim['end'] }} s · {{ $anim['motion'] }}</span>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <img src="{{ route('clips-animados.room', ['id' => $project->id, 'i' => $i, 'which' => 'start', 'v' => md5($anim['start_frame'] ?? '')]) }}" alt="Imagem inicial real de {{ $room['room'] }}" class="w-full rounded-sm border border-ink-soft/15 object-contain bg-black/5 max-h-72">
                                                    @if (! empty($anim['staging']))
                                                        <div class="flex items-center justify-center rounded-sm border border-dashed border-ink-soft/25 font-mono text-[0.62rem] text-ink-faint animate-pulse">a preparar…</div>
                                                    @elseif (! empty($anim['staged']))
                                                        <img src="{{ route('clips-animados.room', ['id' => $project->id, 'i' => $i, 'which' => 'staged', 'v' => md5($anim['staged'])]) }}" alt="Imagem modernizada de {{ $room['room'] }}" class="w-full rounded-sm border border-ink-soft/15 object-contain bg-black/5 max-h-72">
                                                    @else
                                                        <div class="flex items-center justify-center rounded-sm border border-dashed border-bad/40 p-2 text-center font-mono text-[0.62rem] text-bad">{{ $anim['error'] ?? 'Ainda sem imagem' }}</div>
                                                    @endif
                                                </div>
                                                @if (count($anim['candidates'] ?? []) > 1 && empty($anim['staging']))
                                                    {{-- Every image the takes made: click one to use it. --}}
                                                    <p class="mt-2 font-mono text-[0.6rem] text-ink-faint">Todas as {{ count($anim['candidates']) }} imagens, a mais recente primeiro — clique numa para a usar:</p>
                                                    <div class="mt-1 grid grid-cols-3 sm:grid-cols-4 gap-1.5">
                                                        @foreach ($anim['candidates'] as $n => $cand)
                                                            @php $chosen = ($anim['staged'] ?? null) === $cand['path']; @endphp
                                                            <button type="button" wire:click="chooseStill('{{ $project->id }}', {{ $i }}, {{ $n }})" @disabled($chosen)
                                                                    title="{{ $cand['problems'] ? implode(' · ', $cand['problems']) : 'Aberturas todas mantidas' }}"
                                                                    class="relative rounded-sm overflow-hidden border-2 transition {{ $chosen ? 'border-teal' : 'border-transparent hover:border-ink-soft/40' }}">
                                                                <img src="{{ route('clips-animados.room', ['id' => $project->id, 'i' => $i, 'which' => 'c'.$n, 'v' => md5($cand['path'])]) }}" alt="Opção {{ $n + 1 }} para {{ $room['room'] }}" loading="lazy" class="w-full aspect-video object-cover bg-black/5">
                                                                <span class="absolute bottom-0 inset-x-0 px-1 py-0.5 font-mono text-[0.55rem] text-left {{ $chosen ? 'bg-teal text-papyrus' : 'bg-ink/60 text-papyrus' }}">
                                                                    {{ $chosen ? '✓ em uso' : ($cand['problems'] ? count($cand['problems']).' problema'.(count($cand['problems']) === 1 ? '' : 's') : 'sem problemas') }}
                                                                </span>
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if (isset($anim['alignment']) && empty($anim['staging']))
                                                    <p class="mt-1 font-mono text-[0.6rem] {{ $anim['alignment'] < \App\Services\RealEstate\RoomStager::DRIFT ? 'text-bad' : 'text-ink-faint' }}">
                                                        Alinhamento com a imagem real: {{ round($anim['alignment'] * 100) }}%{{ $anim['alignment'] < \App\Services\RealEstate\RoomStager::DRIFT ? ' — a câmara mexeu-se; o Kling vai deformar a divisão. Peça outra ou salte.' : '' }}
                                                    </p>
                                                @endif
                                                @if (! empty($anim['problems']) && empty($anim['staging']))
                                                    <ul class="mt-1 font-mono text-[0.6rem] text-bad list-disc pl-4">
                                                        @foreach ($anim['problems'] as $problem)
                                                            <li>{{ $problem }}</li>
                                                        @endforeach
                                                    </ul>
                                                @elseif (isset($anim['problems']) && empty($anim['staging']))
                                                    <p class="mt-1 font-mono text-[0.6rem] text-teal">Portas, janelas e aberturas verificadas: todas mantidas.</p>
                                                @endif
                                                <div wire:ignore wire:key="prompt-{{ $project->id }}-{{ $i }}-{{ md5($anim['prompt']) }}" class="mt-2">
                                                    <textarea id="rp-{{ $project->id }}-{{ $i }}" rows="2" aria-label="Prompt da imagem de {{ $room['room'] }}"
                                                              class="w-full bg-papyrus/60 border border-ink-soft/20 rounded-sm px-3 py-2 text-sm text-ink focus:outline-none focus:border-teal/50">{{ $anim['prompt'] }}</textarea>
                                                </div>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <button type="button" @disabled(! empty($anim['staging']))
                                                            @click="$wire.restageRoom('{{ $project->id }}', {{ $i }}, document.getElementById('rp-{{ $project->id }}-{{ $i }}').value)"
                                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40">↻ Outra versão</button>
                                                    <button type="button" wire:click="toggleRoom('{{ $project->id }}', {{ $i }})"
                                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition">{{ $on ? 'Saltar — manter a filmagem' : 'Animar esta divisão' }}</button>
                                                    @if (! empty($project->meta['property']['photos']))
                                                        <button type="button" wire:click="flipRoomMotion('{{ $project->id }}', {{ $i }})" title="Inverter o sentido da câmara (volta a fazer a animação desta divisão)"
                                                                class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition">Câmara: {{ ($anim['motion'] ?? 'forward') === 'backward' ? '← recua para fora' : '→ avança para a divisão' }} · inverter</button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                        @if ($plain !== '')
                                            <p class="font-mono text-[0.6rem] text-ink-faint">Mantidas como filmagem: {{ $plain }}</p>
                                        @endif
                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="button" wire:click="animateProperty('{{ $project->id }}')" @disabled($staging)
                                                    class="font-display text-lg px-5 py-2 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-50">▶ Animar e montar o vídeo</button>
                                            @if (empty($project->meta['property']['photos']))
                                            <button type="button" wire:click="reselectSections('{{ $project->id }}')" @disabled($staging)
                                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/30 text-ink-soft hover:bg-ink/5 transition disabled:opacity-40">↺ Mudar os excertos</button>
                                            @endif
                                            <label class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft">Formato final
                                                <select wire:change="setPropertyAspect('{{ $project->id }}', $event.target.value)"
                                                        class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink text-xs focus:outline-none focus:border-teal/60">
                                                    @foreach (\App\Services\RealEstate\PropertyVideoEditor::ASPECTS as $value => $label)
                                                        <option value="{{ $value }}" @selected(($project->meta['property']['aspect'] ?? 'source') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label x-data="{ sent: false }" class="flex items-center gap-2 font-mono text-[0.62rem] text-ink-soft cursor-pointer hover:text-ink">
                                                <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="sent">✓ Foto do consultor — substituir</span>
                                                    <span class="px-2 py-1 rounded-sm border border-ink-soft/25" x-show="!sent">{{ collect($project->images ?? [])->contains(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato')) ? '✓ Foto do consultor — substituir' : '+ Foto do consultor' }}</span>
                                                <input type="file" accept="image/*" class="hidden" @change="sent = true" wire:model="agentPhotos.{{ $project->id }}">
                                                <span wire:loading wire:target="agentPhotos.{{ $project->id }}" class="text-ink-faint">a carregar…</span>
                                            </label>
                                            <span class="font-mono text-[0.6rem] text-ink-faint">{{ $paid }} {{ $paid === 1 ? 'divisão' : 'divisões' }} para animar (Kling 3.0, 4–7 s cada) no kie.ai</span>
                                        </div>
                                    </div>
                                @elseif ($project->status === 'collecting')
                                    <div class="w-full self-start text-center">
                                        <p class="font-mono text-[0.6rem] text-ink-faint mb-2">O plano está pronto — carregue as suas imagens ou deixe que sejam geradas.</p>
                                        <button type="button" wire:click="revisarImagens('{{ $project->id }}')"
                                                class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">◆ Recolher imagens</button>
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
                                              title="Custo registado nos fornecedores para este vídeo (imagens, tokens de LLM, narração)">≈ ${{ number_format($custo, 2) }}</span>
                                    @endif
                                </div>

                                <div class="font-display text-xl text-ink mt-2">{{ $project->title ?? 'Sem título' }}</div>
                                <div class="font-mono text-[0.58rem] text-ink-faint">{{ $project->created_at ? \Illuminate\Support\Carbon::parse($project->created_at)->diffForHumans() : '' }}</div>

                                @if (!empty($sug['description']))
                                    <p class="mt-3 text-ink-soft text-sm leading-relaxed whitespace-pre-line">{{ $sug['description'] }}</p>
                                    {{-- Built here: a double quote inside the :text attribute ended it early. --}}
                                    @php $legenda = trim($sug['description'].PHP_EOL.PHP_EOL.collect($sug['tags'] ?? [])->map(fn ($t) => '#'.preg_replace('/\s+/u', '', ltrim($t, '#')))->implode(' ')); @endphp
                                    <x-copy-btn class="mt-2 font-mono text-[0.62rem] px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition"
                                                :text="$legenda" label="⧉ copiar legenda + hashtags" />
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
                                            ⌂ Anúncio · {{ $anuncio['agent'] ?? '' }} · {{ $anuncio['phone'] ?? '' }}
                                            @if (!empty($anuncio['flags'])) <span class="text-warn">· {{ count($anuncio['flags']) }} a verificar</span> @endif
                                        </summary>
                                        @if (!empty($anuncio['flags']))
                                            <ul class="mt-2 list-disc pl-5 text-sm text-warn">
                                                @foreach ($anuncio['flags'] as $flag) <li>{{ $flag }}</li> @endforeach
                                            </ul>
                                        @endif
                                        @if (!empty($anuncio['message']))
                                            <p class="mt-2 text-sm text-ink-soft whitespace-pre-line">{{ $anuncio['message'] }}</p>
                                            <div class="mt-2 flex gap-2">
                                                <x-copy-btn :text="$anuncio['message']" label="⧉ copiar mensagem" />
                                                @if (!empty($anuncio['phone']))
                                                    <x-copy-btn :text="$anuncio['phone']" label="⧉ copiar número" />
                                                @endif
                                                <a href="{{ $anuncio['url'] }}" target="_blank" rel="noopener" class="font-mono text-[0.7rem] px-3 py-1.5 text-teal hover:underline">↗ anúncio</a>
                                            </div>
                                        @endif
                                    </details>
                                @endif

                                {{-- actions --}}
                                <div class="mt-auto pt-4 flex flex-wrap items-center gap-x-4 gap-y-2 font-mono text-[0.62rem]">
                                    @if (!empty($project->plan['scenes']) && $project->status !== 'collecting')
                                        <button type="button" wire:click="editarClip('{{ $project->id }}')" class="text-teal hover:underline">✎ editar vídeo</button>
                                    @endif
                                    @if (!empty($project->transcript['text']))
                                        <button type="button" wire:click="editarTranscricao('{{ $project->id }}')" class="text-teal hover:underline">✎ editar transcrição · gerar de novo</button>
                                    @endif
                                    @if ($project->status === 'done' && $project->output_path)
                                        <a href="{{ route('clips-animados.media', ['id' => $project->id, 'download' => 1]) }}" class="text-teal hover:underline">↓ descarregar</a>
                                    @endif
                                    <button type="button" wire:click="apagar('{{ $project->id }}')"
                                            wire:confirm="Apagar este vídeo e os seus ficheiros? Esta ação é definitiva."
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
    {{-- COLLECT IMAGES (upload your own, or let it be generated)     --}}
    {{-- ============================================================ --}}
    @if ($view === 'reviewImages')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← voltar à lista</button>

        <x-panel eyebrow="Passo · III — Imagens" title="As suas imagens ou geradas" glyph="◆">
            <p class="text-ink-soft text-sm mb-5">O plano sugere estas imagens e o estúdio já foi procurar as reais — uma foto, um logótipo, a própria página. Para cada uma pode manter o que foi encontrado, <span class="text-teal">carregar</span> o seu ficheiro (ou escolher um da biblioteca), procurar <span class="text-teal">de novo</span>, usar uma <span class="text-teal">imagem de IA</span> ou escolher <span class="text-teal">sem imagem</span> — essa cena passa a ser um visual de texto (cartão / lista / diagrama) como as cenas sem imagem. As miniaturas verdes vieram da sua biblioteca de recursos ou da web.</p>

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
                                 title="clique para ampliar"
                                 class="w-12 h-12 object-cover rounded-sm border cursor-zoom-in {{ !empty($r['fromLibrary']) ? 'border-good/50' : 'border-ink-soft/20' }} bg-vellum/40" alt="" />
                        @else
                            <div class="w-12 h-12 rounded-sm border border-dashed border-ink-soft/30 flex items-center justify-center text-ink-faint text-lg bg-vellum/20">{{ $r['mode'] === 'text' ? '✎' : '✦' }}</div>
                        @endif

                        <span class="flex-1 min-w-0 text-sm text-ink">
                            {{ $r['label'] ?? (trim(\Illuminate\Support\Str::after($r['prompt'], 'Illustrate this moment:')) ?: $r['prompt']) }}
                            @if (!empty($r['fromLibrary']))
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· da biblioteca</span>
                            @elseif ($r['mode'] === 'upload' && !empty($r['fetched']))
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· encontrada na web</span>
                                @if (!empty($r['source']))
                                    <a href="{{ $r['source'] }}" target="_blank" rel="noopener noreferrer"
                                       class="ml-1 font-mono text-[0.55rem] text-ink-faint hover:text-teal underline"
                                       title="{{ $r['source'] }}">fonte ↗</a>
                                @endif
                            @elseif ($r['mode'] === 'upload' && !empty($r['gerado']))
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· imagem de IA — pronta</span>
                            @elseif ($r['mode'] === 'upload')
                                <span class="ml-1 font-mono text-[0.55rem] text-good">· a sua imagem</span>
                            @elseif ($r['mode'] === 'text')
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· sem imagem — passa a cena de texto</span>
                            @elseif (!empty($r['animado']) && !empty($r['collecting']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· a gerar a imagem…</span>
                            @elseif (!empty($r['animado']) && empty($r['path']))
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· intro animada — gere primeiro a imagem e depois decida</span>
                            @elseif (!empty($r['animado']))
                                <span class="ml-1 font-mono text-[0.55rem] {{ ($r['videoMode'] ?? 'video') === 'video' ? 'text-teal' : 'text-ink-faint' }}">
                                    · intro animada — {{ ($r['videoMode'] ?? 'video') === 'video' ? 'o vídeo é gerado ao aprovar' : 'fica como imagem fixa' }}</span>
                            @elseif (!empty($r['collecting']) && !empty($r['site']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· a filmar {{ $r['site'] }}…</span>
                            @elseif (!empty($r['searching']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· a procurar uma imagem real na web…</span>
                            @elseif (!empty($r['collecting']))
                                <span class="ml-1 font-mono text-[0.55rem] text-teal">· a gerar a imagem…</span>
                            @elseif (!empty($r['site']))
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· filma {{ $r['site'] }} ao aprovar</span>
                            @else
                                <span class="ml-1 font-mono text-[0.55rem] text-ink-faint">· vai ser gerada</span>
                            @endif
                        </span>

                        {{-- What this suggestion becomes: your file · an AI image · no image at all. --}}
                        <div class="flex items-center gap-1 font-mono text-[0.6rem] whitespace-nowrap">
                            @php $on = 'border-teal/60 text-teal bg-teal/10'; $off = 'border-ink-soft/20 text-ink-faint hover:text-ink'; @endphp
                            <label class="px-2 py-1 rounded-sm border cursor-pointer {{ $r['mode'] === 'upload' ? $on : $off }}">
                                <span wire:loading.remove wire:target="reviewUploads.{{ $r['key'] }}">↑ carregar</span>
                                <span wire:loading wire:target="reviewUploads.{{ $r['key'] }}">a carregar…</span>
                                <input type="file" class="hidden" accept="image/*,video/mp4,video/quicktime,video/webm" wire:model="reviewUploads.{{ $r['key'] }}" />
                            </label>
                            @if (!empty($r['site']))
                                <button type="button" wire:click="coletarSite('{{ $r['key'] }}')" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'generate' && !$r['uploadedId'] ? $on : $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="filmar {{ $r['site'] }} agora, para ver antes de aprovar">
                                    {{ !empty($r['collecting']) ? '⏺ a filmar…' : '⏺ recolher agora' }}
                                </button>
                            @elseif (!empty($r['animado']) && empty($r['path']))
                                <button type="button" wire:click="recriarImagemIntro" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $on }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="gerar a imagem inicial — a escolha do vídeo fica disponível quando a vir">
                                    {{ !empty($r['collecting']) ? '✦ a gerar…' : '✦ gerar imagem' }}</button>
                            @elseif (!empty($r['animado']))
                                <button type="button" wire:click="modoVideoIntro('video')"
                                        class="px-2 py-1 rounded-sm border {{ ($r['videoMode'] ?? 'video') === 'video' && $r['mode'] === 'generate' ? $on : $off }}"
                                        title="animar esta imagem para o vídeo de intro (e guardá-la como recurso reutilizável)">▶ vídeo</button>
                                <button type="button" wire:click="modoVideoIntro('image')"
                                        class="px-2 py-1 rounded-sm border {{ ($r['videoMode'] ?? 'video') === 'image' && $r['mode'] === 'generate' ? $on : $off }}"
                                        title="manter como imagem fixa — não é gerado vídeo">▣ só imagem</button>
                                <button type="button" wire:click="recriarImagemIntro" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="gerar outra versão desta imagem">{{ !empty($r['collecting']) ? '↻ a gerar…' : '↻ nova imagem' }}</button>
                            @else
                                <button type="button" wire:click="procurarImagem('{{ $r['key'] }}')" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ !empty($r['fetched']) ? $on : $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="procurar na web a imagem REAL de que esta cena precisa — uma foto, um logótipo, uma página">
                                    {{ !empty($r['searching']) ? '⌕ a procurar…' : (empty($r['path']) ? '⌕ procurar imagem' : '⌕ procurar outra') }}</button>
                                <button type="button" wire:click="gerarImagemAgora('{{ $r['key'] }}')" @if (!empty($r['collecting'])) disabled @endif
                                        class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'generate' ? $on : $off }} @if (!empty($r['collecting'])) opacity-60 cursor-wait @endif"
                                        title="gerar esta imagem agora — veja-a aqui antes de aprovar">
                                    {{ !empty($r['collecting']) && empty($r['searching']) ? '✦ a gerar…' : (empty($r['path']) ? '✦ gerar agora' : '↻ nova imagem') }}</button>
                            @endif
                            @if (empty($r['site']))
                                <button type="button" wire:click="editarPrompt('{{ $r['key'] }}')"
                                        class="px-2 py-1 rounded-sm border {{ $promptKey === $r['key'] ? $on : $off }}"
                                        title="ver e editar o prompt de geração da imagem">✎ prompt</button>
                            @endif
                            <button type="button" wire:click="modoImagem('{{ $r['key'] }}', 'text')"
                                    class="px-2 py-1 rounded-sm border {{ $r['mode'] === 'text' ? $on : $off }}"
                                    title="sem imagem — esta cena passa a cartão/lista/diagrama como as outras">✎ sem imagem</button>
                            @if (!empty($this->libraryImages))
                                <button type="button" wire:click="abrirBibliotecaImagens('{{ $r['key'] }}')"
                                        class="px-2 py-1 text-teal hover:opacity-70">▦ biblioteca</button>
                            @endif
                        </div>
                        @error('reviewUploads.'.$r['key']) <p class="w-full text-bad text-xs">{{ $message }}</p> @enderror
                        @if (!empty($r['siteError'])) <p class="w-full text-bad text-xs">Não foi possível filmar a página: {{ $r['siteError'] }}</p> @endif

                        {{-- Generation-prompt editor for this suggestion --}}
                        @if ($promptKey === $r['key'])
                            <div class="w-full mt-1 border-t border-ink-soft/15 pt-2">
                                <label class="font-mono text-[0.55rem] text-ink-faint uppercase tracking-wider block mb-1">Prompt de geração da imagem (em inglês)</label>
                                <textarea wire:model="promptEdits.{{ $r['key'] }}" rows="3"
                                          class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-xs focus:border-teal focus:outline-none"></textarea>
                                <div class="mt-1 flex items-center gap-2">
                                    <button type="button" wire:click="gerarImagemAgora('{{ $r['key'] }}')"
                                            class="px-3 py-1 rounded-sm border border-teal/60 text-teal bg-teal/10 font-mono text-[0.6rem]">✦ gerar com este prompt</button>
                                    <button type="button" wire:click="editarPrompt('{{ $r['key'] }}')"
                                            class="font-mono text-[0.6rem] text-ink-faint hover:text-ink">fechar</button>
                                </div>
                            </div>
                        @endif

                        {{-- Library picker for this suggestion --}}
                        @if ($libraryPickerKey === $r['key'])
                            <div class="w-full mt-1 border-t border-ink-soft/15 pt-2">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-mono text-[0.55rem] text-ink-faint uppercase tracking-wider">Escolher da biblioteca</span>
                                    <button type="button" wire:click="abrirBibliotecaImagens('{{ $r['key'] }}')" class="font-mono text-[0.55rem] text-ink-faint hover:text-ink">fechar</button>
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
                    <p class="text-ink-soft text-sm">Sem sugestões de imagens para este vídeo.</p>
                @endforelse
            </div>

            {{-- Disabled while images are still arriving: continuing now would
                 settle the plan and throw away whatever landed a second later. --}}
            <button type="button" wire:click="finalizarImagens" @if ($this->siteCollecting) disabled @endif
                    x-on:click="window.CMLoader?.busy('A gerar a animação…')"
                    class="font-display text-lg px-6 py-2.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition @if ($this->siteCollecting) opacity-50 cursor-wait hover:bg-transparent @endif">
                {{ $this->siteCollecting ? 'Ainda a recolher imagens…' : '✦ Gerar o resto e continuar →' }}
            </button>
        </x-panel>
    @endif

    {{-- ============================================================ --}}
    {{-- CREATION                                                     --}}
    {{-- ============================================================ --}}
    @if ($view === 'create')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← voltar à lista</button>

        @if ($createType === null)
            {{-- step 1: choose type --}}
            <div class="eyebrow mb-4">Passo · I — que tipo de vídeo?</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <button type="button" wire:click="escolherTipo('animation')" class="text-left group">
                    <x-panel class="h-full transition group-hover:border-teal/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="eyebrow mb-2">Tipo · A</div>
                                <h2 class="font-display text-3xl text-ink">Animação</h2>
                                <p class="mt-2 text-ink-soft">A partir de texto ou narração — vídeo totalmente animado, com animação em cada segundo.</p>
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
                                <h2 class="font-display text-3xl text-ink">Vídeo + animações</h2>
                                <p class="mt-2 text-ink-soft">A partir de um vídeo já feito — animações por cima só nos momentos que valem a pena.</p>
                            </div>
                            <span class="text-4xl text-gold/70 select-none">❖</span>
                        </div>
                        <div class="mt-6 font-mono text-[0.62rem] text-teal">escolher →</div>
                    </x-panel>
                </button>
                <button type="button" wire:click="escolherTipo('property')" class="text-left group">
                    <x-panel class="h-full transition group-hover:border-teal/40">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="eyebrow mb-2">Tipo · C</div>
                                <h2 class="font-display text-3xl text-ink">Animação de imóvel</h2>
                                <p class="mt-2 text-ink-soft">A partir do vídeo da visita e do anúncio — divisões montadas com zooms, mobília animada a entrar ou a sair, com narração e os cartões do consultor.</p>
                            </div>
                            <span class="text-4xl text-gold/70 select-none">⌂</span>
                        </div>
                        <div class="mt-6 font-mono text-[0.62rem] text-teal">escolher →</div>
                    </x-panel>
                </button>
            </div>
        @elseif ($createType === 'animation')
            {{-- step 2A: animation form --}}
            <x-panel eyebrow="Passo · II — Animação" title="Nova animação" glyph="❈">
                <form wire:submit="submitAnimation" x-on:submit="window.CMLoader.busy('A preparar o vídeo animado…')" class="space-y-5">
                    <div>
                        <label class="eyebrow block mb-2">Guião / texto para a narração</label>
                        <textarea wire:model="text" rows="5"
                                  placeholder="Escreva o texto. Vai ser convertido em narração e transcrito para obter os tempos exatos."
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
                            <span x-show="uploading" x-cloak class="font-mono text-[0.6rem] text-ink-faint">a carregar a gravação…</span>
                            <span x-show="ready && !recording && !uploading" x-cloak class="font-mono text-[0.6rem] text-teal">gravação pronta ✔</span>
                            <span x-show="error" x-cloak class="font-mono text-[0.6rem] text-bad" x-text="error"></span>
                        </div>
                        <audio x-ref="player" x-show="ready" x-cloak controls class="w-full rounded-sm border border-ink-soft/15"></audio>
                    </div>

                    <div>
                        <label class="eyebrow block mb-2">Ou carregue a narração (áudio)</label>
                        <input type="file" wire:model="audio" accept="audio/*"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="audio" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
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
                            Gerar vídeo animado
                        </button>
                        <span wire:loading wire:target="submitAnimation" class="font-mono text-[0.6rem] text-ink-faint">a preparar…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">Modo denso — cada segundo da narração tem animação.</p>
                </form>
            </x-panel>
        @elseif ($createType === 'overlay')
            {{-- step 2B: video+animations form --}}
            <x-panel eyebrow="Passo · II — Vídeo + animações" title="Vídeo com animações" glyph="❖">
                <form wire:submit="submitOverlay" x-on:submit="window.CMLoader.busy('A preparar o vídeo…')" class="space-y-5">
                    @if ($videoPronto)
                        {{-- Came from the Shorts Generator: the cut is already staged. --}}
                        <div>
                            <label class="eyebrow block mb-2">Vídeo</label>
                            <div class="flex flex-wrap items-center gap-3 rounded-sm border border-teal/40 bg-teal/5 px-3 py-2">
                                <span class="text-teal">✂</span>
                                <span class="min-w-0">
                                    <span class="block font-display text-lg text-ink leading-tight truncate">{{ $videoProntoNome }}</span>
                                    <span class="block font-mono text-[0.6rem] text-ink-faint">cortado no gerador de Shorts — sem legendas gravadas, sem música</span>
                                </span>
                                <button type="button" wire:click="$set('videoPronto', null)"
                                        class="ml-auto font-mono text-[0.62rem] text-ink-soft hover:text-ink shrink-0">usar outro vídeo</button>
                            </div>
                        </div>
                    @else
                        <div>
                            <label class="eyebrow block mb-2">Carregar vídeo (mp4 / mov)</label>
                            <input type="file" wire:model="video" accept="video/mp4,video/quicktime"
                                   class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                            <div wire:loading wire:target="video" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
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
                            Gerar vídeo
                        </button>
                        <span wire:loading wire:target="submitOverlay" class="font-mono text-[0.6rem] text-ink-faint">a preparar…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">A IA alterna automaticamente os estilos escolhidos, conforme o conteúdo.</p>
                </form>
            </x-panel>
        @elseif ($createType === 'property')
            {{-- step 2C: property walkthrough form --}}
            <x-panel eyebrow="Passo · II — Animação de imóvel" title="Visita ao imóvel" glyph="⌂">
                <form wire:submit="submitProperty" x-on:submit="window.CMLoader.busy('A carregar o vídeo da visita…')" class="space-y-5">
                    <fieldset>
                        <legend class="eyebrow mb-2">Animar a partir de</legend>
                        <div class="flex flex-wrap gap-4 text-sm text-ink">
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertySource" value="video"> um vídeo da visita (marca os momentos)</label>
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertySource" value="photos"> fotos (cada uma é uma divisão)</label>
                        </div>
                    </fieldset>
                    @if ($propertySource === 'photos')
                    <div>
                        <label for="propertyPhotos" class="eyebrow block mb-2">Fotos das divisões (até 15, pela ordem em que aparecem)</label>
                        <input id="propertyPhotos" type="file" wire:model="propertyPhotos" accept="image/*" multiple
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="propertyPhotos" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                        @if (is_array($propertyPhotos) && count($propertyPhotos))
                            <p class="mt-1 font-mono text-[0.6rem] text-ink-soft">{{ count($propertyPhotos) }} {{ count($propertyPhotos) === 1 ? 'foto pronta' : 'fotos prontas' }}.</p>
                        @endif
                        @error('propertyPhotos') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        @error('propertyPhotos.*') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Cada foto é modernizada e a câmara avança devagar para a divisão ou recua (pode inverter por divisão). O vídeo fica com o formato da primeira foto.</p>
                    </div>
                    @else
                    <div>
                        <label for="propertyVideo" class="eyebrow block mb-2">Vídeo da visita (mp4 / mov)</label>
                        <input id="propertyVideo" type="file" wire:model="video" accept="video/mp4,video/quicktime"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                        <div wire:loading wire:target="video" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                        @error('video') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Avanços lentos e a direito em cada divisão animam melhor.</p>
                    </div>
                    @endif
                    <div>
                        <label for="propertyAspect" class="eyebrow block mb-2">Formato final</label>
                        <select id="propertyAspect" wire:model="propertyAspect"
                                class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                            @foreach (\App\Services\RealEstate\PropertyVideoEditor::ASPECTS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">As divisões são cortadas ao centro para esse formato; os cartões de intro e de fecho são desenhados nesse tamanho. Vertical a partir de filmagem horizontal fica com o terço central de cada imagem — filme em 4K para isso.</p>
                    </div>
                    <div>
                        <label for="propertyListingUrl" class="eyebrow block mb-2">URL do anúncio (opcional)</label>
                        <input id="propertyListingUrl" type="url" wire:model.blur="propertyListingUrl" placeholder="https://www.remax.pt/pt/imoveis/…"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink placeholder:text-ink-faint focus:outline-none focus:border-teal/60">
                        @error('propertyListingUrl') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Dá o cartão final do consultor e os dados da casa para o cartão de intro.</p>
                    </div>
                    @if (trim($propertyListingUrl) === '')
                        <fieldset class="border border-ink-soft/15 rounded-sm p-3 space-y-3">
                            <legend class="eyebrow px-1">Sem URL do anúncio? Escreva o consultor e os dados</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input type="text" wire:model="propertyAgent" placeholder="Nome do consultor" aria-label="Nome do consultor"
                                       class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                <input type="text" wire:model="propertyPhone" placeholder="Telefone (+351 …)" aria-label="Telefone do consultor"
                                       class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                <input type="text" wire:model="propertyAgency" placeholder="Agência (ex.: RE/MAX Valor)" aria-label="Agência"
                                       class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                <select wire:model="propertyGender" aria-label="Título no cartão"
                                        class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60">
                                    <option value="m">Consultor imobiliário</option>
                                    <option value="f">Consultora imobiliária</option>
                                </select>
                            </div>
                            <div>
                                <label for="propertyPhoto" class="font-mono text-[0.62rem] text-ink-soft">Foto do consultor (para o cartão final)</label>
                                <input id="propertyPhoto" type="file" wire:model="propertyPhoto" accept="image/*"
                                       class="mt-1 block w-full text-sm text-ink-soft file:mr-3 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                                @error('propertyPhoto') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                            </div>
                            <textarea wire:model="propertyFacts" rows="4" aria-label="Dados da casa"
                                      placeholder="Dados da casa, um por linha — para o cartão de intro&#10;Área útil: 152 m²&#10;Quartos: 3&#10;Preço: 350.000 €"
                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink text-sm focus:outline-none focus:border-teal/60"></textarea>
                        </fieldset>
                    @endif
                    <fieldset>
                        <legend class="eyebrow block mb-2">Narração</legend>
                        <div class="flex flex-wrap gap-4 text-sm text-ink-soft">
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertyVoice" value="none"> Nenhuma — só música</label>
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertyVoice" value="listing"> Escrita e gravada a partir do anúncio (pt-PT)</label>
                            <label class="flex items-center gap-2"><input type="radio" wire:model.live="propertyVoice" value="upload"> Carregar a minha</label>
                        </div>
                        @if ($propertyVoice === 'upload')
                            <input type="file" wire:model="audio" accept="audio/*" aria-label="Áudio da narração"
                                   class="mt-2 block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                            <div wire:loading wire:target="audio" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                        @endif
                        @error('audio') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    </fieldset>
                    @include('livewire.partials.agent-extras-field')
                    @include('livewire.partials.musica-picker')
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition" wire:loading.attr="disabled">
                            Fazer o vídeo do imóvel
                        </button>
                        <span wire:loading wire:target="submitProperty" class="font-mono text-[0.6rem] text-ink-faint">a preparar…</span>
                    </div>
                    <p class="font-mono text-[0.6rem] text-ink-faint">Estabiliza e corrige a cor da filmagem; depois marca os excertos a modernizar e aprova cada imagem modernizada antes de pagar qualquer vídeo. Cada excerto transforma-se no ecrã com os seus próprios sons, ligados por whip pans, com a sua música e narração e o cartão final do consultor — menos de um minuto.</p>
                </form>
            </x-panel>
        @endif

        {{-- Images (optional) — for the animation and video types --}}
        @if (in_array($createType, ['animation', 'overlay'], true))
            <x-panel class="mt-6" eyebrow="Opcional" title="Imagens" glyph="▣">
                <p class="text-ink-soft text-sm mb-4">Carregue imagens e descreva o que mostram. A IA usa-as animadas nas cenas onde encaixam.</p>
                @include('livewire.partials.clip-images')
            </x-panel>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- EDIT CLIP (animations)                                       --}}
    {{-- ============================================================ --}}
    @if ($view === 'editPlan')
        <div class="flex items-center justify-between mb-6">
            <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">← voltar à lista</button>
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

        <x-panel class="mb-6" eyebrow="Carregadas" title="Imagens" glyph="▣">
            <p class="text-ink-soft text-sm mb-4">Substitua uma imagem (mantém o lugar no vídeo) ou adicione/remova carregamentos. As alterações renderizam de novo o plano atual — uma imagem substituída aparece logo; uma nova só aparece se uma cena a usar.</p>
            @include('livewire.partials.clip-images')
        </x-panel>

        @if ($editMode === 'json')
            <x-panel eyebrow="Editar vídeo" title="Plano Remotion (JSON)" glyph="⌘">
                <form wire:submit="guardarJson" class="space-y-4">
                    <p class="text-ink-soft text-sm">Edite diretamente o plano que vai para o Remotion (cenas, camadas, parâmetros). O áudio, o karaoke e as imagens são adicionados ao renderizar.</p>
                    @include('livewire.partials.musica-picker')
                    @include('livewire.partials.captions-picker')
                    <textarea wire:model="editPlanJson" rows="22" spellcheck="false"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink font-mono text-xs leading-relaxed focus:border-teal/50 focus:outline-none" style="tab-size:2"></textarea>
                    @error('editPlanJson') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Guardar JSON e renderizar de novo</button>
                        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancelar</button>
                    </div>
                </form>
            </x-panel>
        @else
        <x-panel eyebrow="Editar vídeo" title="Cenas" glyph="✎">
            <form wire:submit="guardarPlano" class="space-y-5">
                <div>
                    <label class="eyebrow block mb-2">Título</label>
                    <input type="text" wire:model="editTitle" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none" />
                </div>

                @include('livewire.partials.musica-picker')
                @include('livewire.partials.captions-picker')

                <div class="space-y-3">
                    @foreach ($editScenes as $i => $scene)
                        <div class="border border-ink-soft/15 rounded-sm p-3 bg-surface/20" wire:key="scene-{{ $i }}">
                            <div class="flex items-center justify-between mb-2">
                                <span class="eyebrow">Cena {{ $i + 1 }} · <span class="text-ink-faint normal-case tracking-normal">{{ $scene['layersSummary'] ?? '—' }}</span></span>
                                <button type="button" wire:click="removerCena({{ $i }})" class="text-bad font-mono text-sm hover:opacity-70" title="remover">✕</button>
                            </div>

                            {{-- Animation: its sample + a picker to swap it. --}}
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-14 aspect-[9/16] rounded-sm overflow-hidden bg-black/50 shrink-0 flex items-center justify-center">
                                    @if (! empty($scene['animacao']) && in_array($scene['animacao'], $sfxReady, true))
                                        <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $scene['animacao']) }}" autoplay loop muted playsinline onerror="this.style.display='none'"></video>
                                    @else
                                        <span class="font-mono text-[0.45rem] text-ink-faint text-center px-1">sem amostra</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-mono text-[0.55rem] text-ink-faint">animação</div>
                                    <div class="font-display text-ink truncate">{{ $scene['animacao'] ?? '—' }}</div>
                                </div>
                                @if (in_array($scene['animacao'] ?? null, $freeSfxSlugs, true))
                                    {{-- Written by Free SFX for this clip only: offer to keep it. --}}
                                    <button type="button" wire:click="guardarFreeSfx('{{ $scene['animacao'] }}')"
                                            class="font-mono text-[0.62rem] px-3 py-1.5 rounded-sm border border-gold/40 text-gold hover:bg-gold/10 transition shrink-0">✦ Adicionar à biblioteca de SFX</button>
                                @endif
                                <button type="button" wire:click="escolherAnimacao({{ $i }})"
                                        class="font-mono text-[0.62rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition shrink-0">⇆ Mudar</button>
                            </div>

                            @if ($animacaoPickerCena === $i)
                                <div class="mb-3 border border-teal/30 rounded-sm p-2 bg-vellum/40" @if (count($animacoes) > count($sfxReady)) wire:poll.4s @endif>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="eyebrow">Escolher uma animação</span>
                                        <span class="font-mono text-[0.55rem] text-ink-faint">gráficos e cronologias são escritos a partir das palavras desta cena ao guardar</span>
                                        <button type="button" wire:click="fecharAnimacoes" class="font-mono text-[0.6rem] text-ink-soft hover:text-ink">fechar</button>
                                    </div>
                                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2 max-h-96 overflow-y-auto">
                                        @foreach ($animacoes as $a)
                                            <button type="button" wire:click="mudarAnimacao({{ $i }}, '{{ $a['slug'] }}')" wire:key="anim-{{ $i }}-{{ $a['slug'] }}"
                                                    class="rounded-sm border p-1 flex flex-col transition hover:border-teal/50 {{ ($scene['animacao'] ?? null) === $a['slug'] ? 'border-teal/60 bg-teal/10' : 'border-ink-soft/15' }}">
                                                <div class="aspect-[9/16] rounded-sm overflow-hidden bg-black/50 flex items-center justify-center">
                                                    @if (in_array($a['slug'], $sfxReady, true))
                                                        <video class="w-full h-full object-cover" src="{{ route('clips-animados.sfx-preview', $a['slug']) }}" autoplay loop muted playsinline onerror="this.style.display='none'"></video>
                                                    @else
                                                        <span class="font-mono text-[0.45rem] text-ink-faint animate-pulse">a renderizar…</span>
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
                                                ainda sem imagem
                                            @else
                                                {{ !empty($sceneImg['video']) ? 'vídeo' : 'imagem' }}{{ $sceneImg['library'] ? ' · da biblioteca' : '' }}
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 font-mono text-[0.6rem] mt-1">
                                            @if (!empty($this->libraryImages))
                                                <button type="button" wire:click="abrirBibliotecaCena({{ $i }})" class="text-teal hover:opacity-70">▦ biblioteca</button>
                                            @endif
                                            <label class="text-teal hover:opacity-70 cursor-pointer">
                                                <span wire:loading.remove wire:target="sceneImageUploads.{{ $i }}">↑ {{ $temImagem ? 'substituir' : 'adicionar imagem' }}</span>
                                                <span wire:loading wire:target="sceneImageUploads.{{ $i }}">a carregar…</span>
                                                <input type="file" class="hidden" accept="image/*,video/mp4,video/quicktime,video/webm" wire:model="sceneImageUploads.{{ $i }}" />
                                            </label>
                                        </div>
                                        @error('sceneImageUploads.'.$i) <p class="text-bad text-xs mt-0.5">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                @if ($sceneLibraryPicker === $i)
                                    <div class="mb-3 border border-teal/30 rounded-sm p-2 bg-vellum/40">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="eyebrow">Escolher da biblioteca</span>
                                            <button type="button" wire:click="abrirBibliotecaCena({{ $i }})" class="font-mono text-[0.6rem] text-ink-soft hover:text-ink">fechar</button>
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
                                        🎞 Escolher do vídeo do imóvel{{ count($slots) > 1 ? ' ('.count($slots).' momentos)' : '' }}
                                    </button>
                                    @if ($momentPicker === $i)
                                        @php $block = max(0.5, (float) $scene['end'] - (float) $scene['start']); @endphp
                                        <div class="mt-2 border border-teal/30 rounded-sm p-2 bg-vellum/40" wire:ignore wire:key="moments-{{ $i }}"
                                             x-data="sectionPicker(@js($this->listingVideoLength), @js(collect($slots)->filter(fn ($s) => $s['start'] !== null)->values()->all()), { max: {{ count($slots) }}, min: 0.5, maxLen: 10 })">
                                            <video x-ref="video" class="w-full rounded-sm border border-ink-soft/15 bg-black max-h-[40vh]" muted playsinline preload="auto"
                                                   src="{{ route('clips-animados.listing-video', ['id' => $editingId, 'what' => 'footage']) }}"></video>
                                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 font-mono text-[0.6rem] text-ink-faint">
                                                <span x-text="label(cursor)"></span>
                                                <span>Arraste na faixa para marcar {{ count($slots) > 1 ? 'até '.count($slots).' momentos, um por imagem deste efeito' : 'o momento' }} · este bloco dura {{ number_format($block, 1) }} s — um momento mais curto continua até o preencher</span>
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
                                                        <button type="button" class="px-2 py-0.5 rounded-sm border border-ink-soft/30 hover:bg-ink/5" @click="preview(s)">▶ pré-visualizar</button>
                                                        <button type="button" class="text-bad hover:underline" @click="sections.splice(k, 1)">remover</button>
                                                    </li>
                                                </template>
                                            </ol>
                                            <div class="mt-2 flex items-center gap-3">
                                                <button type="button" :disabled="!sections.length || sending" @click="sending = true; $wire.usarMomentos({{ $i }}, sections).finally(() => sending = false)"
                                                        class="font-mono text-[0.65rem] px-3 py-1.5 rounded-sm bg-teal-deep text-papyrus hover:opacity-90 transition disabled:opacity-40"
                                                        x-text="sending ? 'A cortar…' : 'Usar {{ count($slots) > 1 ? 'estes momentos' : 'este momento' }}'">Usar</button>
                                                <span class="font-mono text-[0.55rem] text-ink-faint">depois Guarde em baixo para renderizar de novo</span>
                                            </div>
                                        </div>
                                    @endif
                                    @error('moments.'.$i) <p class="text-bad text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endif


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
                                <div class="col-span-12">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">legendas ditas neste bloco</label>
                                    <textarea wire:model="editScenes.{{ $i }}.subtitles" rows="2" placeholder="—"
                                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm leading-relaxed focus:border-teal/50 focus:outline-none"></textarea>
                                    <p class="font-mono text-[0.5rem] text-ink-faint mt-0.5">Só as legendas — a voz continua a dizer as palavras antigas até gerar de novo a narração.</p>
                                </div>
                                @if ($editOverlay)
                                    <div class="col-span-12">
                                        <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">como este bloco aparece sobre o vídeo</label>
                                        <select wire:model="editScenes.{{ $i }}.present" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none">
                                            <option value="">auto (escolha do planeador)</option>
                                            <option value="video">só o vídeo — sem grafismos</option>
                                            <option value="over">grafismos sobre o vídeo</option>
                                            <option value="split">ecrã dividido</option>
                                            <option value="animation">só animação</option>
                                        </select>
                                    </div>
                                @endif
                                <div class="col-span-7">
                                    <label class="block font-mono text-[0.55rem] text-ink-faint mb-0.5">palavra de destaque</label>
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
                    <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition mt-4">Guardar e renderizar de novo</button>
                    @if ($editSpeakable)
                        <button type="button" wire:click="guardarPlanoComVoz" wire:loading.attr="disabled"
                                class="font-display text-lg px-6 py-2 rounded-sm border border-gold/50 text-gold hover:bg-gold/10 transition mt-4"
                                title="Volta a dizer as legendas acima e renderiza de novo. As cenas mantêm-se e os tempos são ajustados ao novo áudio.">♺ Guardar e gerar de novo a narração</button>
                    @endif
                    <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mt-4">cancelar</button>
                </div>
                <p class="font-mono text-[0.6rem] text-ink-faint">Edite o ritmo das cenas (tempos, fundo, transição, destaque, karaoke). As camadas de cada cena mantêm-se. No modo denso, os intervalos são preenchidos com «ambient».</p>
            </form>
        </x-panel>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- EDIT TRANSCRIPT                                              --}}
    {{-- ============================================================ --}}
    @if ($view === 'editTranscript')
        <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← voltar à lista</button>
        <x-panel eyebrow="Editar transcrição" title="Corrigir e gerar de novo" glyph="✎">
            <form wire:submit="regenerar" x-on:submit="window.CMLoader.busy('A gerar de novo as animações…')" class="space-y-5">
                <div>
                    <label class="eyebrow block mb-2">Transcrição (corrija os erros de reconhecimento)</label>
                    <textarea wire:model="editTranscriptText" rows="6" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('editTranscriptText') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
                    @if ($this->editingSpokenAgain)
                        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Uma transcrição alterada é dita de novo (nova narração) e o vídeo é planeado a partir dela.</p>
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
                        <span class="eyebrow block">⌂ Anúncio</span>
                        <label class="block text-sm text-ink-soft">Link do anúncio
                            <input type="url" wire:model="editListingUrl" class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60">
                        </label>
                        @error('editListingUrl') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                            <span>Foto do consultor — {{ $temRetrato ? '✓ já tem; carregue para substituir' : 'ainda sem foto' }}:</span>
                            <input type="file" accept="image/*" wire:model="editAgentPhoto" class="text-xs">
                            <span wire:loading wire:target="editAgentPhoto" class="text-ink-faint">a carregar…</span>
                        </label>
                        @error('editAgentPhoto') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                            <span>🏠 Vídeo do imóvel — {{ $videoLen ? '✓ '.gmdate('i:s', (int) $videoLen).' de duração; carregue para substituir' : 'nenhum (o vídeo usa as fotos do anúncio)' }}:</span>
                            <input type="file" accept="video/*" wire:model="editListingVideo" class="text-xs">
                            <span wire:loading wire:target="editListingVideo" class="text-ink-faint">a carregar…</span>
                        </label>
                        @error('editListingVideo') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="ml-4 flex items-center gap-2 text-sm text-ink-soft cursor-pointer">
                            <input type="checkbox" wire:model="editMixPhotos" class="accent-teal">
                            <span>Misturar o vídeo com as fotos do anúncio</span>
                        </label>
                        <label class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                            <span>🎬 Vídeo após a intro — {{ ! empty($em['after_intro']) ? '✓ este vídeo tem o seu; carregue para substituir' : (app(\App\Services\Clips\Store\EffectStore::class)->afterIntroPath() ? 'o do projeto (estúdio de SFX)' : 'nenhum') }}:</span>
                            <input type="file" accept="video/*" wire:model="editAfterIntro" class="text-xs">
                            <span wire:loading wire:target="editAfterIntro" class="text-ink-faint">a carregar…</span>
                        </label>
                        @error('editAfterIntro') <p class="text-sm text-bad">{{ $message }}</p> @enderror
                        <label class="block text-sm text-ink-soft">Frase após a intro (dita logo a seguir à intro, sobre o vídeo após a intro)
                            <input type="text" wire:model="editAfterIntroLine" class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60">
                        </label>
                        <label class="block text-sm text-ink-soft">Notas para o guião (sobrepõem-se ao anúncio)
                            <textarea wire:model="editListingNotes" rows="2" class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink focus:outline-none focus:border-teal/60"></textarea>
                        </label>
                        <p class="font-mono text-[0.58rem] text-ink-faint">Mudar o link, as notas, o vídeo do imóvel ou a mistura de fotos volta a escrever o guião a partir do anúncio e refaz o vídeo (nova narração). Uma nova frase após a intro substitui a antiga na transcrição; uma nova foto ou vídeo após a intro é trocado.</p>
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
                    <button type="submit" wire:loading.attr="disabled" wire:target="editAgentPhoto, editListingVideo, editAfterIntro" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40">Gerar de novo as animações</button>
                    <button type="button" wire:click="voltar" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancelar</button>
                </div>
                <p class="font-mono text-[0.6rem] text-ink-faint">A IA volta a planear as animações a partir do texto corrigido e renderiza de novo.</p>
            </form>
        </x-panel>
    @endif

    {{-- ============================================================ --}}
    {{-- BACKGROUNDS STUDIO                                          --}}
    {{-- ============================================================ --}}
    @if ($view === 'backgrounds')
        @include('livewire.partials.studio-tabs')
        <a href="{{ route('clips-animados') }}" wire:navigate class="inline-block font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6">← voltar a Imóveis</a>

        <x-panel eyebrow="Biblioteca de fundos" title="Fundos" glyph="◆">
            <p class="text-ink-soft text-sm mb-5">
                O fundo de ecrã inteiro que um vídeo mostra atrás de cada cena. Gere um animado a partir de uma
                descrição (a IA escreve um componente Remotion que segue o sistema de design e repete em qualquer
                duração) ou carregue um vídeo. Os fundos ativos podem ser escolhidos automaticamente pelo gerador
                de vídeos ou à mão quando cria um vídeo.
            </p>

            <form wire:submit="gerarBackground" class="space-y-3 mb-6">
                <label class="eyebrow block">Gerar um fundo animado</label>
                <textarea wire:model="bgPrompt" rows="3" placeholder="ex.: uma aurora lenta de luz verde-azulada e dourada a deslizar sobre tinta escura, com partículas ténues a flutuar"
                          class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                @error('bgPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <div class="flex items-center gap-3">
                    <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition"
                            wire:loading.attr="disabled" wire:target="gerarBackground">
                        ✦ Criar fundo
                    </button>
                    <span class="font-mono text-[0.6rem] text-ink-faint">As cores seguem o sistema de design.</span>
                </div>
            </form>

            <form wire:submit="uploadBackground" class="space-y-3 border-t border-ink-soft/15 pt-5">
                <label class="eyebrow block">…ou carregue um fundo em vídeo (mp4 / mov)</label>
                <input type="text" wire:model="bgVideoName" placeholder="Nome (ex.: Timelapse da cidade)"
                       class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none" />
                @error('bgVideoName') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <input type="file" wire:model="bgVideo" accept="video/mp4,video/quicktime"
                       class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
                <div wire:loading wire:target="bgVideo" class="font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                @error('bgVideo') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition"
                        wire:loading.attr="disabled" wire:target="bgVideo,uploadBackground">
                    ✦ Adicionar fundo em vídeo
                </button>
            </form>

            {{-- Export / import — move backgrounds (component or mp4) between installs. --}}
            <div class="flex flex-wrap items-center gap-3 border-t border-ink-soft/15 pt-5 mt-5">
                @if ($this->backgrounds->isNotEmpty())
                    <a href="{{ route('clips-animados.background-export', 'all') }}"
                       class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Exportar todos os fundos</a>
                @endif
                <form wire:submit="importarBackgrounds" class="flex items-center gap-2">
                    <input type="file" wire:model="importBackgroundFile" accept="application/json,.json"
                           class="block text-xs text-ink-soft file:mr-2 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-[0.6rem] file:cursor-pointer" />
                    <button type="submit" wire:loading.attr="disabled" wire:target="importBackgroundFile,importarBackgrounds"
                            class="font-mono text-xs px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40">⤒ Importar</button>
                </form>
            </div>
            @error('importBackgroundFile') <p class="text-bad text-sm mt-2">{{ $message }}</p> @enderror
        </x-panel>

        {{-- Reel: one video cycling through every background, name centered --}}
        @if ($this->backgrounds->isNotEmpty())
            <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-4 mt-6">
                <div class="flex items-center justify-between gap-4 mb-3">
                    <div>
                        <div class="eyebrow">Pré-visualizar todos</div>
                        <p class="text-ink-soft text-sm mt-1">Um vídeo que mostra cada fundo à vez, com o nome ao centro.</p>
                    </div>
                    <button type="button" wire:click="gerarBackgroundReel" wire:loading.attr="disabled" wire:target="gerarBackgroundReel"
                            @if ($this->backgroundReelBusy) disabled @endif
                            class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50 shrink-0">
                        @if ($this->backgroundReelBusy) a renderizar… @elseif ($this->backgroundReelReady) ↻ Refazer @else ✦ Criar pré-visualização @endif
                    </button>
                </div>
                @if ($this->backgroundReelBusy)
                    <p class="font-mono text-[0.6rem] text-ink-faint">A renderizar o vídeo — pode demorar um minuto. Atualiza sozinho.</p>
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
                    <label class="eyebrow block">Afinar este fundo</label>
                    <textarea wire:model="bgEditPrompt" rows="3"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('bgEditPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">✦ Gerar de novo</button>
                        <button type="button" wire:click="cancelarBackgroundEdicao" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancelar</button>
                        <span class="font-mono text-[0.6rem] text-ink-faint">Mesmo slug; a versão atual fica até a nova estar renderizada.</span>
                    </div>
                </form>
            </div>
        @endif

        {{-- Backgrounds grid --}}
        @if ($this->backgrounds->isNotEmpty())
            <div class="mt-8">
                <div class="flex items-baseline justify-between mb-4 gap-3">
                    <div class="eyebrow">Os seus fundos · {{ $this->backgrounds->count() }}</div>
                    <span class="font-mono text-[0.55rem] text-ink-faint">● ligado = disponível para vídeos · ○ desligado = guardado mas não usado</span>
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
                                            <p class="font-mono text-[0.55rem] text-teal animate-pulse">A atualizar…</p>
                                        </div>
                                    @endif
                                    @if ($bg->kind === 'video')
                                        <div class="absolute top-1 left-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-ink/40 text-vellum border border-vellum/20">vídeo</div>
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
                                        <p class="font-mono text-[0.52rem] text-ink-faint mt-2">{{ $bg->status === 'updating' ? 'A atualizar…' : 'A gerar…' }}</p>
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
                                                title="{{ $bg->enabled ? 'Disponível para vídeos — clique para desativar' : 'Desativado — clique para ativar' }}"
                                                class="font-mono text-[0.62rem] px-2 py-1 rounded-sm border {{ $bg->enabled ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">
                                            {{ $bg->enabled ? '● ligado' : '○ desligado' }}
                                        </button>
                                        @if ($bg->kind !== 'video')
                                            <button type="button" wire:click="editarBackground('{{ $bg->id }}')" title="Afinar este fundo"
                                                    class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎</button>
                                        @endif
                                        <a href="{{ route('clips-animados.background-export', $bg->id) }}" title="Exportar este fundo"
                                           class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓</a>
                                    @endif
                                    <button type="button" wire:click="apagarBackground('{{ $bg->id }}')"
                                            wire:confirm="Apagar este fundo? Os vídeos já renderizados mantêm o seu vídeo." title="Apagar este fundo"
                                            class="text-sm leading-none px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <x-empty-state glyph="◇" title="Ainda sem fundos" note="Gere um fundo animado ou carregue um vídeo para começar." />
        @endif
    @endif

    @script
    <script>
        Alpine.data('voiceRecorder', () => ({
            recording: false, uploading: false, ready: false, elapsed: 0, error: null,
            recorder: null, chunks: [], timer: null,

            async start() {
                this.error = null;
                if (!navigator.mediaDevices?.getUserMedia) { this.error = 'Este navegador não permite gravar.'; return; }
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
                    () => { this.uploading = false; this.error = 'Não foi possível carregar a gravação.'; },
                    () => {}
                );
            },
        }));
    </script>
    @endscript
    @include('livewire.partials.lightbox')
</div>
