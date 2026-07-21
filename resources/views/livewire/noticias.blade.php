<div>
    <x-page-header
        eyebrow="Tomus · VII"
        title="Agregador de Notícias"
        cota="070.4 · IAT · '26"
        lead="Reúne conteúdo de várias fontes e destila um relatório personalizado, pronto a virar guião." />

    {{-- ============ Agregador multi-plataforma ============ --}}
    <x-panel eyebrow="Recolha multi-plataforma" title="Conteúdo dos canais" glyph="▶" class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
            <p class="text-ink-soft max-w-xl">
                Vasculha os canais configurados em <a href="{{ route('definicoes') }}" class="text-teal hover:underline">Definições</a>
                (YouTube, TikTok, Instagram, LinkedIn) e arquiva cada item no vault <em>por dia</em>, com transcrição e uma lista de tópicos.
            </p>
            <button wire:click="agregarAgora" wire:loading.attr="disabled" wire:target="agregarAgora"
                    class="shrink-0 bg-teal text-papyrus font-display text-lg px-6 py-2.5 rounded-sm hover:bg-teal-deep transition shadow-engraved disabled:opacity-50">
                <span wire:loading.remove wire:target="agregarAgora">Agregar agora</span>
                <span wire:loading wire:target="agregarAgora">A vasculhar canais…</span>
            </button>
        </div>

        @if ($resumoAgregacao)
            <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <x-metric-card label="Itens recolhidos" :value="$resumoAgregacao['total']" accent="#2dbab4" />
                @foreach ($resumoAgregacao['por_plataforma'] as $plat => $n)
                    <x-metric-card :label="$plat" :value="$n" :accent="config('contentmachine.plataformas_meta.'.$plat.'.cor', '#9a8c78')" />
                @endforeach
            </div>
            @if (!empty($resumoAgregacao['avisos']))
                <div class="mt-3 space-y-1">
                    @foreach ($resumoAgregacao['avisos'] as $aviso)
                        <div class="border border-warn/40 bg-warn/10 text-warn rounded-sm px-3 py-1.5 font-mono text-xs">⚠ {{ $aviso }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Selector de dia --}}
        @if ($dias->isNotEmpty())
            <x-fleuron glyph="☙" />
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($dias as $d)
                    <button wire:click="focarDia('{{ $d }}')"
                            class="font-mono text-xs px-3 py-1 rounded-sm border transition
                                   {{ $d === $diaAtivo ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft hover:text-ink' }}">
                        {{ \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y') }}
                    </button>
                @endforeach
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                {{-- Itens do dia --}}
                <div class="lg:col-span-2 space-y-3">
                    <div class="eyebrow mb-1">Itens de {{ \Illuminate\Support\Carbon::parse($diaAtivo)->translatedFormat('d M Y') }}</div>
                    @forelse ($itensDoDia as $nota)
                        @php $m = config('contentmachine.plataformas_meta.'.$nota->get('plataforma'), ['cor' => '#9a8c78', 'glifo' => '•']); @endphp
                        <div class="flex gap-4 py-3 border-b border-ink-soft/10 last:border-0">
                            @if ($nota->get('thumbnail'))
                                <img src="{{ $nota->get('thumbnail') }}" alt="" loading="lazy"
                                     class="shrink-0 w-28 h-16 object-cover rounded-sm border border-ink-soft/15">
                            @endif
                            <div class="min-w-0 flex-1">
                                <a href="{{ $nota->get('url') }}" target="_blank" rel="noopener"
                                   class="font-body text-ink hover:text-teal transition line-clamp-2">{{ $nota->title() }}</a>
                                @php
                                    // Sinopse: resumo por IA (preferido) ou o início da transcrição — nunca os links promocionais.
                                    $sinopse = trim((string) $nota->get('resumo'));
                                    if ($sinopse === '' && preg_match('/##\s*Transcri[cç][aã]o\s*\n+(.*)$/isu', $nota->body, $mm)) {
                                        $sinopse = \Illuminate\Support\Str::of($mm[1])->squish()->limit(200)->toString();
                                        $sinopse = $sinopse === '_Sem transcrição disponível._' ? '' : $sinopse;
                                    }
                                @endphp
                                @if ($sinopse !== '')
                                    <p class="mt-1 text-sm text-ink-soft line-clamp-2">{{ $sinopse }}</p>
                                @endif
                                <div class="mt-1 flex items-center gap-2 flex-wrap">
                                    <x-badge tone="leather"><span style="color: {{ $m['cor'] }}">{{ $m['glifo'] }}</span> {{ $nota->get('plataforma') }}</x-badge>
                                    <span class="font-mono text-[0.62rem] text-ink-faint">{{ $nota->get('canal') }}</span>
                                    @if (!empty($nota->get('fontes')))
                                        <x-badge tone="gold">☍ {{ count($nota->get('fontes')) }} fontes</x-badge>
                                    @endif
                                </div>
                                @if (!empty($nota->get('tags')))
                                    <div class="mt-1 font-mono text-[0.6rem] text-ink-faint truncate">
                                        {{ collect($nota->get('tags'))->take(6)->map(fn ($t) => '#'.$t)->implode(' ') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-ink-soft italic">Sem itens arquivados para este dia.</p>
                    @endforelse
                </div>

                {{-- Tópicos do dia --}}
                <div>
                    <div class="eyebrow mb-1">Tópicos «no ar»</div>
                    @if ($topicosHtml)
                        <div class="prose-nocturna text-ink-soft text-sm leading-relaxed [&_h2]:font-display [&_h2]:text-ink [&_h2]:text-lg [&_h2]:mt-4 [&_h2]:mb-1 [&_a]:text-teal [&_ul]:list-disc [&_ul]:pl-4 [&_li]:my-0.5">
                            {!! $topicosHtml !!}
                        </div>
                    @else
                        <p class="text-ink-soft italic">Ainda sem tópicos para este dia.</p>
                    @endif
                </div>
            </div>
        @else
            <div class="mt-4 border border-ink-soft/15 rounded-sm px-4 py-6 text-center text-ink-soft italic">
                Ainda nada agregado. Carregue em «Agregar agora» para recolher os canais configurados.
            </div>
        @endif
    </x-panel>

    {{-- ============ Relatório por período ============ --}}
    <div class="grid lg:grid-cols-4 gap-6">
        {{-- Gerador --}}
        <div class="lg:col-span-1">
            <x-panel eyebrow="Relatório" title="Criar relatório" glyph="☙">
                <p class="text-ink-soft text-sm -mt-2 mb-4">Destila os itens já agregados num relatório, pronto a virar guião.</p>

                <label class="eyebrow block mb-1.5">Período</label>
                <div class="flex gap-2 mb-4">
                    @foreach (['dia' => 'Dia', 'semana' => 'Semana'] as $modo => $rotulo)
                        <button type="button" wire:click="$set('modoRelatorio', '{{ $modo }}')"
                                class="flex-1 px-3 py-1.5 rounded-sm border font-mono text-xs transition
                                       {{ $modoRelatorio === $modo ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft hover:text-ink' }}">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>

                <label class="eyebrow block mb-1.5">{{ $modoRelatorio === 'semana' ? '7 dias até' : 'Dia' }}</label>
                <input type="date" wire:model="dataRelatorio"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">

                <label class="mt-3 flex items-start gap-2 cursor-pointer text-ink-soft hover:text-ink text-sm">
                    <input type="checkbox" wire:model="recolherPrimeiro" class="accent-teal w-4 h-4 mt-0.5">
                    <span>Recolher vídeos de hoje primeiro <span class="text-ink-faint">(vasculha os canais antes de redigir)</span></span>
                </label>

                <button wire:click="criarRelatorio" wire:loading.attr="disabled" wire:target="criarRelatorio"
                        class="mt-4 w-full bg-teal text-papyrus font-display text-base px-4 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved disabled:opacity-50">
                    <span wire:loading.remove wire:target="criarRelatorio">Criar relatório de notícias</span>
                    <span wire:loading wire:target="criarRelatorio">A recolher e redigir…</span>
                </button>

                @if ($relatorioGuardado)
                    <div class="mt-3 border border-good/40 bg-good/10 text-good rounded-sm px-3 py-2 font-mono text-xs break-all">
                        ✓ Guardado no vault: {{ $relatorioGuardado }}
                    </div>
                @endif
            </x-panel>
        </div>

        {{-- Relatório gerado --}}
        <div class="lg:col-span-3">
            <x-panel>
                @if ($relatorio)
                    <div class="flex items-start justify-between gap-4 mb-2">
                        <div>
                            <div class="eyebrow mb-1">Relatório · {{ $relatorio['modo'] }} · {{ $relatorio['total'] }} item(s)</div>
                            <h2 class="font-display text-3xl text-ink leading-tight">{{ $relatorio['titulo'] }}</h2>
                        </div>
                        <x-selo label="IATECA" sub="NOTÍCIAS" date="MMXXVI" color="#c89b3c" />
                    </div>

                    @if (!empty($relatorio['redacao']))
                        <div class="mt-2 space-y-3 text-ink leading-relaxed max-w-3xl">
                            @foreach (preg_split('/\n\n+/', trim($relatorio['redacao'])) as $i => $par)
                                <p class="{{ $i === 0 ? 'dropcap text-lg' : '' }}">{{ $par }}</p>
                            @endforeach
                        </div>
                        <div class="mt-1 font-mono text-[0.6rem] text-ink-faint">redação · {{ $relatorio['redacao_metodo'] ?? 'heuristica' }}</div>
                    @else
                        <p class="text-lg text-ink-soft italic dropcap">{{ $relatorio['resumo'] }}</p>
                    @endif

                    @if (!empty($relatorio['por_plataforma']))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($relatorio['por_plataforma'] as $plat => $n)
                                <x-badge tone="leather">{{ $plat }} · {{ $n }}</x-badge>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($relatorio['destaques']))
                        <x-fleuron glyph="☙" />
                        <div class="eyebrow mb-2">Destaques</div>
                        <div class="space-y-1">
                            @foreach ($relatorio['destaques'] as $d)
                                <div class="flex items-start gap-3 py-2.5 border-b border-ink-soft/10 last:border-0">
                                    <x-badge tone="leather">{{ $d['plataforma'] }}</x-badge>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $d['url'] }}" target="_blank" rel="noopener" class="text-ink hover:text-teal transition line-clamp-2">{{ $d['titulo'] }}</a>
                                        <div class="text-sm text-ink-soft italic">↳ {{ $d['angulo'] }}</div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="font-display text-2xl text-teal leading-none">{{ $d['relevancia'] }}</div>
                                        <div class="eyebrow !text-[0.55rem]">relevância</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($relatorio['topicos']))
                        <x-fleuron glyph="❧" />
                        <div class="eyebrow mb-2">Tópicos</div>
                        <div class="space-y-3">
                            @foreach ($relatorio['topicos'] as $t)
                                <div>
                                    <div class="font-display text-lg text-ink">{{ $t['topico'] }}</div>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ($t['itens'] as $it)
                                            <li class="text-sm text-ink-soft flex items-center gap-2">
                                                <x-badge tone="teal">{{ $it['plataforma'] }}</x-badge>
                                                <a href="{{ $it['url'] }}" target="_blank" rel="noopener" class="hover:text-teal truncate">{{ $it['titulo'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($relatorio['ideias_guiao']))
                        <x-fleuron glyph="✒" />
                        <div class="eyebrow mb-2">Ideias de guião</div>
                        <ul class="space-y-2">
                            @foreach ($relatorio['ideias_guiao'] as $ideia)
                                <li class="flex gap-3 text-ink"><span class="text-gold shrink-0">❦</span><span>{{ $ideia }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <div class="py-16 text-center">
                        <div class="text-5xl text-gold/60 mb-3 select-none">☙</div>
                        <p class="text-ink-soft italic">Escolha um período e carregue em «Criar relatório de notícias».</p>
                        <p class="text-ink-faint text-sm mt-2">O relatório usa os itens já recolhidos com «Agregar agora».</p>
                    </div>
                @endif
            </x-panel>
        </div>
    </div>
</div>
